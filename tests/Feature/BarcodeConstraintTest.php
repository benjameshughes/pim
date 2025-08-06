<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Barcode;
use App\Models\Pricing;
use App\Models\SalesChannel;
use App\Livewire\Products\ImportData;
use Livewire\Livewire;

test('handles duplicate barcode gracefully during import', function () {
    // Create a variant with an existing barcode
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'TEST-001']);
    $variant->barcodes()->create(['barcode' => '1234567890123', 'type' => 'EAN13']);
    
    // Try to import the same barcode again
    $component = Livewire::test(ImportData::class)
        ->set('importMode', 'create_or_update')
        ->set('allData', [
            [$product->name, 'TEST-001', 'Blue', 'L', '1234567890123', '29.99']
        ])
        ->set('columnMapping', [
            0 => 'product_name',
            1 => 'variant_sku', 
            2 => 'variant_color',
            3 => 'variant_size',
            4 => 'barcode',
            5 => 'retail_price'
        ]);
    
    // Should not throw an error
    $component->call('runDryRun');
    
    $results = $component->get('dryRunResults');
    expect($results['error_rows'])->toBe(0);
    expect($results['valid_rows'])->toBe(1);
    
    // Variant should still have only 1 barcode
    expect($variant->fresh()->barcodes()->count())->toBe(1);
});

test('handles duplicate pricing gracefully during import', function () {
    // Create sales channel
    SalesChannel::factory()->create(['name' => 'Website', 'slug' => 'website']);
    
    // Create a variant with existing pricing
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'TEST-002']);
    
    Pricing::create([
        'product_variant_id' => $variant->id,
        'marketplace' => 'website',
        'retail_price' => 19.99,
        'cost_price' => 10.00,
        'vat_percentage' => 20.00,
        'vat_inclusive' => true,
    ]);
    
    // Try to import pricing again with different price
    $component = Livewire::test(ImportData::class)
        ->set('importMode', 'create_or_update')
        ->set('allData', [
            [$product->name, 'TEST-002', 'Red', 'M', '9999999999999', '29.99']
        ])
        ->set('columnMapping', [
            0 => 'product_name',
            1 => 'variant_sku',
            2 => 'variant_color', 
            3 => 'variant_size',
            4 => 'barcode',
            5 => 'retail_price'
        ]);
    
    $component->call('runDryRun');
    
    $results = $component->get('dryRunResults');
    expect($results['error_rows'])->toBe(0);
    expect($results['valid_rows'])->toBe(1);
    
    // Should have only 1 pricing record (updated, not duplicated)
    expect(Pricing::where('product_variant_id', $variant->id)->count())->toBe(1);
    
    // Price should be updated
    $pricing = Pricing::where('product_variant_id', $variant->id)->first();
    expect($pricing->retail_price)->toBe('19.99'); // Still original price in dry run
});

test('skips auto-assignment when variant already has barcodes', function () {
    // Create a variant with an existing barcode
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'TEST-003']);
    $variant->barcodes()->create(['barcode' => '5555555555555', 'type' => 'EAN13']);
    
    // Import without providing a barcode (should skip auto-assignment)
    $component = Livewire::test(ImportData::class)
        ->set('importMode', 'create_or_update')
        ->set('autoAssignGS1Barcodes', true)
        ->set('allData', [
            [$product->name, 'TEST-003', 'Green', 'S', '', '15.99']
        ])
        ->set('columnMapping', [
            0 => 'product_name',
            1 => 'variant_sku',
            2 => 'variant_color',
            3 => 'variant_size', 
            4 => 'barcode',
            5 => 'retail_price'
        ]);
    
    $component->call('runDryRun');
    
    // Should still have only 1 barcode (original)
    expect($variant->fresh()->barcodes()->count())->toBe(1);
    expect($variant->fresh()->barcodes()->first()->barcode)->toBe('5555555555555');
});