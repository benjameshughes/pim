<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BulkOperationsAttributesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create and authenticate a user
        $user = User::factory()->create();
        $this->actingAs($user);
    }

    public function test_can_add_new_product_attribute()
    {
        // Create test data
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        // Set up session state with selected variants
        session(['bulk_operations.selected_variants' => [$variant->id]]);

        $component = Livewire::test(\App\Livewire\Operations\BulkOperationsAttributes::class);

        // Set attribute data
        $component->set('bulkAttributeKey', 'material');
        $component->set('bulkAttributeValue', 'Cotton');
        $component->set('bulkAttributeType', 'product');

        // Apply the attribute
        $component->call('applyBulkAttribute');

        // Check if attribute was created
        $this->assertDatabaseHas('product_attributes', [
            'product_id' => $product->id,
            'attribute_key' => 'material',
            'attribute_value' => 'Cotton',
        ]);

        $component->assertHasNoErrors();
    }

    public function test_can_add_new_variant_attribute()
    {
        // Create test data
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        // Set up session state with selected variants
        session(['bulk_operations.selected_variants' => [$variant->id]]);

        $component = Livewire::test(\App\Livewire\Operations\BulkOperationsAttributes::class);

        // Set attribute data
        $component->set('bulkAttributeKey', 'size');
        $component->set('bulkAttributeValue', 'Large');
        $component->set('bulkAttributeType', 'variant');

        // Apply the attribute
        $component->call('applyBulkAttribute');

        // Check if attribute was created
        $this->assertDatabaseHas('variant_attributes', [
            'variant_id' => $variant->id,
            'attribute_key' => 'size',
            'attribute_value' => 'Large',
        ]);

        $component->assertHasNoErrors();
    }

    public function test_can_update_existing_attribute()
    {
        // Create test data
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        // Create existing attribute
        ProductAttribute::create([
            'product_id' => $product->id,
            'attribute_key' => 'material',
            'attribute_value' => 'Polyester',
            'data_type' => 'string',
            'category' => 'general',
        ]);

        // Set up session state with selected variants
        session(['bulk_operations.selected_variants' => [$variant->id]]);

        $component = Livewire::test(\App\Livewire\Operations\BulkOperationsAttributes::class);

        // Update existing attribute
        $component->set('selectedExistingAttribute', 'product:material');
        $component->set('updateAttributeValue', 'Cotton');

        // Apply the update
        $component->call('updateExistingAttribute');

        // Check if attribute was updated
        $this->assertDatabaseHas('product_attributes', [
            'product_id' => $product->id,
            'attribute_key' => 'material',
            'attribute_value' => 'Cotton',
        ]);

        $component->assertHasNoErrors();
    }

    public function test_shows_error_when_no_variants_selected()
    {
        $component = Livewire::test(\App\Livewire\Operations\BulkOperationsAttributes::class);

        // Try to apply attribute without selecting variants
        $component->set('bulkAttributeKey', 'material');
        $component->set('bulkAttributeValue', 'Cotton');
        $component->call('applyBulkAttribute');

        // Should show error message
        $component->assertHasErrors(); // Might not work with flash messages
        // Let's check the response contains the error message
        $component->assertSee('Please select variants from the Overview tab first.');
    }

    public function test_shows_error_when_missing_required_fields()
    {
        // Create test data and set session state
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        session(['bulk_operations.selected_variants' => [$variant->id]]);

        $component = Livewire::test(\App\Livewire\Operations\BulkOperationsAttributes::class);

        // Try to apply attribute without key
        $component->set('bulkAttributeValue', 'Cotton');
        $component->call('applyBulkAttribute');

        $component->assertSee('Please provide both attribute key and value.');

        // Try to apply attribute without value
        $component->set('bulkAttributeKey', 'material');
        $component->set('bulkAttributeValue', '');
        $component->call('applyBulkAttribute');

        $component->assertSee('Please provide both attribute key and value.');
    }
}
