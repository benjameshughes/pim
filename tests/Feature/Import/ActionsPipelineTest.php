<?php

use App\Services\Import\Actions\ActionPipeline;
use App\Services\Import\Actions\ActionContext;
use App\Services\Import\Actions\ActionResult;
use App\Services\Import\Actions\ImportMiddleware;
use App\Models\ImportSession;

describe('Actions Pipeline', function () {
    beforeEach(function () {
        $this->actingAs(\App\Models\User::factory()->create());
    });

    describe('ActionContext', function () {
        it('creates context with session and data', function () {
            $session = ImportSession::factory()->create(['user_id' => auth()->id()]);
            $data = ['test' => 'value'];
            
            $context = new ActionContext($session, $data);
            
            expect($context->session)->toBe($session);
            expect($context->data)->toBe($data);
            expect($context->metadata)->toBe([]);
        });

        it('allows setting and getting metadata', function () {
            $session = ImportSession::factory()->create(['user_id' => auth()->id()]);
            $context = new ActionContext($session, []);
            
            $context->setMetadata('key', 'value');
            
            expect($context->getMetadata('key'))->toBe('value');
            expect($context->getMetadata('missing', 'default'))->toBe('default');
        });

        it('updates data correctly', function () {
            $session = ImportSession::factory()->create(['user_id' => auth()->id()]);
            $context = new ActionContext($session, ['original' => 'data']);
            
            $context->updateData(['new' => 'value', 'original' => 'updated']);
            
            expect($context->data)->toBe(['new' => 'value', 'original' => 'updated']);
        });

        it('adds configuration correctly', function () {
            $session = ImportSession::factory()->create(['user_id' => auth()->id()]);
            $context = new ActionContext($session, []);
            
            $context->addConfiguration('setting', 'value');
            
            expect($context->getConfiguration('setting'))->toBe('value');
            expect($context->getConfiguration('missing', 'default'))->toBe('default');
        });
    });

    describe('ActionResult', function () {
        it('creates successful result', function () {
            $context = new ActionContext(
                ImportSession::factory()->create(['user_id' => auth()->id()]),
                ['test' => 'data']
            );
            
            $result = ActionResult::success($context, 'Operation completed');
            
            expect($result->success)->toBeTrue();
            expect($result->message)->toBe('Operation completed');
            expect($result->context)->toBe($context);
            expect($result->errors)->toBe([]);
        });

        it('creates failure result', function () {
            $context = new ActionContext(
                ImportSession::factory()->create(['user_id' => auth()->id()]),
                []
            );
            $errors = ['Field validation failed', 'Missing required data'];
            
            $result = ActionResult::failure($context, 'Operation failed', $errors);
            
            expect($result->success)->toBeFalse();
            expect($result->message)->toBe('Operation failed');
            expect($result->errors)->toBe($errors);
        });

        it('allows updating context in result', function () {
            $session = ImportSession::factory()->create(['user_id' => auth()->id()]);
            $originalContext = new ActionContext($session, ['original' => 'data']);
            $result = ActionResult::success($originalContext);
            
            $updatedContext = new ActionContext($session, ['updated' => 'data']);
            $result->updateContext($updatedContext);
            
            expect($result->context)->toBe($updatedContext);
            expect($result->context->data)->toBe(['updated' => 'data']);
        });
    });

    describe('ActionPipeline', function () {
        it('executes middleware chain in correct order', function () {
            $session = ImportSession::factory()->create(['user_id' => auth()->id()]);
            $context = new ActionContext($session, ['counter' => 0]);
            
            $middleware1 = new class implements ImportMiddleware {
                public function handle(ActionContext $context, \Closure $next): ActionResult
                {
                    $context->updateData(['counter' => $context->data['counter'] + 1]);
                    $context->setMetadata('middleware1', 'executed');
                    return $next($context);
                }
            };
            
            $middleware2 = new class implements ImportMiddleware {
                public function handle(ActionContext $context, \Closure $next): ActionResult
                {
                    $context->updateData(['counter' => $context->data['counter'] * 2]);
                    $context->setMetadata('middleware2', 'executed');
                    return $next($context);
                }
            };
            
            $pipeline = new ActionPipeline();
            $result = $pipeline
                ->through([$middleware1, $middleware2])
                ->execute($context);
            
            expect($result->success)->toBeTrue();
            expect($result->context->data['counter'])->toBe(2); // (0 + 1) * 2
            expect($result->context->getMetadata('middleware1'))->toBe('executed');
            expect($result->context->getMetadata('middleware2'))->toBe('executed');
        });

        it('stops execution when middleware returns failure', function () {
            $session = ImportSession::factory()->create(['user_id' => auth()->id()]);
            $context = new ActionContext($session, []);
            
            $failingMiddleware = new class implements ImportMiddleware {
                public function handle(ActionContext $context, \Closure $next): ActionResult
                {
                    return ActionResult::failure($context, 'Middleware failed');
                }
            };
            
            $neverExecutedMiddleware = new class implements ImportMiddleware {
                public function handle(ActionContext $context, \Closure $next): ActionResult
                {
                    $context->setMetadata('never_executed', true);
                    return $next($context);
                }
            };
            
            $pipeline = new ActionPipeline();
            $result = $pipeline
                ->through([$failingMiddleware, $neverExecutedMiddleware])
                ->execute($context);
            
            expect($result->success)->toBeFalse();
            expect($result->message)->toBe('Middleware failed');
            expect($result->context->getMetadata('never_executed'))->toBeNull();
        });

        it('handles empty middleware chain gracefully', function () {
            $session = ImportSession::factory()->create(['user_id' => auth()->id()]);
            $context = new ActionContext($session, ['test' => 'data']);
            
            $pipeline = new ActionPipeline();
            $result = $pipeline->execute($context);
            
            expect($result->success)->toBeTrue();
            expect($result->context->data)->toBe(['test' => 'data']);
        });

        it('passes data through complete pipeline', function () {
            $session = ImportSession::factory()->create(['user_id' => auth()->id()]);
            $context = new ActionContext($session, ['items' => []]);
            
            $addItemMiddleware = new class implements ImportMiddleware {
                public function handle(ActionContext $context, \Closure $next): ActionResult
                {
                    $items = $context->data['items'];
                    $items[] = 'item1';
                    $context->updateData(['items' => $items]);
                    return $next($context);
                }
            };
            
            $validateMiddleware = new class implements ImportMiddleware {
                public function handle(ActionContext $context, \Closure $next): ActionResult
                {
                    if (empty($context->data['items'])) {
                        return ActionResult::failure($context, 'No items to process');
                    }
                    return $next($context);
                }
            };
            
            $processMiddleware = new class implements ImportMiddleware {
                public function handle(ActionContext $context, \Closure $next): ActionResult
                {
                    $items = $context->data['items'];
                    $processedItems = array_map(fn($item) => "processed_$item", $items);
                    $context->updateData(['items' => $processedItems]);
                    return $next($context);
                }
            };
            
            $pipeline = new ActionPipeline();
            $result = $pipeline
                ->through([$addItemMiddleware, $validateMiddleware, $processMiddleware])
                ->execute($context);
            
            expect($result->success)->toBeTrue();
            expect($result->context->data['items'])->toBe(['processed_item1']);
        });

        it('accumulates errors from multiple middleware', function () {
            $session = ImportSession::factory()->create(['user_id' => auth()->id()]);
            $context = new ActionContext($session, []);
            
            $errorCollectingMiddleware1 = new class implements ImportMiddleware {
                public function handle(ActionContext $context, \Closure $next): ActionResult
                {
                    $result = $next($context);
                    if (!$result->success) {
                        $result->errors[] = 'Additional error from middleware 1';
                    }
                    return $result;
                }
            };
            
            $failingMiddleware = new class implements ImportMiddleware {
                public function handle(ActionContext $context, \Closure $next): ActionResult
                {
                    return ActionResult::failure($context, 'Core failure', ['Original error']);
                }
            };
            
            $pipeline = new ActionPipeline();
            $result = $pipeline
                ->through([$errorCollectingMiddleware1, $failingMiddleware])
                ->execute($context);
            
            expect($result->success)->toBeFalse();
            expect($result->errors)->toContain('Original error');
            expect($result->errors)->toContain('Additional error from middleware 1');
        });
    });

    describe('Pipeline Error Handling', function () {
        it('handles exceptions in middleware gracefully', function () {
            $session = ImportSession::factory()->create(['user_id' => auth()->id()]);
            $context = new ActionContext($session, []);
            
            $throwingMiddleware = new class implements ImportMiddleware {
                public function handle(ActionContext $context, \Closure $next): ActionResult
                {
                    throw new \Exception('Unexpected error in middleware');
                }
            };
            
            $pipeline = new ActionPipeline();
            
            expect(fn() => $pipeline
                ->through([$throwingMiddleware])
                ->execute($context))
                ->toThrow(\Exception::class, 'Unexpected error in middleware');
        });

        it('preserves context state during pipeline execution', function () {
            $session = ImportSession::factory()->create(['user_id' => auth()->id()]);
            $context = new ActionContext($session, ['initial' => 'data']);
            $context->setMetadata('initial_meta', 'value');
            
            $preservingMiddleware = new class implements ImportMiddleware {
                public function handle(ActionContext $context, \Closure $next): ActionResult
                {
                    $context->setMetadata('processed', true);
                    return $next($context);
                }
            };
            
            $pipeline = new ActionPipeline();
            $result = $pipeline
                ->through([$preservingMiddleware])
                ->execute($context);
            
            expect($result->context->data['initial'])->toBe('data');
            expect($result->context->getMetadata('initial_meta'))->toBe('value');
            expect($result->context->getMetadata('processed'))->toBeTrue();
        });
    });
});