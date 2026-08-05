<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Prometheus 指标暴露接口
 * GET /api/metrics
 * 提供应用级别的业务指标供 Prometheus 抓取
 */
class MetricsController
{
    public function __invoke(): Response
    {
        $metrics = [];

        // 应用启动时间 (Unix 时间戳)
        $metrics[] = '# HELP app_info Application information';
        $metrics[] = '# TYPE app_info gauge';
        $metrics[] = sprintf('app_info{version="%s",env="%s"} 1', config('app.version', '1.0'), app()->environment());

        // HTTP 请求总数 (从应用缓存/数据库获取)
        $metrics[] = '# HELP http_requests_total Total HTTP requests';
        $metrics[] = '# TYPE http_requests_total counter';

        // 审核统计
        $metrics[] = '# HELP audit_articles_total Total articles audited';
        $metrics[] = '# TYPE audit_articles_total gauge';
        try {
            $audited = DB::table('articles')->whereNotNull('audited_at')->count();
            $pending  = DB::table('articles')->whereNull('audited_at')->count();
            $metrics[] = sprintf('audit_articles_total{status="audited"} %d', $audited);
            $metrics[] = sprintf('audit_articles_total{status="pending"} %d', $pending);
        } catch (\Throwable) {
            $metrics[] = 'audit_articles_total{status="audited"} 0';
            $metrics[] = 'audit_articles_total{status="pending"} 0';
        }

        // 敏感词数量
        $metrics[] = '# HELP sensitive_words_count Total sensitive words';
        $metrics[] = '# TYPE sensitive_words_count gauge';
        try {
            $count = DB::table('sensitive_words')->count();
            $metrics[] = sprintf('sensitive_words_count %d', $count);
        } catch (\Throwable) {
            $metrics[] = 'sensitive_words_count 0';
        }

        // 队列大小
        $metrics[] = '# HELP laravel_queue_size Laravel queue size';
        $metrics[] = '# TYPE laravel_queue_size gauge';
        try {
            $queueSize = Redis::llen('queues:default') ?: 0;
            $metrics[] = sprintf('laravel_queue_size %d', $queueSize);
        } catch (\Throwable) {
            $metrics[] = 'laravel_queue_size 0';
        }

        // 用户数量
        $metrics[] = '# HELP users_total Total registered users';
        $metrics[] = '# TYPE users_total gauge';
        try {
            $userCount = DB::table('users')->count();
            $metrics[] = sprintf('users_total %d', $userCount);
        } catch (\Throwable) {
            $metrics[] = 'users_total 0';
        }

        // PHP 进程信息
        $metrics[] = '# HELP php_info PHP process info';
        $metrics[] = '# TYPE php_info gauge';
        $metrics[] = sprintf('php_info{version="%s"} 1', PHP_VERSION);

        return response(implode("\n", $metrics) . "\n", 200)
            ->header('Content-Type', 'text/plain; version=0.0.4');
    }
}
