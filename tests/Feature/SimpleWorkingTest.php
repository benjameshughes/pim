<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Simple Working Tests', function () {
    it('can create a basic product', function () {
        $product = Product::create([
            'name' => 'Test Product',
            'parent_sku' => 'TEST-001',
            'description' => 'A test product',
            'status' => 'active',
        ]);

        expect($product->name)->toBe('Test Product');
        expect($product->parent_sku)->toBe('TEST-001');
        expect(Product::count())->toBe(1);
    });

    it('can create a user', function () {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        expect($user->name)->toBe('Test User');
        expect($user->email)->toBe('test@example.com');
    });

    it('can list products', function () {
        Product::create(['name' => 'Product 1', 'parent_sku' => 'P001']);
        Product::create(['name' => 'Product 2', 'parent_sku' => 'P002']);
        Product::create(['name' => 'Product 3', 'parent_sku' => 'P003']);

        expect(Product::count())->toBe(3);
        expect(Product::where('name', 'Product 1')->exists())->toBeTrue();
    });

    it('can find products by status', function () {
        Product::create(['name' => 'Active Product', 'parent_sku' => 'A001', 'status' => 'active']);
        Product::create(['name' => 'Inactive Product', 'parent_sku' => 'I001', 'status' => 'inactive']);

        $activeProducts = Product::where('status', 'active')->get();
        expect($activeProducts)->toHaveCount(1);
        expect($activeProducts->first()->name)->toBe('Active Product');
    });
});