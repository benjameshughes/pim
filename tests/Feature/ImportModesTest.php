<?php

use App\Livewire\Products\ImportData;
use App\Models\Product;
use App\Models\ProductVariant;
use Livewire\Livewire;

test('create_only mode skips existing products and variants', function () {
    // Create existing data
    $product = Product::factory()->create(['name' => 'Existing Product']);
    $variant = ProductVariant::factory()->create(['sku' => 'EXISTING-SKU']);

    $component = Livewire::test(ImportData::class)
        ->set('importMode', 'create_only')
        ->set('allData', [
            ['Existing Product', 'EXISTING-SKU', 'Blue', 'L'],
            ['New Product', 'NEW-SKU', 'Red', 'M'],
        ])
        ->set('columnMapping', [
            0 => 'product_name',
            1 => 'variant_sku',
            2 => 'variant_color',
            3 => 'variant_size',
        ]);

    $component->call('runDryRun');

    $results = $component->get('dryRunResults');

    expect($results['products_to_create'])->toBe(1);
    expect($results['products_to_skip'])->toBe(1);
    expect($results['variants_to_create'])->toBe(1);
    expect($results['variants_to_skip'])->toBe(1);
});

test('update_existing mode only updates existing records', function () {
    // Create existing data
    $product = Product::factory()->create(['name' => 'Existing Product']);
    $variant = ProductVariant::factory()->create(['sku' => 'EXISTING-SKU']);

    $component = Livewire::test(ImportData::class)
        ->set('importMode', 'update_existing')
        ->set('allData', [
            ['Existing Product', 'EXISTING-SKU', 'Blue', 'L'],
            ['New Product', 'NEW-SKU', 'Red', 'M'],
        ])
        ->set('columnMapping', [0 => 'product_name', 1 => 'variant_sku', 2 => 'variant_color', 3 => 'variant_size']);

    $component->call('runDryRun');

    expect($component->get('dryRunResults'))
        ->toMatchArray([
            'products_to_update' => 1,
            'products_to_skip' => 1,
            'variants_to_update' => 1,
            'variants_to_skip' => 1,
        ]);
});

test('create_or_update mode handles both scenarios', function () {
    // Create existing data
    $product = Product::factory()->create(['name' => 'Existing Product']);
    $variant = ProductVariant::factory()->create(['sku' => 'EXISTING-SKU']);

    $component = Livewire::test(ImportData::class)
        ->set('importMode', 'create_or_update')
        ->set('allData', [
            ['Existing Product', 'EXISTING-SKU', 'Blue', 'L'],
            ['New Product', 'NEW-SKU', 'Red', 'M'],
        ])
        ->set('columnMapping', [0 => 'product_name', 1 => 'variant_sku', 2 => 'variant_color', 3 => 'variant_size']);

    $component->call('runDryRun');

    expect($component->get('dryRunResults'))
        ->toMatchArray([
            'products_to_create' => 1,
            'products_to_update' => 1,
            'products_to_skip' => 0,
            'variants_to_create' => 1,
            'variants_to_update' => 1,
            'variants_to_skip' => 0,
        ]);
});

test('import mode selection UI renders correctly', function () {
    $component = Livewire::test(ImportData::class)
        ->set('step', 2);

    $component->assertSee('Import Mode')
        ->assertSee('Create Only (Skip existing SKUs)')
        ->assertSee('Update Only (Skip non-existing SKUs)')
        ->assertSee('Create or Update (Upsert all records)');
});

test('default import mode is create_only', function () {
    $component = Livewire::test(ImportData::class);

    expect($component->get('importMode'))->toBe('create_only');
});
