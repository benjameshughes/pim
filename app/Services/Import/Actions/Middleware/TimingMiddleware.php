<?php

namespace App\Services\Import\Actions\Middleware;

use App\Services\Import\Actions\ActionContext;
use App\Services\Import\Actions\ActionResult;
use App\Services\Import\Actions\ImportMiddleware;

class TimingMiddleware implements ImportMiddleware
{
    private float $timeoutSeconds;
    private bool $includeTimingData;

    public function __construct(array $config = [])
    {
        $this->timeoutSeconds = $config['timeout_seconds'] ?? 30.0;
        $this->includeTimingData = $config['include_timing_data'] ?? true;
    }

    public function handle(ActionContext $context, \Closure $next): ActionResult
    {
        $startTime = microtime(true);
        
        try {
            // Set time limit for this pipeline execution
            set_time_limit((int) ceil($this->timeoutSeconds));
            
            $result = $next();
            
            $duration = microtime(true) - $startTime;
            
            if ($this->includeTimingData) {
                $result->withData([
                    'execution_time_ms' => round($duration * 1000, 2),
                    'execution_time_seconds' => round($duration, 3),
                ]);
                
                $context->setMetadata('execution_duration', $duration);
            }
            
            // Check if we're approaching timeout
            if ($duration > ($this->timeoutSeconds * 0.9)) {
                $context->setMetadata('performance_warning', 'Execution time approaching timeout limit');
            }
            
            return $result;
            
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;
            
            // Check if this was a timeout
            $isTimeout = strpos($e->getMessage(), 'Maximum execution time') !== false;
            
            return ActionResult::failed(
                $isTimeout ? 'Pipeline execution timed out' : $e->getMessage(),
                [
                    'execution_time_ms' => round($duration * 1000, 2),
                    'timeout_occurred' => $isTimeout,
                    'timeout_limit_seconds' => $this->timeoutSeconds,
                ]
            );
        }
    }

    public static function create(float $timeoutSeconds = 30.0, bool $includeTimingData = true): self
    {
        return new self([
            'timeout_seconds' => $timeoutSeconds,
            'include_timing_data' => $includeTimingData,
        ]);
    }
}