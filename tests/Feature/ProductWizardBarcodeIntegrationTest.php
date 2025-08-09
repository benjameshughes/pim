<?php

use App\Livewire\Pim\Products\Management\ProductWizard;
use App\Models\Barcode;
use App\Models\BarcodePool;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

test('wizard can create product with barcode assignment', function () {
    $user = User::factory()->create();

    // Create test barcodes in the pool
    BarcodePool::create([
        'barcode' => '5059032000001',
        'barcode_type' => 'EAN13',
        'status' => 'available',
        'is_legacy' => false,
    ]);

    BarcodePool::create([
        'barcode' => '5059032000002',
        'barcode_type' => 'EAN13',
        'status' => 'available',
        'is_legacy' => false,
    ]);

    Livewire::actingAs($user)
        ->test(ProductWizard::class)
        ->set('form.name', 'Barcode Test Product')
        ->set('form.description', 'Testing barcode assignment')
        ->set('form.status', 'active')
        ->set('parentSku', '888')
        ->call('nextStep') // Step 1 to 2
        ->call('nextStep') // Step 2 to 3
        ->call('nextStep') // Step 3 to 4
        ->call('nextStep') // Step 4 to 5
        ->set('skuGenerationMethod', 'sequential')
        ->set('selectedColors', ['White', 'Black'])
        ->set('selectedWidths', ['120cm'])
        ->set('selectedDrops', ['160cm'])
        ->call('generateVariantMatrix')
        ->call('nextStep') // Step 5 to 6
        ->set('assignBarcodes', true)
        ->set('barcodeAssignmentMethod', 'auto')
        ->call('assignBarcodesToVariants')
        ->call('nextStep') // Step 6 to 7
        ->call('createProduct')
        ->assertHasNoErrors();

    // Check product was created
    $product = Product::where('name', 'Barcode Test Product')->first();
    expect($product)->not()->toBeNull();
    expect($product->variants)->toHaveCount(2);

    // Check variants have barcodes assigned
    $variants = $product->variants()->orderBy('sku')->get();

    // Check first variant has barcode
    $firstVariant = $variants[0];
    expect($firstVariant->sku)->toBe('888-001');
    expect($firstVariant->color)->toBe('White');
    expect($firstVariant->barcodes)->toHaveCount(1);
    expect($firstVariant->barcodes->first()->barcode)->toBeIn(['5059032000001', '5059032000002']);

    // Check second variant has barcode
    $secondVariant = $variants[1];
    expect($secondVariant->sku)->toBe('888-002');
    expect($secondVariant->color)->toBe('Black');
    expect($secondVariant->barcodes)->toHaveCount(1);
    expect($secondVariant->barcodes->first()->barcode)->toBeIn(['5059032000001', '5059032000002']);

    // Check that barcodes in pool are marked as assigned
    $assignedBarcodes = BarcodePool::where('status', 'assigned')->get();
    expect($assignedBarcodes)->toHaveCount(2);

    // Check that each barcode is assigned to correct variant
    foreach ($assignedBarcodes as $poolBarcode) {
        expect($poolBarcode->assigned_to_variant_id)->not()->toBeNull();
        expect($poolBarcode->assigned_at)->not()->toBeNull();
        expect($poolBarcode->date_first_used)->not()->toBeNull();
    }
});

test('wizard skips barcode assignment when disabled', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ProductWizard::class)
        ->set('form.name', 'No Barcode Product')
        ->set('form.status', 'active')
        ->call('nextStep') // Step 1 to 2
        ->call('nextStep') // Step 2 to 3
        ->call('nextStep') // Step 3 to 4
        ->call('nextStep') // Step 4 to 5
        ->set('generateVariants', false)
        ->set('customVariants', [
            ['sku' => 'NO-BARCODE-001', 'color' => 'Red', 'width' => '100cm', 'drop' => '150cm', 'stock_level' => 5, 'status' => 'active'],
        ])
        ->call('nextStep') // Step 5 to 6
        ->set('assignBarcodes', false) // Disable barcode assignment
        ->call('nextStep') // Step 6 to 7
        ->call('createProduct')
        ->assertHasNoErrors();

    // Check product was created without barcodes
    $product = Product::where('name', 'No Barcode Product')->first();
    expect($product)->not()->toBeNull();
    expect($product->variants)->toHaveCount(1);

    $variant = $product->variants->first();
    expect($variant->sku)->toBe('NO-BARCODE-001');
    expect($variant->color)->toBe('Red');
    expect($variant->barcodes)->toHaveCount(0); // No barcodes assigned
});

test('wizard validates insufficient barcodes', function () {
    $user = User::factory()->create();

    // Create only one barcode but we'll try to create 2 variants
    BarcodePool::create([
        'barcode' => '5059032000001',
        'barcode_type' => 'EAN13',
        'status' => 'available',
        'is_legacy' => false,
    ]);

    Livewire::actingAs($user)
        ->test(ProductWizard::class)
        ->set('form.name', 'Insufficient Barcode Test')
        ->set('form.status', 'active')
        ->call('nextStep') // Step 1 to 2
        ->call('nextStep') // Step 2 to 3
        ->call('nextStep') // Step 3 to 4
        ->call('nextStep') // Step 4 to 5
        ->set('selectedColors', ['White', 'Black']) // 2 variants but only 1 barcode
        ->set('selectedWidths', ['120cm'])
        ->set('selectedDrops', ['160cm'])
        ->call('generateVariantMatrix')
        ->call('nextStep') // Step 5 to 6
        ->set('assignBarcodes', true)
        ->set('barcodeAssignmentMethod', 'auto')
        ->call('nextStep') // This should fail validation
        ->assertHasErrors(['barcodes']); // Should have validation error
});
