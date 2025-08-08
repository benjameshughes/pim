<?php

namespace App\Services\Import\Actions\Middleware;

use App\Services\Import\Actions\ActionContext;
use App\Services\Import\Actions\ActionResult;
use App\Services\Import\Actions\ImportMiddleware;
use Illuminate\Support\Facades\Log;

class ErrorHandlingMiddleware implements ImportMiddleware
{
    private bool $retryOnFailure;
    private int $maxRetries;
    private array $retryableExceptions;
    private bool $gracefulDegradation;

    public function __construct(array $config = [])
    {
        $this->retryOnFailure = $config['retry_on_failure'] ?? false;
        $this->maxRetries = $config['max_retries'] ?? 2;
        $this->retryableExceptions = $config['retryable_exceptions'] ?? [
            \Illuminate\Database\QueryException::class,
            \GuzzleHttp\Exception\ConnectException::class,
        ];
        $this->gracefulDegradation = $config['graceful_degradation'] ?? true;
    }

    public function handle(ActionContext $context, \Closure $next): ActionResult
    {
        $attempt = 0;
        $lastException = null;
        
        while ($attempt <= $this->maxRetries) {
            try {
                $result = $next();
                
                // If we succeeded after retries, log it
                if ($attempt > 0 && $result->isSuccess()) {
                    Log::info('Action pipeline succeeded after retry', [
                        'row_number' => $context->getRowNumber(),
                        'attempt' => $attempt + 1,
                        'max_attempts' => $this->maxRetries + 1,
                    ]);
                    
                    $result->withData([
                        'retry_attempt' => $attempt,
                        'retry_successful' => true,
                    ]);
                }
                
                return $result;
                
            } catch (\Exception $e) {
                $lastException = $e;
                $attempt++;
                
                // Check if this exception is retryable
                $isRetryable = $this->isRetryableException($e);
                
                // If we shouldn't retry or have exceeded max retries, handle the error
                if (!$this->retryOnFailure || !$isRetryable || $attempt > $this->maxRetries) {
                    return $this->handleFinalError($e, $context, $attempt - 1);
                }
                
                // Log retry attempt
                Log::warning('Action pipeline failed, attempting retry', [
                    'row_number' => $context->getRowNumber(),
                    'attempt' => $attempt,
                    'max_attempts' => $this->maxRetries + 1,
                    'error' => $e->getMessage(),
                    'exception_type' => get_class($e),
                ]);
                
                // Brief pause before retry to handle transient issues
                usleep(100000 * $attempt); // 100ms, 200ms, 300ms...
            }
        }
        
        // This shouldn't be reached, but handle it just in case
        return $this->handleFinalError($lastException ?? new \Exception('Unknown error'), $context, $this->maxRetries);
    }

    private function isRetryableException(\Exception $e): bool
    {
        foreach ($this->retryableExceptions as $retryableClass) {
            if ($e instanceof $retryableClass) {
                return true;
            }
        }
        
        // Check for specific error messages that might indicate transient issues
        $message = strtolower($e->getMessage());
        $transientIndicators = ['timeout', 'connection', 'deadlock', 'lock wait', 'temporary'];
        
        foreach ($transientIndicators as $indicator) {
            if (strpos($message, $indicator) !== false) {
                return true;
            }
        }
        
        return false;
    }

    private function handleFinalError(\Exception $e, ActionContext $context, int $retryAttempts): ActionResult
    {
        Log::error('Action pipeline failed after all retries', [
            'row_number' => $context->getRowNumber(),
            'retry_attempts' => $retryAttempts,
            'final_error' => $e->getMessage(),
            'exception_type' => get_class($e),
            'trace' => $e->getTraceAsString(),
        ]);

        $errorData = [
            'exception_type' => get_class($e),
            'retry_attempts' => $retryAttempts,
            'max_retries' => $this->maxRetries,
        ];

        // If graceful degradation is enabled, try to provide partial results
        if ($this->gracefulDegradation) {
            $errorData['graceful_degradation'] = true;
            
            // Collect any useful context data that was processed before failure
            $contextData = $context->getData();
            if (!empty($contextData)) {
                $errorData['partial_data'] = $contextData;
            }
        }

        return ActionResult::failed(
            'Pipeline failed after ' . ($retryAttempts + 1) . ' attempt(s): ' . $e->getMessage(),
            $errorData
        );
    }

    public static function create(array $config = []): self
    {
        return new self($config);
    }

    public static function withRetries(int $maxRetries = 2, array $retryableExceptions = []): self
    {
        return new self([
            'retry_on_failure' => true,
            'max_retries' => $maxRetries,
            'retryable_exceptions' => array_merge([
                \Illuminate\Database\QueryException::class,
                \GuzzleHttp\Exception\ConnectException::class,
            ], $retryableExceptions),
        ]);
    }

    public static function withGracefulDegradation(): self
    {
        return new self([
            'graceful_degradation' => true,
        ]);
    }
}