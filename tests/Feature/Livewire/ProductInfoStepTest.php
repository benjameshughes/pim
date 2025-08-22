<?php

use App\Livewire\Products\Wizard\ProductInfoStep;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('Product Info Step Component', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    it('can render the product info step component', function () {
        $component = Livewire::test(ProductInfoStep::class)
            ->assertStatus(200);
            
        expect($component)->not->toBeNull();
    });

    it('initializes with default values', function () {
        Livewire::test(ProductInfoStep::class)
            ->assertSet('name', '')
            ->assertSet('parent_sku', '')
            ->assertSet('description', '')
            ->assertSet('status', 'active')
            ->assertSet('image_url', '')
            ->assertSet('currentStep', 1)
            ->assertSet('isActive', false)
            ->assertSet('isEditMode', false);
    });

    it('loads existing step data on mount', function () {
        $stepData = [
            'name' => 'Test Product',
            'parent_sku' => '123', // Valid 3-digit format
            'description' => 'Test Description',
            'status' => 'draft',
            'image_url' => 'https://example.com/image.jpg'
        ];

        Livewire::test(ProductInfoStep::class, [
            'stepData' => $stepData,
            'isActive' => true,
            'currentStep' => 1,
            'isEditMode' => false
        ])
            ->assertSet('name', 'Test Product')
            ->assertSet('parent_sku', '123')
            ->assertSet('description', 'Test Description')
            ->assertSet('status', 'draft')
            ->assertSet('image_url', 'https://example.com/image.jpg')
            ->assertSet('isActive', true);
    });

    it('validates required name field', function () {
        $component = Livewire::test(ProductInfoStep::class)
            ->set('name', '') // Empty name
            ->set('parent_sku', '123') // Valid parent_sku needed for test
            ->call('completeStep');

        $component->assertHasErrors(['name']);
    });

    it('validates name minimum length', function () {
        $component = Livewire::test(ProductInfoStep::class)
            ->set('name', 'AB') // Too short (min 3 chars)
            ->set('parent_sku', '123') // Valid parent_sku needed for test
            ->call('completeStep');

        $component->assertHasErrors(['name']);
    });

    it('accepts valid name', function () {
        $component = Livewire::test(ProductInfoStep::class)
            ->set('name', 'Valid Product Name')
            ->set('parent_sku', '123') // Valid parent_sku needed for test
            ->call('completeStep');

        $component->assertHasNoErrors(['name']);
    });

    it('validates status field with valid options', function () {
        $validStatuses = ['draft', 'active', 'inactive', 'archived'];
        
        foreach ($validStatuses as $status) {
            $component = Livewire::test(ProductInfoStep::class)
                ->set('name', 'Test Product')
                ->set('parent_sku', '123') // Valid parent_sku needed for test
                ->set('status', $status)
                ->call('completeStep');

            $component->assertHasNoErrors(['status']);
        }
    });

    it('rejects invalid status', function () {
        $component = Livewire::test(ProductInfoStep::class)
            ->set('name', 'Test Product')
            ->set('parent_sku', '123') // Valid parent_sku needed for test
            ->set('status', 'invalid_status')
            ->call('completeStep');

        $component->assertHasErrors(['status']);
    });

    it('validates parent_sku format - requires 3 digits', function () {
        $component = Livewire::test(ProductInfoStep::class)
            ->set('name', 'Test Product')
            ->set('parent_sku', 'X') // Invalid format (must be 3 digits)
            ->call('completeStep');

        $component->assertHasErrors(['parent_sku']);
    });

    it('validates parent_sku is required', function () {
        $component = Livewire::test(ProductInfoStep::class)
            ->set('name', 'Test Product')
            ->set('parent_sku', '') // Empty is NOT allowed
            ->call('completeStep');

        $component->assertHasErrors(['parent_sku']);
    });

    it('validates image_url format when provided', function () {
        $component = Livewire::test(ProductInfoStep::class)
            ->set('name', 'Test Product')
            ->set('parent_sku', '123') // Valid parent_sku needed for test
            ->set('image_url', 'not-a-url') // Invalid URL
            ->call('completeStep');

        $component->assertHasErrors(['image_url']);
    });

    it('accepts valid image_url', function () {
        $component = Livewire::test(ProductInfoStep::class)
            ->set('name', 'Test Product')
            ->set('parent_sku', '123') // Valid parent_sku needed for test
            ->set('image_url', 'https://example.com/image.jpg')
            ->call('completeStep');

        $component->assertHasNoErrors(['image_url']);
    });

    it('accepts empty image_url as optional field', function () {
        $component = Livewire::test(ProductInfoStep::class)
            ->set('name', 'Test Product')
            ->set('parent_sku', '123') // Valid parent_sku needed for test
            ->set('image_url', '')
            ->call('completeStep');

        $component->assertHasNoErrors(['image_url']);
    });

    it('emits step-completed event with valid data', function () {
        Livewire::test(ProductInfoStep::class)
            ->set('name', 'Test Product')
            ->set('parent_sku', '123') // Valid 3-digit format
            ->set('description', 'Test Description')
            ->set('status', 'active')
            ->set('image_url', 'https://example.com/image.jpg')
            ->call('completeStep')
            ->assertDispatched('step-completed', 1, [
                'name' => 'Test Product',
                'parent_sku' => '123', 
                'description' => 'Test Description',
                'status' => 'active',
                'image_url' => 'https://example.com/image.jpg'
            ]);
    });

    it('emits product-info-updated event on field changes', function () {
        Livewire::test(ProductInfoStep::class)
            ->set('name', 'Updated Product')
            ->assertDispatched('product-info-updated');
    });

    it('validates individual fields on update', function () {
        $component = Livewire::test(ProductInfoStep::class)
            ->set('name', 'AB'); // Too short

        $component->assertHasErrors(['name']);
    });

    it('clears field errors when validation passes', function () {
        $component = Livewire::test(ProductInfoStep::class)
            ->set('name', 'AB'); // Invalid first

        $component->assertHasErrors(['name']);

        $component->set('name', 'Valid Name'); // Now valid
        $component->assertHasNoErrors(['name']);
    });

    it('provides correct status options', function () {
        $component = Livewire::test(ProductInfoStep::class);
        $statusOptions = $component->instance()->getStatusOptions();

        expect($statusOptions)->toMatchArray([
            'active' => '✅ Active - Ready for sale',
            'draft' => '📝 Draft - Work in progress',
            'inactive' => '⏸️ Inactive - Hidden from sale',
            'archived' => '📦 Archived - Discontinued',
        ]);
    });

    it('handles edit mode with existing product', function () {
        $product = Product::factory()->create([
            'name' => 'Existing Product',
            'parent_sku' => '456', // Valid 3-digit format
            'description' => 'Existing Description',
            'status' => 'active'
        ]);

        $productData = [
            'id' => $product->id,
            'name' => $product->name,
            'parent_sku' => $product->parent_sku,
            'description' => $product->description,
            'status' => $product->status->value
        ];

        Livewire::test(ProductInfoStep::class, [
            'stepData' => $productData,
            'isEditMode' => true,
            'productData' => $productData
        ])
            ->assertSet('name', 'Existing Product')
            ->assertSet('parent_sku', '456')
            ->assertSet('description', 'Existing Description')
            ->assertSet('status', 'active')
            ->assertSet('isEditMode', true);
    });

    it('shows validation errors in component', function () {
        Livewire::test(ProductInfoStep::class)
            ->assertSee('Product Information')
            ->assertSee('Product Name *')
            ->assertSee('Parent SKU')
            ->assertSee('Product Status *')
            ->assertSee('Description')
            ->assertSee('Product Image URL');
    });

    it('displays preview when name is provided', function () {
        Livewire::test(ProductInfoStep::class)
            ->set('name', 'Test Product')
            ->set('parent_sku', '123') // Valid 3-digit format
            ->set('description', 'Test Description')
            ->assertSee('Preview')
            ->assertSee('Test Product')
            ->assertSee('SKU: 123')
            ->assertSee('Test Description');
    });

    it('validates parent SKU format - accepts valid 3-digit numbers', function () {
        $validSkus = ['001', '123', '999'];
        
        foreach ($validSkus as $sku) {
            $component = Livewire::test(ProductInfoStep::class)
                ->set('name', 'Test Product')
                ->set('parent_sku', $sku)
                ->call('completeStep');

            $component->assertHasNoErrors(['parent_sku']);
        }
    });

    it('validates parent SKU format - rejects invalid formats', function () {
        $invalidSkus = ['1', '12', '1234', 'abc', '12a', 'AB1'];
        
        foreach ($invalidSkus as $sku) {
            $component = Livewire::test(ProductInfoStep::class)
                ->set('name', 'Test Product')
                ->set('parent_sku', $sku)
                ->call('completeStep');

            $component->assertHasErrors(['parent_sku']);
        }
    });

    it('shows SKU suggestions when validation fails', function () {
        // Create existing product to trigger duplicate validation
        Product::factory()->create(['parent_sku' => '123']);
        
        $component = Livewire::test(ProductInfoStep::class)
            ->set('parent_sku', '123'); // This should trigger duplicate validation
        
        // The component should show suggestions
        expect($component->get('skuSuggestions'))->not->toBeEmpty();
    });

    it('allows using suggested SKU', function () {
        $component = Livewire::test(ProductInfoStep::class)
            ->call('useSuggestedSku', '456');
        
        expect($component->get('parent_sku'))->toBe('456');
        expect($component->get('skuSuggestions'))->toBeEmpty();
    });

    it('handles complete step flow without errors', function () {
        $component = Livewire::test(ProductInfoStep::class)
            ->set('name', 'Complete Test Product')
            ->set('parent_sku', '123') // Valid 3-digit format
            ->set('description', 'Complete test description')
            ->set('status', 'active')
            ->set('image_url', 'https://example.com/complete.jpg')
            ->call('completeStep');

        // Should have no validation errors
        $component->assertHasNoErrors();
        
        // Should dispatch step completed event
        $component->assertDispatched('step-completed', 1);
    });
});