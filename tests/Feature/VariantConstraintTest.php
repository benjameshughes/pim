<?php

use App\Livewire\Products\ImportData;
use App\Models\Product;
use App\Models\ProductVariant;
use Livewire\Livewire;

test('handles duplicate color/size combination gracefully in create_only mode', function () {
    // Create a product with an existing variant
    $product = Product::factory()->create(['name' => 'Test Product']);
    $existingVariant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'sku' => 'EXISTING-001',
        'color' => 'Grey',
        'size' => '60cm',
    ]);

    // Try to import another variant with same color/size but different SKU
    $component = Livewire::test(ImportData::class)
        ->set('importMode', 'create_only')
        ->set('allData', [
            ['Test Product', 'NEW-SKU-001', 'Grey', '60cm', '29.99'],
        ])
        ->set('columnMapping', [
            0 => 'product_name',
            1 => 'variant_sku',
            2 => 'variant_color',
            3 => 'variant_size',
            4 => 'retail_price',
        ]);

    $component->call('runDryRun');

    $results = $component->get('dryRunResults');

    // Should skip the variant due to duplicate color/size
    expect($results['variants_to_skip'])->toBe(1);
    expect($results['variants_to_create'])->toBe(0);

    // Should have only 1 variant (the original)
    expect($product->variants()->count())->toBe(1);
});

test('updates existing variant by color/size in create_or_update mode', function () {
    // Create a product with an existing variant
    $product = Product::factory()->create(['name' => 'Test Product']);
    $existingVariant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'sku' => 'OLD-SKU-001',
        'color' => 'Blue',
        'size' => '90cm',
        'stock_level' => 10,
    ]);

    // Import with same color/size but different SKU and stock
    $component = Livewire::test(ImportData::class)
        ->set('importMode', 'create_or_update')
        ->set('allData', [
            ['Test Product', 'NEW-SKU-001', 'Blue', '90cm', '19.99'],
        ])
        ->set('columnMapping', [
            0 => 'product_name',
            1 => 'variant_sku',
            2 => 'variant_color',
            3 => 'variant_size',
            4 => 'retail_price',
        ]);

    $component->call('runDryRun');

    $results = $component->get('dryRunResults');

    // Should update the existing variant
    expect($results['variants_to_update'])->toBe(1);
    expect($results['variants_to_create'])->toBe(0);

    // Should still have only 1 variant
    expect($product->variants()->count())->toBe(1);
});

test('finds variant by color/size when SKU is different', function () {
    // Create a product with an existing variant
    $product = Product::factory()->create(['name' => 'Test Product']);
    $existingVariant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'sku' => 'ORIGINAL-SKU',
        'color' => 'Red',
        'size' => 'Large',
    ]);

    // Import with different SKU but same color/size
    $component = Livewire::test(ImportData::class)
        ->set('importMode', 'update_existing')
        ->set('allData', [
            ['Test Product', 'DIFFERENT-SKU', 'Red', 'Large', '25.99'],
        ])
        ->set('columnMapping', [
            0 => 'product_name',
            1 => 'variant_sku',
            2 => 'variant_color',
            3 => 'variant_size',
            4 => 'retail_price',
        ]);

    $component->call('runDryRun');

    $results = $component->get('dryRunResults');

    // Should find and update the existing variant by color/size
    expect($results['variants_to_update'])->toBe(1);
    expect($results['variants_to_skip'])->toBe(0);
});

test('creates new variant when color/size combination is unique', function () {
    // Create a product with an existing variant
    $product = Product::factory()->create(['name' => 'Test Product']);
    $existingVariant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'sku' => 'EXISTING-001',
        'color' => 'Black',
        'size' => 'Small',
    ]);

    // Import with unique color/size combination
    $component = Livewire::test(ImportData::class)
        ->set('importMode', 'create_only')
        ->set('allData', [
            ['Test Product', 'NEW-SKU-002', 'White', 'Large', '35.99'],
        ])
        ->set('columnMapping', [
            0 => 'product_name',
            1 => 'variant_sku',
            2 => 'variant_color',
            3 => 'variant_size',
            4 => 'retail_price',
        ]);

    $component->call('runDryRun');

    $results = $component->get('dryRunResults');

    // Should create new variant since color/size is unique
    expect($results['variants_to_create'])->toBe(1);
    expect($results['variants_to_skip'])->toBe(0);
});
