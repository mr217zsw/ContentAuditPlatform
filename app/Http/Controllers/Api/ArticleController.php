<?php

namespace App\Http\Controllers\Api;

use App\Events\ArticleSubmitted;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessSensitiveWords;
use App\Models\Article;
use App\Models\AuditLog;
use App\Services\SensitiveWordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    protected SensitiveWordService $sensitiveWordService;

    public function __construct(SensitiveWordService $sensitiveWordService)
    {
        $this->sensitiveWordService = $sensitiveWordService;
    }

    public function index(Request $request): JsonResponse
    {
        $query = Article::with(['author:id,name', 'currentAuditor:id,name']);

        if (! $request->user()->isAdmin()) {
            $query->where('author_id', $request->user()->id);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $articles = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json($articles);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:200',
            'content' => 'required|string',
        ]);

        // 同步敏感词检测
        $hits = $this->sensitiveWordService->detect($request->title . ' ' . $request->content);

        $article = Article::create([
            'author_id' => $request->user()->id,
            'title' => $request->title,
            'content' => $request->content,
            'status' => Article::STATUS_DRAFT,
            'approval_level' => Article::LEVEL_NONE,
            'sensitive_words_hit' => $hits,
        ]);

        // 异步持久化敏感词命中记录
        if (!empty($hits)) {
            ProcessSensitiveWords::dispatch($article);
        }

        return response()->json([
            'message' => '稿件创建成功',
            'article' => $article,
            'sensitive_warnings' => !empty($hits) ? '检测到敏感词，请检查后提交' : null,
        ], 201);
    }

    public function show(Article $article, Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin() && $article->author_id !== $request->user()->id) {
            return response()->json(['message' => '无权查看'], 403);
        }

        $article->load(['author:id,name', 'currentAuditor:id,name', 'auditLogs.auditor:id,name']);

        return response()->json(['article' => $article]);
    }

    public function update(Request $request, Article $article): JsonResponse
    {
        if ($article->author_id !== $request->user()->id) {
            return response()->json(['message' => '无权修改'], 403);
        }

        if (! $article->canBeEdited()) {
            return response()->json(['message' => '当前状态不可编辑'], 422);
        }

        $request->validate([
            'title' => 'sometimes|string|max:200',
            'content' => 'sometimes|string',
        ]);

        $data = $request->only(['title', 'content']);
        $hits = $this->sensitiveWordService->detect(
            ($request->title ?? $article->title) . ' ' . ($request->content ?? $article->content)
        );
        $data['sensitive_words_hit'] = $hits;

        $article->update($data);

        if (!empty($hits)) {
            ProcessSensitiveWords::dispatch($article);
        }

        return response()->json([
            'message' => '稿件更新成功',
            'article' => $article->fresh(),
        ]);
    }

    public function destroy(Article $article, Request $request): JsonResponse
    {
        if ($article->author_id !== $request->user()->id) {
            return response()->json(['message' => '无权删除'], 403);
        }

        if (! $article->isDraft()) {
            return response()->json(['message' => '只能删除草稿状态的稿件'], 422);
        }

        $article->delete();

        return response()->json(['message' => '稿件已删除']);
    }

    public function submit(Article $article, Request $request): JsonResponse
    {
        if ($article->author_id !== $request->user()->id) {
            return response()->json(['message' => '无权操作'], 403);
        }

        if (! $article->canBeSubmitted()) {
            return response()->json(['message' => '当前状态不可提交'], 422);
        }

        $article->update([
            'status' => Article::STATUS_PENDING,
            'approval_level' => Article::LEVEL_EDITOR,
            'submitted_at' => now(),
        ]);

        AuditLog::create([
            'article_id' => $article->id,
            'auditor_id' => $request->user()->id,
            'action' => AuditLog::ACTION_SUBMITTED,
            'from_level' => Article::LEVEL_NONE,
            'to_level' => Article::LEVEL_EDITOR,
            'result_status' => Article::STATUS_PENDING,
        ]);

        // 广播：通知编辑级别审核员
        ArticleSubmitted::dispatch($article);

        return response()->json(['message' => '稿件已提交审核']);
    }
}
