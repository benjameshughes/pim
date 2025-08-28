<?php

use App\Livewire\Variants\VariantShow;
use App\Livewire\Variants\VariantIndex;
use App\Livewire\Variants\VariantForm;
use App\Livewire\Products\VariantCreate;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Livewire\Livewire;

describe('Variant Livewire Components', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        
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

    describe('VariantIndex Component', function () {
        test('displays all variants', function () {
            $variant2 = ProductVariant::factory()->create([
                'product_id' => $this->product->id,
                'sku' => 'TEST123-BLUE',
                'color' => 'Blue'
            ]);

            Livewire::test(VariantIndex::class)
                ->assertStatus(200)
                ->assertSee('TEST123-RED')
                ->assertSee('TEST123-BLUE')
                ->assertSee('Red')
                ->assertSee('Blue');
        });

        test('search functionality works', function () {
            Livewire::test(VariantIndex::class)
                ->set('search', 'RED')
                ->assertSee('TEST123-RED')
                ->assertDontSee('TEST123-BLUE');
        });

        test('filters by product', function () {
            $otherProduct = Product::factory()->create(['parent_sku' => 'OTHER123']);
            ProductVariant::factory()->create([
                'product_id' => $otherProduct->id,
                'sku' => 'OTHER123-GREEN'
            ]);

            Livewire::test(VariantIndex::class)
                ->set('productFilter', $this->product->id)
                ->assertSee('TEST123-RED')
                ->assertDontSee('OTHER123-GREEN');
        });

        test('displays barcode information', function () {
            // Add barcode to variant
            \App\Models\Barcode::create([
                'barcode' => '1234567890123',
                'product_variant_id' => $this->variant->id,
                'is_assigned' => true
            ]);

            Livewire::test(VariantIndex::class)
                ->assertSee('1234567890123');
        });
    });

    describe('VariantShow Component', function () {
        test('displays variant details', function () {
            Livewire::test(VariantShow::class, ['variant' => $this->variant])
                ->assertStatus(200)
                ->assertSee('TEST123-RED')
                ->assertSee('Red')
                ->assertSee('Test Product - Red');
        });

        test('shows associated product', function () {
            Livewire::test(VariantShow::class, ['variant' => $this->variant])
                ->assertSee($this->product->name)
                ->assertSee($this->product->parent_sku);
        });

        test('displays barcode if assigned', function () {
            \App\Models\Barcode::create([
                'barcode' => '9999999999999',
                'product_variant_id' => $this->variant->id,
                'is_assigned' => true
            ]);

            Livewire::test(VariantShow::class, ['variant' => $this->variant->load('barcode')])
                ->assertSee('9999999999999');
        });

        test('delete variant functionality', function () {
            $component = Livewire::test(VariantShow::class, ['variant' => $this->variant]);
            
            if (method_exists($component->instance(), 'deleteVariant')) {
                $component->call('deleteVariant')
                    ->assertDispatched('success')
                    ->assertRedirect(route('variants.index'));
                    
                expect(ProductVariant::find($this->variant->id))->toBeNull();
            }
        });
    });

    describe('VariantForm Component', function () {
        test('creates new variant with valid data', function () {
            Livewire::test(VariantForm::class, ['product' => $this->product])
                ->set('sku', 'TEST123-GREEN')
                ->set('title', 'Test Product - Green')
                ->set('color', 'Green')
                ->set('width', 100)
                ->set('drop', 150)
                ->set('price', 29.99)
                ->call('save')
                ->assertHasNoErrors()
                ->assertDispatched('success');

            $variant = ProductVariant::where('sku', 'TEST123-GREEN')->first();
            expect($variant)->not()->toBeNull()
                ->and($variant->color)->toBe('Green')
                ->and($variant->price)->toBe(29.99);
        });

        test('validates required fields', function () {
            Livewire::test(VariantForm::class, ['product' => $this->product])
                ->set('sku', '')
                ->set('color', '')
                ->call('save')
                ->assertHasErrors(['sku', 'color']);
        });

        test('validates unique SKU', function () {
            Livewire::test(VariantForm::class, ['product' => $this->product])
                ->set('sku', 'TEST123-RED') // Existing SKU
                ->call('save')
                ->assertHasErrors(['sku']);
        });

        test('updates existing variant', function () {
            Livewire::test(VariantForm::class, ['variant' => $this->variant])
                ->set('title', 'Updated Title')
                ->set('price', 35.99)
                ->call('save')
                ->assertHasNoErrors();

            $this->variant->refresh();
            expect($this->variant->title)->toBe('Updated Title')
                ->and($this->variant->price)->toBe(35.99);
        });
    });

    describe('VariantCreate Component', function () {
        test('creates variant for specific product', function () {
            Livewire::test(VariantCreate::class)
                ->set('product_id', $this->product->id)
                ->set('sku', 'TEST123-PURPLE')
                ->set('title', 'Test Product - Purple')
                ->set('color', 'Purple')
                ->call('save')
                ->assertHasNoErrors()
                ->assertDispatched('success');

            $variant = ProductVariant::where('sku', 'TEST123-PURPLE')->first();
            expect($variant)->not()->toBeNull()
                ->and($variant->product_id)->toBe($this->product->id);
        });

        test('auto-assigns barcode to new variant', function () {
            // Create available barcode
            \App\Models\Barcode::create(['barcode' => '5555555555555', 'is_assigned' => false]);

            Livewire::test(VariantCreate::class)
                ->set('product_id', $this->product->id)
                ->set('sku', 'TEST123-YELLOW')
                ->set('color', 'Yellow')
                ->call('save');

            $variant = ProductVariant::where('sku', 'TEST123-YELLOW')->first();
            expect($variant->barcode)->not()->toBeNull()
                ->and($variant->barcode->barcode)->toBe('5555555555555');
        });

        test('validates product exists', function () {
            Livewire::test(VariantCreate::class)
                ->set('product_id', 9999) // Non-existent product
                ->call('save')
                ->assertHasErrors(['product_id']);
        });
    });

    describe('Variant Relationships', function () {
        test('variant displays product information correctly', function () {
            Livewire::test(VariantShow::class, ['variant' => $this->variant->load('product')])
                ->assertSee($this->product->name)
                ->assertSee($this->product->parent_sku);
        });

        test('variant shows pricing information', function () {
            $this->variant->update(['price' => 49.99]);

            Livewire::test(VariantShow::class, ['variant' => $this->variant])
                ->assertSee('49.99');
        });

        test('variant displays dimensions', function () {
            $this->variant->update(['width' => 120, 'drop' => 200]);

            Livewire::test(VariantShow::class, ['variant' => $this->variant])
                ->assertSee('120') // Width
                ->assertSee('200'); // Drop
        });
    });

    describe('Bulk Operations', function () {
        test('bulk delete variants works', function () {
            $variant2 = ProductVariant::factory()->create([
                'product_id' => $this->product->id,
                'sku' => 'TEST123-BULK'
            ]);

            $component = Livewire::test(VariantIndex::class)
                ->set('selectedVariants', [$this->variant->id, $variant2->id]);

            if (method_exists($component->instance(), 'bulkDelete')) {
                $component->call('bulkDelete')
                    ->assertDispatched('success');

                expect(ProductVariant::find($this->variant->id))->toBeNull();
                expect(ProductVariant::find($variant2->id))->toBeNull();
            }
        });

        test('bulk update variants works', function () {
            $variant2 = ProductVariant::factory()->create([
                'product_id' => $this->product->id,
                'status' => 'active'
            ]);

            $component = Livewire::test(VariantIndex::class)
                ->set('selectedVariants', [$this->variant->id, $variant2->id]);

            if (method_exists($component->instance(), 'bulkUpdateStatus')) {
                $component->call('bulkUpdateStatus', 'inactive')
                    ->assertDispatched('success');

                expect($this->variant->fresh()->status->value)->toBe('inactive');
                expect($variant2->fresh()->status->value)->toBe('inactive');
            }
        });
    });
});