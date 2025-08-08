<?php

use App\Services\Import\Actions\ActionContext;
use App\Services\Import\Actions\ActionResult;
use App\Services\Import\Actions\ValidateRowAction;
use App\Services\Import\Actions\ExtractAttributesAction;
use App\Services\Import\Actions\ResolveProductAction;
use App\Services\Import\Actions\PipelineBuilder;
use App\Models\Product;

describe('Import Actions System', function () {
    beforeEach(function () {
        $this->actLikeUser();
    });

    describe('ActionContext', function () {
        it('manages data and metadata correctly', function () {
            $data = ['product_name' => 'Test Product', 'variant_sku' => 'TEST-001'];
            $context = new ActionContext($data, 1, ['import_mode' => 'create_only']);

            expect($context->getData())->toBe($data);
            expect($context->getRowNumber())->toBe(1);
            expect($context->getConfig('import_mode'))->toBe('create_only');
            expect($context->get('product_name'))->toBe('Test Product');
            expect($context->has('variant_sku'))->toBeTrue();
            expect($context->has('nonexistent'))->toBeFalse();

            $context->set('new_field', 'new_value');
            expect($context->get('new_field'))->toBe('new_value');

            $context->setMetadata('processing_time', 123);
            expect($context->getMetadataValue('processing_time'))->toBe(123);
        });

        it('merges data correctly', function () {
            $context = new ActionContext(['a' => 1, 'b' => 2], 1);
            $context->mergeData(['b' => 3, 'c' => 4]);

            expect($context->getData())->toBe(['a' => 1, 'b' => 3, 'c' => 4]);
        });
    });

    describe('ActionResult', function () {
        it('creates success and failure results correctly', function () {
            $success = ActionResult::success(['key' => 'value']);
            expect($success->isSuccess())->toBeTrue();
            expect($success->isFailure())->toBeFalse();
            expect($success->get('key'))->toBe('value');

            $failure = ActionResult::failed('Error message', ['error_code' => 500]);
            expect($failure->isFailure())->toBeTrue();
            expect($failure->isSuccess())->toBeFalse();
            expect($failure->getError())->toBe('Error message');
            expect($failure->get('error_code'))->toBe(500);
        });

        it('handles context updates', function () {
            $result = ActionResult::success()
                ->withContextUpdate('extracted_width', 150)
                ->withContextUpdates(['extracted_drop' => 200, 'made_to_measure' => true]);

            expect($result->hasContextUpdates())->toBeTrue();
            expect($result->getContextUpdates())->toBe([
                'extracted_width' => 150,
                'extracted_drop' => 200,
                'made_to_measure' => true,
            ]);
        });
    });

    describe('ValidateRowAction', function () {
        it('validates data successfully with default rules', function () {
            $action = new ValidateRowAction();
            $context = new ActionContext([
                'product_name' => 'Test Product',
                'variant_sku' => 'TEST-001',
                'retail_price' => '99.99',
            ], 1);

            $result = $action->execute($context);

            expect($result->isSuccess())->toBeTrue();
            expect($result->get('validation_passed'))->toBeTrue();
        });

        it('fails validation with invalid data', function () {
            $action = new ValidateRowAction();
            $context = new ActionContext([
                'product_name' => '', // Required field empty
                'retail_price' => 'not-a-number',
            ], 1);

            $result = $action->execute($context);

            expect($result->isFailure())->toBeTrue();
            expect($result->getData())->toHaveKey('validation_errors');
            expect($result->getData())->toHaveKey('failed_fields');
        });

        it('uses custom validation rules', function () {
            $action = new ValidateRowAction([
                'rules' => [
                    'custom_field' => 'required|string|min:5',
                ]
            ]);

            $context = new ActionContext(['custom_field' => 'short'], 1);
            $result = $action->execute($context);

            expect($result->isFailure())->toBeTrue();
        });
    });

    describe('ExtractAttributesAction', function () {
        it('extracts MTM attributes successfully', function () {
            $action = new ExtractAttributesAction([
                'extract_mtm' => true,
                'extract_dimensions' => false,
            ]);

            $context = new ActionContext([
                'product_name' => 'Custom Blinds MTM',
                'description' => 'Made to measure roller blinds',
            ], 1);

            $result = $action->execute($context);

            expect($result->isSuccess())->toBeTrue();
            expect($context->has('made_to_measure'))->toBeTrue();
            expect($context->has('mtm_confidence'))->toBeTrue();
        });

        it('extracts dimension attributes successfully', function () {
            $action = new ExtractAttributesAction([
                'extract_mtm' => false,
                'extract_dimensions' => true,
            ]);

            $context = new ActionContext([
                'product_name' => 'Venetian Blinds 150x200',
                'description' => 'Custom venetian blinds 150cm x 200cm',
            ], 1);

            $result = $action->execute($context);

            expect($result->isSuccess())->toBeTrue();
            expect($context->has('extracted_width'))->toBeTrue();
            expect($context->has('extracted_drop'))->toBeTrue();
            expect($context->has('dimension_confidence'))->toBeTrue();
        });

        it('handles extraction failures gracefully when optional', function () {
            $action = (new ExtractAttributesAction([
                'extract_mtm' => true,
                'extract_dimensions' => true,
            ]))->setOptional();

            $context = new ActionContext([
                'product_name' => 'Simple Product',
                'description' => 'No special attributes',
            ], 1);

            $result = $action->execute($context);

            expect($result->isSuccess())->toBeTrue();
        });
    });

    describe('ResolveProductAction', function () {
        it('creates new product in create_or_update mode', function () {
            $action = new ResolveProductAction([
                'import_mode' => 'create_or_update',
                'use_sku_grouping' => false,
            ]);

            $context = new ActionContext([
                'product_name' => 'New Test Product',
                'description' => 'A test product',
            ], 1);

            $result = $action->execute($context);

            expect($result->isSuccess())->toBeTrue();
            expect($result->get('was_created'))->toBeTrue();
            expect($context->has('product'))->toBeTrue();

            $product = $context->get('product');
            expect($product)->toBeInstanceOf(Product::class);
            expect($product->name)->toBe('New Test Product');
        });

        it('updates existing product in create_or_update mode', function () {
            $existingProduct = Product::factory()->create([
                'name' => 'Existing Product',
                'description' => 'Old description',
            ]);

            $action = new ResolveProductAction([
                'import_mode' => 'create_or_update',
            ]);

            $context = new ActionContext([
                'product_name' => 'Existing Product',
                'description' => 'Updated description',
            ], 1);

            $result = $action->execute($context);

            expect($result->isSuccess())->toBeTrue();
            expect($result->get('was_created'))->toBeFalse();

            $existingProduct->refresh();
            expect($existingProduct->description)->toBe('Updated description');
        });

        it('skips existing product in create_only mode', function () {
            $existingProduct = Product::factory()->create(['name' => 'Existing Product']);

            $action = new ResolveProductAction(['import_mode' => 'create_only']);

            $context = new ActionContext(['product_name' => 'Existing Product'], 1);
            $result = $action->execute($context);

            expect($result->isSuccess())->toBeTrue();
            expect($result->get('was_created'))->toBeFalse();
            expect($context->get('product')->id)->toBe($existingProduct->id);
        });

        it('fails when product not found in update_existing mode', function () {
            $action = new ResolveProductAction(['import_mode' => 'update_existing']);

            $context = new ActionContext(['product_name' => 'Non-existent Product'], 1);
            $result = $action->execute($context);

            expect($result->isFailure())->toBeTrue();
        });
    });
});

describe('Pipeline Integration', function () {
    beforeEach(function () {
        $this->actLikeUser();
    });

    it('executes complete import pipeline successfully', function () {
        $pipeline = PipelineBuilder::importPipeline([
            'import_mode' => 'create_or_update',
            'extract_mtm' => true,
            'extract_dimensions' => true,
            'use_sku_grouping' => false,
            'handle_conflicts' => false, // Disable for simpler test
        ]);

        $context = new ActionContext([
            'product_name' => 'Test Pipeline Product',
            'variant_sku' => 'PIPE-001',
            'description' => 'Made to measure blinds 120x150',
            'retail_price' => '199.99',
            'variant_color' => 'white',
        ], 1, [
            'import_mode' => 'create_or_update',
            'detect_made_to_measure' => true,
            'dimensions_digits_only' => true,
        ]);

        $result = $pipeline->execute($context);

        expect($result->isSuccess())->toBeTrue();
        expect($context->has('product'))->toBeTrue();
        expect($context->has('made_to_measure'))->toBeTrue();
        expect($context->has('extracted_width'))->toBeTrue();
        expect($context->has('extracted_drop'))->toBeTrue();
    });

    it('handles validation failures in pipeline', function () {
        $pipeline = PipelineBuilder::importPipeline([
            'validation_rules' => [
                'product_name' => 'required|string|min:10',
                'variant_sku' => 'required|string',
            ],
            'validation_optional' => false, // Make validation required
        ]);

        $context = new ActionContext([
            'product_name' => 'Short', // Too short
            'variant_sku' => 'TEST-001',
        ], 1);

        $result = $pipeline->execute($context);

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toContain('validation');
    });

    it('continues with optional actions when they fail', function () {
        $pipeline = PipelineBuilder::importPipeline([
            'validation_optional' => true,
            'attribute_extraction_optional' => true,
        ]);

        $context = new ActionContext([
            'product_name' => '', // Invalid but optional
            'variant_sku' => 'TEST-001',
        ], 1);

        // This should still succeed because validation is optional
        $result = $pipeline->execute($context);

        expect($result->isSuccess())->toBeTrue();
    });
});

describe('Middleware Integration', function () {
    beforeEach(function () {
        $this->actLikeUser();
    });

    it('applies timing middleware correctly', function () {
        $pipeline = PipelineBuilder::create()
            ->addAction(new ValidateRowAction())
            ->withTestMiddleware()
            ->build();

        $context = new ActionContext([
            'product_name' => 'Test Product',
            'variant_sku' => 'TEST-001',
        ], 1);

        $result = $pipeline->execute($context);

        expect($result->isSuccess())->toBeTrue();
        expect($result->getData())->toHaveKey('execution_time_ms');
        expect($result->get('execution_time_ms'))->toBeGreaterThan(0);
    });

    it('applies error handling middleware correctly', function () {
        $pipeline = PipelineBuilder::create()
            ->addAction(new ValidateRowAction([
                'rules' => ['required_field' => 'required']
            ]))
            ->withTestMiddleware()
            ->build();

        $context = new ActionContext(['optional_field' => 'value'], 1);
        $result = $pipeline->execute($context);

        expect($result->isFailure())->toBeTrue();
        expect($result->getData())->toHaveKey('graceful_degradation');
    });
});