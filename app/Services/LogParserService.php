<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * 📊 LOG PARSER SERVICE
 * 
 * Parses Laravel log files and performance logs for dashboard display
 */
class LogParserService
{
    protected string $logPath;
    protected string $performancePath;

    public function __construct()
    {
        $this->logPath = storage_path('logs/laravel.log');
        $this->performancePath = storage_path('logs/performance.log');
    }

    /**
     * Get recent HTTP requests with metrics
     */
    public function getRecentRequests(int $limit = 50): Collection
    {
        if (!File::exists($this->logPath)) {
            return collect();
        }

        $content = File::get($this->logPath);
        $lines = array_reverse(explode("\n", $content));
        
        $requests = collect();
        
        foreach ($lines as $line) {
            if (empty($line) || $requests->count() >= $limit) {
                continue;
            }

            // Parse HTTP request/response lines
            if (str_contains($line, 'HTTP Request') || str_contains($line, 'HTTP Response')) {
                $parsed = $this->parseLogLine($line);
                if ($parsed) {
                    $requests->push($parsed);
                }
            }
        }

        return $requests->take($limit);
    }

    /**
     * Get performance metrics summary
     */
    public function getPerformanceMetrics(): array
    {
        if (!File::exists($this->performancePath)) {
            return [
                'total_requests' => 0,
                'avg_response_time' => 0,
                'slow_requests' => 0,
                'error_rate' => 0,
            ];
        }

        $content = File::get($this->performancePath);
        $lines = explode("\n", $content);
        
        $totalRequests = 0;
        $totalTime = 0;
        $slowRequests = 0;
        $errors = 0;
        
        foreach ($lines as $line) {
            if (empty($line) || !str_contains($line, 'Performance Metrics')) {
                continue;
            }
            
            $data = $this->extractJsonFromLogLine($line);
            if (!$data) continue;
            
            $totalRequests++;
            
            if (isset($data['duration_ms'])) {
                $duration = (float) $data['duration_ms'];
                $totalTime += $duration;
                
                if ($duration > 1000) {
                    $slowRequests++;
                }
            }
            
            if (isset($data['status']) && $data['status'] >= 400) {
                $errors++;
            }
        }
        
        return [
            'total_requests' => $totalRequests,
            'avg_response_time' => $totalRequests > 0 ? round($totalTime / $totalRequests, 2) : 0,
            'slow_requests' => $slowRequests,
            'error_rate' => $totalRequests > 0 ? round(($errors / $totalRequests) * 100, 1) : 0,
        ];
    }

    /**
     * Get top slowest endpoints
     */
    public function getSlowestEndpoints(int $limit = 10): Collection
    {
        if (!File::exists($this->performancePath)) {
            return collect();
        }

        $content = File::get($this->performancePath);
        $lines = explode("\n", $content);
        
        $endpoints = collect();
        
        foreach ($lines as $line) {
            if (empty($line) || !str_contains($line, 'Performance Metrics')) {
                continue;
            }
            
            $data = $this->extractJsonFromLogLine($line);
            if (!$data || !isset($data['path'], $data['duration_ms'])) {
                continue;
            }
            
            $endpoints->push([
                'path' => $data['path'],
                'method' => $data['method'] ?? 'GET',
                'duration_ms' => (float) $data['duration_ms'],
                'status' => $data['status'] ?? 200,
                'timestamp' => $data['timestamp'] ?? now()->toISOString(),
            ]);
        }
        
        return $endpoints
            ->sortByDesc('duration_ms')
            ->take($limit)
            ->values();
    }

    /**
     * Get error log entries
     */
    public function getRecentErrors(int $limit = 20): Collection
    {
        if (!File::exists($this->logPath)) {
            return collect();
        }

        $content = File::get($this->logPath);
        $lines = array_reverse(explode("\n", $content));
        
        $errors = collect();
        
        foreach ($lines as $line) {
            if (empty($line) || $errors->count() >= $limit) {
                continue;
            }

            if (str_contains($line, '.ERROR') || str_contains($line, '.WARNING')) {
                $parsed = $this->parseLogLine($line);
                if ($parsed) {
                    $errors->push($parsed);
                }
            }
        }

        return $errors->take($limit);
    }

    /**
     * Parse a single log line
     */
    protected function parseLogLine(string $line): ?array
    {
        // Laravel log format: [timestamp] environment.level: message context
        if (!preg_match('/\[(.*?)\]\s+(\w+)\.(\w+):\s+(.*?)(\{.*\})?$/', $line, $matches)) {
            return null;
        }

        $timestamp = $matches[1];
        $environment = $matches[2];
        $level = $matches[3];
        $message = trim($matches[4]);
        $jsonContext = $matches[5] ?? '{}';

        $context = $this->extractJsonFromLogLine($line) ?? [];

        return [
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'method' => $context['method'] ?? null,
            'path' => $context['path'] ?? null,
            'status' => $context['status'] ?? null,
            'duration_ms' => $context['duration_ms'] ?? null,
            'user_id' => $context['user_id'] ?? null,
            'ip' => $context['ip'] ?? null,
        ];
    }

    /**
     * Extract JSON context from log line
     */
    protected function extractJsonFromLogLine(string $line): ?array
    {
        if (preg_match('/\{.*\}$/', $line, $matches)) {
            try {
                return json_decode($matches[0], true);
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * Get log file sizes
     */
    public function getLogFileSizes(): array
    {
        return [
            'laravel_log_size' => File::exists($this->logPath) 
                ? $this->formatBytes(File::size($this->logPath)) 
                : '0 B',
            'performance_log_size' => File::exists($this->performancePath) 
                ? $this->formatBytes(File::size($this->performancePath)) 
                : '0 B',
        ];
    }

    /**
     * Format bytes to human readable format
     */
    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        
        return $bytes . ' B';
    }
}