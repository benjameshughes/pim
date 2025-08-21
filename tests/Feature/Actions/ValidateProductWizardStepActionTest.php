<?php

use App\Actions\Products\Wizard\ValidateProductWizardStepAction;

beforeEach(function () {
    $this->action = new ValidateProductWizardStepAction;
});

describe('ValidateProductWizardStepAction', function () {
    describe('Product Info Step (Step 1)', function () {
        it('passes validation with valid product info', function () {
            $stepData = [
                'name' => 'Valid Product Name',
                'parent_sku' => 'VALID-001',
                'description' => 'A valid product description',
                'status' => 'active',
                'image_url' => 'https://example.com/image.jpg',
            ];

            $result = $this->action->execute(1, $stepData);

            expect($result['success'])->toBeTrue();
            expect($result['data']['valid'])->toBeTrue();
            expect($result['data']['errors'])->toBeEmpty();
            expect($result['data']['step'])->toBe(1);
        });

        it('fails validation when name is missing', function () {
            $stepData = [
                'status' => 'active',
            ];

            $result = $this->action->execute(1, $stepData);

            expect($result['data']['valid'])->toBeFalse();
            expect($result['data']['errors']['name'])->toBe('Product name is required');
        });

        it('fails validation when name is too short', function () {
            $stepData = [
                'name' => 'Ab',
                'status' => 'active',
            ];

            $result = $this->action->execute(1, $stepData);

            expect($result['data']['valid'])->toBeFalse();
            expect($result['data']['errors']['name'])->toBe('Product name must be at least 3 characters');
        });

        it('fails validation with invalid status', function () {
            $stepData = [
                'name' => 'Valid Product',
                'status' => 'invalid_status',
            ];

            $result = $this->action->execute(1, $stepData);

            expect($result['data']['valid'])->toBeFalse();
            expect($result['data']['errors']['status'])->toBe('Invalid product status');
        });

        it('fails validation with invalid image URL', function () {
            $stepData = [
                'name' => 'Valid Product',
                'status' => 'active',
                'image_url' => 'not-a-valid-url',
            ];

            $result = $this->action->execute(1, $stepData);

            expect($result['data']['valid'])->toBeFalse();
            expect($result['data']['errors']['image_url'])->toBe('Image URL must be a valid URL');
        });

        it('fails validation with short parent SKU', function () {
            $stepData = [
                'name' => 'Valid Product',
                'parent_sku' => 'A',
                'status' => 'active',
            ];

            $result = $this->action->execute(1, $stepData);

            expect($result['data']['valid'])->toBeFalse();
            expect($result['data']['errors']['parent_sku'])->toBe('Parent SKU must be at least 2 characters');
        });
    });

    describe('Variants Step (Step 2)', function () {
        it('passes validation with valid variants', function () {
            $stepData = [
                'generated_variants' => [
                    [
                        'sku' => 'VAR-001',
                        'color' => 'Red',
                        'price' => 29.99,
                        'stock' => 10,
                    ],
                    [
                        'sku' => 'VAR-002',
                        'color' => 'Blue',
                        'price' => 39.99,
                        'stock' => 5,
                    ],
                ],
            ];

            $result = $this->action->execute(2, $stepData);

            expect($result['data']['valid'])->toBeTrue();
            expect($result['data']['errors'])->toBeEmpty();
        });

        it('fails validation when no variants provided', function () {
            $stepData = [];

            $result = $this->action->execute(2, $stepData);

            expect($result['data']['valid'])->toBeFalse();
            expect($result['data']['errors']['variants'])->toBe('At least one variant is required');
        });

        it('fails validation when variant missing SKU', function () {
            $stepData = [
                'generated_variants' => [
                    [
                        'color' => 'Red',
                        'price' => 29.99,
                        // Missing SKU
                    ],
                ],
            ];

            $result = $this->action->execute(2, $stepData);

            expect($result['data']['valid'])->toBeFalse();
            expect($result['data']['errors']['variant_0']['sku'])->toBe('SKU is required');
        });

        it('fails validation with duplicate SKUs', function () {
            $stepData = [
                'generated_variants' => [
                    ['sku' => 'DUPLICATE', 'price' => 29.99],
                    ['sku' => 'DUPLICATE', 'price' => 39.99],
                ],
            ];

            $result = $this->action->execute(2, $stepData);

            expect($result['data']['valid'])->toBeFalse();
            expect($result['data']['errors']['variant_1']['sku'])->toBe('Duplicate SKU detected');
        });

        it('fails validation with invalid pricing', function () {
            $stepData = [
                'generated_variants' => [
                    [
                        'sku' => 'VAR-001',
                        'price' => -10, // Negative price
                        'stock' => -5,  // Negative stock
                    ],
                ],
            ];

            $result = $this->action->execute(2, $stepData);

            expect($result['data']['valid'])->toBeFalse();
            expect($result['data']['errors']['variant_0']['price'])->toBe('Price must be a positive number');
            expect($result['data']['errors']['variant_0']['stock'])->toBe('Stock must be a positive number');
        });
    });

    describe('Images Step (Step 3)', function () {
        it('passes validation with no images (optional)', function () {
            $stepData = [];

            $result = $this->action->execute(3, $stepData);

            expect($result['data']['valid'])->toBeTrue();
            expect($result['data']['errors'])->toBeEmpty();
        });

        it('passes validation with valid images', function () {
            $stepData = [
                'product_images' => [
                    ['path' => '/storage/images/product1.jpg'],
                    ['url' => 'https://example.com/product2.jpg'],
                ],
            ];

            $result = $this->action->execute(3, $stepData);

            expect($result['data']['valid'])->toBeTrue();
        });

        it('fails validation when image has no path or URL', function () {
            $stepData = [
                'product_images' => [
                    ['filename' => 'image.jpg'], // No path or URL
                ],
            ];

            $result = $this->action->execute(3, $stepData);

            expect($result['data']['valid'])->toBeFalse();
            expect($result['data']['errors']['image_0'])->toBe('Image must have either path or URL');
        });
    });

    describe('Pricing Step (Step 4)', function () {
        it('passes validation with no pricing data', function () {
            $stepData = [];

            $result = $this->action->execute(4, $stepData);

            expect($result['data']['valid'])->toBeTrue();
        });

        it('passes validation with valid pricing data', function () {
            $stepData = [
                'variant_pricing' => [
                    'variant_1' => [
                        'retail_price' => 29.99,
                        'cost_price' => 15.00,
                    ],
                ],
            ];

            $result = $this->action->execute(4, $stepData);

            expect($result['data']['valid'])->toBeTrue();
        });

        it('fails validation with negative pricing', function () {
            $stepData = [
                'variant_pricing' => [
                    'variant_1' => [
                        'retail_price' => -10,
                        'cost_price' => -5,
                    ],
                ],
            ];

            $result = $this->action->execute(4, $stepData);

            expect($result['data']['valid'])->toBeFalse();
            expect($result['data']['errors']['pricing_variant_1_retail'])->toBe('Retail price must be a positive number');
            expect($result['data']['errors']['pricing_variant_1_cost'])->toBe('Cost price must be a positive number');
        });
    });

    describe('General validation', function () {
        it('throws exception for invalid step number', function () {
            expect(fn () => $this->action->execute(5, []))
                ->toThrow(InvalidArgumentException::class, 'Invalid step number. Must be between 1 and 4.');

            expect(fn () => $this->action->execute(0, []))
                ->toThrow(InvalidArgumentException::class, 'Invalid step number. Must be between 1 and 4.');
        });

        it('can validate all steps at once', function () {
            $wizardData = [
                'product_info' => [
                    'name' => 'Complete Product',
                    'status' => 'active',
                ],
                'variants' => [
                    'generated_variants' => [
                        ['sku' => 'COMPLETE-001', 'price' => 29.99],
                    ],
                ],
                'images' => [
                    'product_images' => [
                        ['path' => '/storage/image.jpg'],
                    ],
                ],
                'pricing' => [
                    'variant_pricing' => [
                        'variant_1' => ['retail_price' => 29.99],
                    ],
                ],
            ];

            $result = $this->action->validateAllSteps($wizardData);

            expect($result['data']['overall_valid'])->toBeTrue();
            expect($result['data']['steps_valid'])->toBe([
                1 => true,
                2 => true,
                3 => true,
                4 => true,
            ]);
            expect($result['data']['errors'])->toBeEmpty();
        });

        it('identifies invalid steps in complete validation', function () {
            $wizardData = [
                'product_info' => [
                    'name' => 'Valid Product',
                    'status' => 'active',
                ],
                'variants' => [
                    // Missing generated_variants
                ],
                'images' => [
                    'product_images' => [
                        ['path' => '/valid/path.jpg'],
                    ],
                ],
                'pricing' => [
                    'variant_pricing' => [
                        'variant_1' => ['retail_price' => -10], // Invalid price
                    ],
                ],
            ];

            $result = $this->action->validateAllSteps($wizardData);

            expect($result['data']['overall_valid'])->toBeFalse();
            expect($result['data']['steps_valid'][1])->toBeTrue();  // Product info valid
            expect($result['data']['steps_valid'][2])->toBeFalse(); // Variants invalid
            expect($result['data']['steps_valid'][3])->toBeTrue();  // Images valid
            expect($result['data']['steps_valid'][4])->toBeFalse(); // Pricing invalid

            expect($result['data']['errors'])->toHaveKey('variants');
            expect($result['data']['errors'])->toHaveKey('pricing');
        });

        it('returns proper response format', function () {
            $result = $this->action->execute(1, ['name' => 'Test', 'status' => 'active']);

            expect($result)->toHaveKeys(['success', 'message', 'data', 'action', 'timestamp']);
            expect($result['data'])->toHaveKeys(['step', 'valid', 'errors', 'validated_fields']);
            expect($result['data']['validated_fields'])->toBe(['name', 'status']);
        });
    });
});
