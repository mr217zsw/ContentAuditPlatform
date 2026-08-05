<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\SensitiveWordController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MetricsController;
use Illuminate\Support\Facades\Route;

// 健康检查 + 监控指标 (无认证)
Route::get('/health', [HealthController::class, 'index']);
Route::get('/health/live', [HealthController::class, 'live']);
Route::get('/health/ready', [HealthController::class, 'ready']);
Route::get('/metrics', [MetricsController::class, '__invoke']);

Route::prefix('v1')->group(function () {

    // 公开接口
    Route::post('/auth/login', [AuthController::class, 'login']);

    // 需要认证的接口
    Route::middleware('auth:sanctum')->group(function () {

        // 用户
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });

        // 稿件管理
        Route::prefix('articles')->group(function () {
            Route::get('/', [ArticleController::class, 'index']);
            Route::post('/', [ArticleController::class, 'store']);
            Route::get('/{article}', [ArticleController::class, 'show']);
            Route::put('/{article}', [ArticleController::class, 'update']);
            Route::delete('/{article}', [ArticleController::class, 'destroy']);
            Route::post('/{article}/submit', [ArticleController::class, 'submit']);
        });

        // 审核
        Route::prefix('audit')->group(function () {
            Route::get('/pending', [AuditController::class, 'pending']);
            Route::post('/{article}/approve', [AuditController::class, 'approve']);
            Route::post('/{article}/reject', [AuditController::class, 'reject']);
            Route::get('/{article}/logs', [AuditController::class, 'logs']);
            Route::get('/history', [AuditController::class, 'history']);
        });

        // 敏感词管理 (仅 admin)
        Route::prefix('sensitive-words')->group(function () {
            Route::get('/', [SensitiveWordController::class, 'index']);
            Route::post('/', [SensitiveWordController::class, 'store']);
            Route::delete('/{sensitiveWord}', [SensitiveWordController::class, 'destroy']);
            Route::post('/check', [SensitiveWordController::class, 'check']);
        });

        // 统计看板
        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    });
});
