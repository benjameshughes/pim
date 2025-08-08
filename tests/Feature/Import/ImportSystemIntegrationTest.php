<?php

use App\Models\ImportSession;
use App\Services\Import\ImportBuilder;
use App\Jobs\Import\AnalyzeFileJob;
use App\Jobs\Import\DryRunJob;
use App\Jobs\Import\ProcessImportJob;
use App\Jobs\Import\FinalizeImportJob;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

describe('Import System Integration', function () {
    beforeEach(function () {
        $this->actLikeUser();
        Storage::fake('local');
        Queue::fake();
    });

    describe('ImportBuilder', function () {
        it('creates import session with fluent API', function () {
            $file = UploadedFile::fake()->create('test.xlsx', 1024);
            
            $session = ImportBuilder::create()
                ->fromFile($file)
                ->withMode('create_or_update')
                ->extractAttributes()
                ->detectMadeToMeasure()
                ->dimensionsDigitsOnly()
                ->groupBySku()
                ->execute();
            
            expect($session)->toBeInstanceOf(ImportSession::class);
            expect($session->original_filename)->toBe('test.xlsx');
            expect($session->status)->toBe('initializing');
            
            $config = $session->configuration;
            expect($config['import_mode'])->toBe('create_or_update');
            expect($config['smart_attribute_extraction'])->toBeTrue();
            expect($config['detect_made_to_measure'])->toBeTrue();
            expect($config['dimensions_digits_only'])->toBeTrue();
            expect($config['group_by_sku'])->toBeTrue();
        });

        it('validates file type and size', function () {
            $invalidFile = UploadedFile::fake()->create('test.txt', 1024);
            
            expect(fn() => ImportBuilder::create()->fromFile($invalidFile))
                ->toThrow(InvalidArgumentException::class);
        });

        it('sets default configuration values', function () {
            $file = UploadedFile::fake()->create('test.csv', 1024);
            
            $session = ImportBuilder::create()
                ->fromFile($file)
                ->execute();
            
            $config = $session->configuration;
            expect($config['import_mode'])->toBe('create_or_update');
            expect($config['chunk_size'])->toBe(50);
            expect($config['smart_attribute_extraction'])->toBeTrue();
        });
    });

    describe('ImportSession Model', function () {
        it('tracks progress correctly', function () {
            $session = ImportSession::factory()->create([
                'status' => 'processing',
                'progress_percentage' => 0,
            ]);
            
            $session->updateProgress('processing', 'Analyzing data', 25);
            
            expect($session->fresh()->progress_percentage)->toBe(25);
            expect($session->fresh()->current_stage)->toBe('processing');
            expect($session->fresh()->current_operation)->toBe('Analyzing data');
        });

        it('manages status transitions', function () {
            $session = ImportSession::factory()->create();
            
            expect($session->status)->toBe('initializing');
            
            $session->markAsStarted();
            expect($session->fresh()->status)->toBe('processing');
            expect($session->fresh()->started_at)->not->toBeNull();
            
            $session->markAsCompleted();
            expect($session->fresh()->status)->toBe('completed');
            expect($session->fresh()->completed_at)->not->toBeNull();
        });

        it('calculates processing time', function () {
            $session = ImportSession::factory()->create([
                'started_at' => now()->subMinutes(5),
                'completed_at' => now(),
            ]);
            
            expect($session->processing_time_seconds)->toBe(300);
        });

        it('manages errors and warnings', function () {
            $session = ImportSession::factory()->create();
            
            $session->addError('Test error message');
            $session->addWarning('Test warning message');
            
            $errors = $session->fresh()->errors;
            $warnings = $session->fresh()->warnings;
            
            expect($errors)->toHaveCount(1);
            expect($warnings)->toHaveCount(1);
            expect($errors[0])->toContain('Test error message');
            expect($warnings[0])->toContain('Test warning message');
        });
    });

    describe('Job Pipeline', function () {
        it('dispatches analyze file job on import creation', function () {
            $file = UploadedFile::fake()->create('test.xlsx', 1024);
            
            ImportBuilder::create()
                ->fromFile($file)
                ->execute();
            
            Queue::assertPushed(AnalyzeFileJob::class);
        });

        it('processes complete job pipeline', function () {
            $session = ImportSession::factory()->create([
                'status' => 'analyzing_file',
                'file_analysis' => [
                    'worksheets' => [
                        ['name' => 'Sheet1', 'total_rows' => 100]
                    ],
                    'suggested_mapping' => [
                        0 => 'product_name',
                        1 => 'variant_sku',
                    ]
                ],
                'column_mapping' => [
                    0 => 'product_name',
                    1 => 'variant_sku',
                ],
            ]);
            
            // Test analyze file job
            $analyzeJob = new AnalyzeFileJob($session);
            $analyzeJob->handle();
            
            // Should dispatch dry run job
            Queue::assertPushed(DryRunJob::class);
            
            // Test dry run job
            $session->refresh();
            $session->update(['status' => 'dry_run']);
            
            $dryRunJob = new DryRunJob($session);
            $dryRunJob->handle();
            
            // Should dispatch process job
            Queue::assertPushed(ProcessImportJob::class);
        });
    });

    describe('File Analysis', function () {
        it('analyzes Excel file structure', function () {
            // Create a simple Excel file for testing
            $file = UploadedFile::fake()->create('test.xlsx', 1024);
            
            $session = ImportSession::factory()->create([
                'file_path' => $file->store('imports'),
                'file_type' => 'xlsx',
            ]);
            
            $analyzeJob = new AnalyzeFileJob($session);
            $analyzeJob->handle();
            
            $session->refresh();
            expect($session->file_analysis)->toHaveKey('worksheets');
            expect($session->file_analysis)->toHaveKey('headers');
            expect($session->file_analysis)->toHaveKey('sample_data');
        });

        it('suggests column mapping', function () {
            $session = ImportSession::factory()->create([
                'file_analysis' => [
                    'headers' => [
                        'Product Name',
                        'SKU',
                        'Price',
                        'Color',
                        'Barcode'
                    ]
                ]
            ]);
            
            $analyzeJob = new AnalyzeFileJob($session);
            $mapping = $analyzeJob->suggestColumnMapping($session->file_analysis['headers']);
            
            expect($mapping)->toHaveKey(0); // Product Name -> product_name
            expect($mapping)->toHaveKey(1); // SKU -> variant_sku
            expect($mapping[0])->toBe('product_name');
            expect($mapping[1])->toBe('variant_sku');
        });
    });

    describe('Dry Run Validation', function () {
        it('validates data before processing', function () {
            $session = ImportSession::factory()->create([
                'status' => 'dry_run',
                'file_path' => 'test-file.csv',
                'file_type' => 'csv',
                'column_mapping' => [
                    0 => 'product_name',
                    1 => 'variant_sku',
                    2 => 'retail_price',
                ],
                'configuration' => [
                    'import_mode' => 'create_or_update',
                    'detect_made_to_measure' => true,
                ]
            ]);
            
            // Mock CSV content
            Storage::put($session->file_path, "Product Name,SKU,Price\nTest Product,TEST-001,99.99\nMTM Blinds,MTM-001,199.99");
            
            $dryRunJob = new DryRunJob($session);
            $dryRunJob->handle();
            
            $session->refresh();
            expect($session->dry_run_results)->toHaveKey('predictions');
            expect($session->dry_run_results)->toHaveKey('validation_summary');
            expect($session->dry_run_results)->toHaveKey('quality_metrics');
        });

        it('detects potential conflicts', function () {
            // Create existing data that would conflict
            ProductVariant::factory()->create(['sku' => 'EXISTING-001']);
            
            $session = ImportSession::factory()->create([
                'status' => 'dry_run',
                'file_path' => 'conflict-test.csv',
                'file_type' => 'csv',
                'column_mapping' => [0 => 'product_name', 1 => 'variant_sku'],
            ]);
            
            Storage::put($session->file_path, "Product,SKU\nTest,EXISTING-001");
            
            $dryRunJob = new DryRunJob($session);
            $dryRunJob->handle();
            
            $session->refresh();
            $conflicts = $session->dry_run_results['conflict_analysis'] ?? [];
            expect($conflicts['potential_sku_conflicts'])->toBeGreaterThan(0);
        });
    });

    describe('Data Processing', function () {
        it('processes import data with actions pipeline', function () {
            $product = Product::factory()->create(['name' => 'Existing Product']);
            
            $session = ImportSession::factory()->create([
                'status' => 'processing',
                'file_path' => 'process-test.csv',
                'file_type' => 'csv',
                'column_mapping' => [
                    0 => 'product_name',
                    1 => 'variant_sku',
                    2 => 'variant_color',
                ],
                'configuration' => [
                    'import_mode' => 'create_or_update',
                    'extract_attributes' => true,
                ]
            ]);
            
            Storage::put($session->file_path, "Product,SKU,Color\nExisting Product,VAR-001,blue\nNew Product,VAR-002,red");
            
            $processJob = new ProcessImportJob($session);
            $processJob->handle();
            
            $session->refresh();
            expect($session->status)->toBe('completed');
            expect($session->successful_rows)->toBeGreaterThan(0);
            
            // Verify data was created
            expect(ProductVariant::where('sku', 'VAR-001')->exists())->toBeTrue();
            expect(ProductVariant::where('sku', 'VAR-002')->exists())->toBeTrue();
        });

        it('handles processing errors gracefully', function () {
            $session = ImportSession::factory()->create([
                'status' => 'processing',
                'file_path' => 'error-test.csv',
                'file_type' => 'csv',
                'column_mapping' => [0 => 'product_name', 1 => 'variant_sku'],
            ]);
            
            // Invalid data that should cause errors
            Storage::put($session->file_path, "Product,SKU\n,INVALID-001\nValid Product,");
            
            $processJob = new ProcessImportJob($session);
            $processJob->handle();
            
            $session->refresh();
            expect($session->failed_rows)->toBeGreaterThan(0);
            expect($session->errors)->not->toBeEmpty();
        });
    });

    describe('Import Finalization', function () {
        it('generates comprehensive report', function () {
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
                    ]
                ]
            ]);
            
            $finalizeJob = new FinalizeImportJob($session);
            $finalizeJob->handle();
            
            $session->refresh();
            $report = $session->final_results['comprehensive_report'];
            
            expect($report)->toHaveKey('import_summary');
            expect($report)->toHaveKey('processing_results');
            expect($report)->toHaveKey('data_created');
            expect($report)->toHaveKey('quality_metrics');
            expect($report)->toHaveKey('recommendations');
            expect($report['processing_results']['success_rate'])->toBe(94.74); // 90/95 * 100
        });

        it('provides quality recommendations', function () {
            $session = ImportSession::factory()->create([
                'final_results' => [
                    'statistics' => [
                        'processed_rows' => 100,
                        'failed_rows' => 20, // High error rate
                        'successful_rows' => 80,
                    ]
                ]
            ]);
            
            $finalizeJob = new FinalizeImportJob($session);
            $finalizeJob->handle();
            
            $session->refresh();
            $recommendations = $session->final_results['comprehensive_report']['recommendations'];
            
            expect($recommendations)->not->toBeEmpty();
            expect(collect($recommendations)->pluck('type'))->toContain('data_quality');
        });
    });
});