<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BulkOperationsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create and authenticate a user
        $user = User::factory()->create();
        $this->actingAs($user);
    }

    public function test_complete_bulk_operations_workflow()
    {
        // Create test data
        $product1 = Product::factory()->create(['name' => 'Test Product 1']);
        $product2 = Product::factory()->create(['name' => 'Test Product 2']);

        $variant1 = ProductVariant::factory()->create(['product_id' => $product1->id]);
        $variant2 = ProductVariant::factory()->create(['product_id' => $product1->id]);
        $variant3 = ProductVariant::factory()->create(['product_id' => $product2->id]);

        // Step 1: Go to overview and select variants
        $overviewComponent = Livewire::test(\App\Livewire\Operations\BulkOperationsOverview::class);

        // Select some variants
        $overviewComponent->set('selectedVariants', [$variant1->id, $variant2->id]);

        // Verify selection was stored in session
        $this->assertEquals([$variant1->id, $variant2->id], session('bulk_operations.selected_variants'));

        // Step 2: Go to attributes tab (should inherit the selection)
        $attributesComponent = Livewire::test(\App\Livewire\Operations\BulkOperationsAttributes::class);

        // Debug: Check what's in the session and component
        $sessionVariants = session('bulk_operations.selected_variants');
        $this->assertEquals([$variant1->id, $variant2->id], $sessionVariants, 'Session should contain selected variants');

        // Check that the component can see the selected variants
        $selectedVariantsCount = $attributesComponent->get('selectedVariantsCount');
        $this->assertEquals(2, $selectedVariantsCount, 'Component should see 2 selected variants');

        // Also test the computed property for variants
        $selectedVariantsFromProperty = $attributesComponent->get('selectedVariants');
        $this->assertEquals([$variant1->id, $variant2->id], $selectedVariantsFromProperty, 'Computed property should return variant IDs');

        // Step 3: Apply a bulk attribute
        $attributesComponent->set('bulkAttributeKey', 'material');
        $attributesComponent->set('bulkAttributeValue', 'Cotton');
        $attributesComponent->set('bulkAttributeType', 'product');

        $attributesComponent->call('applyBulkAttribute');

        // Verify attributes were created
        $this->assertDatabaseHas('product_attributes', [
            'product_id' => $product1->id,  // Both variants belong to this product
            'attribute_key' => 'material',
            'attribute_value' => 'Cotton',
        ]);

        // Should not have created attribute for product2 since variant3 wasn't selected
        $this->assertDatabaseMissing('product_attributes', [
            'product_id' => $product2->id,
            'attribute_key' => 'material',
        ]);
    }

    public function test_debug_selected_variants_state()
    {
        // Create test data
        $product = Product::factory()->create();
        $variant1 = ProductVariant::factory()->create(['product_id' => $product->id]);
        $variant2 = ProductVariant::factory()->create(['product_id' => $product->id]);

        // Manually set session state (simulating selection from overview)
        session(['bulk_operations.selected_variants' => [$variant1->id, $variant2->id]]);

        $component = Livewire::test(\App\Livewire\Operations\BulkOperationsAttributes::class);

        // Check that component sees the variants
        $this->assertEquals(2, $component->get('selectedVariantsCount'));

        // Call debug method
        $component->call('debugState');

        // Should show debug message
        $this->assertEquals(
            'Debug: Selected variants count = 2. IDs: '.$variant1->id.', '.$variant2->id,
            session('message')
        );
    }
}
