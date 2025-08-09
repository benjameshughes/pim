<?php

use App\Services\Import\Actions\ActionContext;
use App\Services\Import\Actions\ActionResult;
use App\Services\Import\Actions\Middleware\LoggingMiddleware;
use App\Services\Import\Actions\Middleware\TimingMiddleware;
use App\Services\Import\Actions\Middleware\ErrorHandlingMiddleware;
use Illuminate\Support\Facades\Log;

describe('Import Middleware', function () {
    beforeEach(function () {
        Log::shouldReceive('debug')->andReturn(null);
        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('warning')->andReturn(null);
        Log::shouldReceive('error')->andReturn(null);
    });

    describe('LoggingMiddleware', function () {
        it('logs successful pipeline execution when configured', function () {
            $middleware = LoggingMiddleware::create([
                'log_successful' => true,
                'log_failed' => false,
            ]);

            $context = new ActionContext(['test' => 'data'], 1);
            $result = ActionResult::success($context, 'Pipeline succeeded')->withData(['result' => 'success']);

            Log::shouldReceive('info')
                ->with('Action pipeline succeeded', \Mockery::type('array'))
                ->once();

            $actualResult = $middleware->handle($context, function () use ($result) {
                return $result;
            });

            expect($actualResult)->toBe($result);
        });

        it('logs failed pipeline execution', function () {
            $middleware = LoggingMiddleware::create([
                'log_failed' => true,
            ]);

            $context = new ActionContext(['test' => 'data'], 1);
            $result = ActionResult::failed('Test error');

            Log::shouldReceive('warning')
                ->with('Action pipeline failed', \Mockery::type('array'))
                ->once();

            $actualResult = $middleware->handle($context, function () use ($result) {
                return $result;
            });

            expect($actualResult)->toBe($result);
        });

        it('logs context when configured', function () {
            $middleware = LoggingMiddleware::create([
                'log_context' => true,
            ]);

            $context = new ActionContext(['test' => 'data'], 1);

            Log::shouldReceive('debug')
                ->with('Action pipeline context', \Mockery::type('array'))
                ->once();

            $middleware->handle($context, function () {
                return ActionResult::success();
            });
        });

        it('handles pipeline exceptions', function () {
            $middleware = LoggingMiddleware::create();
            $context = new ActionContext(['test' => 'data'], 1);

            Log::shouldReceive('error')
                ->with('Action pipeline threw exception', \Mockery::type('array'))
                ->once();

            $result = $middleware->handle($context, function () {
                throw new \Exception('Test exception');
            });

            expect($result->isFailure())->toBeTrue();
            expect($result->getError())->toContain('Pipeline exception');
        });
    });

    describe('TimingMiddleware', function () {
        it('includes timing data when configured', function () {
            $middleware = TimingMiddleware::create(30.0, true);
            $context = new ActionContext(['test' => 'data'], 1);

            $result = $middleware->handle($context, function () {
                usleep(1000); // 1ms
                return ActionResult::success();
            });

            expect($result->isSuccess())->toBeTrue();
            expect($result->getData())->toHaveKey('execution_time_ms');
            expect($result->getData())->toHaveKey('execution_time_seconds');
            expect($result->get('execution_time_ms'))->toBeGreaterThan(0);
        });

        it('sets metadata for execution duration', function () {
            $middleware = TimingMiddleware::create();
            $context = new ActionContext(['test' => 'data'], 1);

            $middleware->handle($context, function () {
                return ActionResult::success();
            });

            expect($context->getMetadataValue('execution_duration'))->toBeGreaterThan(0);
        });

        it('warns about approaching timeout', function () {
            $middleware = TimingMiddleware::create(0.001, true); // 1ms timeout
            $context = new ActionContext(['test' => 'data'], 1);

            $middleware->handle($context, function () {
                usleep(1500); // 1.5ms - should trigger warning
                return ActionResult::success();
            });

            expect($context->getMetadataValue('performance_warning'))
                ->toContain('approaching timeout');
        });

        it('handles timeout exceptions', function () {
            $middleware = TimingMiddleware::create(30.0, true);
            $context = new ActionContext(['test' => 'data'], 1);

            $result = $middleware->handle($context, function () {
                throw new \Exception('Maximum execution time exceeded');
            });

            expect($result->isFailure())->toBeTrue();
            expect($result->getError())->toBe('Pipeline execution timed out');
            expect($result->get('timeout_occurred'))->toBeTrue();
        });
    });

    describe('ErrorHandlingMiddleware', function () {
        it('succeeds on first attempt', function () {
            $middleware = ErrorHandlingMiddleware::create([
                'retry_on_failure' => true,
                'max_retries' => 2,
            ]);

            $context = new ActionContext(['test' => 'data'], 1);
            $expected = ActionResult::success(null, 'Success on first try')->withData(['first_try' => true]);

            $result = $middleware->handle($context, function () use ($expected) {
                return $expected;
            });

            expect($result)->toBe($expected);
        });

        it('retries on retryable exceptions', function () {
            $middleware = ErrorHandlingMiddleware::withRetries(2, [
                \Illuminate\Database\QueryException::class
            ]);

            $context = new ActionContext(['test' => 'data'], 1);
            $attempts = 0;

            Log::shouldReceive('warning')->twice(); // Two retry attempts
            Log::shouldReceive('info')->once(); // Success after retry

            $result = $middleware->handle($context, function () use (&$attempts) {
                $attempts++;
                if ($attempts < 3) {
                    throw new \Illuminate\Database\QueryException(
                        'SELECT * FROM test',
                        [],
                        new \Exception('Connection timeout')
                    );
                }
                return ActionResult::success(null, 'Success after attempts')->withData(['attempts' => $attempts]);
            });

            expect($result->isSuccess())->toBeTrue();
            expect($result->get('retry_attempt'))->toBe(2);
            expect($result->get('retry_successful'))->toBeTrue();
        });

        it('fails after max retries exceeded', function () {
            $middleware = ErrorHandlingMiddleware::withRetries(1);
            $context = new ActionContext(['test' => 'data'], 1);

            Log::shouldReceive('warning')->once(); // One retry
            Log::shouldReceive('error')->once(); // Final failure

            $result = $middleware->handle($context, function () {
                throw new \Illuminate\Database\QueryException(
                    'SELECT * FROM test',
                    [],
                    new \Exception('Persistent connection error')
                );
            });

            expect($result->isFailure())->toBeTrue();
            expect($result->getError())->toContain('Failed after 2 attempt(s)');
            expect($result->get('exhausted_retries'))->toBeTrue();
        });

        it('does not retry non-retryable exceptions', function () {
            $middleware = ErrorHandlingMiddleware::withRetries(2);
            $context = new ActionContext(['test' => 'data'], 1);

            Log::shouldReceive('error')->once(); // No retries, direct to error

            $result = $middleware->handle($context, function () {
                throw new \InvalidArgumentException('Not retryable');
            });

            expect($result->isFailure())->toBeTrue();
            expect($result->get('retry_attempts'))->toBe(0);
        });

        it('applies graceful degradation', function () {
            $middleware = ErrorHandlingMiddleware::withGracefulDegradation();
            $context = new ActionContext(['partial' => 'data'], 1);

            Log::shouldReceive('error')->once();

            $result = $middleware->handle($context, function () {
                throw new \Exception('Something went wrong');
            });

            expect($result->isFailure())->toBeTrue();
            expect($result->get('graceful_degradation'))->toBeTrue();
            expect($result->get('partial_data'))->toHaveKey('partial');
        });

        it('identifies transient error patterns', function () {
            $middleware = ErrorHandlingMiddleware::withRetries(1);
            $context = new ActionContext(['test' => 'data'], 1);

            // Should retry on timeout errors
            Log::shouldReceive('warning')->once();
            Log::shouldReceive('error')->once();

            $result = $middleware->handle($context, function () {
                throw new \Exception('Connection timeout occurred');
            });

            expect($result->isFailure())->toBeTrue();
            expect($result->get('retry_attempts'))->toBe(1);
        });
    });

    describe('Middleware Stacking', function () {
        it('applies middleware in correct order', function () {
            $context = new ActionContext(['test' => 'data'], 1);
            $order = [];

            $middleware1 = new class($order) implements \App\Services\Import\Actions\ImportMiddleware {
                private $order;
                public function __construct(&$order) { $this->order = &$order; }
                public function handle(\App\Services\Import\Actions\ActionContext $context, \Closure $next): \App\Services\Import\Actions\ActionResult {
                    $this->order[] = 'middleware1_before';
                    $result = $next($context);
                    $this->order[] = 'middleware1_after';
                    return $result;
                }
            };

            $middleware2 = new class($order) implements \App\Services\Import\Actions\ImportMiddleware {
                private $order;
                public function __construct(&$order) { $this->order = &$order; }
                public function handle(\App\Services\Import\Actions\ActionContext $context, \Closure $next): \App\Services\Import\Actions\ActionResult {
                    $this->order[] = 'middleware2_before';
                    $result = $next($context);
                    $this->order[] = 'middleware2_after';
                    return $result;
                }
            };

            // Simulate pipeline execution with stacked middleware
            $pipeline = function () use (&$order) {
                $order[] = 'action_executed';
                return ActionResult::success();
            };

            // Stack middleware (reverse order like Laravel)
            $wrappedPipeline = function () use ($middleware2, $pipeline, $context) {
                return $middleware2->handle($context, $pipeline);
            };

            $finalPipeline = function () use ($middleware1, $wrappedPipeline, $context) {
                return $middleware1->handle($context, $wrappedPipeline);
            };

            $finalPipeline();

            expect($order)->toBe([
                'middleware1_before',
                'middleware2_before', 
                'action_executed',
                'middleware2_after',
                'middleware1_after',
            ]);
        });
    });
});