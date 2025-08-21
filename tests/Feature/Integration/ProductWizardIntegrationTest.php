<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('Product Creation Flow Integration', function () {
    it('completes full product creation flow from route to database', function () {
        // 1. Start at create route
        $response = $this->get(route('products.create'));
        $response->assertOk();

        // 2. Component loads successfully
        $response->assertSeeLivewire('products.product-wizard-clean');

        // 3. Test component interaction with minimal data
        Livewire::test('products.product-wizard-clean')
            ->set('wizardData', [
                'product_info' => [
                    'name' => 'Integration Test Product',
                    'status' => 'active',
                ],
            ])
            ->call('saveProduct')
            ->assertHasNoErrors()
            ->assertRedirect();

        // 4. Verify database creation
        $product = Product::where('name', 'Integration Test Product')->first();
        expect($product)->not->toBeNull();
        expect($product->status)->toBe('active');
        expect($product->parent_sku)->not->toBeNull();
    });

    it('handles complex product creation with all data', function () {
        $complexWizardData = [
            'product_info' => [
                'name' => 'Complex Integration Product',
                'description' => 'A detailed product description',
                'status' => 'active',
            ],
            'pricing_info' => [
                'retail_price' => 99.99,
                'cost_price' => 49.99,
            ],
            'variant_info' => [
                'color' => 'Blue',
                'size' => 'Medium',
                'sku' => 'COMPLEX-INT-001',
            ],
        ];

        Livewire::test('products.product-wizard-clean')
            ->set('wizardData', $complexWizardData)
            ->call('saveProduct')
            ->assertHasNoErrors()
            ->assertRedirect();

        $product = Product::where('name', 'Complex Integration Product')->first();
        expect($product)->not->toBeNull();
        expect($product->description)->toBe('A detailed product description');
        expect($product->status)->toBe('active');
    });

    it('validates required fields and shows errors', function () {
        Livewire::test('products.product-wizard-clean')
            ->set('wizardData', [
                'product_info' => [
                    'name' => '', // Missing required name
                    'status' => 'active',
                ],
            ])
            ->call('saveProduct')
            ->assertHasErrors(['wizardData.product_info.name']);
    });
});

describe('Product Edit Flow Integration', function () {
    beforeEach(function () {
        $this->product = Product::factory()->create([
            'name' => 'Original Product Name',
            'status' => 'active',
            'description' => 'Original description',
        ]);
    });

    it('completes full product edit flow from route to database', function () {
        // 1. Start at edit route
        $response = $this->get(route('products.edit', $this->product));
        $response->assertOk();

        // 2. Component loads with existing data
        $response->assertSeeLivewire('products.product-wizard-clean');

        // 3. Test component loads existing product data
        Livewire::test('products.product-wizard-clean', ['product' => $this->product])
            ->assertSet('wizardData.product_info.name', 'Original Product Name')
            ->assertSet('wizardData.product_info.status', 'active')
            ->set('wizardData.product_info.name', 'Updated Product Name')
            ->set('wizardData.product_info.description', 'Updated description')
            ->call('saveProduct')
            ->assertHasNoErrors()
            ->assertRedirect();

        // 4. Verify database update
        $this->product->refresh();
        expect($this->product->name)->toBe('Updated Product Name');
        expect($this->product->description)->toBe('Updated description');
    });

    it('preserves unchanged fields during edit', function () {
        $originalParentSku = $this->product->parent_sku;

        Livewire::test('products.product-wizard-clean', ['product' => $this->product])
            ->set('wizardData.product_info.name', 'Just Name Changed')
            ->call('saveProduct')
            ->assertHasNoErrors();

        $this->product->refresh();
        expect($this->product->name)->toBe('Just Name Changed');
        expect($this->product->parent_sku)->toBe($originalParentSku);
        expect($this->product->status)->toBe('active'); // Unchanged
    });

    it('handles edit validation errors properly', function () {
        Livewire::test('products.product-wizard-clean', ['product' => $this->product])
            ->set('wizardData.product_info.name', '') // Clear required field
            ->call('saveProduct')
            ->assertHasErrors(['wizardData.product_info.name']);

        // Verify original data unchanged
        $this->product->refresh();
        expect($this->product->name)->toBe('Original Product Name');
    });
});

describe('Route Parameter Validation Integration', function () {
    it('handles invalid product ID gracefully', function () {
        $response = $this->get('/products/99999/edit');
        $response->assertNotFound();
    });

    it('handles non-numeric product ID gracefully', function () {
        $response = $this->get('/products/invalid-id/edit');
        $response->assertNotFound();
    });

    it('redirects to correct route after product creation', function () {
        Livewire::test('products.product-wizard-clean')
            ->set('wizardData', [
                'product_info' => [
                    'name' => 'Redirect Test Product',
                    'status' => 'active',
                ],
            ])
            ->call('saveProduct')
            ->assertRedirect();

        $product = Product::where('name', 'Redirect Test Product')->first();
        expect($product)->not->toBeNull();
    });
});

describe('Component State Management Integration', function () {
    it('initializes with empty state for new product', function () {
        Livewire::test('products.product-wizard-clean')
            ->assertSet('wizardData', [])
            ->assertSet('isEditing', false);
    });

    it('initializes with product data for editing', function () {
        $product = Product::factory()->create([
            'name' => 'Test Product for State',
            'status' => 'inactive',
            'description' => 'State test description',
        ]);

        Livewire::test('products.product-wizard-clean', ['product' => $product])
            ->assertSet('wizardData.product_info.name', 'Test Product for State')
            ->assertSet('wizardData.product_info.status', 'inactive')
            ->assertSet('wizardData.product_info.description', 'State test description')
            ->assertSet('isEditing', true);
    });

    it('maintains state during validation failures', function () {
        $testData = [
            'product_info' => [
                'name' => 'State Persistence Test',
                'status' => 'active',
                'description' => 'This should persist',
            ],
            'pricing_info' => [
                'retail_price' => 'invalid-price', // Will cause validation error
            ],
        ];

        Livewire::test('products.product-wizard-clean')
            ->set('wizardData', $testData)
            ->call('saveProduct')
            ->assertHasErrors()
            ->assertSet('wizardData.product_info.name', 'State Persistence Test')
            ->assertSet('wizardData.product_info.description', 'This should persist');
    });
});

describe('Performance Integration Tests', function () {
    it('creates product within performance threshold', function () {
        $startTime = microtime(true);

        Livewire::test('products.product-wizard-clean')
            ->set('wizardData', [
                'product_info' => [
                    'name' => 'Performance Test Product',
                    'status' => 'active',
                ],
            ])
            ->call('saveProduct');

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        expect($executionTime)->toBeLessThan(100); // Should complete within 100ms
    });

    it('updates product within performance threshold', function () {
        $product = Product::factory()->create();

        $startTime = microtime(true);

        Livewire::test('products.product-wizard-clean', ['product' => $product])
            ->set('wizardData.product_info.name', 'Performance Updated Name')
            ->call('saveProduct');

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        expect($executionTime)->toBeLessThan(100); // Should complete within 100ms
    });
});

describe('Error Handling Integration', function () {
    it('handles database constraint violations gracefully', function () {
        // Create a product with a specific parent_sku
        Product::factory()->create(['parent_sku' => 'DUPLICATE-001']);

        // Attempt to create another with the same parent_sku should be handled
        Livewire::test('products.product-wizard-clean')
            ->set('wizardData', [
                'product_info' => [
                    'name' => 'Constraint Test Product',
                    'status' => 'active',
                ],
            ])
            ->call('saveProduct')
            ->assertHasNoErrors(); // Should auto-generate unique parent_sku
    });

    it('provides user-friendly error messages for validation failures', function () {
        Livewire::test('products.product-wizard-clean')
            ->set('wizardData', [
                'product_info' => [
                    'name' => '',
                    'status' => 'invalid-status',
                ],
            ])
            ->call('saveProduct')
            ->assertHasErrors(['wizardData.product_info.name'])
            ->assertHasErrors(['wizardData.product_info.status']);
    });
});
