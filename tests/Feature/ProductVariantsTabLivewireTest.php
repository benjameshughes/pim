<?php

use App\Livewire\Products\ProductVariantsTab;
use App\Models\Barcode;
use App\Models\Product;
use App\Models\ProductVariant;
use Livewire\Livewire;

describe('ProductVariantsTab Livewire Component', function () {
    beforeEach(function () {
        $this->product = Product::factory()->create([
            'name' => 'Test Product',
            'parent_sku' => 'TEST123',
            'status' => 'active',
        ]);

        $this->variants = collect([
            ProductVariant::factory()->create([
                'product_id' => $this->product->id,
                'sku' => 'TEST123-RED',
                'title' => 'Test Product - Red',
                'color' => 'Red',
                'width' => 100,
                'drop' => 150,
                'price' => 25.99,
                'status' => 'active',
            ]),
            ProductVariant::factory()->create([
                'product_id' => $this->product->id,
                'sku' => 'TEST123-BLUE',
                'title' => 'Test Product - Blue',
                'color' => 'Blue',
                'width' => 120,
                'drop' => 180,
                'price' => 27.99,
                'status' => 'active',
            ]),
        ]);
    });

    test('component mounts with correct product data', function () {
        Livewire::test(ProductVariantsTab::class, ['product' => $this->product])
            ->assertSet('product.id', $this->product->id)
            ->assertSet('product.name', 'Test Product');
    });

    test('eager loads variants with barcode relationship', function () {
        $component = Livewire::test(ProductVariantsTab::class, ['product' => $this->product]);

        $product = $component->get('product');

        expect($product->relationLoaded('variants'))->toBeTrue();

        // Check that barcode relationship is available for variants
        $product->variants->each(function ($variant) {
            expect($variant->relationLoaded('barcode'))->toBeTrue();
        });
    });

    test('component renders variant table correctly', function () {
        Livewire::test(ProductVariantsTab::class, ['product' => $this->product])
            ->assertStatus(200)
            ->assertSee('TEST123-RED')
            ->assertSee('TEST123-BLUE')
            ->assertSee('Red')
            ->assertSee('Blue');
    });

    test('displays variants with barcodes correctly', function () {
        // Assign barcode to first variant
        $barcode = Barcode::create([
            'barcode' => '1234567890123',
            'sku' => $this->variants[0]->sku,
            'title' => $this->variants[0]->title,
            'product_variant_id' => $this->variants[0]->id,
            'is_assigned' => true,
        ]);

        Livewire::test(ProductVariantsTab::class, ['product' => $this->product->load('variants.barcode')])
            ->assertSee('1234567890123') // Should show the barcode
            ->assertSee('None'); // Should show "None" for variant without barcode
    });

    test('displays variants without barcodes correctly', function () {
        Livewire::test(ProductVariantsTab::class, ['product' => $this->product])
            ->assertSee('None') // Should show "None" for variants without barcodes
            ->assertDontSee('1234567890123'); // Should not see any specific barcode
    });

    test('shows correct variant information', function () {
        Livewire::test(ProductVariantsTab::class, ['product' => $this->product])
            ->assertSee('100') // Width of red variant
            ->assertSee('150') // Drop of red variant
            ->assertSee('120') // Width of blue variant
            ->assertSee('180') // Drop of blue variant
            ->assertSee('25.99') // Price of red variant
            ->assertSee('27.99'); // Price of blue variant
    });

    test('handles products with no variants', function () {
        $emptyProduct = Product::factory()->create([
            'name' => 'Empty Product',
            'parent_sku' => 'EMPTY123',
        ]);

        Livewire::test(ProductVariantsTab::class, ['product' => $emptyProduct])
            ->assertStatus(200)
            ->assertSee('Empty Product');
    });

    test('handles variants with mixed barcode states', function () {
        // First variant has barcode
        Barcode::create([
            'barcode' => '1111111111111',
            'sku' => $this->variants[0]->sku,
            'title' => $this->variants[0]->title,
            'product_variant_id' => $this->variants[0]->id,
            'is_assigned' => true,
        ]);

        // Second variant has no barcode (None)

        Livewire::test(ProductVariantsTab::class, ['product' => $this->product->load('variants.barcode')])
            ->assertSee('1111111111111') // First variant's barcode
            ->assertSee('None'); // Second variant without barcode
    });

    test('barcode badges show correct colors', function () {
        // Create variant with barcode
        Barcode::create([
            'barcode' => '2222222222222',
            'sku' => $this->variants[0]->sku,
            'title' => $this->variants[0]->title,
            'product_variant_id' => $this->variants[0]->id,
            'is_assigned' => true,
        ]);

        $component = Livewire::test(ProductVariantsTab::class, ['product' => $this->product->load('variants.barcode')]);

        $html = $component->render();

        // Should contain green badge for variant with barcode
        expect($html)->toContain('color="green"');

        // Should contain gray badge for variant without barcode
        expect($html)->toContain('color="gray"');
    });

    test('displays all variant attributes correctly', function () {
        Livewire::test(ProductVariantsTab::class, ['product' => $this->product])
            ->assertSee('Red') // Color
            ->assertSee('Blue') // Color
            ->assertSee('TEST123-RED') // SKU
            ->assertSee('TEST123-BLUE') // SKU
            ->assertSee('Test Product - Red') // Title
            ->assertSee('Test Product - Blue'); // Title
    });

    test('component handles product updates correctly', function () {
        $component = Livewire::test(ProductVariantsTab::class, ['product' => $this->product]);

        // Update product name
        $this->product->update(['name' => 'Updated Product Name']);

        // Component should handle the updated product
        expect($component->get('product.name'))->toBe('Test Product'); // Original name in component
    });

    test('renders without PHP errors or warnings', function () {
        // This test ensures no PHP errors occur during rendering
        $component = Livewire::test(ProductVariantsTab::class, ['product' => $this->product]);

        expect($component->payload['serverMemo']['errors'])->toBeEmpty();
    });

    test('component uses correct view file', function () {
        $component = new ProductVariantsTab;
        $component->mount($this->product);

        expect($component->render()->name())->toBe('livewire.products.product-variants-tab');
    });

    test('component handles large number of variants', function () {
        // Create many variants
        ProductVariant::factory(50)->create([
            'product_id' => $this->product->id,
            'status' => 'active',
        ]);

        Livewire::test(ProductVariantsTab::class, ['product' => $this->product->load('variants.barcode')])
            ->assertStatus(200);

        // Should load all variants
        expect($this->product->fresh()->variants()->count())->toBe(52); // 2 original + 50 new
    });

    test('eager loading prevents N+1 queries', function () {
        // Add barcodes to variants
        $this->variants->each(function ($variant, $index) {
            Barcode::create([
                'barcode' => '100000000000'.($index + 1),
                'sku' => $variant->sku,
                'title' => $variant->title,
                'product_variant_id' => $variant->id,
                'is_assigned' => true,
            ]);
        });

        // Test that component mounts and renders without additional queries
        $component = Livewire::test(ProductVariantsTab::class, ['product' => $this->product]);

        // Verify relationships are loaded
        $product = $component->get('product');
        expect($product->relationLoaded('variants'))->toBeTrue();

        $product->variants->each(function ($variant) {
            expect($variant->relationLoaded('barcode'))->toBeTrue();
        });
    });
});
