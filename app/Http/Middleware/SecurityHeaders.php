<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 安全响应头中间件
 * 为所有 HTTP 响应添加安全相关的 Header
 */
class SecurityHeaders
{
    /**
     * 处理请求并添加安全头
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // 防止 MIME 类型嗅探
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // 防止点击劫持 (限制同源)
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // 启用浏览器 XSS 过滤器
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Referrer 策略: 同源完整, 跨域仅 origin
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // 权限策略: 限制浏览器 API
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), interest-cohort=()');

        // 生产环境启用 HSTS (仅 HTTPS)
        if (app()->environment('production') && $request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        // CSP 内容安全策略 (生产环境)
        $csp = $this->buildCSP($request);
        if ($csp) {
            $response->headers->set('Content-Security-Policy', $csp);
        }

        // 移除敏感信息
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }

    /**
     * 构建 Content-Security-Policy 头
     */
    protected function buildCSP(Request $request): string
    {
        $policies = collect([
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data:",
            "connect-src 'self' ws: wss:",
            "media-src 'self'",
            "object-src 'none'",
            "frame-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);

        // 开发环境允许更宽松的 CSP (HMR 等)
        if (app()->environment('local')) {
            $policies->push("script-src 'self' 'unsafe-inline' 'unsafe-eval' http://localhost:*");
            $policies->push("connect-src 'self' ws: wss: http://localhost:*");
        }

        return $policies->implode('; ');
    }
}
