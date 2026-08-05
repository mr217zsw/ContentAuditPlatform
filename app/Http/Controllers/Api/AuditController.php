<?php

namespace App\Http\Controllers\Api;

use App\Events\ArticleStatusChanged;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function pending(Request $request): JsonResponse
    {
        $user = $request->user();

        $levelMap = [
            'editor' => Article::LEVEL_EDITOR,
            'supervisor' => Article::LEVEL_SUPERVISOR,
            'final_approver' => Article::LEVEL_FINAL,
            'admin' => null,
        ];

        $query = Article::with(['author:id,name'])
            ->whereIn('status', [Article::STATUS_PENDING, Article::STATUS_EDITOR_APPROVED]);

        if ($user->role !== 'admin') {
            $level = $levelMap[$user->role] ?? null;
            if ($level) {
                $query->where('approval_level', $level);
            }
        }

        $articles = $query->orderBy('submitted_at', 'asc')
            ->paginate($request->get('per_page', 20));

        return response()->json($articles);
    }

    public function approve(Article $article, Request $request): JsonResponse
    {
        $user = $request->user();
        $request->validate(['comment' => 'nullable|string|max:500']);

        if (! $this->canAudit($user, $article)) {
            return response()->json(['message' => '无权审核此稿件'], 403);
        }

        $fromLevel = $article->approval_level;

        switch ($article->approval_level) {
            case Article::LEVEL_EDITOR:
                $nextLevel = Article::LEVEL_SUPERVISOR;
                $nextStatus = Article::STATUS_EDITOR_APPROVED;
                break;
            case Article::LEVEL_SUPERVISOR:
                $nextLevel = Article::LEVEL_FINAL;
                $nextStatus = Article::STATUS_EDITOR_APPROVED;
                break;
            case Article::LEVEL_FINAL:
                $nextLevel = Article::LEVEL_NONE;
                $nextStatus = Article::STATUS_PUBLISHED;
                break;
            default:
                return response()->json(['message' => '无效的审核级别'], 422);
        }

        $article->update([
            'status' => $nextStatus,
            'approval_level' => $nextLevel,
            'current_auditor_id' => $user->id,
            'approved_at' => $nextStatus === Article::STATUS_PUBLISHED ? now() : null,
        ]);

        AuditLog::create([
            'article_id' => $article->id,
            'auditor_id' => $user->id,
            'action' => AuditLog::ACTION_APPROVED,
            'from_level' => $fromLevel,
            'to_level' => $nextLevel,
            'result_status' => $nextStatus,
            'comment' => $request->comment,
            'snapshot' => $article->only(['title', 'content']),
        ]);

        // 广播审核状态变更
        ArticleStatusChanged::dispatch($article, 'approved', $user->name);

        return response()->json([
            'message' => $nextStatus === Article::STATUS_PUBLISHED ? '稿件已发布' : '审核通过，已转入下一级',
        ]);
    }

    public function reject(Article $article, Request $request): JsonResponse
    {
        $user = $request->user();
        $request->validate(['comment' => 'required|string|max:500']);

        if (! $this->canAudit($user, $article)) {
            return response()->json(['message' => '无权审核此稿件'], 403);
        }

        $fromLevel = $article->approval_level;

        $article->update([
            'status' => Article::STATUS_REJECTED,
            'approval_level' => Article::LEVEL_NONE,
            'reject_reason' => $request->comment,
            'current_auditor_id' => $user->id,
        ]);

        AuditLog::create([
            'article_id' => $article->id,
            'auditor_id' => $user->id,
            'action' => AuditLog::ACTION_REJECTED,
            'from_level' => $fromLevel,
            'to_level' => Article::LEVEL_NONE,
            'result_status' => Article::STATUS_REJECTED,
            'comment' => $request->comment,
            'snapshot' => $article->only(['title', 'content']),
        ]);

        // 广播
        ArticleStatusChanged::dispatch($article, 'rejected', $user->name);

        return response()->json(['message' => '稿件已驳回']);
    }

    public function logs(Article $article, Request $request): JsonResponse
    {
        $logs = AuditLog::with('auditor:id,name')
            ->where('article_id', $article->id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json($logs);
    }

    public function history(Request $request): JsonResponse
    {
        $logs = AuditLog::with(['article:id,title', 'auditor:id,name'])
            ->where('auditor_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json($logs);
    }

    protected function canAudit($user, Article $article): bool
    {
        if ($user->isAdmin()) return true;

        $roleLevelMap = [
            'editor' => Article::LEVEL_EDITOR,
            'supervisor' => Article::LEVEL_SUPERVISOR,
            'final_approver' => Article::LEVEL_FINAL,
        ];

        $requiredLevel = $roleLevelMap[$user->role] ?? null;
        return $requiredLevel && $article->approval_level === $requiredLevel;
    }
}
