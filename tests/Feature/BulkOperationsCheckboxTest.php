<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BulkOperationsCheckboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create and authenticate a user
        $user = User::factory()->create();
        $this->actingAs($user);
    }

    public function test_can_select_individual_variants()
    {
        // Create test data
        $product = Product::factory()->create();
        $variant1 = ProductVariant::factory()->create(['product_id' => $product->id]);
        $variant2 = ProductVariant::factory()->create(['product_id' => $product->id]);

        $component = Livewire::test(\App\Livewire\Operations\BulkOperationsOverview::class);

        // Select first variant
        $component->set('selectedVariants', [$variant1->id]);

        $component->assertSet('selectedVariants', [$variant1->id]);

        // Add second variant
        $component->set('selectedVariants', [$variant1->id, $variant2->id]);

        $component->assertSet('selectedVariants', [$variant1->id, $variant2->id]);
    }

    public function test_selecting_all_product_variants_selects_product()
    {
        // Create test data
        $product = Product::factory()->create();
        $variant1 = ProductVariant::factory()->create(['product_id' => $product->id]);
        $variant2 = ProductVariant::factory()->create(['product_id' => $product->id]);

        $component = Livewire::test(\App\Livewire\Operations\BulkOperationsOverview::class);

        // Select all variants of the product
        $component->set('selectedVariants', [$variant1->id, $variant2->id]);

        // Product should be automatically selected
        $component->assertSet('selectedProducts', [$product->id]);
    }

    public function test_selecting_product_selects_all_variants()
    {
        // Create test data
        $product = Product::factory()->create();
        $variant1 = ProductVariant::factory()->create(['product_id' => $product->id]);
        $variant2 = ProductVariant::factory()->create(['product_id' => $product->id]);

        $component = Livewire::test(\App\Livewire\Operations\BulkOperationsOverview::class);

        // Select the product
        $component->set('selectedProducts', [$product->id]);

        // All variants should be selected (order doesn't matter)
        $selectedVariants = $component->get('selectedVariants');
        $this->assertEqualsCanonicalizing([$variant1->id, $variant2->id], $selectedVariants);
    }

    public function test_deselecting_variant_deselects_product()
    {
        // Create test data
        $product = Product::factory()->create();
        $variant1 = ProductVariant::factory()->create(['product_id' => $product->id]);
        $variant2 = ProductVariant::factory()->create(['product_id' => $product->id]);

        $component = Livewire::test(\App\Livewire\Operations\BulkOperationsOverview::class);

        // Select all variants first (product should be selected too)
        $component->set('selectedVariants', [$variant1->id, $variant2->id]);
        $component->assertSet('selectedProducts', [$product->id]);

        // Deselect one variant
        $component->set('selectedVariants', [$variant1->id]);

        // Product should no longer be selected
        $component->assertSet('selectedProducts', []);
    }

    public function test_select_all_checkbox_behavior()
    {
        // Create test data
        $product = Product::factory()->create();
        $variant1 = ProductVariant::factory()->create(['product_id' => $product->id]);
        $variant2 = ProductVariant::factory()->create(['product_id' => $product->id]);

        $component = Livewire::test(\App\Livewire\Operations\BulkOperationsOverview::class);

        // Set select all to true
        $component->set('selectAll', true);

        // Should select all variants (order doesn't matter)
        $selectedVariants = $component->get('selectedVariants');
        $this->assertEqualsCanonicalizing([$variant1->id, $variant2->id], $selectedVariants);

        // Set select all to false
        $component->set('selectAll', false);

        // Should deselect all variants
        $component->assertSet('selectedVariants', []);
    }
}