<?php

use App\Actions\Import\GuessFieldMapping;
use App\Actions\Import\ValidateImportRow;
use App\Repositories\ProductRepository;
use App\Repositories\ProductVariantRepository;
use App\Models\Product;
use App\Models\ProductVariant;

test('guess field mapping basic functionality', function () {
    $action = new GuessFieldMapping();
    
    expect($action->execute('Product Name'))->toBe('product_name')
        ->and($action->execute('SKU'))->toBe('variant_sku')
        ->and($action->execute('Color'))->toBe('variant_color')
        ->and($action->execute('Size'))->toBe('variant_size')
        ->and($action->execute('Price'))->toBe('retail_price');
});

test('validate import row basic validation', function () {
    $action = new ValidateImportRow();
    
    // Valid row with SKU
    $result = $action->execute(['variant_sku' => 'TEST-001'], 1);
    expect($result->hasErrors())->toBeFalse();
    
    // Invalid row with neither SKU nor name
    $result = $action->execute([], 1);
    expect($result->hasErrors())->toBeTrue();
});

test('product repository basic operations', function () {
    $repository = new ProductRepository();
    
    // Test creation
    $product = $repository->create([
        'name' => 'Test Product',
        'slug' => 'test-product',
        'is_parent' => false,
        'status' => 'active'
    ]);
    
    expect($product)->toBeInstanceOf(Product::class)
        ->and($product->name)->toBe('Test Product');
    
    // Test finding by name
    $found = $repository->findByName('Test Product');
    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($product->id);
});

test('variant repository basic operations', function () {
    $productRepo = new ProductRepository();
    $variantRepo = new ProductVariantRepository();
    
    // Create parent product
    $product = $productRepo->create([
        'name' => 'Parent Product',
        'slug' => 'parent-product',
        'is_parent' => true,
        'status' => 'active'
    ]);
    
    // Create variant
    $variant = $variantRepo->create([
        'product_id' => $product->id,
        'sku' => 'TEST-001',
        'color' => 'Red',
        'size' => 'M',
        'status' => 'active'
    ]);
    
    expect($variant)->toBeInstanceOf(ProductVariant::class)
        ->and($variant->sku)->toBe('TEST-001');
    
    // Test finding by SKU
    $found = $variantRepo->findBySku('TEST-001');
    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($variant->id);
});

test('services can be instantiated', function () {
    $importManager = app(\App\Services\ImportManagerService::class);
    $excelService = app(\App\Services\ExcelProcessingService::class);
    $mappingService = app(\App\Services\ColumnMappingService::class);
    $validationService = app(\App\Services\ImportValidationService::class);
    $productService = app(\App\Services\ProductImportService::class);
    
    expect($importManager)->toBeInstanceOf(\App\Services\ImportManagerService::class)
        ->and($excelService)->toBeInstanceOf(\App\Services\ExcelProcessingService::class)
        ->and($mappingService)->toBeInstanceOf(\App\Services\ColumnMappingService::class)
        ->and($validationService)->toBeInstanceOf(\App\Services\ImportValidationService::class)
        ->and($productService)->toBeInstanceOf(\App\Services\ProductImportService::class);
});