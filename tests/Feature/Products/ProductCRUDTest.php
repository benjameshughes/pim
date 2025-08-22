<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Product CRUD Operations', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    it('can create a product', function () {
        $category = Category::factory()->create();

        $productData = [
            'name' => 'Test Product',
            'description' => 'A test product description',
            'category_id' => $category->id,
            'sku' => 'TEST-001',
            'status' => 'draft',
        ];

        $component = Livewire::test('products.product-form')
            ->set('product', $productData)
            ->call('save');

        expect(Product::count())->toBe(1);
        $product = Product::first();
        expect($product->name)->toBe('Test Product');
        expect($product->sku)->toBe('TEST-001');
    });

    it('validates required fields when creating product', function () {
        $component = Livewire::test('products.product-form')
            ->set('product', [
                'name' => '',
                'description' => '',
            ])
            ->call('save')
            ->assertHasErrors(['product.name']);
    });

    it('can update a product', function () {
        $product = Product::factory()->create(['name' => 'Original Name']);

        $component = Livewire::test('products.product-form', ['product' => $product])
            ->set('product.name', 'Updated Name')
            ->call('save');

        $product->refresh();
        expect($product->name)->toBe('Updated Name');
    });

    it('can delete a product', function () {
        $product = Product::factory()->create();

        $component = Livewire::test('products.product-show', ['product' => $product])
            ->call('deleteProduct');

        expect(Product::count())->toBe(0);
    });

    it('can list products', function () {
        Product::factory()->count(5)->create();

        $component = Livewire::test('products.product-index');

        expect($component->get('products'))->toHaveCount(5);
    });

    it('can search products', function () {
        Product::factory()->create(['name' => 'Red Shirt']);
        Product::factory()->create(['name' => 'Blue Shirt']);
        Product::factory()->create(['name' => 'Green Pants']);

        $component = Livewire::test('products.product-index')
            ->set('search', 'shirt')
            ->call('render');

        expect($component->get('products'))->toHaveCount(2);
    });

    it('can filter products by status', function () {
        Product::factory()->create(['status' => 'active']);
        Product::factory()->create(['status' => 'draft']);
        Product::factory()->create(['status' => 'archived']);

        $component = Livewire::test('products.product-index')
            ->set('statusFilter', 'active')
            ->call('render');

        expect($component->get('products'))->toHaveCount(1);
    });
});