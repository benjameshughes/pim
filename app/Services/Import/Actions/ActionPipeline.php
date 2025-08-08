<?php

namespace App\Services\Import\Actions;

use Illuminate\Support\Facades\Log;

class ActionPipeline
{
    private array $actions = [];
    private array $middleware = [];

    public function add(ImportAction $action): self
    {
        $this->actions[] = $action;
        return $this;
    }

    public function through($middleware): self
    {
        if (is_array($middleware)) {
            $this->middleware = array_merge($this->middleware, $middleware);
        } else {
            $this->middleware[] = $middleware;
        }
        return $this;
    }

    public function execute(ActionContext $context): ActionResult
    {
        Log::debug('Starting action pipeline execution', [
            'actions_count' => count($this->actions),
            'middleware_count' => count($this->middleware),
            'context_data_keys' => array_keys($context->getData()),
        ]);

        try {
            // Build middleware stack
            $pipeline = $this->buildMiddlewareStack($context);
            
            // Execute through middleware
            $result = $pipeline();
            
            Log::info('Action pipeline completed successfully', [
                'result_type' => get_class($result),
                'success' => $result->isSuccess(),
                'actions_executed' => count($this->actions),
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error('Action pipeline failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'context_row' => $context->getRowNumber(),
            ]);
            
            return ActionResult::failed($e->getMessage(), [
                'pipeline_error' => true,
                'failed_at' => 'middleware_execution',
            ]);
        }
    }

    private function buildMiddlewareStack(ActionContext $context): \Closure
    {
        $pipeline = function () use ($context) {
            return $this->executeActions($context);
        };

        // Wrap pipeline with middleware (in reverse order)
        foreach (array_reverse($this->middleware) as $middleware) {
            $pipeline = function () use ($middleware, $pipeline, $context) {
                return $middleware->handle($context, $pipeline);
            };
        }

        return $pipeline;
    }

    private function executeActions(ActionContext $context): ActionResult
    {
        // If no actions, just return success with the context
        if (empty($this->actions)) {
            return ActionResult::success($context, 'Pipeline completed successfully');
        }
        
        $results = [];
        
        foreach ($this->actions as $index => $action) {
            $actionName = get_class($action);
            
            Log::debug("Executing action: {$actionName}", [
                'action_index' => $index,
                'context_row' => $context->getRowNumber(),
            ]);
            
            try {
                $result = $action->execute($context);
                $results[$actionName] = $result;
                
                // If action failed and is not optional, stop pipeline
                if (!$result->isSuccess() && !$action->isOptional()) {
                    Log::warning("Required action failed, stopping pipeline: {$actionName}", [
                        'error' => $result->getError(),
                        'context_row' => $context->getRowNumber(),
                    ]);
                    
                    return ActionResult::failed(
                        "Required action '{$actionName}' failed: " . $result->getError(),
                        array_merge($result->getData(), [
                            'failed_action' => $actionName,
                            'executed_actions' => array_keys($results),
                        ])
                    );
                }
                
                // Update context with action results if needed
                if ($result->hasContextUpdates()) {
                    $context->mergeData($result->getContextUpdates());
                }
                
            } catch (\Exception $e) {
                Log::error("Action execution failed: {$actionName}", [
                    'error' => $e->getMessage(),
                    'context_row' => $context->getRowNumber(),
                ]);
                
                if (!$action->isOptional()) {
                    return ActionResult::failed(
                        "Action '{$actionName}' threw exception: " . $e->getMessage(),
                        [
                            'failed_action' => $actionName,
                            'exception_type' => get_class($e),
                            'executed_actions' => array_keys($results),
                        ]
                    );
                }
                
                // Log but continue for optional actions
                $results[$actionName] = ActionResult::failed($e->getMessage());
            }
        }
        
        return ActionResult::success([
            'actions_executed' => array_keys($results),
            'action_results' => $results,
            'final_context_data' => $context->getData(),
        ]);
    }

    public static function create(): self
    {
        return new self();
    }
}