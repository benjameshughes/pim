<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Enums\ProductStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Product Model', function () {
    it('has correct fillable attributes', function () {
        $product = new Product();
        
        $expectedFillable = [
            'name', 'parent_sku', 'description', 'status', 'image_url',
            'category_id', 'brand', 'meta_description', 'barcode',
            'length', 'width', 'depth', 'weight', 'retail_price'
        ];

        expect($product->getFillable())->toEqual($expectedFillable);
    });

    it('can create a product with basic data', function () {
        $product = Product::create([
            'name' => 'Test Product',
            'description' => 'A test product',
            'status' => ProductStatus::DRAFT,
        ]);

        expect($product->name)->toBe('Test Product');
        expect($product->status)->toBe(ProductStatus::DRAFT);
    });

    it('has many variants relationship', function () {
        $product = Product::factory()->create();
        
        expect($product->variants())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    });

    it('can scope active products', function () {
        Product::factory()->create(['status' => ProductStatus::ACTIVE]);
        Product::factory()->create(['status' => ProductStatus::DRAFT]);
        Product::factory()->create(['status' => ProductStatus::ACTIVE]);

        $activeProducts = Product::active()->get();
        expect($activeProducts)->toHaveCount(2);
    });

    it('can get smart attribute value', function () {
        $product = Product::factory()->create(['brand' => 'Test Brand']);
        
        expect($product->getSmartBrandValue())->toBe('Test Brand');
    });
});