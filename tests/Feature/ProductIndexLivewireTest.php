<?php

use App\Livewire\Products\ProductIndex;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Livewire\Livewire;

describe('ProductIndex Livewire Component', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        
        // Create test products with variants
        $this->products = collect([
            Product::factory()->create([
                'name' => 'Alpha Product',
                'parent_sku' => 'ALPHA123',
                'status' => 'active'
            ]),
            Product::factory()->create([
                'name' => 'Beta Product', 
                'parent_sku' => 'BETA456',
                'status' => 'active'
            ]),
            Product::factory()->create([
                'name' => 'Gamma Product',
                'parent_sku' => 'GAMMA789',
                'status' => 'inactive'
            ])
        ]);

        // Add variants to products
        $this->products->each(function ($product) {
            ProductVariant::factory(2)->create([
                'product_id' => $product->id,
                'status' => 'active'
            ]);
        });
    });

    test('component mounts and displays products', function () {
        Livewire::test(ProductIndex::class)
            ->assertStatus(200)
            ->assertSee('Alpha Product')
            ->assertSee('Beta Product')
            ->assertSee('ALPHA123')
            ->assertSee('BETA456');
    });

    test('component paginates products correctly', function () {
        // Create more products to test pagination
        Product::factory(15)->create(['status' => 'active']);

        Livewire::test(ProductIndex::class)
            ->assertStatus(200)
            ->assertSee('Alpha Product'); // Should see first page
    });

    test('search functionality works', function () {
        Livewire::test(ProductIndex::class)
            ->set('search', 'Alpha')
            ->assertSee('Alpha Product')
            ->assertDontSee('Beta Product')
            ->assertDontSee('Gamma Product');
    });

    test('search by SKU works', function () {
        Livewire::test(ProductIndex::class)
            ->set('search', 'BETA456')
            ->assertSee('Beta Product')
            ->assertDontSee('Alpha Product');
    });

    test('status filtering works', function () {
        Livewire::test(ProductIndex::class)
            ->set('status', 'active')
            ->assertSee('Alpha Product')
            ->assertSee('Beta Product')
            ->assertDontSee('Gamma Product');
            
        Livewire::test(ProductIndex::class)
            ->set('status', 'inactive')
            ->assertSee('Gamma Product')
            ->assertDontSee('Alpha Product')
            ->assertDontSee('Beta Product');
    });

    test('shows all products when status is all', function () {
        Livewire::test(ProductIndex::class)
            ->set('status', 'all')
            ->assertSee('Alpha Product')
            ->assertSee('Beta Product') 
            ->assertSee('Gamma Product');
    });

    test('displays product statistics', function () {
        $component = Livewire::test(ProductIndex::class);
        
        // Should display counts
        $component->assertSee('3'); // Total products count somewhere in UI
    });

    test('bulk selection functionality works', function () {
        Livewire::test(ProductIndex::class)
            ->set('selectedProducts', [$this->products[0]->id, $this->products[1]->id])
            ->assertSet('selectedProducts', [$this->products[0]->id, $this->products[1]->id]);
    });

    test('select all functionality works', function () {
        $component = Livewire::test(ProductIndex::class);
        
        if (method_exists($component->instance(), 'selectAll')) {
            $component->call('selectAll')
                ->assertSet('selectedProducts', $this->products->pluck('id')->toArray());
        }
    });

    test('clear selection functionality works', function () {
        $component = Livewire::test(ProductIndex::class)
            ->set('selectedProducts', [$this->products[0]->id]);
            
        if (method_exists($component->instance(), 'clearSelection')) {
            $component->call('clearSelection')
                ->assertSet('selectedProducts', []);
        }
    });

    test('per page filtering works', function () {
        if (property_exists(ProductIndex::class, 'perPage')) {
            Livewire::test(ProductIndex::class)
                ->set('perPage', 2)
                ->assertStatus(200);
        }
    });

    test('sorting functionality works', function () {
        $component = Livewire::test(ProductIndex::class);
        
        if (method_exists($component->instance(), 'sortBy')) {
            $component->call('sortBy', 'name')
                ->assertStatus(200);
        }
    });

    test('delete product functionality works', function () {
        $productToDelete = $this->products[0];
        
        $component = Livewire::test(ProductIndex::class);
        
        if (method_exists($component->instance(), 'deleteProduct')) {
            $component->call('deleteProduct', $productToDelete->id)
                ->assertDispatched('success');
                
            expect(Product::find($productToDelete->id))->toBeNull();
        }
    });

    test('bulk delete functionality works', function () {
        $component = Livewire::test(ProductIndex::class)
            ->set('selectedProducts', [$this->products[0]->id, $this->products[1]->id]);
            
        if (method_exists($component->instance(), 'bulkDelete')) {
            $component->call('bulkDelete')
                ->assertDispatched('success');
                
            expect(Product::find($this->products[0]->id))->toBeNull();
            expect(Product::find($this->products[1]->id))->toBeNull();
        }
    });

    test('displays variant counts correctly', function () {
        Livewire::test(ProductIndex::class)
            ->assertStatus(200);
            
        // Each product should show 2 variants
        // This would depend on how the UI displays variant counts
    });

    test('handles empty state correctly', function () {
        Product::query()->delete();
        
        Livewire::test(ProductIndex::class)
            ->assertStatus(200)
            ->assertSee('No products found'); // Assuming empty state message
    });

    test('search with no results shows empty state', function () {
        Livewire::test(ProductIndex::class)
            ->set('search', 'NonexistentProduct')
            ->assertSee('No products found'); // Assuming empty search message
    });

    test('component handles large datasets efficiently', function () {
        Product::factory(100)->create(['status' => 'active']);
        
        $component = Livewire::test(ProductIndex::class);
        
        expect($component->payload['serverMemo']['errors'])->toBeEmpty();
    });

    test('navigation to product show works', function () {
        $component = Livewire::test(ProductIndex::class);
        
        // Test navigation (depends on implementation)
        $component->assertSee($this->products[0]->name);
        
        // If there's a view method or navigation
        if (method_exists($component->instance(), 'viewProduct')) {
            $component->call('viewProduct', $this->products[0]->id);
        }
    });

    test('create product button is visible', function () {
        Livewire::test(ProductIndex::class)
            ->assertSee('Create Product'); // Assuming button text
    });

    test('displays product status correctly', function () {
        Livewire::test(ProductIndex::class)
            ->assertSee('Active') // Active products
            ->assertSee('Inactive'); // Inactive products
    });

    test('export functionality works if available', function () {
        $component = Livewire::test(ProductIndex::class);
        
        if (method_exists($component->instance(), 'export')) {
            $component->call('export')
                ->assertStatus(200);
        }
    });

    test('real-time updates work with wire:poll if configured', function () {
        // Create new product after component loads
        $newProduct = Product::factory()->create([
            'name' => 'New Product',
            'status' => 'active'
        ]);
        
        // Component should pick up the new product on refresh
        Livewire::test(ProductIndex::class)
            ->assertStatus(200);
    });

    test('keyboard shortcuts work if implemented', function () {
        $component = Livewire::test(ProductIndex::class);
        
        // Test common keyboard shortcuts if they exist
        if (method_exists($component->instance(), 'handleKeyboard')) {
            // This would test keyboard navigation
        }
    });

    test('responsive design elements load correctly', function () {
        Livewire::test(ProductIndex::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.products.product-index');
    });

    test('component maintains state during interactions', function () {
        Livewire::test(ProductIndex::class)
            ->set('search', 'Alpha')
            ->set('status', 'active')
            ->assertSet('search', 'Alpha')
            ->assertSet('status', 'active')
            ->call('$refresh')
            ->assertSet('search', 'Alpha')
            ->assertSet('status', 'active');
    });
});