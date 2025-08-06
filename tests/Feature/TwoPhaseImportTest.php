<?php

use App\Models\User;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Livewire\Products\ImportData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();
});

test('auto-generate parent mode creates parent products first then variants', function () {
    $this->actingAs($this->user);
    
    // Create a CSV content with variant data
    $csvContent = implode("\n", [
        'Product Name,SKU,Color,Size,Price',
        'Red T-Shirt Small,001-001,Red,Small,29.99',
        'Red T-Shirt Medium,001-002,Red,Medium,29.99',
        'Blue T-Shirt Small,002-001,Blue,Small,29.99',
        'Blue T-Shirt Large,002-002,Blue,Large,29.99',
    ]);
    
    $file = UploadedFile::fake()->createWithContent('test-import.csv', $csvContent);
    
    $component = Livewire::test(ImportData::class)
        ->set('file', $file)
        ->call('analyzeFile')
        ->set('autoGenerateParentMode', true)
        ->call('runDryRun')
        ->call('startActualImport');
    
    
    // Verify parent products were created
    $parents = Product::whereNull('parent_sku')->get();
    expect($parents)->toHaveCount(2);
    
    // Verify variants were created and linked to parents
    $variants = ProductVariant::all();
    expect($variants)->toHaveCount(4);
});

test('auto-generate parent mode groups by product name when no SKU pattern', function () {
    $this->actingAs($this->user);
    
    $csvContent = implode("\n", [
        'Product Name,SKU,Color,Size,Price',
        'Blue Jeans Slim Fit,JEANS001,Blue,32,59.99',
        'Black Jeans Slim Fit,JEANS002,Black,34,59.99',
        'Red Dress Summer,DRESS001,Red,S,89.99',
        'Yellow Dress Summer,DRESS002,Yellow,M,89.99',
    ]);
    
    $file = UploadedFile::fake()->createWithContent('test-import.csv', $csvContent);
    
    Livewire::test(ImportData::class)
        ->set('file', $file)
        ->call('analyzeFile')
        ->set('autoGenerateParentMode', true)
        ->call('runDryRun')
        ->call('startActualImport');
    
    // Should create 2 parent products (Jeans and Dress)
    $parents = Product::whereNull('parent_sku')->get();
    expect($parents)->toHaveCount(2);
    
    // Verify grouping by product name worked
    $parentNames = $parents->pluck('name')->sort()->values();
    expect($parentNames[0])->toContain('Dress');
    expect($parentNames[1])->toContain('Jeans');
    
    // Verify all 4 variants were created
    expect(ProductVariant::count())->toEqual(4);
});

test('auto-generate parent mode respects import modes', function () {
    $this->actingAs($this->user);
    
    // Create existing parent and variant with proper naming
    $existingParent = Product::factory()->create([
        'name' => 'Red T-Shirt', // Updated to match the similarity algorithm expectation
        'parent_sku' => null
    ]);
    
    ProductVariant::factory()->create([
        'product_id' => $existingParent->id,
        'sku' => '001-001',
        'color' => 'Red',
        'size' => 'Small'
    ]);
    
    $csvContent = implode("\n", [
        'Product Name,SKU,Color,Size,Price',
        'Red T-Shirt Small,001-001,Red,Small,29.99', // Should be skipped in create_only
        'Red T-Shirt Medium,001-002,Red,Medium,29.99', // Should be created
    ]);
    
    $file = UploadedFile::fake()->createWithContent('test-import.csv', $csvContent);
    
    Livewire::test(ImportData::class)
        ->set('file', $file)
        ->call('analyzeFile')
        ->set('autoGenerateParentMode', true)
        ->set('importMode', 'create_only')
        ->call('runDryRun')
        ->call('startActualImport');
    
    // Should still have only 1 parent (the existing "Red T-Shirt")
    expect(Product::whereNull('parent_sku')->count())->toEqual(1);
    
    // Should have 2 variants total (1 existing + 1 new)
    expect(ProductVariant::count())->toEqual(2);
    
    // Verify the existing variant wasn't duplicated
    $redSmallVariants = ProductVariant::where('sku', '001-001')->get();
    expect($redSmallVariants)->toHaveCount(1);
});

test('standard mode still works for explicit parent child relationships', function () {
    $this->actingAs($this->user);
    
    $csvContent = implode("\n", [
        'Product Name,Is Parent,SKU,Color,Size,Price',
        'T-Shirt Collection,true,,,,',
        'Red Variant,false,001-001,Red,Small,29.99',
        'Blue Variant,false,001-002,Blue,Medium,29.99',
    ]);
    
    $file = UploadedFile::fake()->createWithContent('test-import.csv', $csvContent);
    
    Livewire::test(ImportData::class)
        ->set('file', $file)
        ->call('analyzeFile')
        ->set('autoGenerateParentMode', false) // Standard mode
        ->set('columnMapping', [
            0 => 'product_name',
            1 => 'is_parent', 
            2 => 'variant_sku',
            3 => 'variant_color',
            4 => 'variant_size',
            5 => 'retail_price'
        ])
        ->call('runDryRun')
        ->call('startActualImport');
    
    
    // Should have 1 parent product and 2 variants
    expect(Product::count())->toEqual(1);
    expect(ProductVariant::count())->toEqual(2);
    
    $parent = Product::first();
    expect($parent->name)->toEqual('T-Shirt Collection');
    expect($parent->variants)->toHaveCount(2);
});