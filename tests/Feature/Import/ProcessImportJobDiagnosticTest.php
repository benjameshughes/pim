<?php

use App\Jobs\Import\ProcessImportJob;
use App\Models\ImportSession;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Import\Extraction\MadeToMeasureExtractor;
use App\Services\Import\Extraction\SmartDimensionExtractor;
use App\Services\Import\SkuPatternAnalyzer;
use App\Services\Import\Actions\PipelineBuilder;
use App\Services\Import\Actions\ActionContext;
use App\Services\Import\Actions\ActionPipeline;
use App\Services\Import\Performance\ImportPerformanceBuilder;
use App\Services\Import\ColumnMappingService;
use App\Services\Import\ImportConfigurationBuilder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;

describe('ProcessImportJob Diagnostic Tests', function () {
    beforeEach(function () {
        actLikeUser();
        Storage::fake('local');
        Queue::fake();
        
        // Create test CSV content
        $this->testCsvContent = "Product Name,SKU,Color,Price,Barcode\n" .
                               "Test Product,TEST-001,red,99.99,1234567890123\n" .
                               "Test Product,TEST-002,blue,89.99,1234567890124\n" .
                               "Made to Measure Blinds,MTM-001,white,199.99,1234567890125";
        
        Storage::put('test_imports/test.csv', $this->testCsvContent);
    });

    describe('Service Dependencies Existence', function () {
        it('verifies MadeToMeasureExtractor exists and is instantiable', function () {
            expect(class_exists(MadeToMeasureExtractor::class))->toBeTrue();
            
            try {
                $extractor = app(MadeToMeasureExtractor::class);
                expect($extractor)->toBeInstanceOf(MadeToMeasureExtractor::class);
            } catch (\Exception $e) {
                $this->fail("MadeToMeasureExtractor instantiation failed: " . $e->getMessage());
            }
        });

        it('verifies SmartDimensionExtractor exists and is instantiable', function () {
            expect(class_exists(SmartDimensionExtractor::class))->toBeTrue();
            
            try {
                $extractor = app(SmartDimensionExtractor::class);
                expect($extractor)->toBeInstanceOf(SmartDimensionExtractor::class);
            } catch (\Exception $e) {
                $this->fail("SmartDimensionExtractor instantiation failed: " . $e->getMessage());
            }
        });

        it('verifies SkuPatternAnalyzer exists and is instantiable', function () {
            expect(class_exists(SkuPatternAnalyzer::class))->toBeTrue();
            
            try {
                $analyzer = app(SkuPatternAnalyzer::class);
                expect($analyzer)->toBeInstanceOf(SkuPatternAnalyzer::class);
            } catch (\Exception $e) {
                $this->fail("SkuPatternAnalyzer instantiation failed: " . $e->getMessage());
            }
        });

        it('verifies PipelineBuilder exists and can build pipeline', function () {
            expect(class_exists(PipelineBuilder::class))->toBeTrue();
            
            try {
                $pipeline = PipelineBuilder::importPipeline([
                    'import_mode' => 'create_or_update',
                    'extract_mtm' => true,
                    'extract_dimensions' => true,
                ])->build();
                
                expect($pipeline)->toBeInstanceOf(ActionPipeline::class);
            } catch (\Exception $e) {
                $this->fail("PipelineBuilder failed to build pipeline: " . $e->getMessage());
            }
        });

        it('verifies ActionContext and ActionPipeline classes exist', function () {
            expect(class_exists(ActionContext::class))->toBeTrue();
            expect(class_exists(ActionPipeline::class))->toBeTrue();
            
            try {
                $context = new ActionContext(['test' => 'data'], 1, []);
                expect($context)->toBeInstanceOf(ActionContext::class);
            } catch (\Exception $e) {
                $this->fail("ActionContext instantiation failed: " . $e->getMessage());
            }
        });

        it('verifies ImportPerformanceBuilder exists and can be created', function () {
            expect(class_exists(ImportPerformanceBuilder::class))->toBeTrue();
            
            $session = ImportSession::factory()->create();
            
            try {
                $builder = ImportPerformanceBuilder::forSession($session);
                expect($builder)->toBeInstanceOf(ImportPerformanceBuilder::class);
            } catch (\Exception $e) {
                $this->fail("ImportPerformanceBuilder instantiation failed: " . $e->getMessage());
            }
        });
    });

    describe('ProcessImportJob Basic Functionality', function () {
        it('can be instantiated with ImportSession', function () {
            $session = ImportSession::factory()->create([
                'file_path' => 'test_imports/test.csv',
                'status' => 'processing',
                'column_mapping' => [
                    0 => 'product_name',
                    1 => 'variant_sku',
                    2 => 'variant_color',
                    3 => 'retail_price',
                    4 => 'barcode'
                ],
                'configuration' => [
                    'import_mode' => 'create_or_update',
                    'detect_made_to_measure' => true,
                    'dimensions_digits_only' => true,
                    'group_by_sku' => false,
                ]
            ]);
            
            try {
                $job = new ProcessImportJob($session);
                expect($job)->toBeInstanceOf(ProcessImportJob::class);
            } catch (\Exception $e) {
                $this->fail("ProcessImportJob instantiation failed: " . $e->getMessage());
            }
        });

        it('handles missing services gracefully during initialization', function () {
            $session = ImportSession::factory()->create([
                'file_path' => 'test_imports/test.csv',
                'status' => 'processing',
                'configuration' => [
                    'detect_made_to_measure' => true,
                    'dimensions_digits_only' => true,
                    'group_by_sku' => true,
                ]
            ]);
            
            $job = new ProcessImportJob($session);
            
            try {
                // Use reflection to call private method to test initialization
                $reflection = new \ReflectionClass($job);
                $method = $reflection->getMethod('initializeExtractors');
                $method->setAccessible(true);
                $method->invoke($job);
                
                // If we get here, initialization worked
                expect(true)->toBeTrue();
            } catch (\Exception $e) {
                $this->fail("Extractor initialization failed: " . $e->getMessage());
            }
        });
    });

    describe('ProcessImportJob Execution Flow', function () {
        it('handles complete import process without errors', function () {
            $session = ImportSession::factory()->create([
                'file_path' => 'test_imports/test.csv',
                'file_type' => 'csv',
                'status' => 'processing',
                'total_rows' => 3,
                'column_mapping' => [
                    0 => 'product_name',
                    1 => 'variant_sku',
                    2 => 'variant_color',
                    3 => 'retail_price',
                    4 => 'barcode'
                ],
                'configuration' => [
                    'import_mode' => 'create_or_update',
                    'detect_made_to_measure' => false,
                    'dimensions_digits_only' => false,
                    'group_by_sku' => false,
                    'smart_attribute_extraction' => true,
                ]
            ]);
            
            $job = new ProcessImportJob($session);
            
            try {
                $job->handle();
                
                // Verify session was updated
                $session->refresh();
                expect($session->status)->toBeIn(['completed', 'processing']);
                
                // Verify some products/variants were created
                expect(Product::count())->toBeGreaterThan(0);
                expect(ProductVariant::count())->toBeGreaterThan(0);
                
            } catch (\Exception $e) {
                $this->fail("Job execution failed: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
            }
        });

        it('handles different import modes correctly', function () {
            $modes = ['create_only', 'update_existing', 'create_or_update'];
            
            foreach ($modes as $mode) {
                // Clear previous data
                DB::table('products')->delete();
                DB::table('product_variants')->delete();
                
                $session = ImportSession::factory()->create([
                    'file_path' => 'test_imports/test.csv',
                    'file_type' => 'csv',
                    'status' => 'processing',
                    'total_rows' => 3,
                    'column_mapping' => [
                        0 => 'product_name',
                        1 => 'variant_sku',
                        2 => 'variant_color',
                        3 => 'retail_price',
                        4 => 'barcode'
                    ],
                    'configuration' => [
                        'import_mode' => $mode,
                        'detect_made_to_measure' => false,
                        'dimensions_digits_only' => false,
                        'group_by_sku' => false,
                    ]
                ]);
                
                $job = new ProcessImportJob($session);
                
                try {
                    $job->handle();
                    
                    $session->refresh();
                    expect($session->status)->not->toBe('failed')
                        ->and($session->final_results)->not->toBeNull();
                        
                } catch (\Exception $e) {
                    $this->fail("Job execution failed for mode {$mode}: " . $e->getMessage());
                }
            }
        });
    });

    describe('Error Handling and Recovery', function () {
        it('handles invalid file paths gracefully', function () {
            $session = ImportSession::factory()->create([
                'file_path' => 'nonexistent/file.csv',
                'status' => 'processing',
                'configuration' => ['import_mode' => 'create_or_update']
            ]);
            
            $job = new ProcessImportJob($session);
            
            try {
                $job->handle();
            } catch (\Exception $e) {
                // Expected to fail, but should be handled gracefully
                $session->refresh();
                expect($session->status)->toBe('failed');
            }
        });

        it('handles corrupted configuration data', function () {
            $session = ImportSession::factory()->create([
                'file_path' => 'test_imports/test.csv',
                'status' => 'processing',
                'configuration' => null, // Corrupted configuration
            ]);
            
            $job = new ProcessImportJob($session);
            
            try {
                $job->handle();
                
                // Should handle gracefully with default configuration
                $session->refresh();
                expect($session->status)->toBeIn(['completed', 'failed']);
                
            } catch (\Exception $e) {
                $this->fail("Job should handle null configuration gracefully: " . $e->getMessage());
            }
        });

        it('handles empty CSV files', function () {
            Storage::put('test_imports/empty.csv', '');
            
            $session = ImportSession::factory()->create([
                'file_path' => 'test_imports/empty.csv',
                'file_type' => 'csv',
                'status' => 'processing',
                'total_rows' => 0,
                'configuration' => ['import_mode' => 'create_or_update']
            ]);
            
            $job = new ProcessImportJob($session);
            
            try {
                $job->handle();
                
                $session->refresh();
                expect($session->status)->toBe('completed');
                expect($session->processed_rows)->toBe(0);
                
            } catch (\Exception $e) {
                $this->fail("Job should handle empty files gracefully: " . $e->getMessage());
            }
        });
    });

    describe('Performance and Memory Management', function () {
        it('handles large datasets without memory exhaustion', function () {
            // Create a larger CSV for testing
            $largeCsvContent = "Product Name,SKU,Color,Price\n";
            for ($i = 1; $i <= 1000; $i++) {
                $largeCsvContent .= "Product {$i},SKU-{$i},red,99.99\n";
            }
            Storage::put('test_imports/large.csv', $largeCsvContent);
            
            $session = ImportSession::factory()->create([
                'file_path' => 'test_imports/large.csv',
                'file_type' => 'csv',
                'status' => 'processing',
                'total_rows' => 1000,
                'column_mapping' => [
                    0 => 'product_name',
                    1 => 'variant_sku',
                    2 => 'variant_color',
                    3 => 'retail_price'
                ],
                'configuration' => [
                    'import_mode' => 'create_or_update',
                    'chunk_size' => 50,
                ]
            ]);
            
            $memoryBefore = memory_get_usage();
            $job = new ProcessImportJob($session);
            
            try {
                $job->handle();
                
                $memoryAfter = memory_get_usage();
                $memoryIncrease = ($memoryAfter - $memoryBefore) / 1024 / 1024; // MB
                
                // Memory increase should be reasonable (less than 50MB for 1000 rows)
                expect($memoryIncrease)->toBeLessThan(50);
                
                $session->refresh();
                expect($session->status)->toBe('completed');
                expect($session->processed_rows)->toBe(1000);
                
            } catch (\Exception $e) {
                $this->fail("Large dataset processing failed: " . $e->getMessage());
            }
        });

        it('processes files efficiently with performance monitoring', function () {
            $session = ImportSession::factory()->create([
                'file_path' => 'test_imports/test.csv',
                'file_type' => 'csv',
                'status' => 'processing',
                'total_rows' => 3,
                'column_mapping' => [0 => 'product_name', 1 => 'variant_sku'],
                'configuration' => ['import_mode' => 'create_or_update']
            ]);
            
            $startTime = microtime(true);
            $job = new ProcessImportJob($session);
            $job->handle();
            $endTime = microtime(true);
            
            $processingTime = $endTime - $startTime;
            
            // Should process small file quickly (under 5 seconds)
            expect($processingTime)->toBeLessThan(5.0);
            
            $session->refresh();
            expect($session->final_results)->toHaveKey('performance_metrics');
        });
    });

    describe('Data Integrity and Validation', function () {
        it('maintains database consistency during processing', function () {
            $session = ImportSession::factory()->create([
                'file_path' => 'test_imports/test.csv',
                'file_type' => 'csv',
                'status' => 'processing',
                'total_rows' => 3,
                'column_mapping' => [
                    0 => 'product_name',
                    1 => 'variant_sku',
                    2 => 'variant_color'
                ],
                'configuration' => ['import_mode' => 'create_or_update']
            ]);
            
            $productCountBefore = Product::count();
            $variantCountBefore = ProductVariant::count();
            
            $job = new ProcessImportJob($session);
            $job->handle();
            
            $productCountAfter = Product::count();
            $variantCountAfter = ProductVariant::count();
            
            // Should have created some products and variants
            expect($productCountAfter)->toBeGreaterThan($productCountBefore);
            expect($variantCountAfter)->toBeGreaterThan($variantCountBefore);
            
            // All variants should have valid product relationships
            $orphanedVariants = ProductVariant::whereDoesntHave('product')->count();
            expect($orphanedVariants)->toBe(0);
        });

        it('handles database constraint violations gracefully', function () {
            // Create existing data that might cause conflicts
            $product = Product::factory()->create(['name' => 'Test Product']);
            ProductVariant::factory()->create([
                'product_id' => $product->id,
                'sku' => 'TEST-001'
            ]);
            
            // Try to import duplicate SKU
            Storage::put('test_imports/duplicate.csv', "Product Name,SKU\nTest Product,TEST-001");
            
            $session = ImportSession::factory()->create([
                'file_path' => 'test_imports/duplicate.csv',
                'file_type' => 'csv',
                'status' => 'processing',
                'total_rows' => 1,
                'column_mapping' => [0 => 'product_name', 1 => 'variant_sku'],
                'configuration' => ['import_mode' => 'create_only']
            ]);
            
            $job = new ProcessImportJob($session);
            $job->handle();
            
            $session->refresh();
            
            // Should complete without crashing, even with conflicts
            expect($session->status)->toBeIn(['completed', 'failed']);
            
            if ($session->status === 'completed') {
                // Should have recorded the conflict appropriately
                expect($session->final_results['statistics']['skipped_rows'] ?? 0)->toBeGreaterThan(0);
            }
        });
    });

    describe('Progress Tracking and Reporting', function () {
        it('updates progress accurately during processing', function () {
            $session = ImportSession::factory()->create([
                'file_path' => 'test_imports/test.csv',
                'file_type' => 'csv',
                'status' => 'processing',
                'total_rows' => 3,
                'progress_percentage' => 0,
                'column_mapping' => [0 => 'product_name', 1 => 'variant_sku'],
                'configuration' => ['import_mode' => 'create_or_update']
            ]);
            
            $job = new ProcessImportJob($session);
            $job->handle();
            
            $session->refresh();
            
            // Progress should be updated
            expect($session->progress_percentage)->toBeGreaterThan(0);
            expect($session->processed_rows)->toBeGreaterThan(0);
            
            // Final results should contain comprehensive statistics
            $stats = $session->final_results['statistics'] ?? [];
            expect($stats)->toHaveKeys([
                'processed_rows',
                'successful_rows',
                'failed_rows',
                'skipped_rows'
            ]);
        });

        it('generates comprehensive final results', function () {
            $session = ImportSession::factory()->create([
                'file_path' => 'test_imports/test.csv',
                'file_type' => 'csv',
                'status' => 'processing',
                'total_rows' => 3,
                'column_mapping' => [0 => 'product_name', 1 => 'variant_sku'],
                'configuration' => ['import_mode' => 'create_or_update']
            ]);
            
            $job = new ProcessImportJob($session);
            $job->handle();
            
            $session->refresh();
            
            expect($session->final_results)->toHaveKeys([
                'processing_completed_at',
                'processing_duration_seconds',
                'statistics',
                'performance_metrics'
            ]);
            
            $perfMetrics = $session->final_results['performance_metrics'];
            expect($perfMetrics)->toHaveKeys([
                'rows_per_second',
                'memory_peak_mb'
            ]);
        });
    });
});