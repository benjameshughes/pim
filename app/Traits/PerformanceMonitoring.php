<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

/**
 * Performance Monitoring Trait
 * 
 * Provides timing and performance measurement capabilities for tracking
 * slow operations and optimizing performance bottlenecks.
 */
trait PerformanceMonitoring
{
    /**
     * Performance timers storage
     * 
     * @var array
     */
    protected array $performanceTimers = [];
    
    /**
     * Performance thresholds (in milliseconds)
     * 
     * @var array
     */
    protected array $performanceThresholds = [
        'slow_query' => 100,
        'slow_operation' => 500,
        'very_slow_operation' => 1000,
    ];
    
    /**
     * Start performance timer
     */
    protected function startTimer(string $operation): void
    {
        $this->performanceTimers[$operation] = [
            'start' => microtime(true),
            'memory_start' => memory_get_usage(true),
        ];
    }
    
    /**
     * End performance timer and optionally log if slow
     */
    protected function endTimer(string $operation, bool $logIfSlow = true): float
    {
        if (!isset($this->performanceTimers[$operation])) {
            return 0.0;
        }
        
        $timer = $this->performanceTimers[$operation];
        $duration = (microtime(true) - $timer['start']) * 1000; // Convert to milliseconds
        $memoryUsed = memory_get_usage(true) - $timer['memory_start'];
        
        // Log if operation was slow
        if ($logIfSlow && $this->isSlowOperation($duration)) {
            $this->logSlowOperation($operation, $duration, $memoryUsed);
        }
        
        // Clean up timer
        unset($this->performanceTimers[$operation]);
        
        return $duration;
    }
    
    /**
     * Check if operation duration exceeds thresholds
     */
    protected function isSlowOperation(float $duration): bool
    {
        return $duration > $this->performanceThresholds['slow_operation'];
    }
    
    /**
     * Log slow operation with context
     */
    protected function logSlowOperation(string $operation, float $duration, int $memoryUsed): void
    {
        $level = $this->getLogLevel($duration);
        
        Log::$level("Slow operation detected", [
            'operation' => $operation,
            'duration_ms' => round($duration, 2),
            'memory_used_mb' => round($memoryUsed / 1024 / 1024, 2),
            'class' => get_class($this),
            'threshold_exceeded' => $this->getThresholdExceeded($duration),
        ]);
    }
    
    /**
     * Determine log level based on duration
     */
    protected function getLogLevel(float $duration): string
    {
        if ($duration > $this->performanceThresholds['very_slow_operation']) {
            return 'warning';
        }
        
        return 'info';
    }
    
    /**
     * Get which threshold was exceeded
     */
    protected function getThresholdExceeded(float $duration): string
    {
        if ($duration > $this->performanceThresholds['very_slow_operation']) {
            return 'very_slow_operation';
        }
        
        if ($duration > $this->performanceThresholds['slow_operation']) {
            return 'slow_operation';
        }
        
        if ($duration > $this->performanceThresholds['slow_query']) {
            return 'slow_query';
        }
        
        return 'none';
    }
    
    /**
     * Time a closure and return both result and duration
     */
    protected function timeOperation(string $operation, callable $callback, bool $logIfSlow = true): array
    {
        $this->startTimer($operation);
        
        try {
            $result = $callback();
            $duration = $this->endTimer($operation, $logIfSlow);
            
            return ['result' => $result, 'duration' => $duration];
        } catch (\Exception $e) {
            $this->endTimer($operation, false); // Don't log if failed
            throw $e;
        }
    }
    
    /**
     * Get performance summary
     */
    protected function getPerformanceSummary(): array
    {
        $summary = [];
        
        foreach ($this->performanceTimers as $operation => $timer) {
            $duration = (microtime(true) - $timer['start']) * 1000;
            $memoryUsed = memory_get_usage(true) - $timer['memory_start'];
            
            $summary[$operation] = [
                'duration_ms' => round($duration, 2),
                'memory_used_mb' => round($memoryUsed / 1024 / 1024, 2),
                'is_slow' => $this->isSlowOperation($duration),
            ];
        }
        
        return $summary;
    }
    
    /**
     * Set custom performance thresholds
     */
    protected function setPerformanceThresholds(array $thresholds): void
    {
        $this->performanceThresholds = array_merge($this->performanceThresholds, $thresholds);
    }
}