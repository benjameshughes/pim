<?php

use App\Models\ImportSession;
use App\Jobs\Import\AnalyzeFileJob;
use App\Jobs\Import\DryRunJob;
use App\Jobs\Import\ProcessImportJob;
use App\Jobs\Import\FinalizeImportJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;

describe('Import Jobs Pipeline', function () {
    beforeEach(function () {
        $this->actingAs(\App\Models\User::factory()->create());
        Storage::fake('local');
        Queue::fake();
    });

    describe('AnalyzeFileJob', function () {
        it('analyzes CSV file structure correctly', function () {
            $csvContent = "Product Name,SKU,Price,Color\nTest Product,TEST-001,99.99,Red\nAnother Product,TEST-002,149.99,Blue";
            $file = UploadedFile::fake()->createWithContent('test.csv', $csvContent);
            
            $session = ImportSession::factory()->create([
                'file_path' => $file->store('imports'),
                'file_type' => 'csv',
                'status' => 'analyzing_file',
            ]);
            
            $job = new AnalyzeFileJob($session);
            $job->handle();
            
            $session->refresh();
            expect($session->file_analysis)->toHaveKey('headers');
            expect($session->file_analysis)->toHaveKey('total_rows');
            expect($session->file_analysis)->toHaveKey('sample_data');
            expect($session->file_analysis['headers'])->toBe(['Product Name', 'SKU', 'Price', 'Color']);
            expect($session->file_analysis['total_rows'])->toBe(2); // Excluding header
        });

        it('suggests column mapping automatically', function () {
            $csvContent = "Product Name,SKU,Price,Color\nTest Product,TEST-001,99.99,Red";
            $file = UploadedFile::fake()->createWithContent('test.csv', $csvContent);
            
            $session = ImportSession::factory()->create([
                'file_path' => $file->store('imports'),
                'file_type' => 'csv',
                'status' => 'analyzing_file',
            ]);
            
            $job = new AnalyzeFileJob($session);
            $job->handle();
            
            $session->refresh();
            expect($session->file_analysis)->toHaveKey('suggested_mapping');
            
            $mapping = $session->file_analysis['suggested_mapping'];
            expect($mapping[0])->toBe('product_name'); // Product Name
            expect($mapping[1])->toBe('variant_sku');  // SKU
            expect($mapping[2])->toBe('retail_price');  // Price
            expect($mapping[3])->toBe('variant_color'); // Color
        });

        it('dispatches dry run job after completion', function () {
            $session = ImportSession::factory()->create([
                'file_path' => 'test.csv',
                'file_type' => 'csv',
                'status' => 'analyzing_file',
            ]);
            
            Storage::put($session->file_path, "Product,SKU\nTest,TEST-001");
            
            $job = new AnalyzeFileJob($session);
            $job->handle();
            
            Queue::assertPushed(DryRunJob::class);
        });

        it('handles file analysis errors gracefully', function () {
            $session = ImportSession::factory()->create([
                'file_path' => 'nonexistent.csv',
                'file_type' => 'csv',
                'status' => 'analyzing_file',
            ]);
            
            $job = new AnalyzeFileJob($session);
            $job->handle();
            
            $session->refresh();
            expect($session->status)->toBe('failed');
            expect($session->errors)->not->toBeEmpty();
        });
    });

    describe('DryRunJob', function () {
        it('validates import data without making changes', function () {
            $csvContent = "Product Name,SKU,Price\nValid Product,VALID-001,99.99\n,INVALID-002,not-a-price";
            $file = UploadedFile::fake()->createWithContent('test.csv', $csvContent);
            
            $session = ImportSession::factory()->create([
                'file_path' => $file->store('imports'),
                'file_type' => 'csv',
                'status' => 'dry_run',
                'column_mapping' => [
                    0 => 'product_name',
                    1 => 'variant_sku',
                    2 => 'retail_price',
                ],
            ]);
            
            $job = new DryRunJob($session);
            $job->handle();
            
            $session->refresh();
            expect($session->dry_run_results)->toHaveKey('predictions');
            expect($session->dry_run_results)->toHaveKey('validation_summary');
            expect($session->dry_run_results['predictions']['will_create'])->toBe(1);
            expect($session->dry_run_results['predictions']['will_skip'])->toBe(1);
        });

        it('detects potential conflicts', function () {
            // Create existing product with same SKU
            \App\Models\ProductVariant::factory()->create(['sku' => 'EXISTING-001']);
            
            $csvContent = "Product Name,SKU\nTest Product,EXISTING-001";
            $file = UploadedFile::fake()->createWithContent('test.csv', $csvContent);
            
            $session = ImportSession::factory()->create([
                'file_path' => $file->store('imports'),
                'file_type' => 'csv',
                'status' => 'dry_run',
                'column_mapping' => [0 => 'product_name', 1 => 'variant_sku'],
                'configuration' => ['import_mode' => 'create_only'],
            ]);
            
            $job = new DryRunJob($session);
            $job->handle();
            
            $session->refresh();
            expect($session->dry_run_results['conflict_analysis']['potential_sku_conflicts'])->toBeGreaterThan(0);
        });

        it('generates quality metrics', function () {
            $csvContent = "Product Name,SKU,Price\nValid Product,VALID-001,99.99\nAnother Valid,VALID-002,149.99";
            $file = UploadedFile::fake()->createWithContent('test.csv', $csvContent);
            
            $session = ImportSession::factory()->create([
                'file_path' => $file->store('imports'),
                'file_type' => 'csv',
                'status' => 'dry_run',
                'column_mapping' => [
                    0 => 'product_name',
                    1 => 'variant_sku', 
                    2 => 'retail_price',
                ],
            ]);
            
            $job = new DryRunJob($session);
            $job->handle();
            
            $session->refresh();
            expect($session->dry_run_results)->toHaveKey('quality_metrics');
            expect($session->dry_run_results['quality_metrics']['data_completeness'])->toBeGreaterThan(0);
        });

        it('dispatches process job after successful dry run', function () {
            $csvContent = "Product Name,SKU\nTest Product,TEST-001";
            $file = UploadedFile::fake()->createWithContent('test.csv', $csvContent);
            
            $session = ImportSession::factory()->create([
                'file_path' => $file->store('imports'),
                'file_type' => 'csv',
                'status' => 'dry_run',
                'column_mapping' => [0 => 'product_name', 1 => 'variant_sku'],
            ]);
            
            $job = new DryRunJob($session);
            $job->handle();
            
            Queue::assertPushed(ProcessImportJob::class);
        });
    });

    describe('ProcessImportJob', function () {
        it('processes import data and creates records', function () {
            $csvContent = "Product Name,SKU,Price\nTest Product,TEST-001,99.99\nAnother Product,TEST-002,149.99";
            $file = UploadedFile::fake()->createWithContent('test.csv', $csvContent);
            
            $session = ImportSession::factory()->create([
                'file_path' => $file->store('imports'),
                'file_type' => 'csv',
                'status' => 'processing',
                'column_mapping' => [
                    0 => 'product_name',
                    1 => 'variant_sku',
                    2 => 'retail_price',
                ],
                'configuration' => [
                    'import_mode' => 'create_or_update',
                    'chunk_size' => 50,
                ],
            ]);
            
            $job = new ProcessImportJob($session);
            $job->handle();
            
            $session->refresh();
            expect($session->status)->toBe('completed');
            expect($session->successful_rows)->toBe(2);
            expect($session->failed_rows)->toBe(0);
            
            // Verify data was created
            expect(\App\Models\Product::where('name', 'Test Product')->exists())->toBeTrue();
            expect(\App\Models\ProductVariant::where('sku', 'TEST-001')->exists())->toBeTrue();
            expect(\App\Models\ProductVariant::where('sku', 'TEST-002')->exists())->toBeTrue();
        });

        it('handles processing errors gracefully', function () {
            $csvContent = "Product Name,SKU\n,INVALID-001\nValid Product,VALID-002";
            $file = UploadedFile::fake()->createWithContent('test.csv', $csvContent);
            
            $session = ImportSession::factory()->create([
                'file_path' => $file->store('imports'),
                'file_type' => 'csv',
                'status' => 'processing',
                'column_mapping' => [0 => 'product_name', 1 => 'variant_sku'],
            ]);
            
            $job = new ProcessImportJob($session);
            $job->handle();
            
            $session->refresh();
            expect($session->successful_rows)->toBe(1);
            expect($session->failed_rows)->toBe(1);
            expect($session->errors)->not->toBeEmpty();
        });

        it('dispatches finalize job after completion', function () {
            $csvContent = "Product Name,SKU\nTest Product,TEST-001";
            $file = UploadedFile::fake()->createWithContent('test.csv', $csvContent);
            
            $session = ImportSession::factory()->create([
                'file_path' => $file->store('imports'),
                'file_type' => 'csv',
                'status' => 'processing',
                'column_mapping' => [0 => 'product_name', 1 => 'variant_sku'],
            ]);
            
            $job = new ProcessImportJob($session);
            $job->handle();
            
            Queue::assertPushed(FinalizeImportJob::class);
        });
    });

    describe('FinalizeImportJob', function () {
        it('generates comprehensive import report', function () {
            $session = ImportSession::factory()->create([
                'status' => 'completed',
                'total_rows' => 100,
                'processed_rows' => 95,
                'successful_rows' => 90,
                'failed_rows' => 5,
                'final_results' => [
                    'statistics' => [
                        'products_created' => 20,
                        'variants_created' => 85,
                        'products_updated' => 5,
                        'variants_updated' => 5,
                    ],
                ],
            ]);
            
            $job = new FinalizeImportJob($session);
            $job->handle();
            
            $session->refresh();
            $report = $session->final_results['comprehensive_report'];
            
            expect($report)->toHaveKey('import_summary');
            expect($report)->toHaveKey('processing_results');
            expect($report)->toHaveKey('data_created');
            expect($report)->toHaveKey('quality_metrics');
            expect($report)->toHaveKey('recommendations');
            
            expect($report['processing_results']['success_rate'])->toBe(94.74); // 90/95 * 100
        });

        it('provides quality recommendations based on results', function () {
            $session = ImportSession::factory()->create([
                'status' => 'completed',
                'final_results' => [
                    'statistics' => [
                        'processed_rows' => 100,
                        'failed_rows' => 25, // High error rate
                        'successful_rows' => 75,
                    ],
                ],
            ]);
            
            $job = new FinalizeImportJob($session);
            $job->handle();
            
            $session->refresh();
            $recommendations = $session->final_results['comprehensive_report']['recommendations'];
            
            expect($recommendations)->not->toBeEmpty();
            expect(collect($recommendations)->pluck('type'))->toContain('data_quality');
        });

        it('cleans up temporary files', function () {
            $file = UploadedFile::fake()->create('temp.csv', 1024);
            $filePath = $file->store('imports');
            
            $session = ImportSession::factory()->create([
                'file_path' => $filePath,
                'status' => 'completed',
            ]);
            
            expect(Storage::exists($filePath))->toBeTrue();
            
            $job = new FinalizeImportJob($session);
            $job->handle();
            
            expect(Storage::exists($filePath))->toBeFalse();
        });

        it('handles finalization errors gracefully', function () {
            $session = ImportSession::factory()->create([
                'file_path' => 'nonexistent.csv',
                'status' => 'completed',
            ]);
            
            $job = new FinalizeImportJob($session);
            $job->handle(); // Should not throw exception
            
            $session->refresh();
            expect($session->warnings)->not->toBeEmpty();
        });
    });

    describe('Job Pipeline Integration', function () {
        it('executes complete pipeline in correct order', function () {
            $csvContent = "Product Name,SKU\nTest Product,TEST-001\nAnother Product,TEST-002";
            $file = UploadedFile::fake()->createWithContent('test.csv', $csvContent);
            
            $session = ImportSession::factory()->create([
                'file_path' => $file->store('imports'),
                'file_type' => 'csv',
                'status' => 'initializing',
            ]);
            
            // 1. Analyze File
            $analyzeJob = new AnalyzeFileJob($session);
            $analyzeJob->handle();
            
            $session->refresh();
            expect($session->file_analysis)->toHaveKey('headers');
            
            // 2. Set column mapping and start dry run
            $session->update([
                'column_mapping' => [0 => 'product_name', 1 => 'variant_sku'],
                'status' => 'dry_run',
            ]);
            
            $dryRunJob = new DryRunJob($session);
            $dryRunJob->handle();
            
            $session->refresh();
            expect($session->dry_run_results)->toHaveKey('predictions');
            
            // 3. Process Import
            $session->update(['status' => 'processing']);
            
            $processJob = new ProcessImportJob($session);
            $processJob->handle();
            
            $session->refresh();
            expect($session->status)->toBe('completed');
            expect($session->successful_rows)->toBe(2);
            
            // 4. Finalize
            $finalizeJob = new FinalizeImportJob($session);
            $finalizeJob->handle();
            
            $session->refresh();
            expect($session->final_results)->toHaveKey('comprehensive_report');
            
            // Verify all jobs were dispatched
            Queue::assertPushed(DryRunJob::class);
            Queue::assertPushed(ProcessImportJob::class);
            Queue::assertPushed(FinalizeImportJob::class);
        });
    });
});