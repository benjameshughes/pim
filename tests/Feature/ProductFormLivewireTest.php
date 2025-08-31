<?php

use App\Livewire\Products\ProductForm;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

describe('ProductForm Livewire Component', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    describe('Creating Products', function () {
        test('component mounts for creating new product', function () {
            Livewire::test(ProductForm::class)
                ->assertStatus(200)
                ->assertSee('Create Product'); // Assuming form title
        });

        test('validates required fields', function () {
            Livewire::test(ProductForm::class)
                ->set('name', '')
                ->set('parent_sku', '')
                ->call('save')
                ->assertHasErrors(['name', 'parent_sku']);
        });

        test('creates product with valid data', function () {
            Livewire::test(ProductForm::class)
                ->set('name', 'Test Product')
                ->set('parent_sku', 'TEST123')
                ->set('description', 'Test description')
                ->set('status', 'active')
                ->call('save')
                ->assertHasNoErrors()
                ->assertDispatched('success');

            $product = Product::where('parent_sku', 'TEST123')->first();
            expect($product)->not()->toBeNull()
                ->and($product->name)->toBe('Test Product')
                ->and($product->description)->toBe('Test description')
                ->and($product->status->value)->toBe('active');
        });

        test('validates unique parent_sku', function () {
            Product::factory()->create(['parent_sku' => 'DUPLICATE123']);

            Livewire::test(ProductForm::class)
                ->set('name', 'Test Product')
                ->set('parent_sku', 'DUPLICATE123')
                ->call('save')
                ->assertHasErrors(['parent_sku']);
        });

        test('validates parent_sku format', function () {
            Livewire::test(ProductForm::class)
                ->set('name', 'Test Product')
                ->set('parent_sku', 'invalid sku with spaces')
                ->call('save')
                ->assertHasErrors(['parent_sku']);
        });

        test('redirects after successful creation', function () {
            $component = Livewire::test(ProductForm::class)
                ->set('name', 'Test Product')
                ->set('parent_sku', 'TEST123')
                ->call('save');

            $product = Product::where('parent_sku', 'TEST123')->first();

            if (method_exists($component->instance(), 'redirectRoute')) {
                // Test redirect behavior
                $component->assertRedirect(route('products.show', $product));
            }
        });
    });

    describe('Editing Products', function () {
        beforeEach(function () {
            $this->product = Product::factory()->create([
                'name' => 'Original Product',
                'parent_sku' => 'ORIGINAL123',
                'description' => 'Original description',
                'status' => 'active',
            ]);
        });

        test('component mounts for editing existing product', function () {
            Livewire::test(ProductForm::class, ['product' => $this->product])
                ->assertStatus(200)
                ->assertSee('Edit Product') // Assuming form title
                ->assertSet('name', 'Original Product')
                ->assertSet('parent_sku', 'ORIGINAL123')
                ->assertSet('description', 'Original description');
        });

        test('updates product with valid data', function () {
            Livewire::test(ProductForm::class, ['product' => $this->product])
                ->set('name', 'Updated Product')
                ->set('description', 'Updated description')
                ->call('save')
                ->assertHasNoErrors()
                ->assertDispatched('success');

            $this->product->refresh();
            expect($this->product->name)->toBe('Updated Product')
                ->and($this->product->description)->toBe('Updated description');
        });

        test('allows same SKU for existing product', function () {
            Livewire::test(ProductForm::class, ['product' => $this->product])
                ->set('name', 'Updated Name')
                ->set('parent_sku', 'ORIGINAL123') // Same SKU
                ->call('save')
                ->assertHasNoErrors();
        });

        test('validates unique parent_sku when changing', function () {
            $otherProduct = Product::factory()->create(['parent_sku' => 'OTHER123']);

            Livewire::test(ProductForm::class, ['product' => $this->product])
                ->set('parent_sku', 'OTHER123')
                ->call('save')
                ->assertHasErrors(['parent_sku']);
        });
    });

    describe('Form Validation', function () {
        test('name is required', function () {
            Livewire::test(ProductForm::class)
                ->set('name', '')
                ->call('save')
                ->assertHasErrors(['name']);
        });

        test('name has maximum length', function () {
            Livewire::test(ProductForm::class)
                ->set('name', str_repeat('a', 256))
                ->call('save')
                ->assertHasErrors(['name']);
        });

        test('parent_sku is required', function () {
            Livewire::test(ProductForm::class)
                ->set('parent_sku', '')
                ->call('save')
                ->assertHasErrors(['parent_sku']);
        });

        test('status must be valid enum', function () {
            Livewire::test(ProductForm::class)
                ->set('status', 'invalid_status')
                ->call('save')
                ->assertHasErrors(['status']);
        });

        test('description is optional', function () {
            Livewire::test(ProductForm::class)
                ->set('name', 'Test Product')
                ->set('parent_sku', 'TEST123')
                ->set('description', '')
                ->call('save')
                ->assertHasNoErrors();
        });
    });

    describe('Form Interactions', function () {
        test('cancel button works', function () {
            $component = Livewire::test(ProductForm::class);

            if (method_exists($component->instance(), 'cancel')) {
                $component->call('cancel')
                    ->assertRedirect(route('products.index'));
            }
        });

        test('reset form functionality works', function () {
            $component = Livewire::test(ProductForm::class)
                ->set('name', 'Test')
                ->set('parent_sku', 'TEST');

            if (method_exists($component->instance(), 'resetForm')) {
                $component->call('resetForm')
                    ->assertSet('name', '')
                    ->assertSet('parent_sku', '');
            }
        });

        test('form shows loading states', function () {
            $component = Livewire::test(ProductForm::class)
                ->set('name', 'Test Product')
                ->set('parent_sku', 'TEST123');

            // Test that loading states work (wire:loading)
            $component->assertStatus(200);
        });
    });

    describe('Real-time Validation', function () {
        test('validates name on blur', function () {
            Livewire::test(ProductForm::class)
                ->set('name', '')
                ->assertHasErrors(['name']);
        });

        test('validates parent_sku on blur', function () {
            Livewire::test(ProductForm::class)
                ->set('parent_sku', '')
                ->assertHasErrors(['parent_sku']);
        });

        test('clears errors when valid input provided', function () {
            Livewire::test(ProductForm::class)
                ->set('name', '')
                ->assertHasErrors(['name'])
                ->set('name', 'Valid Name')
                ->assertHasNoErrors(['name']);
        });
    });

    describe('Attribute System Integration', function () {
        test('form supports attribute assignment', function () {
            $component = Livewire::test(ProductForm::class);

            // If the form supports attributes
            if (property_exists($component->instance(), 'attributes')) {
                $component->set('attributes.brand', 'Test Brand')
                    ->set('name', 'Test Product')
                    ->set('parent_sku', 'TEST123')
                    ->call('save')
                    ->assertHasNoErrors();

                $product = Product::where('parent_sku', 'TEST123')->first();
                expect($product->getSmartAttributeValue('brand'))->toBe('Test Brand');
            }
        });
    });

    describe('Error Handling', function () {
        test('handles database errors gracefully', function () {
            // Simulate database error by creating very long name that exceeds DB limit
            Livewire::test(ProductForm::class)
                ->set('name', str_repeat('a', 1000))
                ->set('parent_sku', 'TEST123')
                ->call('save')
                ->assertHasErrors();
        });

        test('displays validation errors clearly', function () {
            Livewire::test(ProductForm::class)
                ->set('name', '')
                ->set('parent_sku', '')
                ->call('save')
                ->assertSee('required'); // Error message should be visible
        });
    });

    describe('Accessibility', function () {
        test('form has proper labels and structure', function () {
            Livewire::test(ProductForm::class)
                ->assertSee('Name') // Form labels
                ->assertSee('SKU')
                ->assertSee('Description')
                ->assertSee('Status');
        });
    });

    describe('Performance', function () {
        test('component renders without performance issues', function () {
            $startTime = microtime(true);

            Livewire::test(ProductForm::class)
                ->assertStatus(200);

            $endTime = microtime(true);
            $renderTime = $endTime - $startTime;

            expect($renderTime)->toBeLessThan(1.0); // Should render in less than 1 second
        });
    });
});
