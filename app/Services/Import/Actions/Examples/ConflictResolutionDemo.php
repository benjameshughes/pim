<?php

namespace App\Services\Import\Actions\Examples;

use App\Services\Import\Actions\ActionContext;
use App\Services\Import\Actions\PipelineBuilder;
use App\Services\Import\Conflicts\ConflictResolver;
use App\Services\Import\Conflicts\DuplicateSkuResolver;
use App\Services\Import\Conflicts\DuplicateBarcodeResolver;
use App\Services\Import\Conflicts\VariantConstraintResolver;
use Illuminate\Database\QueryException;

/**
 * Demo class showing how to use the Conflict Resolution system
 */
class ConflictResolutionDemo
{
    /**
     * Example 1: SKU conflict resolution
     */
    public static function skuConflictExample(): array
    {
        // Simulate data that would cause a SKU conflict
        $data = [
            'product_name' => 'Test Product',
            'variant_sku' => 'EXISTING-SKU-001', // This SKU already exists
            'retail_price' => '99.99',
            'variant_color' => 'blue',
        ];

        $pipeline = PipelineBuilder::importPipeline([
            'import_mode' => 'create_only',
            'handle_conflicts' => true,
            'conflict_max_retries' => 3,
            'conflict_resolution' => [
                'sku_resolution' => [
                    'strategy' => 'generate_unique',
                    'generate_unique_sku' => true,
                ]
            ],
        ]);

        $context = new ActionContext($data, 1, [
            'import_mode' => 'create_only',
        ]);

        $result = $pipeline->execute($context);

        return [
            'scenario' => 'SKU conflict with unique generation',
            'success' => $result->isSuccess(),
            'original_sku' => $data['variant_sku'],
            'final_sku' => $context->get('variant_sku'),
            'conflict_stats' => $result->get('conflict_resolver_stats'),
            'resolutions_applied' => $result->get('resolutions_applied'),
        ];
    }

    /**
     * Example 2: Barcode conflict resolution
     */
    public static function barcodeConflictExample(): array
    {
        $data = [
            'product_name' => 'New Product',
            'variant_sku' => 'NEW-001',
            'barcode' => '1234567890123', // This barcode already exists
            'retail_price' => '149.99',
        ];

        $pipeline = PipelineBuilder::importPipeline([
            'import_mode' => 'create_or_update',
            'handle_conflicts' => true,
            'conflict_resolution' => [
                'barcode_resolution' => [
                    'strategy' => 'remove_barcode',
                ]
            ],
        ]);

        $context = new ActionContext($data, 1);
        $result = $pipeline->execute($context);

        return [
            'scenario' => 'Barcode conflict with removal strategy',
            'success' => $result->isSuccess(),
            'original_barcode' => $data['barcode'],
            'final_barcode' => $context->get('barcode'),
            'action_taken' => $result->get('action_taken'),
            'conflict_stats' => $result->get('conflict_resolver_stats'),
        ];
    }

    /**
     * Example 3: Variant constraint conflict resolution
     */
    public static function variantConstraintExample(): array
    {
        $data = [
            'product_name' => 'Existing Product',
            'variant_sku' => 'NEW-VARIANT-001',
            'variant_color' => 'red',
            'variant_size' => 'large',
            // This color/size combination already exists for this product
        ];

        $pipeline = PipelineBuilder::importPipeline([
            'import_mode' => 'create_or_update',
            'handle_conflicts' => true,
            'conflict_resolution' => [
                'variant_resolution' => [
                    'strategy' => 'merge_data',
                    'allow_merging' => true,
                    'allow_dimension_updates' => true,
                ]
            ],
        ]);

        $context = new ActionContext($data, 1);
        $result = $pipeline->execute($context);

        return [
            'scenario' => 'Variant constraint with data merging',
            'success' => $result->isSuccess(),
            'original_data' => $data,
            'merged_data' => $context->getData(),
            'conflict_stats' => $result->get('conflict_resolver_stats'),
        ];
    }

    /**
     * Example 4: Multiple conflict types in sequence
     */
    public static function multipleConflictsExample(): array
    {
        $data = [
            'product_name' => 'Complex Product',
            'variant_sku' => 'CONFLICT-SKU-001', // SKU exists
            'barcode' => '9876543210987',        // Barcode exists
            'variant_color' => 'green',          // Color/size combo exists
            'variant_size' => 'medium',
            'retail_price' => '199.99',
        ];

        $pipeline = PipelineBuilder::importPipeline([
            'import_mode' => 'create_or_update',
            'handle_conflicts' => true,
            'conflict_max_retries' => 5,
            'halt_on_unresolvable_conflicts' => false,
            'conflict_resolution' => [
                'sku_resolution' => [
                    'strategy' => 'generate_unique',
                    'generate_unique_sku' => true,
                ],
                'barcode_resolution' => [
                    'strategy' => 'reassign',
                    'allow_reassignment' => true,
                ],
                'variant_resolution' => [
                    'strategy' => 'modify_attributes',
                ],
            ],
        ]);

        $context = new ActionContext($data, 1);
        $result = $pipeline->execute($context);

        return [
            'scenario' => 'Multiple conflict types',
            'success' => $result->isSuccess(),
            'original_data' => $data,
            'final_data' => $context->getData(),
            'attempts' => $result->get('conflict_resolution_attempts'),
            'resolutions' => $result->get('resolutions_applied'),
            'final_stats' => $result->get('conflict_resolver_stats'),
        ];
    }

    /**
     * Example 5: Custom conflict resolver configuration
     */
    public static function customResolverExample(): array
    {
        // Create custom conflict resolver
        $conflictResolver = ConflictResolver::create([
            'sku_resolution' => [
                'strategy' => 'use_existing',
                'allow_updates' => true,
            ],
            'barcode_resolution' => [
                'strategy' => 'skip',
            ],
            'variant_resolution' => [
                'strategy' => 'use_existing',
                'allow_merging' => false,
            ],
            'unique_resolution' => [
                'default_strategy' => 'append_suffix',
                'field_strategies' => [
                    'slug' => 'generate_unique',
                    'name' => 'skip',
                ],
            ],
        ]);

        // Simulate a QueryException
        try {
            // This would normally come from a real database conflict
            throw new QueryException(
                'SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry',
                [],
                new \Exception('Duplicate entry \'TEST-001\' for key \'product_variants_sku_unique\'')
            );
        } catch (QueryException $e) {
            $context = [
                'variant_sku' => 'TEST-001',
                'product_name' => 'Test Product',
                'import_mode' => 'create_only',
            ];

            $resolution = $conflictResolver->resolve($e, $context);

            return [
                'scenario' => 'Custom resolver configuration',
                'conflict_detected' => true,
                'resolution' => $resolution->toArray(),
                'resolver_stats' => $conflictResolver->getStatistics(),
            ];
        }
    }

    /**
     * Example 6: Conflict resolution strategies comparison
     */
    public static function strategiesComparisonExample(): array
    {
        $strategies = [
            'skip' => 'Skip conflicting rows',
            'use_existing' => 'Use existing records without changes',
            'update_existing' => 'Update existing records with new data',
            'generate_unique' => 'Generate unique values for conflicts',
            'merge_data' => 'Merge new data with existing',
            'modify_attributes' => 'Modify attributes to avoid conflicts',
        ];

        $recommendations = [
            'high_volume_imports' => [
                'recommended' => ['skip', 'use_existing'],
                'reason' => 'Fast processing, minimal database impact',
            ],
            'data_quality_focus' => [
                'recommended' => ['merge_data', 'update_existing'],
                'reason' => 'Preserves all data, ensures completeness',
            ],
            'automated_systems' => [
                'recommended' => ['generate_unique', 'modify_attributes'],
                'reason' => 'Handles conflicts automatically without data loss',
            ],
            'manual_review' => [
                'recommended' => ['skip'],
                'reason' => 'Allows manual review of conflicts',
            ],
        ];

        return [
            'available_strategies' => $strategies,
            'use_case_recommendations' => $recommendations,
            'barcode_specific_strategies' => DuplicateBarcodeResolver::getSuggestedStrategies(),
            'unique_constraint_config' => \App\Services\Import\Conflicts\UniqueConstraintResolver::getRecommendedConfig(),
        ];
    }

    /**
     * Run all conflict resolution examples
     */
    public static function runAllExamples(): array
    {
        return [
            'sku_conflict' => self::skuConflictExample(),
            'barcode_conflict' => self::barcodeConflictExample(),
            'variant_constraint' => self::variantConstraintExample(),
            'multiple_conflicts' => self::multipleConflictsExample(),
            'custom_resolver' => self::customResolverExample(),
            'strategies_guide' => self::strategiesComparisonExample(),
        ];
    }
}