<?php

use App\Models\Barcode;
use App\Models\Product;
use App\Models\ProductVariant;

describe('ProductVariant Barcode Relationship', function () {
    beforeEach(function () {
        $this->product = Product::factory()->create([
            'name' => 'Test Product',
            'parent_sku' => 'TEST123',
            'status' => 'active'
        ]);
        
        $this->variant = ProductVariant::factory()->create([
            'product_id' => $this->product->id,
            'sku' => 'TEST123-RED',
            'title' => 'Test Product - Red',
            'color' => 'Red',
            'status' => 'active'
        ]);
    });

    test('variant can have a barcode assigned', function () {
        $barcode = Barcode::create([
            'barcode' => '1234567890123',
            'sku' => $this->variant->sku,
            'title' => $this->variant->title,
            'product_variant_id' => $this->variant->id,
            'is_assigned' => true
        ]);

        expect($this->variant->fresh()->barcode)
            ->toBeInstanceOf(Barcode::class)
            ->and($this->variant->barcode->barcode)
            ->toBe('1234567890123');
    });

    test('variant returns null when no barcode assigned', function () {
        expect($this->variant->barcode)->toBeNull();
    });

    test('barcode belongs to variant', function () {
        $barcode = Barcode::create([
            'barcode' => '1234567890123',
            'sku' => $this->variant->sku,
            'title' => $this->variant->title,
            'product_variant_id' => $this->variant->id,
            'is_assigned' => true
        ]);

        expect($barcode->variant)
            ->toBeInstanceOf(ProductVariant::class)
            ->and($barcode->variant->id)
            ->toBe($this->variant->id);
    });

    test('can eager load barcode with variant', function () {
        Barcode::create([
            'barcode' => '1234567890123',
            'sku' => $this->variant->sku,
            'title' => $this->variant->title,
            'product_variant_id' => $this->variant->id,
            'is_assigned' => true
        ]);

        $variantWithBarcode = ProductVariant::with('barcode')->find($this->variant->id);
        
        expect($variantWithBarcode->barcode)
            ->toBeInstanceOf(Barcode::class)
            ->and($variantWithBarcode->barcode->barcode)
            ->toBe('1234567890123');
    });

    test('can check if variant has barcode using truthy check', function () {
        // Without barcode
        expect((bool) $this->variant->barcode)->toBeFalse();

        // With barcode
        Barcode::create([
            'barcode' => '1234567890123',
            'sku' => $this->variant->sku,
            'title' => $this->variant->title,
            'product_variant_id' => $this->variant->id,
            'is_assigned' => true
        ]);

        expect((bool) $this->variant->fresh()->barcode)->toBeTrue();
    });

    test('product can load variants with barcodes', function () {
        // Create two variants with barcodes
        $variant2 = ProductVariant::factory()->create([
            'product_id' => $this->product->id,
            'sku' => 'TEST123-BLUE',
            'title' => 'Test Product - Blue',
            'color' => 'Blue',
            'status' => 'active'
        ]);

        Barcode::create([
            'barcode' => '1111111111111',
            'sku' => $this->variant->sku,
            'title' => $this->variant->title,
            'product_variant_id' => $this->variant->id,
            'is_assigned' => true
        ]);

        Barcode::create([
            'barcode' => '2222222222222',
            'sku' => $variant2->sku,
            'title' => $variant2->title,
            'product_variant_id' => $variant2->id,
            'is_assigned' => true
        ]);

        $productWithVariants = Product::with('variants.barcode')->find($this->product->id);
        
        expect($productWithVariants->variants)
            ->toHaveCount(2)
            ->and($productWithVariants->variants->filter(fn($v) => $v->barcode))
            ->toHaveCount(2);
    });

    test('barcode relationship uses correct foreign key', function () {
        $barcode = Barcode::create([
            'barcode' => '1234567890123',
            'sku' => $this->variant->sku,
            'title' => $this->variant->title,
            'product_variant_id' => $this->variant->id,
            'is_assigned' => true
        ]);

        // Test the relationship constraint
        $relationshipQuery = $this->variant->barcode();
        
        expect($relationshipQuery->getForeignKeyName())
            ->toBe('product_variant_id')
            ->and($relationshipQuery->getLocalKeyName())
            ->toBe('id');
    });
});