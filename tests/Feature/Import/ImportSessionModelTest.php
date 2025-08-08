<?php

use App\Models\ImportSession;
use App\Models\User;

describe('ImportSession Model', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
    });

    describe('Status Management', function () {
        it('has correct default status', function () {
            $session = ImportSession::factory()->create(['user_id' => $this->user->id]);
            
            expect($session->status)->toBe('initializing');
        });

        it('can mark as started', function () {
            $session = ImportSession::factory()->create(['user_id' => $this->user->id]);
            
            $session->markAsStarted();
            
            expect($session->status)->toBe('processing');
            expect($session->started_at)->not->toBeNull();
            expect($session->started_at->diffInSeconds(now()))->toBeLessThan(2);
        });

        it('can mark as completed', function () {
            $session = ImportSession::factory()->create([
                'user_id' => $this->user->id,
                'started_at' => now()->subMinutes(5),
            ]);
            
            $session->markAsCompleted();
            
            expect($session->status)->toBe('completed');
            expect($session->completed_at)->not->toBeNull();
            expect($session->processing_time_seconds)->toBe(300); // 5 minutes
        });

        it('can mark as failed', function () {
            $session = ImportSession::factory()->create(['user_id' => $this->user->id]);
            
            $session->markAsFailed('Test error message');
            
            expect($session->status)->toBe('failed');
            expect($session->completed_at)->not->toBeNull();
            expect($session->errors)->toContain('Test error message');
        });

        it('validates status transitions', function () {
            $session = ImportSession::factory()->create([
                'user_id' => $this->user->id,
                'status' => 'completed',
            ]);
            
            expect(fn() => $session->markAsStarted())
                ->toThrow(InvalidArgumentException::class, 'Cannot start completed import');
        });
    });

    describe('Progress Tracking', function () {
        it('updates progress correctly', function () {
            $session = ImportSession::factory()->create(['user_id' => $this->user->id]);
            
            $session->updateProgress('processing', 'Processing data', 75);
            
            expect($session->current_stage)->toBe('processing');
            expect($session->current_operation)->toBe('Processing data');
            expect($session->progress_percentage)->toBe(75);
        });

        it('validates progress percentage bounds', function () {
            $session = ImportSession::factory()->create(['user_id' => $this->user->id]);
            
            expect(fn() => $session->updateProgress('processing', 'Test', -5))
                ->toThrow(InvalidArgumentException::class, 'Progress percentage must be between 0 and 100');

            expect(fn() => $session->updateProgress('processing', 'Test', 105))
                ->toThrow(InvalidArgumentException::class, 'Progress percentage must be between 0 and 100');
        });

        it('calculates processing time correctly', function () {
            $session = ImportSession::factory()->create([
                'user_id' => $this->user->id,
                'started_at' => now()->subMinutes(10),
                'completed_at' => now(),
            ]);
            
            expect($session->processing_time_seconds)->toBe(600); // 10 minutes
        });

        it('returns null processing time when not started', function () {
            $session = ImportSession::factory()->create(['user_id' => $this->user->id]);
            
            expect($session->processing_time_seconds)->toBeNull();
        });
    });

    describe('Error and Warning Management', function () {
        it('adds errors correctly', function () {
            $session = ImportSession::factory()->create(['user_id' => $this->user->id]);
            
            $session->addError('First error');
            $session->addError('Second error');
            
            $errors = $session->fresh()->errors;
            expect($errors)->toHaveCount(2);
            expect($errors[0])->toContain('First error');
            expect($errors[1])->toContain('Second error');
        });

        it('adds warnings correctly', function () {
            $session = ImportSession::factory()->create(['user_id' => $this->user->id]);
            
            $session->addWarning('First warning');
            $session->addWarning('Second warning');
            
            $warnings = $session->fresh()->warnings;
            expect($warnings)->toHaveCount(2);
            expect($warnings[0])->toContain('First warning');
            expect($warnings[1])->toContain('Second warning');
        });

        it('includes timestamps in error messages', function () {
            $session = ImportSession::factory()->create(['user_id' => $this->user->id]);
            
            $session->addError('Test error');
            
            $error = $session->fresh()->errors[0];
            expect($error)->toContain(now()->format('Y-m-d'));
        });
    });

    describe('Relationships', function () {
        it('belongs to user', function () {
            $session = ImportSession::factory()->create(['user_id' => $this->user->id]);
            
            expect($session->user->id)->toBe($this->user->id);
        });

        it('scopes to user correctly', function () {
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();
            
            $session1 = ImportSession::factory()->create(['user_id' => $user1->id]);
            $session2 = ImportSession::factory()->create(['user_id' => $user2->id]);
            
            expect(ImportSession::forUser($user1)->count())->toBe(1);
            expect(ImportSession::forUser($user2)->count())->toBe(1);
            expect(ImportSession::forUser($user1)->first()->id)->toBe($session1->id);
        });
    });

    describe('Configuration Management', function () {
        it('casts configuration as array', function () {
            $config = [
                'import_mode' => 'create_only',
                'chunk_size' => 100,
                'smart_extraction' => true,
            ];
            
            $session = ImportSession::factory()->create([
                'user_id' => $this->user->id,
                'configuration' => $config,
            ]);
            
            expect($session->configuration)->toBe($config);
            expect($session->configuration['import_mode'])->toBe('create_only');
        });

        it('handles empty configuration correctly', function () {
            $session = ImportSession::factory()->create([
                'user_id' => $this->user->id,
                'configuration' => null,
            ]);
            
            expect($session->configuration)->toBe([]);
        });
    });

    describe('File Analysis', function () {
        it('stores file analysis as array', function () {
            $analysis = [
                'headers' => ['Product', 'SKU', 'Price'],
                'total_rows' => 100,
                'sample_data' => [
                    ['Test Product 1', 'SKU-001', '99.99'],
                    ['Test Product 2', 'SKU-002', '199.99'],
                ],
            ];
            
            $session = ImportSession::factory()->create([
                'user_id' => $this->user->id,
                'file_analysis' => $analysis,
            ]);
            
            expect($session->file_analysis)->toBe($analysis);
            expect($session->file_analysis['total_rows'])->toBe(100);
        });

        it('handles column mapping correctly', function () {
            $mapping = [
                0 => 'product_name',
                1 => 'variant_sku',
                2 => 'retail_price',
            ];
            
            $session = ImportSession::factory()->create([
                'user_id' => $this->user->id,
                'column_mapping' => $mapping,
            ]);
            
            expect($session->column_mapping)->toBe($mapping);
        });
    });

    describe('Statistics and Results', function () {
        it('stores final results correctly', function () {
            $results = [
                'statistics' => [
                    'processed_rows' => 100,
                    'successful_rows' => 95,
                    'failed_rows' => 5,
                ],
                'performance_metrics' => [
                    'processing_time' => 120,
                    'rows_per_second' => 0.83,
                ],
            ];
            
            $session = ImportSession::factory()->create([
                'user_id' => $this->user->id,
                'final_results' => $results,
            ]);
            
            expect($session->final_results)->toBe($results);
            expect($session->final_results['statistics']['processed_rows'])->toBe(100);
        });

        it('calculates success rate correctly', function () {
            $session = ImportSession::factory()->create([
                'user_id' => $this->user->id,
                'processed_rows' => 100,
                'successful_rows' => 90,
                'failed_rows' => 10,
            ]);
            
            $successRate = ($session->successful_rows / $session->processed_rows) * 100;
            expect($successRate)->toBe(90.0);
        });
    });

    describe('Query Scopes', function () {
        it('filters by status', function () {
            ImportSession::factory()->create(['user_id' => $this->user->id, 'status' => 'completed']);
            ImportSession::factory()->create(['user_id' => $this->user->id, 'status' => 'failed']);
            ImportSession::factory()->create(['user_id' => $this->user->id, 'status' => 'processing']);
            
            expect(ImportSession::whereStatus('completed')->count())->toBe(1);
            expect(ImportSession::whereStatus('failed')->count())->toBe(1);
            expect(ImportSession::whereStatus('processing')->count())->toBe(1);
        });

        it('finds recent imports', function () {
            ImportSession::factory()->create([
                'user_id' => $this->user->id,
                'created_at' => now()->subDays(5),
            ]);
            ImportSession::factory()->create([
                'user_id' => $this->user->id,
                'created_at' => now()->subDays(1),
            ]);
            
            $recent = ImportSession::recent()->get();
            expect($recent)->toHaveCount(2);
            expect($recent->first()->created_at->isAfter($recent->last()->created_at))->toBeTrue();
        });
    });
});