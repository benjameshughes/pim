<?php

namespace App\Actions\Base;

use App\Traits\PerformanceMonitoring;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 💎 ENHANCED BASE ACTION CLASS 💎
 *
 * Abstract base class for all action implementations following the Action Pattern.
 * Actions encapsulate single-responsibility business logic operations with STYLE!
 * 
 * Features: Performance monitoring, transaction safety, standardized responses! 💅
 */
abstract class BaseAction
{
    use PerformanceMonitoring;

    protected bool $useTransactions = true;
    protected string $actionName;

    public function __construct()
    {
        $this->actionName = class_basename(static::class);
    }

    /**
     * Execute the action with provided parameters (main entry point)
     *
     * @param  mixed  ...$params  Variable parameters for action execution
     * @return array Standardized action result
     */
    public function execute(...$params): array
    {
        $startTime = microtime(true);
        $this->logActionStart($params);

        try {
            $this->validate($params);
            
            if ($this->useTransactions) {
                return DB::transaction(function () use ($params) {
                    return $this->performAction(...$params);
                });
            } else {
                return $this->performAction(...$params);
            }

        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;
            $this->logActionFailure($e, $duration);
            
            return $this->failure($e->getMessage(), [
                'error_type' => get_class($e),
                'error_code' => $e->getCode(),
                'duration_ms' => round($duration * 1000, 2)
            ]);
        }
    }

    /**
     * Perform the actual action logic (must be implemented by subclasses)
     */
    abstract protected function performAction(...$params): array;

    /**
     * Validate parameters before execution (optional override)
     *
     * @param  array  $params  Parameters to validate
     * @return bool True if validation passes
     *
     * @throws \InvalidArgumentException If validation fails
     */
    protected function validate(array $params): bool
    {
        return true;
    }

    /**
     * Handle any cleanup after action execution (optional override)
     *
     * @param  array  $result  The result from performAction()
     */
    protected function cleanup(array $result): void
    {
        // Default: no cleanup needed
    }

    /**
     * Create standardized success response
     */
    protected function success(string $message, array $data = []): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'action' => $this->actionName,
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Create standardized failure response
     */
    protected function failure(string $message, array $data = []): array
    {
        return [
            'success' => false,
            'message' => $message,
            'data' => $data,
            'action' => $this->actionName,
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Log action start for monitoring
     */
    private function logActionStart(array $params): void
    {
        Log::info("🎬 Action started", [
            'action' => $this->actionName,
            'params_count' => count($params),
            'started_at' => now()->toISOString()
        ]);
    }

    /**
     * Log action failure for debugging
     */
    private function logActionFailure(\Exception $e, float $duration): void
    {
        Log::error("💥 Action failed", [
            'action' => $this->actionName,
            'error' => $e->getMessage(),
            'error_type' => get_class($e),
            'duration_ms' => round($duration * 1000, 2),
            'failed_at' => now()->toISOString()
        ]);
    }

    /**
     * Disable database transactions for this action
     */
    protected function disableTransactions(): static
    {
        $this->useTransactions = false;
        return $this;
    }

    /**
     * Enable database transactions for this action (default)
     */
    protected function enableTransactions(): static
    {
        $this->useTransactions = true;
        return $this;
    }

    /**
     * Legacy handle method for backward compatibility
     */
    public function handle(...$params): array
    {
        return $this->execute(...$params);
    }
}
