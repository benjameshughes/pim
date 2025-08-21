<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * 🎭 LEGENDARY SHOPIFY WEBHOOK MIDDLEWARE 🎭
 *
 * Protects webhook endpoints with MAXIMUM SASS and security!
 * Because security should be tighter than my stage costume! 💅
 */
class ShopifyWebhookMiddleware
{
    /**
     * 🛡️ Handle incoming webhook request with LEGENDARY protection
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $requestId = uniqid('webhook_middleware_', true);

        Log::info('🛡️ LEGENDARY webhook middleware engaged!', [
            'request_id' => $requestId,
            'ip' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'topic' => $request->header('X-Shopify-Topic'),
            'shop_domain' => $request->header('X-Shopify-Shop-Domain'),
        ]);

        // 🚨 Security Check 1: Validate request method
        if (! $request->isMethod('POST')) {
            Log::warning('🚨 Invalid request method - webhooks must be POST!', [
                'method' => $request->method(),
                'ip' => $request->ip(),
            ]);

            return response('Method Not Allowed', 405);
        }

        // 🚨 Security Check 2: Validate required Shopify headers
        if (! $this->hasRequiredShopifyHeaders($request)) {
            Log::warning('🚨 Missing required Shopify headers - suspicious request!', [
                'ip' => $request->ip(),
                'headers' => $request->headers->all(),
            ]);

            return response('Bad Request', 400);
        }

        // 🚨 Security Check 3: Rate limiting by IP
        if (! $this->passesRateLimit($request)) {
            Log::warning("🚨 Rate limit exceeded - someone's being TOO enthusiastic!", [
                'ip' => $request->ip(),
                'topic' => $request->header('X-Shopify-Topic'),
            ]);

            return response('Too Many Requests', 429);
        }

        // 🚨 Security Check 4: Content-Type validation
        if (! $this->hasValidContentType($request)) {
            Log::warning('🚨 Invalid content type - expecting JSON!', [
                'content_type' => $request->header('Content-Type'),
                'ip' => $request->ip(),
            ]);

            return response('Unsupported Media Type', 415);
        }

        // 🚨 Security Check 5: Payload size validation
        if (! $this->hasValidPayloadSize($request)) {
            Log::warning("🚨 Payload too large - that's suspicious!", [
                'content_length' => $request->header('Content-Length'),
                'ip' => $request->ip(),
            ]);

            return response('Payload Too Large', 413);
        }

        // 🚨 Security Check 6: User-Agent validation
        if (! $this->hasValidUserAgent($request)) {
            Log::warning('🚨 Invalid User-Agent - not from Shopify!', [
                'user_agent' => $request->header('User-Agent'),
                'ip' => $request->ip(),
            ]);

            return response('Forbidden', 403);
        }

        // 📊 Log successful middleware passage
        $middlewareTime = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('✨ LEGENDARY webhook middleware passed all checks!', [
            'request_id' => $requestId,
            'middleware_time_ms' => $middlewareTime,
            'topic' => $request->header('X-Shopify-Topic'),
            'shop_domain' => $request->header('X-Shopify-Shop-Domain'),
        ]);

        // Add middleware metadata to request
        $request->merge([
            '_webhook_middleware_time' => $middlewareTime,
            '_webhook_request_id' => $requestId,
        ]);

        return $next($request);
    }

    /**
     * 🔍 Check for required Shopify headers
     */
    private function hasRequiredShopifyHeaders(Request $request): bool
    {
        $requiredHeaders = [
            'X-Shopify-Topic',
            'X-Shopify-Hmac-Sha256',
            'X-Shopify-Shop-Domain',
        ];

        foreach ($requiredHeaders as $header) {
            if (! $request->header($header)) {
                Log::debug("Missing required header: {$header}");

                return false;
            }
        }

        return true;
    }

    /**
     * 🚦 Rate limiting by IP and shop domain
     */
    private function passesRateLimit(Request $request): bool
    {
        $ip = $request->ip();
        $shopDomain = $request->header('X-Shopify-Shop-Domain', 'unknown');

        // Rate limit by IP: 100 requests per minute
        $ipKey = "webhook_rate_limit_ip:{$ip}";
        if (! RateLimiter::attempt($ipKey, 100, function () {}, 60)) {
            return false;
        }

        // Rate limit by shop domain: 200 requests per minute
        $shopKey = "webhook_rate_limit_shop:{$shopDomain}";
        if (! RateLimiter::attempt($shopKey, 200, function () {}, 60)) {
            return false;
        }

        return true;
    }

    /**
     * 📝 Validate Content-Type
     */
    private function hasValidContentType(Request $request): bool
    {
        $contentType = $request->header('Content-Type', '');

        return str_contains($contentType, 'application/json') ||
               str_contains($contentType, 'application/x-www-form-urlencoded');
    }

    /**
     * 📏 Validate payload size (max 1MB for webhooks)
     */
    private function hasValidPayloadSize(Request $request): bool
    {
        $contentLength = (int) $request->header('Content-Length', 0);
        $maxSize = 1024 * 1024; // 1MB

        return $contentLength > 0 && $contentLength <= $maxSize;
    }

    /**
     * 🕵️ Validate User-Agent (should be from Shopify)
     */
    private function hasValidUserAgent(Request $request): bool
    {
        $userAgent = $request->header('User-Agent', '');

        // Shopify typically uses User-Agent like: "Shopify/1.0 (https://shopify.com)"
        return str_contains(strtolower($userAgent), 'shopify') ||
               str_contains(strtolower($userAgent), 'webhook');
    }

    /**
     * 🔒 Additional security logging for suspicious activity
     */
    private function logSuspiciousActivity(Request $request, string $reason): void
    {
        $suspiciousData = [
            'reason' => $reason,
            'ip' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'all_headers' => $request->headers->all(),
            'content_length' => $request->header('Content-Length'),
            'request_time' => now()->toISOString(),
        ];

        Log::warning('🚨 SUSPICIOUS webhook activity detected!', $suspiciousData);

        // Store in cache for monitoring dashboard
        $cacheKey = 'suspicious_webhook_activity:'.now()->format('Y-m-d-H');
        $existing = Cache::get($cacheKey, []);
        $existing[] = $suspiciousData;
        Cache::put($cacheKey, $existing, now()->addHours(24));
    }

    /**
     * 📊 Get middleware statistics for monitoring
     */
    public static function getMiddlewareStats(): array
    {
        $now = now();
        $stats = [];

        // Get hourly stats for the last 24 hours
        for ($i = 0; $i < 24; $i++) {
            $hour = $now->copy()->subHours($i);
            $cacheKey = 'suspicious_webhook_activity:'.$hour->format('Y-m-d-H');
            $hourlyActivity = Cache::get($cacheKey, []);

            $stats[$hour->format('H:00')] = [
                'suspicious_requests' => count($hourlyActivity),
                'unique_ips' => count(array_unique(array_column($hourlyActivity, 'ip'))),
                'reasons' => array_count_values(array_column($hourlyActivity, 'reason')),
            ];
        }

        return [
            'hourly_stats' => $stats,
            'rate_limit_stats' => $this->getRateLimitStats(),
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * 🚦 Get rate limiting statistics
     */
    private static function getRateLimitStats(): array
    {
        // This would need to be implemented based on your specific rate limiting store
        return [
            'ip_limits_active' => RateLimiter::remaining('webhook_rate_limit_ip:*'),
            'shop_limits_active' => RateLimiter::remaining('webhook_rate_limit_shop:*'),
            'note' => 'Rate limiting stats require custom implementation based on your cache driver',
        ];
    }
}
