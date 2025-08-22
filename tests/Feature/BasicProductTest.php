<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Basic Product Operations', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    it('can create a product', function () {
        $productData = [
            'name' => 'Test Product',
            'description' => 'A test product description',
            'sku' => 'TEST-001',
            'status' => 'draft',
        ];

        $product = Product::create($productData);

        expect(Product::count())->toBe(1);
        expect($product->name)->toBe('Test Product');
        expect($product->sku)->toBe('TEST-001');
    });

    it('can list products', function () {
        Product::factory()->count(3)->create();

        $products = Product::all();
        expect($products)->toHaveCount(3);
    });
});