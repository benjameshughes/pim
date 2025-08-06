<?php

use App\Repositories\ProductRepository;
use App\Repositories\ProductVariantRepository;
use App\Models\Product;
use App\Models\ProductVariant;

test('product repository can find by name', function () {
    $repository = new ProductRepository();
    $product = Product::factory()->create(['name' => 'Test Product']);
    
    $found = $repository->findByName('Test Product');
    
    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($product->id);
});

test('product repository returns null when not found', function () {
    $repository = new ProductRepository();
    
    $found = $repository->findByName('Non-existent Product');
    
    expect($found)->toBeNull();
});

test('product repository can create product', function () {
    $repository = new ProductRepository();
    
    $product = $repository->create([
        'name' => 'New Product',
        'slug' => 'new-product',
        'is_parent' => false,
        'status' => 'active'
    ]);
    
    expect($product)->toBeInstanceOf(Product::class)
        ->and($product->name)->toBe('New Product')
        ->and($product->wasRecentlyCreated)->toBeTrue();
});

test('variant repository can find by SKU', function () {
    $repository = new ProductVariantRepository();
    $variant = ProductVariant::factory()->create(['sku' => 'TEST-001']);
    
    $found = $repository->findBySku('TEST-001');
    
    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($variant->id);
});

test('variant repository can create variant', function () {
    $repository = new ProductVariantRepository();
    $product = Product::factory()->create();
    
    $variant = $repository->create([
        'product_id' => $product->id,
        'sku' => 'NEW-001',
        'color' => 'Red',
        'size' => 'M',
        'status' => 'active'
    ]);
    
    expect($variant)->toBeInstanceOf(ProductVariant::class)
        ->and($variant->sku)->toBe('NEW-001')
        ->and($variant->wasRecentlyCreated)->toBeTrue();
});