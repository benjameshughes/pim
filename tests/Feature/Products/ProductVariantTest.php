<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Barcode;
use App\Models\Pricing;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Product Variants', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->product = Product::factory()->create();
    });

    it('can create a variant', function () {
        $variantData = [
            'product_id' => $this->product->id,
            'sku' => 'VARIANT-001',
            'size' => 'Large',
            'color' => 'Red',
            'material' => 'Cotton',
        ];

        $component = Livewire::test('variants.variant-form', ['product' => $this->product])
            ->set('variant', $variantData)
            ->call('save');

        expect(ProductVariant::count())->toBe(1);
        $variant = ProductVariant::first();
        expect($variant->sku)->toBe('VARIANT-001');
        expect($variant->size)->toBe('Large');
    });

    it('can create variant with barcode', function () {
        $barcode = Barcode::factory()->create();

        $component = Livewire::test('products.variant-create', ['product' => $this->product])
            ->set('variant.sku', 'VARIANT-001')
            ->set('selectedBarcodeId', $barcode->id)
            ->call('save');

        $variant = ProductVariant::first();
        expect($variant->barcode_id)->toBe($barcode->id);
        expect($variant->barcode->code)->toBe($barcode->code);
    });

    it('validates unique SKU within product', function () {
        ProductVariant::factory()->create([
            'product_id' => $this->product->id,
            'sku' => 'DUPLICATE-SKU',
        ]);

        $component = Livewire::test('variants.variant-form', ['product' => $this->product])
            ->set('variant', [
                'product_id' => $this->product->id,
                'sku' => 'DUPLICATE-SKU',
            ])
            ->call('save')
            ->assertHasErrors(['variant.sku']);
    });

    it('can update variant attributes', function () {
        $variant = ProductVariant::factory()->create([
            'product_id' => $this->product->id,
            'size' => 'Small',
        ]);

        $component = Livewire::test('variants.variant-form', [
            'product' => $this->product,
            'variant' => $variant,
        ])
            ->set('variant.size', 'Large')
            ->call('save');

        $variant->refresh();
        expect($variant->size)->toBe('Large');
    });

    it('can delete variant', function () {
        $variant = ProductVariant::factory()->create(['product_id' => $this->product->id]);

        $component = Livewire::test('variants.variant-show', ['variant' => $variant])
            ->call('deleteVariant');

        expect(ProductVariant::count())->toBe(0);
    });

    it('can manage variant pricing', function () {
        $variant = ProductVariant::factory()->create(['product_id' => $this->product->id]);

        $pricingData = [
            'cost_price' => 10.00,
            'wholesale_price' => 15.00,
            'retail_price' => 25.00,
        ];

        $component = Livewire::test('pricing.pricing-form', ['variant' => $variant])
            ->set('pricing', $pricingData)
            ->call('save');

        expect(Pricing::count())->toBe(1);
        $pricing = Pricing::first();
        expect($pricing->cost_price)->toBe(10.00);
        expect($pricing->retail_price)->toBe(25.00);
    });

    it('can generate multiple variants', function () {
        $attributes = [
            'sizes' => ['S', 'M', 'L'],
            'colors' => ['Red', 'Blue'],
        ];

        $component = Livewire::test('products.wizard.variant-generation-step', ['product' => $this->product])
            ->set('attributes', $attributes)
            ->call('generateVariants');

        expect(ProductVariant::count())->toBe(6); // 3 sizes × 2 colors
        
        expect(ProductVariant::where('size', 'S')->where('color', 'Red')->exists())->toBeTrue();
        expect(ProductVariant::where('size', 'L')->where('color', 'Blue')->exists())->toBeTrue();
    });
});