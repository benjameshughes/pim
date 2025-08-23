<?php

use App\Exceptions\ProductWizard\NoVariantsException;
use App\Livewire\ProductWizard;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('Product Wizard Save Functionality', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    it('can save a complete product with variants', function () {
        $component = Livewire::test(ProductWizard::class)
            ->set('name', 'Test Product')
            ->set('parent_sku', '123')
            ->set('status', 'active')
            ->set('brand', 'Test Brand')
            ->call('nextStep') // Go to step 2
            ->set('new_color', 'Red')
            ->call('addColor')
            ->set('new_width', '30')
            ->call('addWidth');
            
        // Debug: Check if variants were generated
        $variants = $component->get('generated_variants');
        expect($variants)->not->toBeEmpty();
        expect($variants[0]['sku'])->toBe('123-001');
        
        $component->call('nextStep') // Go to step 3 (images)
            ->call('nextStep') // Go to step 4 (pricing)
            ->call('saveProduct');

        // Check that product was created
        $product = Product::where('parent_sku', '123')->first();
        expect($product)->not->toBeNull();
        expect($product->name)->toBe('Test Product');
        expect($product->status->value)->toBe('active');
        
        // Check that variants were created
        expect($product->variants)->toHaveCount(1);
        expect($product->variants->first()->color)->toBe('Red');
        expect($product->variants->first()->width)->toBe(30);
        
        // Check that brand attribute was set
        $brandAttribute = \App\Models\ProductAttribute::where('product_id', $product->id)
            ->whereHas('attributeDefinition', fn($q) => $q->where('key', 'brand'))
            ->first();
        expect($brandAttribute)->not->toBeNull();
        expect($brandAttribute->value)->toBe('Test Brand');
    });

    it('shows proper error messages when save fails', function () {
        Livewire::test(ProductWizard::class)
            ->set('name', 'ab') // Too short
            ->set('parent_sku', '') // Required
            ->call('saveProduct')
            ->assertHasErrors(['name', 'parent_sku']);
    });

    it('prevents saving without any variants', function () {
        expect(function () {
            Livewire::test(ProductWizard::class)
                ->set('name', 'Test Product')
                ->set('parent_sku', '123')
                ->set('status', 'active')
                ->call('saveProduct');
        })->toThrow(NoVariantsException::class, 'Cannot save product without variants. Please add at least one color, width, or drop to generate variants.');
    });
});