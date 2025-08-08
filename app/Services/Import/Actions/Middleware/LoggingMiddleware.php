<?php

namespace App\Services\Import\Actions\Middleware;

use App\Services\Import\Actions\ActionContext;
use App\Services\Import\Actions\ActionResult;
use App\Services\Import\Actions\ImportMiddleware;
use Illuminate\Support\Facades\Log;

class LoggingMiddleware implements ImportMiddleware
{
    private bool $logSuccessfulActions;
    private bool $logFailedActions;
    private bool $logContext;

    public function __construct(array $config = [])
    {
        $this->logSuccessfulActions = $config['log_successful'] ?? false;
        $this->logFailedActions = $config['log_failed'] ?? true;
        $this->logContext = $config['log_context'] ?? false;
    }

    public function handle(ActionContext $context, \Closure $next): ActionResult
    {
        $startTime = microtime(true);
        $rowNumber = $context->getRowNumber();

        if ($this->logContext) {
            Log::debug('Action pipeline context', [
                'row_number' => $rowNumber,
                'context_data_keys' => array_keys($context->getData()),
                'metadata' => $context->getMetadata(),
            ]);
        }

        try {
            $result = $next();
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            if ($result->isSuccess() && $this->logSuccessfulActions) {
                Log::info('Action pipeline succeeded', [
                    'row_number' => $rowNumber,
                    'duration_ms' => $duration,
                    'result_data_keys' => array_keys($result->getData()),
                    'context_updates' => $result->hasContextUpdates() ? array_keys($result->getContextUpdates()) : [],
                ]);
            } elseif ($result->isFailure() && $this->logFailedActions) {
                Log::warning('Action pipeline failed', [
                    'row_number' => $rowNumber,
                    'duration_ms' => $duration,
                    'error' => $result->getError(),
                    'result_data' => $result->getData(),
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            Log::error('Action pipeline threw exception', [
                'row_number' => $rowNumber,
                'duration_ms' => $duration,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ActionResult::failed(
                'Pipeline exception: ' . $e->getMessage(),
                [
                    'exception_type' => get_class($e),
                    'duration_ms' => $duration,
                ]
            );
        }
    }

    public static function create(array $config = []): self
    {
        return new self($config);
    }
}