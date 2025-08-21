<?php

use App\Actions\Products\Wizard\SaveProductWizardDataAction;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->action = new SaveProductWizardDataAction;
});

describe('SaveProductWizardDataAction', function () {
    it('can create a new product with minimal data', function () {
        $wizardData = [
            'product_info' => [
                'name' => 'Test Product',
                'status' => 'active',
            ],
        ];

        $result = $this->action->execute($wizardData);

        expect($result['success'])->toBeTrue();
        expect($result['data']['product'])->toBeInstanceOf(Product::class);
        expect($result['data']['product']->name)->toBe('Test Product');
        expect($result['data']['product']->status->value)->toBe('active');
        expect($result['data']['action'])->toBe('created');
    });

    it('can create a product with full product info', function () {
        $wizardData = [
            'product_info' => [
                'name' => 'Complete Test Product',
                'parent_sku' => 'TEST-001',
                'description' => 'A complete test product description',
                'status' => 'draft',
                'image_url' => 'https://example.com/image.jpg',
            ],
        ];

        $result = $this->action->execute($wizardData);

        expect($result['success'])->toBeTrue();

        $product = $result['data']['product'];
        expect($product->name)->toBe('Complete Test Product');
        expect($product->parent_sku)->toBe('TEST-001');
        expect($product->description)->toBe('A complete test product description');
        expect($product->status->value)->toBe('draft');
        expect($product->image_url)->toBe('https://example.com/image.jpg');
    });

    it('can create a product with variants', function () {
        $wizardData = [
            'product_info' => [
                'name' => 'Product with Variants',
                'status' => 'active',
            ],
            'variants' => [
                'generated_variants' => [
                    [
                        'sku' => 'VAR-001',
                        'color' => 'Red',
                        'width' => 120,
                        'drop' => 160,
                        'price' => 29.99,
                        'stock' => 10,
                    ],
                    [
                        'sku' => 'VAR-002',
                        'color' => 'Blue',
                        'width' => 150,
                        'drop' => 180,
                        'price' => 39.99,
                        'stock' => 5,
                    ],
                ],
            ],
        ];

        $result = $this->action->execute($wizardData);

        expect($result['success'])->toBeTrue();

        $product = $result['data']['product'];
        expect($product->variants)->toHaveCount(2);

        $variant1 = $product->variants->firstWhere('sku', 'VAR-001');
        expect($variant1->color)->toBe('Red');
        expect($variant1->width)->toBe(120);
        expect((float) $variant1->price)->toBe(29.99);
        expect($variant1->stock_level)->toBe(10);
    });

    it('can update an existing product', function () {
        $existingProduct = Product::factory()->create([
            'name' => 'Original Product',
            'status' => 'draft',
        ]);

        $wizardData = [
            'product_info' => [
                'name' => 'Updated Product Name',
                'description' => 'Updated description',
                'status' => 'active',
            ],
        ];

        $result = $this->action->execute($wizardData, $existingProduct);

        expect($result['success'])->toBeTrue();
        expect($result['data']['action'])->toBe('updated');

        $updatedProduct = $result['data']['product'];
        expect($updatedProduct->id)->toBe($existingProduct->id);
        expect($updatedProduct->name)->toBe('Updated Product Name');
        expect($updatedProduct->description)->toBe('Updated description');
        expect($updatedProduct->status->value)->toBe('active');
    });

    it('validates required product name', function () {
        $wizardData = [
            'product_info' => [
                'status' => 'active',
            ],
        ];

        $result = $this->action->execute($wizardData);

        expect($result['success'])->toBeFalse();
        expect($result['message'])->toBe('Product name is required');
        expect($result['data']['error_type'])->toBe('InvalidArgumentException');
    });

    it('validates variant SKUs are provided', function () {
        $wizardData = [
            'product_info' => [
                'name' => 'Test Product',
                'status' => 'active',
            ],
            'variants' => [
                'generated_variants' => [
                    [
                        'color' => 'Red',
                        'price' => 29.99,
                        // Missing SKU
                    ],
                ],
            ],
        ];

        $result = $this->action->execute($wizardData);

        expect($result['success'])->toBeFalse();
        expect($result['message'])->toBe('Variant at index 0 is missing SKU');
        expect($result['data']['error_type'])->toBe('InvalidArgumentException');
    });

    it('validates wizard data structure', function () {
        $wizardData = [
            // Missing product_info
        ];

        $result = $this->action->execute($wizardData);

        expect($result['success'])->toBeFalse();
        expect($result['message'])->toBe('Product information is required');
        expect($result['data']['error_type'])->toBe('InvalidArgumentException');
    });

    it('returns proper standardized response format', function () {
        $wizardData = [
            'product_info' => [
                'name' => 'Response Format Test',
                'status' => 'active',
            ],
        ];

        $result = $this->action->execute($wizardData);

        expect($result)->toHaveKeys(['success', 'message', 'data', 'action', 'timestamp']);
        expect($result['success'])->toBeTrue();
        expect($result['data'])->toHaveKeys(['product', 'action']);
        expect($result['data']['product'])->toBeInstanceOf(Product::class);
    });
});
