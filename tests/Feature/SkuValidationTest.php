<?php

use App\Models\Product;
use App\Models\User;
use App\Rules\ParentSkuRule;
use App\Services\SkuGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('SKU Validation System', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    it('validates parent SKU format correctly', function () {
        $rule = new ParentSkuRule();
        
        // Valid formats
        expect(validator(['sku' => '001'], ['sku' => [$rule]])->passes())->toBeTrue();
        expect(validator(['sku' => '123'], ['sku' => [$rule]])->passes())->toBeTrue();
        expect(validator(['sku' => '999'], ['sku' => [$rule]])->passes())->toBeTrue();
        
        // Invalid formats
        expect(validator(['sku' => '1'], ['sku' => [$rule]])->fails())->toBeTrue();
        expect(validator(['sku' => '12'], ['sku' => [$rule]])->fails())->toBeTrue();
        expect(validator(['sku' => '1234'], ['sku' => [$rule]])->fails())->toBeTrue();
        expect(validator(['sku' => 'abc'], ['sku' => [$rule]])->fails())->toBeTrue();
        expect(validator(['sku' => '12a'], ['sku' => [$rule]])->fails())->toBeTrue();
    });

    it('detects duplicate parent SKUs', function () {
        // Create existing product
        Product::factory()->create(['parent_sku' => '123']);
        
        $rule = new ParentSkuRule();
        
        // Should fail for duplicate
        expect(validator(['sku' => '123'], ['sku' => [$rule]])->fails())->toBeTrue();
        
        // Should pass for different SKU
        expect(validator(['sku' => '124'], ['sku' => [$rule]])->passes())->toBeTrue();
    });

    it('excludes current product from uniqueness check', function () {
        $product = Product::factory()->create(['parent_sku' => '123']);
        
        $rule = new ParentSkuRule($product->id);
        
        // Should pass when excluding the current product
        expect(validator(['sku' => '123'], ['sku' => [$rule]])->passes())->toBeTrue();
    });

    it('generates sequential variant SKUs', function () {
        $service = app(SkuGeneratorService::class);
        
        $variants = $service->generateVariantSkus('123', 3);
        
        expect($variants)->toBe(['123-001', '123-002', '123-003']);
    });

    it('suggests alternative parent SKUs', function () {
        // Create existing products
        Product::factory()->create(['parent_sku' => '123']);
        Product::factory()->create(['parent_sku' => '124']);
        
        $service = app(SkuGeneratorService::class);
        $suggestions = $service->suggestAlternativeParentSkus('123', 3);
        
        expect($suggestions)->toHaveCount(3);
        expect($suggestions)->not->toContain('123');
        expect($suggestions)->not->toContain('124');
        
        // Should be properly formatted 3-digit strings
        foreach ($suggestions as $suggestion) {
            expect($suggestion)->toMatch('/^[0-9]{3}$/');
        }
    });

    it('finds next available variant SKU', function () {
        $product = Product::factory()->create(['parent_sku' => '123']);
        
        // Create some variants using factory
        $product->variants()->create([
            'sku' => '123-001',
            'title' => 'Red Variant',
            'color' => 'Red',
            'width' => 60,
            'price' => 29.99
        ]);
        $product->variants()->create([
            'sku' => '123-003',
            'title' => 'Blue Variant', 
            'color' => 'Blue',
            'width' => 90,
            'price' => 34.99
        ]);
        
        $service = app(SkuGeneratorService::class);
        $nextSku = $service->getNextVariantSku('123');
        
        expect($nextSku)->toBe('123-004');
    });

    it('starts at 001 for first variant', function () {
        $service = app(SkuGeneratorService::class);
        $nextSku = $service->getNextVariantSku('999');
        
        expect($nextSku)->toBe('999-001');
    });
});