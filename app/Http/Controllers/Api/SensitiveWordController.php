<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SensitiveWord;
use App\Services\SensitiveWordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SensitiveWordController extends Controller
{
    protected SensitiveWordService $service;

    public function __construct(SensitiveWordService $service)
    {
        $this->service = $service;
    }

    /**
     * 敏感词列表
     */
    public function index(Request $request): JsonResponse
    {
        $query = SensitiveWord::query();

        if ($level = $request->get('level')) {
            $query->where('level', $level);
        }

        $words = $query->orderBy('level')->orderBy('word')->get();

        return response()->json(['data' => $words]);
    }

    /**
     * 添加敏感词
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'word' => 'required|string|max:100|unique:sensitive_words,word',
            'level' => 'required|in:low,medium,high,forbidden',
        ]);

        $word = SensitiveWord::create($request->only(['word', 'level']));

        return response()->json(['message' => '敏感词已添加', 'data' => $word], 201);
    }

    /**
     * 删除敏感词
     */
    public function destroy(SensitiveWord $sensitiveWord): JsonResponse
    {
        $sensitiveWord->delete();

        return response()->json(['message' => '敏感词已删除']);
    }

    /**
     * 文本检测（调试用）
     */
    public function check(Request $request): JsonResponse
    {
        $request->validate(['text' => 'required|string']);

        $hits = $this->service->detect($request->text);

        return response()->json([
            'text' => $request->text,
            'has_sensitive' => !empty($hits),
            'hits' => $hits,
        ]);
    }
}
