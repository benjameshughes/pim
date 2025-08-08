<?php

namespace App\Services\Import\Actions\Examples;

use App\Services\Import\Actions\ActionContext;
use App\Services\Import\Actions\PipelineBuilder;
use App\Services\Import\Actions\ValidateRowAction;
use App\Services\Import\Actions\ExtractAttributesAction;
use App\Services\Import\Actions\ResolveProductAction;

/**
 * Demo class showing how to use the Actions Pipeline system
 */
class PipelineDemo
{
    /**
     * Example 1: Simple validation pipeline
     */
    public static function validateRowExample(): array
    {
        $data = [
            'product_name' => 'Test Product',
            'variant_sku' => 'TEST-001',
            'retail_price' => '99.99',
            'variant_color' => 'red',
        ];

        $pipeline = PipelineBuilder::validationPipeline([
            'product_name' => 'required|string|max:255',
            'variant_sku' => 'required|string|max:100',
            'retail_price' => 'nullable|numeric|min:0',
        ]);

        $context = new ActionContext($data, 1);
        $result = $pipeline->execute($context);

        return [
            'success' => $result->isSuccess(),
            'error' => $result->getError(),
            'data' => $result->getData(),
        ];
    }

    /**
     * Example 2: Attribute extraction pipeline  
     */
    public static function extractAttributesExample(): array
    {
        $data = [
            'product_name' => 'Custom Blinds MTM',
            'variant_sku' => 'BLI-150-200-RED',
            'description' => 'Made to measure roller blinds 150cm x 200cm',
        ];

        $pipeline = PipelineBuilder::attributePipeline([
            'extract_mtm' => true,
            'extract_dimensions' => true,
        ]);

        $context = new ActionContext($data, 1);
        $result = $pipeline->execute($context);

        return [
            'success' => $result->isSuccess(),
            'original_data' => $data,
            'extracted_data' => $context->getData(),
            'pipeline_result' => $result->getData(),
        ];
    }

    /**
     * Example 3: Complete import pipeline
     */
    public static function completeImportExample(): array
    {
        $data = [
            'product_name' => 'Venetian Blinds',
            'variant_sku' => 'VEN-120-150-WHITE',
            'retail_price' => '149.99',
            'variant_color' => 'white',
            'description' => 'Custom venetian blinds made to measure 120x150cm',
            'stock_level' => '10',
        ];

        $pipeline = PipelineBuilder::importPipeline([
            'import_mode' => 'create_or_update',
            'extract_mtm' => true,
            'extract_dimensions' => true,
            'use_sku_grouping' => true,
            'validation_rules' => [
                'product_name' => 'required|string|max:255',
                'variant_sku' => 'required|string|max:100',
            ],
            'timeout_seconds' => 10.0,
        ]);

        $context = new ActionContext($data, 1, [
            'import_mode' => 'create_or_update',
            'detect_made_to_measure' => true,
            'dimensions_digits_only' => true,
            'group_by_sku' => true,
        ]);

        $result = $pipeline->execute($context);

        return [
            'success' => $result->isSuccess(),
            'error' => $result->getError(),
            'final_context' => $context->getData(),
            'execution_data' => $result->getData(),
        ];
    }

    /**
     * Example 4: Custom pipeline with specific actions
     */
    public static function customPipelineExample(): array
    {
        $pipeline = PipelineBuilder::create()
            ->addAction(new ValidateRowAction([
                'rules' => [
                    'product_name' => 'required|string',
                    'variant_sku' => 'required|string',
                ]
            ]))
            ->addAction((new ExtractAttributesAction([
                'extract_mtm' => true,
                'extract_dimensions' => false,
            ]))->setOptional()) // Don't fail if extraction fails
            ->addAction(new ResolveProductAction([
                'import_mode' => 'create_only',
                'use_sku_grouping' => false,
            ]))
            ->withDebugMiddleware() // Use debug middleware for development
            ->build();

        $data = [
            'product_name' => 'Bespoke Curtains',
            'variant_sku' => 'CUR-001',
            'description' => 'Handmade bespoke curtains',
        ];

        $context = new ActionContext($data, 1);
        $result = $pipeline->execute($context);

        return [
            'pipeline_type' => 'custom',
            'success' => $result->isSuccess(),
            'result' => $result->toArray(),
            'context' => $context->toArray(),
        ];
    }

    /**
     * Run all examples and return results
     */
    public static function runAllExamples(): array
    {
        return [
            'validation_example' => self::validateRowExample(),
            'attribute_extraction_example' => self::extractAttributesExample(),
            'complete_import_example' => self::completeImportExample(),
            'custom_pipeline_example' => self::customPipelineExample(),
        ];
    }
}