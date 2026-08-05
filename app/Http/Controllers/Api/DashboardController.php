<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * 工作台统计数据
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        // 待审核数量（根据角色）
        $pendingQuery = Article::whereIn('status', ['pending', 'editor_approved']);

        $roleLevelMap = [
            'editor' => 'editor',
            'supervisor' => 'supervisor',
            'final_approver' => 'final',
            'admin' => null,
        ];

        if ($user->role !== 'admin') {
            $level = $roleLevelMap[$user->role] ?? null;
            if ($level) {
                $pendingQuery->where('approval_level', $level);
            }
        }

        $pendingCount = $pendingQuery->count();

        // 今日通过
        $todayApproved = AuditLog::whereDate('created_at', today())
            ->where('action', 'approved')
            ->count();

        // 今日驳回
        $todayRejected = AuditLog::whereDate('created_at', today())
            ->where('action', 'rejected')
            ->count();

        // 本月总计
        $monthTotal = Article::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        // 近期审核趋势（近7天）
        $trend = AuditLog::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw("SUM(CASE WHEN action = 'approved' THEN 1 ELSE 0 END) as approved"),
                DB::raw("SUM(CASE WHEN action = 'rejected' THEN 1 ELSE 0 END) as rejected")
            )
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'pending_count' => $pendingCount,
            'today_approved' => $todayApproved,
            'today_rejected' => $todayRejected,
            'month_total' => $monthTotal,
            'trend' => $trend,
        ]);
    }
}
