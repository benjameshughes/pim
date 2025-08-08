<?php

use App\Models\Product;
use App\Services\AutoParentCreator;

test('creates parent from SKU pattern 001-001', function () {
    $variantData = [
        'variant_sku' => '001-002',
        'product_name' => 'Blackout Roller Blind Blue 60cm',
        'description' => 'A blue roller blind',
    ];

    $parent = AutoParentCreator::createParentFromVariant($variantData);

    expect($parent)->toBeInstanceOf(Product::class);
    expect($parent->parent_sku)->toBe('001');
    expect($parent->auto_generated)->toBeTrue();
    expect($parent->name)->toBe('Blackout Roller Blind'); // Should remove "Blue 60cm"
});

test('finds existing parent by SKU pattern', function () {
    // Create existing parent
    $existingParent = Product::factory()->create([
        'parent_sku' => '001',
        'name' => 'Existing Parent Product',
    ]);

    $variantData = [
        'variant_sku' => '001-003',
        'product_name' => 'Some Variant Name Red Large',
    ];

    $parent = AutoParentCreator::createParentFromVariant($variantData);

    expect($parent->id)->toBe($existingParent->id);
    expect($parent->name)->toBe('Existing Parent Product');
});

test('creates parent from name when SKU pattern fails', function () {
    $variantData = [
        'variant_sku' => 'INVALID-SKU-FORMAT',
        'product_name' => 'Smart TV 55inch QLED Black',
        'description' => 'A black smart TV',
    ];

    $parent = AutoParentCreator::createParentFromVariant($variantData);

    expect($parent)->toBeInstanceOf(Product::class);
    expect($parent->auto_generated)->toBeTrue();
    expect($parent->name)->toBe('Smart TV 55inch QLED'); // Should remove "Black"
});

test('creates generic parent when all else fails', function () {
    $variantData = [
        'variant_sku' => 'WEIRD-FORMAT',
        'product_name' => 'X', // Too short to extract meaningful parent name
    ];

    $parent = AutoParentCreator::createParentFromVariant($variantData);

    expect($parent)->toBeInstanceOf(Product::class);
    expect($parent->auto_generated)->toBeTrue();
    expect($parent->name)->toBe('X'); // Fallback to original name
});

test('smart grouping creates parent from multiple variants', function () {
    $variantGroup = [
        ['product_name' => 'iPhone 15 Pro Blue 128GB', 'variant_sku' => '001-001'],
        ['product_name' => 'iPhone 15 Pro Red 256GB', 'variant_sku' => '001-002'],
        ['product_name' => 'iPhone 15 Pro Black 512GB', 'variant_sku' => '001-003'],
    ];

    $parent = AutoParentCreator::createParentFromVariantGroup($variantGroup);

    expect($parent)->toBeInstanceOf(Product::class);
    expect($parent->parent_sku)->toBe('001');
    expect($parent->name)->toBe('iPhone 15 Pro'); // Common parts
    expect($parent->auto_generated)->toBeTrue();
});

test('parent name extraction removes colors and sizes correctly', function () {
    $testCases = [
        ['input' => 'Blackout Roller Blind Blue 60cm', 'expected' => 'Blackout Roller Blind', 'sku' => '001-001'],
        ['input' => 'Nike Air Max White Size 10', 'expected' => 'Nike Air Max', 'sku' => '002-001'],
        ['input' => 'Samsung TV 55inch QLED Black', 'expected' => 'Samsung TV 55inch QLED', 'sku' => '003-001'],
        ['input' => 'Wooden Chair Brown Large', 'expected' => 'Wooden Chair', 'sku' => '004-001'],
        ['input' => 'Coffee Mug Red 300ml', 'expected' => 'Coffee Mug', 'sku' => '005-001'],
    ];

    foreach ($testCases as $testCase) {
        $variantData = [
            'variant_sku' => $testCase['sku'],
            'product_name' => $testCase['input'],
        ];

        $parent = AutoParentCreator::createParentFromVariant($variantData);
        expect($parent->name)->toBe($testCase['expected'], "Failed for input: {$testCase['input']}");
    }
});
