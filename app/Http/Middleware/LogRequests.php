<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * 📊 ENHANCED REQUEST LOGGING MIDDLEWARE
 * 
 * Comprehensive request/response logging with metrics, performance tracking,
 * and smart filtering for production-ready observability
 */
class LogRequests
{
    /** @var string[] Routes to skip logging (too noisy) */
    protected array $skipRoutes = [
        'livewire/update',
        'livewire/upload-file',
        '_debugbar',
        'telescope',
        'favicon.ico',
    ];

    /** @var string[] Sensitive headers to exclude from logs */
    protected array $sensitiveHeaders = [
        'authorization',
        'cookie',
        'x-csrf-token',
        'x-api-key',
    ];

    /** @var string[] Sensitive form fields to exclude */
    protected array $sensitiveFields = [
        'password',
        'password_confirmation',
        'token',
        'secret',
        'key',
        '_token',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Skip logging for certain routes to reduce noise
        if ($this->shouldSkipLogging($request)) {
            return $next($request);
        }

        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        // Log incoming request
        $this->logRequest($request, $startTime);

        $response = $next($request);

        // Log response with metrics
        $this->logResponse($request, $response, $startTime, $startMemory);

        return $response;
    }

    protected function shouldSkipLogging(Request $request): bool
    {
        $path = $request->path();
        
        foreach ($this->skipRoutes as $skipRoute) {
            if (str_starts_with($path, $skipRoute)) {
                return true;
            }
        }

        return false;
    }

    protected function logRequest(Request $request, float $startTime): void
    {
        $context = [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => date('Y-m-d H:i:s', (int) $startTime),
        ];

        // Add user info if authenticated
        if ($user = $request->user()) {
            $context['user_id'] = $user->id;
            $context['user_email'] = $user->email;
        }

        // Add route info if available
        if ($route = $request->route()) {
            $context['route_name'] = $route->getName();
            $context['controller'] = $route->getActionName();
        }

        // Add filtered headers (excluding sensitive ones)
        $context['headers'] = $this->filterSensitiveData(
            $request->headers->all(), 
            $this->sensitiveHeaders
        );

        // Add request data (excluding sensitive fields)
        if ($request->isMethod('POST') || $request->isMethod('PUT') || $request->isMethod('PATCH')) {
            $context['data'] = $this->filterSensitiveData(
                $request->all(), 
                $this->sensitiveFields
            );
        }

        // Add query parameters for GET requests
        if ($request->isMethod('GET') && $request->query()) {
            $context['query'] = $request->query();
        }

        Log::info('🔄 HTTP Request', $context);
    }

    protected function logResponse(Request $request, Response $response, float $startTime, int $startMemory): void
    {
        $duration = round((microtime(true) - $startTime) * 1000, 2); // milliseconds
        $memoryUsage = round((memory_get_usage() - $startMemory) / 1024, 2); // KB
        $peakMemory = round(memory_get_peak_usage() / 1024 / 1024, 2); // MB

        $context = [
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => $duration,
            'memory_used_kb' => $memoryUsage,
            'peak_memory_mb' => $peakMemory,
        ];

        // Add performance classification
        $context['performance'] = $this->classifyPerformance($duration);

        // Add user info if authenticated
        if ($user = $request->user()) {
            $context['user_id'] = $user->id;
        }

        // Add response size
        if ($content = $response->getContent()) {
            $context['response_size_kb'] = round(strlen($content) / 1024, 2);
        }

        // Log based on status code
        if ($response->getStatusCode() >= 500) {
            Log::error('🔴 HTTP Response - Server Error', $context);
        } elseif ($response->getStatusCode() >= 400) {
            Log::warning('🟡 HTTP Response - Client Error', $context);
        } elseif ($duration > 1000) {
            Log::warning('🐌 HTTP Response - Slow Request', $context);
        } else {
            Log::info('✅ HTTP Response', $context);
        }

        // Log performance metrics separately for analysis
        Log::channel('performance')->info('⚡ Performance Metrics', [
            'path' => $request->path(),
            'method' => $request->method(),
            'duration_ms' => $duration,
            'memory_kb' => $memoryUsage,
            'status' => $response->getStatusCode(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    protected function classifyPerformance(float $durationMs): string
    {
        return match (true) {
            $durationMs < 100 => 'excellent',
            $durationMs < 300 => 'good', 
            $durationMs < 1000 => 'acceptable',
            $durationMs < 3000 => 'slow',
            default => 'very_slow'
        };
    }

    /**
     * Filter out sensitive data from arrays
     * @param array<string, mixed> $data
     * @param string[] $sensitiveKeys
     * @return array<string, mixed>
     */
    protected function filterSensitiveData(array $data, array $sensitiveKeys): array
    {
        $filtered = [];
        
        foreach ($data as $key => $value) {
            $lowerKey = strtolower($key);
            
            // Check if key is sensitive
            $isSensitive = false;
            foreach ($sensitiveKeys as $sensitiveKey) {
                if (str_contains($lowerKey, strtolower($sensitiveKey))) {
                    $isSensitive = true;
                    break;
                }
            }
            
            if ($isSensitive) {
                $filtered[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $filtered[$key] = $this->filterSensitiveData($value, $sensitiveKeys);
            } else {
                $filtered[$key] = $value;
            }
        }
        
        return $filtered;
    }
}