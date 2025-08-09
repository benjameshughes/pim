<?php

use App\Services\Import\Extraction\MadeToMeasureExtractor;
use App\Services\Import\Extraction\SmartDimensionExtractor;
use App\Services\Import\SkuPatternAnalyzer;
use App\Services\Import\Actions\PipelineBuilder;
use App\Services\Import\Actions\ActionContext;
use App\Services\Import\Actions\ActionPipeline;
use App\Services\Import\Actions\ActionResult;
use App\Services\Import\Actions\ValidateRowAction;
use App\Services\Import\Actions\ExtractAttributesAction;
use App\Services\Import\Actions\ResolveProductAction;
use App\Services\Import\Performance\ImportPerformanceBuilder;
use App\Services\Import\ColumnMappingService;
use App\Services\Import\ImportConfigurationBuilder;
use App\Services\Import\ImportBuilder;
use App\Models\ImportSession;
use App\Jobs\Import\ProcessImportJob;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

describe('Import System COMPREHENSIVE DIAGNOSTIC REPORT', function () {
    beforeEach(function () {
        actLikeUser();
        Storage::fake('local');
        
        // Create comprehensive test data
        Storage::put('comprehensive_test.csv', 
            "Product Name,SKU,Color,Price,Barcode,Width,Drop,MTM\n" .
            "Vertical Blinds,VB-001,white,99.99,1234567890123,120,160,No\n" .
            "Made to Measure Curtains,MTM-001,blue,199.99,1234567890124,150,180,Yes\n" .
            "Window Shades 90x120,WS-001,beige,79.99,1234567890125,90,120,No"
        );
    });

    describe('🔍 DIAGNOSTIC REPORT: Import System Health Check', function () {
        it('🚨 MASTER DIAGNOSTIC: Full Import System Health Check', function () {
            $diagnosticResults = [];
            
            // === SERVICE EXISTENCE CHECK ===
            $diagnosticResults['service_existence'] = [];
            
            $services = [
                'MadeToMeasureExtractor' => MadeToMeasureExtractor::class,
                'SmartDimensionExtractor' => SmartDimensionExtractor::class,
                'SkuPatternAnalyzer' => SkuPatternAnalyzer::class,
                'PipelineBuilder' => PipelineBuilder::class,
                'ActionContext' => ActionContext::class,
                'ActionPipeline' => ActionPipeline::class,
                'ActionResult' => ActionResult::class,
                'ImportPerformanceBuilder' => ImportPerformanceBuilder::class,
                'ColumnMappingService' => ColumnMappingService::class,
                'ImportConfigurationBuilder' => ImportConfigurationBuilder::class,
                'ImportBuilder' => ImportBuilder::class,
                'ProcessImportJob' => ProcessImportJob::class,
            ];
            
            foreach ($services as $name => $class) {
                $diagnosticResults['service_existence'][$name] = [
                    'exists' => class_exists($class),
                    'instantiable' => false,
                    'error' => null,
                ];
                
                if ($diagnosticResults['service_existence'][$name]['exists']) {
                    try {
                        if ($name === 'ProcessImportJob') {
                            $session = ImportSession::factory()->create();
                            new $class($session);
                        } elseif ($name === 'ActionContext') {
                            new $class([], 1, []);
                        } else {
                            app($class);
                        }
                        $diagnosticResults['service_existence'][$name]['instantiable'] = true;
                    } catch (\Exception $e) {
                        $diagnosticResults['service_existence'][$name]['error'] = $e->getMessage();
                    }
                }
            }
            
            // === ACTION RESULT COMPATIBILITY CHECK ===
            $diagnosticResults['action_result_compatibility'] = [];
            
            try {
                // Test ActionResult::success() method signature
                $result = ActionResult::success();
                $diagnosticResults['action_result_compatibility']['success_no_params'] = true;
            } catch (\Exception $e) {
                $diagnosticResults['action_result_compatibility']['success_no_params'] = false;
                $diagnosticResults['action_result_compatibility']['success_no_params_error'] = $e->getMessage();
            }
            
            try {
                $context = new ActionContext(['test' => 'data'], 1, []);
                $result = ActionResult::success($context, 'Test message');
                $diagnosticResults['action_result_compatibility']['success_with_context'] = true;
            } catch (\Exception $e) {
                $diagnosticResults['action_result_compatibility']['success_with_context'] = false;
                $diagnosticResults['action_result_compatibility']['success_with_context_error'] = $e->getMessage();
            }
            
            try {
                // This is what's currently breaking - calling with array instead of context
                $result = ActionResult::success(['test' => 'data']);
                $diagnosticResults['action_result_compatibility']['success_with_array'] = true;
            } catch (\Exception $e) {
                $diagnosticResults['action_result_compatibility']['success_with_array'] = false;
                $diagnosticResults['action_result_compatibility']['success_with_array_error'] = $e->getMessage();
            }
            
            // === PIPELINE BUILDER TEST ===
            $diagnosticResults['pipeline_builder'] = [];
            
            try {
                $pipeline = PipelineBuilder::importPipeline([
                    'import_mode' => 'create_or_update'
                ])->build();
                
                $diagnosticResults['pipeline_builder']['can_build'] = true;
                $diagnosticResults['pipeline_builder']['pipeline_type'] = get_class($pipeline);
                
                // Test pipeline execution
                $context = new ActionContext([
                    'product_name' => 'Test Product',
                    'variant_sku' => 'TEST-001'
                ], 1, ['import_mode' => 'create_or_update']);
                
                $result = $pipeline->execute($context);
                $diagnosticResults['pipeline_builder']['can_execute'] = true;
                $diagnosticResults['pipeline_builder']['execution_result'] = [
                    'success' => $result->isSuccess(),
                    'message' => $result->getError(),
                    'type' => get_class($result),
                ];
                
            } catch (\Exception $e) {
                $diagnosticResults['pipeline_builder']['can_build'] = false;
                $diagnosticResults['pipeline_builder']['error'] = $e->getMessage();
                $diagnosticResults['pipeline_builder']['trace'] = $e->getTraceAsString();
            }
            
            // === INDIVIDUAL ACTION TESTS ===
            $diagnosticResults['individual_actions'] = [];
            
            $actions = [
                'ValidateRowAction' => ValidateRowAction::class,
                'ExtractAttributesAction' => ExtractAttributesAction::class,
                'ResolveProductAction' => ResolveProductAction::class,
            ];
            
            foreach ($actions as $name => $class) {
                $diagnosticResults['individual_actions'][$name] = [
                    'exists' => class_exists($class),
                    'can_instantiate' => false,
                    'can_execute' => false,
                    'error' => null,
                ];
                
                if (class_exists($class)) {
                    try {
                        $action = new $class();
                        $diagnosticResults['individual_actions'][$name]['can_instantiate'] = true;
                        
                        $context = new ActionContext([
                            'product_name' => 'Test Product',
                            'variant_sku' => 'TEST-001'
                        ], 1, ['import_mode' => 'create_or_update']);
                        
                        $result = $action->execute($context);
                        $diagnosticResults['individual_actions'][$name]['can_execute'] = true;
                        $diagnosticResults['individual_actions'][$name]['execution_result'] = [
                            'success' => $result->isSuccess(),
                            'message' => $result->getError(),
                        ];
                        
                    } catch (\Exception $e) {
                        $diagnosticResults['individual_actions'][$name]['error'] = $e->getMessage();
                    }
                }
            }
            
            // === IMPORT BUILDER TEST ===
            $diagnosticResults['import_builder'] = [];
            
            try {
                $file = new \Illuminate\Http\UploadedFile(
                    Storage::path('comprehensive_test.csv'),
                    'comprehensive_test.csv',
                    'text/csv',
                    null,
                    true
                );
                
                $session = ImportBuilder::create()
                    ->fromFile($file)
                    ->withMode('create_or_update')
                    ->execute();
                
                $diagnosticResults['import_builder']['can_create_session'] = true;
                $diagnosticResults['import_builder']['session_id'] = $session->session_id;
                $diagnosticResults['import_builder']['session_status'] = $session->status;
                
            } catch (\Exception $e) {
                $diagnosticResults['import_builder']['can_create_session'] = false;
                $diagnosticResults['import_builder']['error'] = $e->getMessage();
            }
            
            // === PROCESS IMPORT JOB TEST ===
            $diagnosticResults['process_import_job'] = [];
            
            try {
                $session = ImportSession::factory()->create([
                    'file_path' => 'comprehensive_test.csv',
                    'file_type' => 'csv',
                    'status' => 'processing',
                    'total_rows' => 3,
                    'column_mapping' => [
                        0 => 'product_name',
                        1 => 'variant_sku',
                        2 => 'variant_color',
                        3 => 'retail_price',
                        4 => 'barcode',
                        5 => 'width',
                        6 => 'drop',
                        7 => 'made_to_measure'
                    ],
                    'configuration' => [
                        'import_mode' => 'create_or_update',
                        'detect_made_to_measure' => false,
                        'dimensions_digits_only' => false,
                        'group_by_sku' => false,
                    ]
                ]);
                
                $job = new ProcessImportJob($session);
                
                // Test job initialization
                $diagnosticResults['process_import_job']['can_instantiate'] = true;
                
                // Test job execution
                $job->handle();
                
                $session->refresh();
                $diagnosticResults['process_import_job']['can_execute'] = true;
                $diagnosticResults['process_import_job']['final_status'] = $session->status;
                $diagnosticResults['process_import_job']['processed_rows'] = $session->processed_rows;
                $diagnosticResults['process_import_job']['successful_rows'] = $session->successful_rows;
                $diagnosticResults['process_import_job']['failed_rows'] = $session->failed_rows;
                $diagnosticResults['process_import_job']['errors'] = $session->errors ?? [];
                
            } catch (\Exception $e) {
                $diagnosticResults['process_import_job']['can_execute'] = false;
                $diagnosticResults['process_import_job']['error'] = $e->getMessage();
                $diagnosticResults['process_import_job']['trace'] = $e->getTraceAsString();
            }
            
            // === GENERATE COMPREHENSIVE REPORT ===
            Log::info('🔥 IMPORT SYSTEM DIAGNOSTIC REPORT 🔥', $diagnosticResults);
            
            // Create human-readable summary
            $summary = [];
            $criticalIssues = [];
            
            // Check service existence
            foreach ($diagnosticResults['service_existence'] as $service => $data) {
                if (!$data['exists']) {
                    $criticalIssues[] = "❌ CRITICAL: {$service} class does not exist";
                } elseif (!$data['instantiable']) {
                    $criticalIssues[] = "⚠️  WARNING: {$service} exists but cannot be instantiated - {$data['error']}";
                } else {
                    $summary[] = "✅ {$service} - OK";
                }
            }
            
            // Check ActionResult compatibility
            if (!$diagnosticResults['action_result_compatibility']['success_with_array']) {
                $criticalIssues[] = "❌ CRITICAL: ActionResult::success() incompatible with array parameter - this breaks ValidateRowAction";
            }
            
            // Check pipeline builder
            if (!$diagnosticResults['pipeline_builder']['can_build']) {
                $criticalIssues[] = "❌ CRITICAL: PipelineBuilder cannot build pipeline - " . $diagnosticResults['pipeline_builder']['error'];
            } elseif (!$diagnosticResults['pipeline_builder']['can_execute']) {
                $criticalIssues[] = "❌ CRITICAL: Pipeline can be built but execution fails";
            }
            
            // Check ProcessImportJob
            if (!$diagnosticResults['process_import_job']['can_execute']) {
                $criticalIssues[] = "❌ CRITICAL: ProcessImportJob execution fails - " . $diagnosticResults['process_import_job']['error'];
            }
            
            $report = [
                'summary' => $summary,
                'critical_issues' => $criticalIssues,
                'detailed_results' => $diagnosticResults,
            ];
            
            Log::info('📊 IMPORT SYSTEM HEALTH SUMMARY', $report);
            
            // Output to console
            dump('🔥 IMPORT DRAGON DIAGNOSTIC REPORT 🔥');
            dump('✅ WORKING SERVICES:', $summary);
            dump('❌ CRITICAL ISSUES FOUND:', $criticalIssues);
            
            // Assert that we have diagnostic data
            expect($diagnosticResults)->not->toBeEmpty();
            expect($criticalIssues)->toBeArray(); // We expect issues, so this is just checking we have the data
        });
        
        it('🔧 SPECIFIC ISSUE: ActionResult::success() Method Signature', function () {
            // This specifically tests the known issue
            $context = new ActionContext(['test' => 'data'], 1, []);
            
            // This should work (with ActionContext)
            $result1 = ActionResult::success($context, 'Success message');
            expect($result1->isSuccess())->toBeTrue();
            
            // This should work (with null)
            $result2 = ActionResult::success(null, 'Success message');
            expect($result2->isSuccess())->toBeTrue();
            
            // This is what's currently breaking in ValidateRowAction
            try {
                $result3 = ActionResult::success(['array' => 'data']);
                expect(false)->toBeTrue('This should have failed due to type mismatch');
            } catch (\TypeError $e) {
                expect($e->getMessage())->toContain('Argument #1 ($context) must be of type ?App\Services\Import\Actions\ActionContext, array given');
            }
        });
        
        it('🔧 SPECIFIC ISSUE: ValidateRowAction Execution', function () {
            $action = new ValidateRowAction();
            $context = new ActionContext([
                'product_name' => 'Test Product',
                'variant_sku' => 'TEST-001'
            ], 1, []);
            
            try {
                $result = $action->execute($context);
                expect($result)->toBeInstanceOf(ActionResult::class);
            } catch (\TypeError $e) {
                // This is the expected error that's breaking the import
                expect($e->getMessage())->toContain('ActionContext, array given');
                dump('🔧 CONFIRMED: ValidateRowAction calls ActionResult::success() with array instead of ActionContext');
            }
        });
    });
});