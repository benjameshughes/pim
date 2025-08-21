<?php

use App\Livewire\Products\Wizard\ProductInfoStep;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('ProductInfoStep Component', function () {
    it('renders successfully with default state', function () {
        Livewire::test(ProductInfoStep::class)
            ->assertSee('Product Information')
            ->assertSee('Product Name')
            ->assertSet('name', '')
            ->assertSet('status', 'active')
            ->assertSet('isActive', false)
            ->assertSet('currentStep', 1);
    });

    it('initializes with provided step data', function () {
        $stepData = [
            'name' => 'Test Product',
            'parent_sku' => 'TEST-001',
            'description' => 'Test description',
            'status' => 'draft',
            'image_url' => 'https://example.com/image.jpg',
        ];

        Livewire::test(ProductInfoStep::class, [
            'stepData' => $stepData,
            'isActive' => true,
            'currentStep' => 1,
        ])
            ->assertSet('name', 'Test Product')
            ->assertSet('parent_sku', 'TEST-001')
            ->assertSet('description', 'Test description')
            ->assertSet('status', 'draft')
            ->assertSet('image_url', 'https://example.com/image.jpg')
            ->assertSet('isActive', true);
    });

    it('validates required fields on complete step', function () {
        Livewire::test(ProductInfoStep::class)
            ->call('completeStep')
            ->assertSet('errors', ['name' => 'Product name is required'])
            ->assertNotDispatched('step-completed');
    });

    it('completes step with valid data', function () {
        Livewire::test(ProductInfoStep::class)
            ->set('name', 'Valid Product Name')
            ->set('status', 'active')
            ->call('completeStep')
            ->assertSet('errors', [])
            ->assertDispatched('step-completed', 1, [
                'name' => 'Valid Product Name',
                'parent_sku' => '',
                'description' => '',
                'status' => 'active',
                'image_url' => '',
            ]);
    });

    it('validates field updates in real-time', function () {
        Livewire::test(ProductInfoStep::class)
            ->set('name', 'Va') // Too short
            ->assertHasErrors(['name'])
            ->set('name', 'Valid Name') // Now valid
            ->assertHasNoErrors(['name']);
    });

    it('validates parent SKU length', function () {
        Livewire::test(ProductInfoStep::class)
            ->set('name', 'Valid Product')
            ->set('parent_sku', 'A') // Too short
            ->call('completeStep')
            ->assertSet('errors', ['parent_sku' => 'Parent SKU must be at least 2 characters']);
    });

    it('validates image URL format', function () {
        Livewire::test(ProductInfoStep::class)
            ->set('name', 'Valid Product')
            ->set('image_url', 'not-a-url')
            ->call('completeStep')
            ->assertSet('errors', ['image_url' => 'Image URL must be a valid URL']);
    });

    it('validates status values', function () {
        Livewire::test(ProductInfoStep::class)
            ->set('name', 'Valid Product')
            ->set('status', 'invalid_status')
            ->call('completeStep')
            ->assertSet('errors', ['status' => 'Invalid product status']);
    });

    it('accepts all valid status values', function () {
        $validStatuses = ['active', 'draft', 'inactive', 'archived'];

        foreach ($validStatuses as $status) {
            Livewire::test(ProductInfoStep::class)
                ->set('name', 'Valid Product')
                ->set('status', $status)
                ->call('completeStep')
                ->assertNotSet('errors.status');
        }
    });

    it('emits data updates on field changes', function () {
        Livewire::test(ProductInfoStep::class)
            ->set('name', 'Updated Name')
            ->assertDispatched('product-info-updated');
    });

    it('provides correct status options', function () {
        $expectedOptions = [
            'active' => '✅ Active - Ready for sale',
            'draft' => '📝 Draft - Work in progress',
            'inactive' => '⏸️ Inactive - Hidden from sale',
            'archived' => '📦 Archived - Discontinued',
        ];

        $component = Livewire::test(ProductInfoStep::class);
        expect($component->instance()->getStatusOptions())->toBe($expectedOptions);
    });

    it('handles edit mode correctly', function () {
        $product = Product::factory()->create([
            'name' => 'Existing Product',
        ]);

        Livewire::test(ProductInfoStep::class, [
            'isEditMode' => true,
            'product' => $product,
        ])
            ->assertSet('isEditMode', true)
            ->assertSet('product.name', 'Existing Product');
    });

    it('clears field errors when validation passes', function () {
        Livewire::test(ProductInfoStep::class)
            ->set('name', 'A') // Invalid
            ->set('errors.name', 'Name is too short')
            ->set('name', 'Valid Name') // Now valid
            ->assertNotSet('errors.name');
    });

    it('builds correct form data', function () {
        $component = Livewire::test(ProductInfoStep::class)
            ->set('name', 'Test Product')
            ->set('parent_sku', 'TEST-001')
            ->set('description', 'Test Description')
            ->set('status', 'active')
            ->set('image_url', 'https://example.com/image.jpg');

        $formData = $component->instance()->getFormData();

        expect($formData)->toBe([
            'name' => 'Test Product',
            'parent_sku' => 'TEST-001',
            'description' => 'Test Description',
            'status' => 'active',
            'image_url' => 'https://example.com/image.jpg',
        ]);
    });

    it('handles empty optional fields correctly', function () {
        Livewire::test(ProductInfoStep::class)
            ->set('name', 'Minimal Product')
            ->set('status', 'active')
            ->call('completeStep')
            ->assertSet('errors', [])
            ->assertDispatched('step-completed', 1, [
                'name' => 'Minimal Product',
                'parent_sku' => '',
                'description' => '',
                'status' => 'active',
                'image_url' => '',
            ]);
    });

    it('validates on individual field update methods', function () {
        $component = Livewire::test(ProductInfoStep::class);

        // Test each field update method
        $component->call('updatedName');
        $component->call('updatedParentSku');
        $component->call('updatedDescription');
        $component->call('updatedStatus');
        $component->call('updatedImageUrl');

        // Should not throw errors
        expect(true)->toBeTrue();
    });

    it('shows preview when name is provided', function () {
        Livewire::test(ProductInfoStep::class)
            ->set('name', 'Preview Product')
            ->assertSee('Preview')
            ->assertSee('Preview Product');
    });

    it('includes image in preview when provided', function () {
        Livewire::test(ProductInfoStep::class)
            ->set('name', 'Product with Image')
            ->set('image_url', 'https://example.com/image.jpg')
            ->assertSee('Preview')
            ->assertSeeHtml('src="https://example.com/image.jpg"');
    });

    it('shows status badge in preview', function () {
        Livewire::test(ProductInfoStep::class)
            ->set('name', 'Status Product')
            ->set('status', 'draft')
            ->assertSee('📝 Draft - Work in progress');
    });

    it('validates complete form data structure', function () {
        $component = Livewire::test(ProductInfoStep::class)
            ->set('name', 'Complete Product')
            ->set('parent_sku', 'COMP-001')
            ->set('description', 'A complete product')
            ->set('status', 'active')
            ->set('image_url', 'https://example.com/complete.jpg')
            ->call('completeStep');

        $component->assertDispatched('step-completed', function ($step, $data) {
            return $step === 1 &&
                   $data['name'] === 'Complete Product' &&
                   $data['parent_sku'] === 'COMP-001' &&
                   $data['description'] === 'A complete product' &&
                   $data['status'] === 'active' &&
                   $data['image_url'] === 'https://example.com/complete.jpg';
        });
    });
});
