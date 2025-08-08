<?php

use App\Livewire\Products\ProductWizard;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

test('wizard can create product with basic information', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ProductWizard::class)
        ->set('form.name', 'Test Wizard Product')
        ->set('form.description', 'A product created through the wizard')
        ->set('form.status', 'active')
        ->call('nextStep') // Step 1 to 2
        ->call('nextStep') // Step 2 to 3
        ->call('nextStep') // Step 3 to 4
        ->call('nextStep') // Step 4 to 5
        ->set('generateVariants', false)
        ->set('customVariants', [
            ['sku' => 'TEST-001', 'color' => 'White', 'width' => '120cm', 'drop' => '160cm', 'stock_level' => 10, 'status' => 'active'],
        ])
        ->call('nextStep') // Step 5 to 6
        ->set('assignBarcodes', false) // Disable barcode assignment for test
        ->call('nextStep') // Step 6 to 7
        ->call('createProduct')
        ->assertHasNoErrors();

    expect(Product::where('name', 'Test Wizard Product')->exists())->toBeTrue();

    $product = Product::where('name', 'Test Wizard Product')->first();
    expect($product->slug)->toBe('test-wizard-product');
    expect($product->variants()->count())->toBe(1);
    expect($product->variants()->first()->sku)->toBe('TEST-001');
});

test('wizard can generate variant matrix automatically', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(ProductWizard::class)
        ->set('form.name', 'Matrix Product')
        ->set('form.status', 'active')
        ->set('selectedColors', ['White', 'Cream'])
        ->set('selectedWidths', ['120cm'])
        ->set('selectedDrops', ['160cm'])
        ->call('generateVariantMatrix');

    expect($component->get('variantMatrix'))->toHaveLength(2);

    $variants = $component->get('variantMatrix');
    expect($variants[0]['color'])->toBe('White');
    expect($variants[0]['width'])->toBe('120cm');
    expect($variants[0]['drop'])->toBe('160cm');
    expect($variants[1]['color'])->toBe('Cream');
    expect($variants[1]['width'])->toBe('120cm');
    expect($variants[1]['drop'])->toBe('160cm');
});

test('wizard can generate variants with separate width and drop', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(ProductWizard::class)
        ->set('form.name', 'Width Drop Product')
        ->set('form.status', 'active')
        ->set('selectedColors', ['White'])
        ->set('selectedWidths', ['120cm', '160cm'])
        ->set('selectedDrops', ['180cm', '200cm'])
        ->call('generateVariantMatrix');

    // 1 color × 2 widths × 2 drops = 4 variants
    expect($component->get('variantMatrix'))->toHaveLength(4);

    $variants = $component->get('variantMatrix');
    expect($variants[0]['color'])->toBe('White');
    expect($variants[0]['width'])->toBe('120cm');
    expect($variants[0]['drop'])->toBe('180cm');
    expect($variants[1]['color'])->toBe('White');
    expect($variants[1]['width'])->toBe('120cm');
    expect($variants[1]['drop'])->toBe('200cm');
});

test('wizard validates required fields', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(ProductWizard::class)
        ->set('form.name', '') // Explicitly set empty
        ->call('nextStep');

    // Check that we're still on step 1 due to validation failure
    expect($component->get('currentStep'))->toBe(1);
});

test('wizard navigation works correctly', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(ProductWizard::class)
        ->set('form.name', 'Navigation Test')
        ->set('form.status', 'active');

    expect($component->get('currentStep'))->toBe(1);

    $component->call('nextStep');
    expect($component->get('currentStep'))->toBe(2);

    $component->call('previousStep');
    expect($component->get('currentStep'))->toBe(1);
});

test('wizard generates sequential SKUs correctly', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(ProductWizard::class)
        ->set('form.name', 'SKU Test Product')
        ->set('form.status', 'active')
        ->set('skuGenerationMethod', 'sequential')
        ->set('selectedColors', ['White', 'Black'])
        ->set('selectedWidths', ['120cm'])
        ->call('generateVariantMatrix');

    $variants = $component->get('variantMatrix');
    $parentSku = $component->get('parentSku');

    expect($variants)->toHaveLength(2);
    expect($variants[0]['sku'])->toBe($parentSku.'-001');
    expect($variants[1]['sku'])->toBe($parentSku.'-002');
});

test('wizard generates random SKUs correctly', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(ProductWizard::class)
        ->set('form.name', 'Random SKU Test')
        ->set('form.status', 'active')
        ->set('skuGenerationMethod', 'random')
        ->set('selectedColors', ['White'])
        ->set('selectedWidths', ['120cm'])
        ->call('generateVariantMatrix');

    $variants = $component->get('variantMatrix');
    $parentSku = $component->get('parentSku');

    expect($variants)->toHaveLength(1);
    expect($variants[0]['sku'])->toStartWith($parentSku.'-');
    expect(strlen($variants[0]['sku']))->toBe(strlen($parentSku) + 4); // parent + '-' + 3 digits
});

test('wizard auto-generates incremental Parent SKU', function () {
    $user = User::factory()->create();

    // Create existing products with parent SKUs
    Product::factory()->create(['parent_sku' => '005']);
    Product::factory()->create(['parent_sku' => '010']);
    Product::factory()->create(['parent_sku' => '002']);

    $component = Livewire::actingAs($user)
        ->test(ProductWizard::class);

    // Should generate next available SKU (011, since 010 is the highest)
    expect($component->get('parentSku'))->toBe('011');
});

test('wizard detects Parent SKU conflicts', function () {
    $user = User::factory()->create();

    // Create existing product
    $existingProduct = Product::factory()->create([
        'name' => 'Existing Product',
        'parent_sku' => '007',
    ]);

    $component = Livewire::actingAs($user)
        ->test(ProductWizard::class)
        ->set('parentSku', '007');

    expect($component->get('skuConflictProduct'))->toBe('Existing Product');
    $component->assertHasErrors(['parentSku']);
});

test('wizard auto-generates slug from product name', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(ProductWizard::class)
        ->set('form.name', 'Premium Window Shade');

    expect($component->get('form.slug'))->toBe('premium-window-shade');
});

test('wizard can regenerate Parent SKU', function () {
    $user = User::factory()->create();

    // Create existing product
    Product::factory()->create(['parent_sku' => '005']);

    $component = Livewire::actingAs($user)
        ->test(ProductWizard::class);

    $initialSku = $component->get('parentSku');
    expect($initialSku)->toBe('006'); // Should be next after 005

    // Manually set a conflicting SKU
    $component->set('parentSku', '005');
    expect($component->get('skuConflictProduct'))->not()->toBeNull();

    // Regenerate SKU to resolve conflict
    $component->call('regenerateParentSku');

    $newSku = $component->get('parentSku');
    expect($newSku)->toBe('006'); // Should generate next available
    expect($component->get('skuConflictProduct'))->toBeNull(); // Conflict resolved
});

test('wizard creates complete product with sequential variant SKUs', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ProductWizard::class)
        ->set('form.name', 'Complete Test Product')
        ->set('form.description', 'A complete test product')
        ->set('form.status', 'active')
        ->set('parentSku', '999')
        ->call('nextStep') // Step 1 to 2
        ->call('nextStep') // Step 2 to 3
        ->call('nextStep') // Step 3 to 4
        ->call('nextStep') // Step 4 to 5
        ->set('skuGenerationMethod', 'sequential')
        ->set('selectedColors', ['White'])
        ->set('selectedWidths', ['120cm'])
        ->set('selectedDrops', ['160cm'])
        ->call('generateVariantMatrix')
        ->call('nextStep') // Step 5 to 6
        ->set('assignBarcodes', false) // Disable barcode assignment for test
        ->call('nextStep') // Step 6 to 7
        ->call('createProduct')
        ->assertHasNoErrors();

    $product = Product::where('name', 'Complete Test Product')->first();
    expect($product)->not()->toBeNull();
    expect($product->parent_sku)->toBe('999');
    expect($product->slug)->toBe('complete-test-product');

    $variants = $product->variants()->orderBy('sku')->get();
    expect($variants)->toHaveCount(1);
    expect($variants[0]->sku)->toBe('999-001');
    expect($variants[0]->color)->toBe('White');
    expect($variants[0]->width)->toBe('120cm');
    expect($variants[0]->drop)->toBe('160cm');
});

test('wizard creates complex product with multiple dimensions', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(ProductWizard::class)
        ->set('form.name', 'Complex Window Shade')
        ->set('form.status', 'active')
        ->set('parentSku', '777')
        ->call('nextStep') // Step 1 to 2
        ->call('nextStep') // Step 2 to 3
        ->call('nextStep') // Step 3 to 4
        ->call('nextStep') // Step 4 to 5
        ->set('skuGenerationMethod', 'sequential')
        ->set('selectedColors', ['White', 'Cream'])
        ->set('selectedWidths', ['120cm', '160cm'])
        ->set('selectedDrops', ['180cm'])
        ->call('generateVariantMatrix');

    // Verify matrix was generated correctly
    $matrix = $component->get('variantMatrix');
    expect($matrix)->toHaveLength(4); // 2 colors × 2 widths × 1 drop

    $component->call('nextStep') // Step 5 to 6
        ->set('assignBarcodes', false); // Disable barcode assignment for test

    $component->call('nextStep'); // Step 6 to 7

    $initialProductCount = Product::count();

    $component->call('createProduct')
        ->assertHasNoErrors();

    // Check if a product was actually created
    $finalProductCount = Product::count();
    expect($finalProductCount)->toBe($initialProductCount + 1, 'Product should have been created');

    $product = Product::where('name', 'Complex Window Shade')->first();
    expect($product)->not()->toBeNull();

    // Check variants
    $variants = $product->variants()->orderBy('sku')->get();
    expect($variants)->toHaveCount(4);

    // Check first variant (White, null, 120cm, 180cm)
    expect($variants[0]->color)->toBe('White');
    expect($variants[0]->size)->toBe('120cm × 180cm'); // size returns formatted dimensions
    expect($variants[0]->width)->toBe('120cm');
    expect($variants[0]->drop)->toBe('180cm');

    // Check last variant (Cream, null, 160cm, 180cm)
    expect($variants[3]->color)->toBe('Cream');
    expect($variants[3]->size)->toBe('160cm × 180cm'); // size returns formatted dimensions
    expect($variants[3]->width)->toBe('160cm');
    expect($variants[3]->drop)->toBe('180cm');
});
