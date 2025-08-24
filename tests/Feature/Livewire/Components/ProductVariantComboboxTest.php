<?php

use App\Livewire\Components\ProductVariantCombobox;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('combobox component mounts with default values', function () {
    Livewire::test(ProductVariantCombobox::class)
        ->assertSet('search', '')
        ->assertSet('selectedType', '')
        ->assertSet('selectedId', 0)
        ->assertSet('isOpen', false)
        ->assertSet('allowProducts', true)
        ->assertSet('allowVariants', true)
        ->assertSet('maxResults', 10);
});

test('can configure component properties', function () {
    Livewire::test(ProductVariantCombobox::class, [
        'placeholder' => 'Custom placeholder',
        'allowProducts' => false,
        'allowVariants' => true,
        'maxResults' => 5,
    ])
        ->assertSet('placeholder', 'Custom placeholder')
        ->assertSet('allowProducts', false)
        ->assertSet('allowVariants', true)
        ->assertSet('maxResults', 5);
});

test('search opens dropdown when typing 2+ characters', function () {
    Livewire::test(ProductVariantCombobox::class)
        ->set('search', 'te')
        ->assertSet('isOpen', true);
});

test('search stays closed with less than 2 characters', function () {
    Livewire::test(ProductVariantCombobox::class)
        ->set('search', 'a')
        ->assertSet('isOpen', false);
});

test('searchResults returns empty collection for short search', function () {
    Product::factory()->create(['name' => 'Test Product']);
    
    $component = Livewire::test(ProductVariantCombobox::class)
        ->set('search', 'a');
    
    expect($component->instance()->searchResults)->toHaveCount(0);
});

test('searchResults finds products by name', function () {
    $product = Product::factory()->create(['name' => 'Test Product']);
    Product::factory()->create(['name' => 'Other Product']);
    
    $component = Livewire::test(ProductVariantCombobox::class)
        ->set('search', 'Test');
    
    $results = $component->instance()->searchResults;
    expect($results)->toHaveCount(1)
        ->and($results->first()['type'])->toBe('product')
        ->and($results->first()['name'])->toBe('Test Product')
        ->and($results->first()['id'])->toBe($product->id);
});

test('searchResults finds products by SKU', function () {
    $product = Product::factory()->create([
        'name' => 'Some Product',
        'parent_sku' => 'TEST123'
    ]);
    
    $component = Livewire::test(ProductVariantCombobox::class)
        ->set('search', 'TEST');
    
    $results = $component->instance()->searchResults;
    expect($results)->toHaveCount(1)
        ->and($results->first()['sku'])->toBe('TEST123');
});

test('searchResults finds products by description', function () {
    $product = Product::factory()->create([
        'name' => 'Product',
        'description' => 'This is a test product description'
    ]);
    
    $component = Livewire::test(ProductVariantCombobox::class)
        ->set('search', 'test product');
    
    $results = $component->instance()->searchResults;
    expect($results)->toHaveCount(1);
});

test('searchResults finds variants by SKU', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'sku' => 'VAR123'
    ]);
    
    $component = Livewire::test(ProductVariantCombobox::class)
        ->set('search', 'VAR');
    
    $results = $component->instance()->searchResults;
    expect($results)->toHaveCount(1)
        ->and($results->first()['type'])->toBe('variant')
        ->and($results->first()['sku'])->toBe('VAR123')
        ->and($results->first()['id'])->toBe($variant->id);
});

test('searchResults finds variants by name', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'name' => 'Test Variant'
    ]);
    
    $component = Livewire::test(ProductVariantCombobox::class)
        ->set('search', 'Test Variant');
    
    $results = $component->instance()->searchResults;
    expect($results)->toHaveCount(1)
        ->and($results->first()['type'])->toBe('variant');
});

test('searchResults finds variants by product name', function () {
    $product = Product::factory()->create(['name' => 'Test Product']);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'name' => 'Variant'
    ]);
    
    $component = Livewire::test(ProductVariantCombobox::class)
        ->set('search', 'Test Product');
    
    $results = $component->instance()->searchResults;
    expect($results->where('type', 'variant'))->toHaveCount(1);
});

test('searchResults respects allowProducts setting', function () {
    Product::factory()->create(['name' => 'Test Product']);
    
    $component = Livewire::test(ProductVariantCombobox::class, [
        'allowProducts' => false
    ])
        ->set('search', 'Test');
    
    $results = $component->instance()->searchResults;
    expect($results->where('type', 'product'))->toHaveCount(0);
});

test('searchResults respects allowVariants setting', function () {
    $product = Product::factory()->create(['name' => 'Test Product']);
    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'sku' => 'TEST123'
    ]);
    
    $component = Livewire::test(ProductVariantCombobox::class, [
        'allowVariants' => false
    ])
        ->set('search', 'TEST');
    
    $results = $component->instance()->searchResults;
    expect($results->where('type', 'variant'))->toHaveCount(0);
});

test('searchResults respects maxResults setting', function () {
    // Create more products than maxResults
    Product::factory()->count(5)->create(['name' => 'Test Product']);
    
    $component = Livewire::test(ProductVariantCombobox::class, [
        'maxResults' => 3
    ])
        ->set('search', 'Test');
    
    $results = $component->instance()->searchResults;
    expect($results)->toHaveCount(3);
});

test('searchResults includes variants_count for products', function () {
    $product = Product::factory()->create(['name' => 'Test Product']);
    ProductVariant::factory()->count(3)->create(['product_id' => $product->id]);
    
    $component = Livewire::test(ProductVariantCombobox::class)
        ->set('search', 'Test');
    
    $results = $component->instance()->searchResults;
    $productResult = $results->where('type', 'product')->first();
    expect($productResult['variants_count'])->toBe(3);
});

test('searchResults includes product_name for variants', function () {
    $product = Product::factory()->create(['name' => 'Test Product']);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'sku' => 'VAR123'
    ]);
    
    $component = Livewire::test(ProductVariantCombobox::class)
        ->set('search', 'VAR');
    
    $results = $component->instance()->searchResults;
    $variantResult = $results->where('type', 'variant')->first();
    expect($variantResult['product_name'])->toBe('Test Product');
});

test('can select product', function () {
    $product = Product::factory()->create([
        'name' => 'Test Product',
        'parent_sku' => 'PROD123'
    ]);
    
    Livewire::test(ProductVariantCombobox::class)
        ->call('selectItem', 'product', $product->id)
        ->assertSet('selectedType', 'product')
        ->assertSet('selectedId', $product->id)
        ->assertSet('isOpen', false)
        ->assertSet('displayValue', 'Test Product (SKU: PROD123)')
        ->assertSet('search', 'Test Product (SKU: PROD123)')
        ->assertDispatched('item-selected', [
            'type' => 'product',
            'id' => $product->id,
            'display' => 'Test Product (SKU: PROD123)',
        ]);
});

test('can select variant', function () {
    $product = Product::factory()->create(['name' => 'Test Product']);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'name' => 'Test Variant',
        'sku' => 'VAR123'
    ]);
    
    Livewire::test(ProductVariantCombobox::class)
        ->call('selectItem', 'variant', $variant->id)
        ->assertSet('selectedType', 'variant')
        ->assertSet('selectedId', $variant->id)
        ->assertSet('isOpen', false)
        ->assertSet('displayValue', 'Test Product - Test Variant (SKU: VAR123)')
        ->assertSet('search', 'Test Product - Test Variant (SKU: VAR123)')
        ->assertDispatched('item-selected', [
            'type' => 'variant',
            'id' => $variant->id,
            'display' => 'Test Product - Test Variant (SKU: VAR123)',
        ]);
});

test('can clear selection', function () {
    $product = Product::factory()->create();
    
    Livewire::test(ProductVariantCombobox::class)
        ->set('selectedType', 'product')
        ->set('selectedId', $product->id)
        ->set('displayValue', 'Some Product')
        ->set('search', 'Some Product')
        ->call('clear')
        ->assertSet('selectedType', '')
        ->assertSet('selectedId', 0)
        ->assertSet('displayValue', '')
        ->assertSet('search', '')
        ->assertDispatched('item-cleared');
});

test('clears selection when search changes', function () {
    $product = Product::factory()->create();
    
    Livewire::test(ProductVariantCombobox::class)
        ->set('selectedType', 'product')
        ->set('selectedId', $product->id)
        ->set('displayValue', 'Original Product')
        ->set('search', 'Different search')
        ->assertSet('selectedType', '')
        ->assertSet('selectedId', 0)
        ->assertSet('displayValue', '');
});

test('keeps selection when search matches display value', function () {
    $product = Product::factory()->create();
    $displayValue = 'Test Product (SKU: PROD123)';
    
    Livewire::test(ProductVariantCombobox::class)
        ->set('selectedType', 'product')
        ->set('selectedId', $product->id)
        ->set('displayValue', $displayValue)
        ->set('search', $displayValue)
        ->assertSet('selectedType', 'product')
        ->assertSet('selectedId', $product->id)
        ->assertSet('displayValue', $displayValue);
});

test('can open and close dropdown', function () {
    Livewire::test(ProductVariantCombobox::class)
        ->call('openDropdown')
        ->assertSet('isOpen', true)
        ->call('closeDropdown')
        ->assertSet('isOpen', false);
});

test('handles selecting nonexistent product gracefully', function () {
    Livewire::test(ProductVariantCombobox::class)
        ->call('selectItem', 'product', 99999)
        ->assertSet('selectedType', 'product')
        ->assertSet('selectedId', 99999)
        ->assertSet('displayValue', '') // Should be empty since product doesn't exist
        ->assertSet('search', '');
});

test('handles selecting nonexistent variant gracefully', function () {
    Livewire::test(ProductVariantCombobox::class)
        ->call('selectItem', 'variant', 99999)
        ->assertSet('selectedType', 'variant')
        ->assertSet('selectedId', 99999)
        ->assertSet('displayValue', '') // Should be empty since variant doesn't exist
        ->assertSet('search', '');
});