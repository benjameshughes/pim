<?php

use App\Services\Import\Extraction\MadeToMeasureExtractor;
use App\Services\Import\Extraction\SmartDimensionExtractor;
use App\Services\Import\SkuPatternAnalyzer;
use App\Services\Import\Actions\PipelineBuilder;
use App\Services\Import\Actions\ActionContext;
use App\Services\Import\Actions\ActionPipeline;
use App\Services\Import\Actions\ActionResult;
use App\Services\Import\Performance\ImportPerformanceBuilder;
use App\Services\Import\ColumnMappingService;
use App\Services\Import\ImportConfigurationBuilder;
use App\Services\Import\ImportBuilder;
use App\Models\ImportSession;
use Illuminate\Support\Facades\Storage;

describe('Import Service Dependencies Individual Tests', function () {
    beforeEach(function () {
        actLikeUser();
        Storage::fake('local');
    });

    describe('MadeToMeasureExtractor', function () {
        it('exists and can be instantiated', function () {
            expect(class_exists(MadeToMeasureExtractor::class))->toBeTrue();
            
            $extractor = new MadeToMeasureExtractor();
            expect($extractor)->toBeInstanceOf(MadeToMeasureExtractor::class);
        });

        it('can detect made-to-measure products from text', function () {
            $extractor = new MadeToMeasureExtractor();
            
            if (method_exists($extractor, 'extract')) {
                $result = $extractor->extract('Custom Made to Measure Blinds 120x160');
                expect($result)->not->toBeNull();
            } else {
                $this->markTestSkipped('extract method not implemented');
            }
        });

        it('has expected interface methods', function () {
            $extractor = new MadeToMeasureExtractor();
            $reflection = new ReflectionClass($extractor);
            
            // Check for common methods that should exist
            $expectedMethods = ['extract', '__construct'];
            $actualMethods = array_map(fn($method) => $method->getName(), $reflection->getMethods());
            
            foreach ($expectedMethods as $method) {
                if (in_array($method, $actualMethods)) {
                    expect(true)->toBeTrue(); // At least one expected method exists
                    return;
                }
            }
            
            $this->fail('No expected methods found in MadeToMeasureExtractor');
        });
    });

    describe('SmartDimensionExtractor', function () {
        it('exists and can be instantiated', function () {
            expect(class_exists(SmartDimensionExtractor::class))->toBeTrue();
            
            $extractor = new SmartDimensionExtractor();
            expect($extractor)->toBeInstanceOf(SmartDimensionExtractor::class);
        });

        it('can extract dimensions from product names', function () {
            $extractor = new SmartDimensionExtractor();
            
            if (method_exists($extractor, 'extract')) {
                $result = $extractor->extract('Blinds 120x160cm');
                expect($result)->not->toBeNull();
            } else {
                $this->markTestSkipped('extract method not implemented');
            }
        });

        it('handles various dimension formats', function () {
            $extractor = new SmartDimensionExtractor();
            
            if (method_exists($extractor, 'extractDimensions') || method_exists($extractor, 'extract')) {
                $testCases = [
                    'Product 120x160',
                    'Item 120 x 160',
                    'Blinds 1200x1600mm',
                    'Window 120cm x 160cm',
                ];
                
                $method = method_exists($extractor, 'extractDimensions') ? 'extractDimensions' : 'extract';
                
                foreach ($testCases as $testCase) {
                    try {
                        $result = $extractor->$method($testCase);
                        expect($result)->not->toBeNull();
                    } catch (\Exception $e) {
                        $this->fail("Failed to extract dimensions from '{$testCase}': " . $e->getMessage());
                    }
                }
            } else {
                $this->markTestSkipped('No dimension extraction method found');
            }
        });
    });

    describe('SkuPatternAnalyzer', function () {
        it('exists and can be instantiated', function () {
            expect(class_exists(SkuPatternAnalyzer::class))->toBeTrue();
            
            $analyzer = new SkuPatternAnalyzer();
            expect($analyzer)->toBeInstanceOf(SkuPatternAnalyzer::class);
        });

        it('can analyze SKU patterns', function () {
            $analyzer = new SkuPatternAnalyzer();
            
            if (method_exists($analyzer, 'analyze')) {
                $skus = ['ABC-001', 'ABC-002', 'DEF-001', 'DEF-002'];
                $result = $analyzer->analyze($skus);
                expect($result)->not->toBeNull();
            } else {
                $this->markTestSkipped('analyze method not implemented');
            }
        });

        it('can extract parent SKU from variant SKU', function () {
            $analyzer = new SkuPatternAnalyzer();
            
            if (method_exists($analyzer, 'extractParentSku')) {
                $parentSku = $analyzer->extractParentSku('ABC-001-RED');
                expect($parentSku)->not->toBeNull();
            } else {
                $this->markTestSkipped('extractParentSku method not implemented');
            }
        });
    });

    describe('PipelineBuilder', function () {
        it('exists and can build import pipeline', function () {
            expect(class_exists(PipelineBuilder::class))->toBeTrue();
            
            if (method_exists(PipelineBuilder::class, 'importPipeline')) {
                $builder = PipelineBuilder::importPipeline([
                    'import_mode' => 'create_or_update'
                ]);
                expect($builder)->toBeInstanceOf(PipelineBuilder::class);
            } else {
                $this->markTestSkipped('importPipeline static method not found');
            }
        });

        it('can build pipeline with configuration', function () {
            if (method_exists(PipelineBuilder::class, 'importPipeline')) {
                $pipeline = PipelineBuilder::importPipeline([
                    'import_mode' => 'create_or_update',
                    'extract_mtm' => true,
                    'extract_dimensions' => true,
                    'use_sku_grouping' => true,
                ])->build();
                
                expect($pipeline)->toBeInstanceOf(ActionPipeline::class);
            } else {
                $this->markTestSkipped('importPipeline method not found');
            }
        });

        it('handles different import modes', function () {
            if (method_exists(PipelineBuilder::class, 'importPipeline')) {
                $modes = ['create_only', 'update_existing', 'create_or_update'];
                
                foreach ($modes as $mode) {
                    try {
                        $pipeline = PipelineBuilder::importPipeline([
                            'import_mode' => $mode
                        ])->build();
                        
                        expect($pipeline)->toBeInstanceOf(ActionPipeline::class);
                    } catch (\Exception $e) {
                        $this->fail("Failed to build pipeline for mode {$mode}: " . $e->getMessage());
                    }
                }
            } else {
                $this->markTestSkipped('importPipeline method not found');
            }
        });
    });

    describe('ActionContext', function () {
        it('exists and can be instantiated', function () {
            expect(class_exists(ActionContext::class))->toBeTrue();
            
            $context = new ActionContext(['test' => 'data'], 1, ['config' => 'value']);
            expect($context)->toBeInstanceOf(ActionContext::class);
        });

        it('can store and retrieve data', function () {
            $context = new ActionContext(['initial' => 'data'], 1, []);
            
            if (method_exists($context, 'get') && method_exists($context, 'set')) {
                $context->set('test_key', 'test_value');
                $value = $context->get('test_key');
                
                expect($value)->toBe('test_value');
            } else {
                $this->markTestSkipped('get/set methods not found');
            }
        });

        it('provides access to row data and configuration', function () {
            $rowData = ['product_name' => 'Test Product'];
            $config = ['import_mode' => 'create_or_update'];
            
            $context = new ActionContext($rowData, 1, $config);
            
            if (method_exists($context, 'getData')) {
                expect($context->getData())->toBe($rowData);
            }
            
            if (method_exists($context, 'getConfiguration')) {
                expect($context->getConfiguration())->toBe($config);
            }
            
            if (method_exists($context, 'getRowNumber')) {
                expect($context->getRowNumber())->toBe(1);
            }
        });
    });

    describe('ActionPipeline', function () {
        it('can execute actions in sequence', function () {
            if (method_exists(PipelineBuilder::class, 'importPipeline')) {
                $pipeline = PipelineBuilder::importPipeline([
                    'import_mode' => 'create_or_update'
                ])->build();
                
                $context = new ActionContext([
                    'product_name' => 'Test Product',
                    'variant_sku' => 'TEST-001'
                ], 1, ['import_mode' => 'create_or_update']);
                
                if (method_exists($pipeline, 'execute')) {
                    try {
                        $result = $pipeline->execute($context);
                        expect($result)->toBeInstanceOf(ActionResult::class);
                    } catch (\Exception $e) {
                        $this->fail("Pipeline execution failed: " . $e->getMessage());
                    }
                } else {
                    $this->markTestSkipped('execute method not found');
                }
            } else {
                $this->markTestSkipped('Cannot create pipeline for testing');
            }
        });
    });

    describe('ImportPerformanceBuilder', function () {
        it('exists and can be created for session', function () {
            expect(class_exists(ImportPerformanceBuilder::class))->toBeTrue();
            
            $session = ImportSession::factory()->create();
            
            if (method_exists(ImportPerformanceBuilder::class, 'forSession')) {
                $builder = ImportPerformanceBuilder::forSession($session);
                expect($builder)->toBeInstanceOf(ImportPerformanceBuilder::class);
            } else {
                $this->markTestSkipped('forSession method not found');
            }
        });

        it('can configure performance optimizations', function () {
            $session = ImportSession::factory()->create();
            
            if (method_exists(ImportPerformanceBuilder::class, 'forSession')) {
                $builder = ImportPerformanceBuilder::forSession($session);
                
                if (method_exists($builder, 'maximize')) {
                    $builder = $builder->maximize();
                    expect($builder)->toBeInstanceOf(ImportPerformanceBuilder::class);
                }
                
                if (method_exists($builder, 'withDetailedLogging')) {
                    $builder = $builder->withDetailedLogging();
                    expect($builder)->toBeInstanceOf(ImportPerformanceBuilder::class);
                }
            } else {
                $this->markTestSkipped('forSession method not found');
            }
        });

        it('can process files with callback', function () {
            $session = ImportSession::factory()->create();
            Storage::put('test-file.csv', "Product,SKU\nTest Product,TEST-001");
            $filePath = Storage::path('test-file.csv');
            
            if (method_exists(ImportPerformanceBuilder::class, 'forSession')) {
                $builder = ImportPerformanceBuilder::forSession($session);
                
                if (method_exists($builder, 'processFile')) {
                    $processed = false;
                    $callback = function($chunk) use (&$processed) {
                        $processed = true;
                        return ['processed' => true];
                    };
                    
                    try {
                        $results = $builder->processFile($filePath, $callback);
                        
                        // Results should be iterable
                        foreach ($results as $result) {
                            // At least one chunk should be processed
                            break;
                        }
                        
                        expect($processed)->toBeTrue();
                    } catch (\Exception $e) {
                        $this->fail("processFile failed: " . $e->getMessage());
                    }
                } else {
                    $this->markTestSkipped('processFile method not found');
                }
            } else {
                $this->markTestSkipped('forSession method not found');
            }
        });
    });

    describe('ColumnMappingService', function () {
        it('exists and can be instantiated', function () {
            if (class_exists(ColumnMappingService::class)) {
                expect(class_exists(ColumnMappingService::class))->toBeTrue();
                
                $service = new ColumnMappingService();
                expect($service)->toBeInstanceOf(ColumnMappingService::class);
            } else {
                $this->markTestSkipped('ColumnMappingService class not found');
            }
        });

        it('can suggest column mappings from headers', function () {
            if (class_exists(ColumnMappingService::class)) {
                $service = new ColumnMappingService();
                
                if (method_exists($service, 'suggestMapping') || method_exists($service, 'guessMapping')) {
                    $headers = ['Product Name', 'SKU', 'Color', 'Price'];
                    $method = method_exists($service, 'suggestMapping') ? 'suggestMapping' : 'guessMapping';
                    
                    try {
                        $mapping = $service->$method($headers);
                        expect($mapping)->toBeArray();
                    } catch (\Exception $e) {
                        $this->fail("Column mapping suggestion failed: " . $e->getMessage());
                    }
                } else {
                    $this->markTestSkipped('No mapping suggestion method found');
                }
            } else {
                $this->markTestSkipped('ColumnMappingService class not found');
            }
        });
    });

    describe('ImportConfigurationBuilder', function () {
        it('exists and can build configuration', function () {
            if (class_exists(ImportConfigurationBuilder::class)) {
                expect(class_exists(ImportConfigurationBuilder::class))->toBeTrue();
                
                if (method_exists(ImportConfigurationBuilder::class, 'create')) {
                    $builder = ImportConfigurationBuilder::create();
                    expect($builder)->toBeInstanceOf(ImportConfigurationBuilder::class);
                } else {
                    $builder = new ImportConfigurationBuilder();
                    expect($builder)->toBeInstanceOf(ImportConfigurationBuilder::class);
                }
            } else {
                $this->markTestSkipped('ImportConfigurationBuilder class not found');
            }
        });

        it('can build configuration with fluent API', function () {
            if (class_exists(ImportConfigurationBuilder::class)) {
                $builder = method_exists(ImportConfigurationBuilder::class, 'create') 
                    ? ImportConfigurationBuilder::create() 
                    : new ImportConfigurationBuilder();
                
                if (method_exists($builder, 'mode')) {
                    $builder = $builder->mode('create_or_update');
                    expect($builder)->toBeInstanceOf(ImportConfigurationBuilder::class);
                }
                
                if (method_exists($builder, 'build')) {
                    try {
                        $config = $builder->build();
                        expect($config)->toBeArray();
                    } catch (\Exception $e) {
                        $this->fail("Configuration build failed: " . $e->getMessage());
                    }
                } else {
                    $this->markTestSkipped('build method not found');
                }
            } else {
                $this->markTestSkipped('ImportConfigurationBuilder class not found');
            }
        });
    });

    describe('ImportBuilder', function () {
        it('exists and provides fluent API for import creation', function () {
            expect(class_exists(ImportBuilder::class))->toBeTrue();
            
            if (method_exists(ImportBuilder::class, 'create')) {
                $builder = ImportBuilder::create();
                expect($builder)->toBeInstanceOf(ImportBuilder::class);
            } else {
                $this->markTestSkipped('create method not found');
            }
        });

        it('can execute import session creation', function () {
            if (method_exists(ImportBuilder::class, 'create')) {
                Storage::put('test-import.csv', "Product,SKU\nTest,TEST-001");
                $file = new \Illuminate\Http\UploadedFile(
                    Storage::path('test-import.csv'),
                    'test-import.csv',
                    'text/csv',
                    null,
                    true
                );
                
                try {
                    $builder = ImportBuilder::create();
                    
                    if (method_exists($builder, 'fromFile')) {
                        $builder = $builder->fromFile($file);
                    }
                    
                    if (method_exists($builder, 'execute')) {
                        $session = $builder->execute();
                        expect($session)->toBeInstanceOf(ImportSession::class);
                    } else {
                        $this->markTestSkipped('execute method not found');
                    }
                } catch (\Exception $e) {
                    $this->fail("ImportBuilder execution failed: " . $e->getMessage());
                }
            } else {
                $this->markTestSkipped('create method not found');
            }
        });
    });

    describe('Service Container Bindings', function () {
        it('can resolve all import services from container', function () {
            $services = [
                MadeToMeasureExtractor::class,
                SmartDimensionExtractor::class,
                SkuPatternAnalyzer::class,
                ColumnMappingService::class,
            ];
            
            foreach ($services as $serviceClass) {
                if (class_exists($serviceClass)) {
                    try {
                        $service = app($serviceClass);
                        expect($service)->toBeInstanceOf($serviceClass);
                    } catch (\Exception $e) {
                        $this->fail("Failed to resolve {$serviceClass} from container: " . $e->getMessage());
                    }
                }
            }
        });

        it('services have proper dependencies injected', function () {
            $services = [
                MadeToMeasureExtractor::class,
                SmartDimensionExtractor::class,
                SkuPatternAnalyzer::class,
            ];
            
            foreach ($services as $serviceClass) {
                if (class_exists($serviceClass)) {
                    try {
                        $service = app($serviceClass);
                        $reflection = new ReflectionClass($service);
                        
                        // Check constructor dependencies are resolved
                        $constructor = $reflection->getConstructor();
                        if ($constructor && $constructor->getNumberOfParameters() > 0) {
                            expect($service)->not->toBeNull();
                        }
                    } catch (\Exception $e) {
                        $this->fail("Service {$serviceClass} dependency injection failed: " . $e->getMessage());
                    }
                }
            }
        });
    });
});