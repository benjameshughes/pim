<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Livewire\Pim\Products\Variants\VariantIndex;
use Livewire\Livewire;

describe('VariantIndex Rendering Tests', function () {
    beforeEach(function () {
        $this->product = Product::factory()->create(['name' => 'Test Product']);
        
        // Create variants with different scenarios
        $this->variantWithoutImages = ProductVariant::factory()->create([
            'product_id' => $this->product->id,
            'sku' => 'TEST-001',
            'color' => 'Red',
            'size' => 'M',
            'status' => 'active'
        ]);
    });

    it('can render VariantIndex component without errors', function () {
        Livewire::test(VariantIndex::class)
            ->assertOk()
            ->assertSee('Product Variants');
    });

    it('can render VariantIndex with specific product filter', function () {
        Livewire::test(VariantIndex::class, ['product' => $this->product])
            ->assertOk()
            ->assertSee($this->product->name . ' Variants')
            ->assertSee('TEST-001');
    });

    it('handles variants without images gracefully', function () {
        Livewire::test(VariantIndex::class, ['product' => $this->product])
            ->assertOk()
            ->assertSee('TEST-001')
            ->assertSee('Active');
    });

    it('can search variants by SKU', function () {
        ProductVariant::factory()->create([
            'product_id' => $this->product->id,
            'sku' => 'OTHER-002'
        ]);

        Livewire::test(VariantIndex::class, ['product' => $this->product])
            ->assertSee('TEST-001')
            ->assertSee('OTHER-002')
            ->set('search', 'TEST')
            ->assertSee('TEST-001');
    });

    it('can filter variants by status', function () {
        ProductVariant::factory()->create([
            'product_id' => $this->product->id,
            'sku' => 'INACTIVE-003',
            'status' => 'inactive'
        ]);

        Livewire::test(VariantIndex::class, ['product' => $this->product])
            ->set('statusFilter', 'active')
            ->assertSee('TEST-001')
            ->assertDontSee('INACTIVE-003');
    });

    it('shows empty state when no variants exist', function () {
        $emptyProduct = Product::factory()->create(['name' => 'Empty Product']);
        
        Livewire::test(VariantIndex::class, ['product' => $emptyProduct])
            ->assertOk()
            ->assertSee('No Variants');
    });
});