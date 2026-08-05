<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

/**
 * 健康检查接口
 * 供 Docker healthcheck 和外部监控使用
 */
class HealthController
{
    /**
     * 完整健康检查 (Docker healthcheck)
     * GET /api/health
     */
    public function index(): JsonResponse
    {
        $checks = [
            'database'  => $this->checkDatabase(),
            'redis'     => $this->checkRedis(),
            'cache'     => $this->checkCache(),
            'timestamp' => now()->toIso8601String(),
        ];

        $allHealthy = collect($checks)->except('timestamp')->every(fn($v) => $v === true);

        return response()->json([
            'status'   => $allHealthy ? 'healthy' : 'degraded',
            'checks'   => $checks,
        ], $allHealthy ? 200 : 503);
    }

    /**
     * 快速存活检查 (K8s liveness probe)
     * GET /api/health/live
     */
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'alive', 'timestamp' => now()->toIso8601String()]);
    }

    /**
     * 就绪检查 (K8s readiness probe)
     * GET /api/health/ready
     */
    public function ready(): JsonResponse
    {
        return $this->index();
    }

    protected function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    protected function checkRedis(): bool
    {
        try {
            Redis::ping();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    protected function checkCache(): bool
    {
        try {
            Cache::put('health_check', 'ok', 10);
            return Cache::get('health_check') === 'ok';
        } catch (\Throwable) {
            return false;
        }
    }
}
