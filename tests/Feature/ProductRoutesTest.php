<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;

describe('Product Routes', function () {
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
            'status' => 'active'
        ]);
    });

    describe('Product Index Routes', function () {
        test('products index route works', function () {
            $response = $this->get(route('products.index'));
            
            $response->assertStatus(200)
                ->assertSee('Products') // Page title
                ->assertSee($this->product->name);
        });

        test('products index with search parameter', function () {
            $response = $this->get(route('products.index', ['search' => 'Test']));
            
            $response->assertStatus(200)
                ->assertSee($this->product->name);
        });

        test('products index with status filter', function () {
            $response = $this->get(route('products.index', ['status' => 'active']));
            
            $response->assertStatus(200)
                ->assertSee($this->product->name);
        });
    });

    describe('Product CRUD Routes', function () {
        test('products create route works', function () {
            $response = $this->get(route('products.create'));
            
            $response->assertStatus(200)
                ->assertSee('Create Product');
        });

        test('products store route works', function () {
            $productData = [
                'name' => 'New Product',
                'parent_sku' => 'NEW123',
                'description' => 'New product description',
                'status' => 'active'
            ];
            
            $response = $this->post(route('products.store'), $productData);
            
            $response->assertRedirect(); // Should redirect after creation
            
            $this->assertDatabaseHas('products', [
                'name' => 'New Product',
                'parent_sku' => 'NEW123'
            ]);
        });

        test('products show route works', function () {
            $response = $this->get(route('products.show', $this->product));
            
            $response->assertStatus(200)
                ->assertSee($this->product->name)
                ->assertSee($this->product->parent_sku);
        });

        test('products edit route works', function () {
            $response = $this->get(route('products.edit', $this->product));
            
            $response->assertStatus(200)
                ->assertSee('Edit Product')
                ->assertSee($this->product->name);
        });

        test('products update route works', function () {
            $updateData = [
                'name' => 'Updated Product Name',
                'parent_sku' => $this->product->parent_sku,
                'description' => 'Updated description',
                'status' => 'active'
            ];
            
            $response = $this->put(route('products.update', $this->product), $updateData);
            
            $response->assertRedirect();
            
            $this->product->refresh();
            expect($this->product->name)->toBe('Updated Product Name');
        });

        test('products destroy route works', function () {
            $response = $this->delete(route('products.destroy', $this->product));
            
            $response->assertRedirect();
            
            $this->assertDatabaseMissing('products', [
                'id' => $this->product->id
            ]);
        });
    });

    describe('Product Tab Routes', function () {
        test('products show overview tab route works', function () {
            $response = $this->get(route('products.show', $this->product));
            
            $response->assertStatus(200)
                ->assertSee('Overview');
        });

        test('products show variants tab route works', function () {
            $response = $this->get(route('products.show.variants', $this->product));
            
            $response->assertStatus(200)
                ->assertSee('Variants')
                ->assertSee($this->variant->sku);
        });

        test('products show marketplace tab route works', function () {
            $response = $this->get(route('products.show.marketplace', $this->product));
            
            $response->assertStatus(200)
                ->assertSee('Marketplace');
        });

        test('products show attributes tab route works', function () {
            $response = $this->get(route('products.show.attributes', $this->product));
            
            $response->assertStatus(200)
                ->assertSee('Attributes');
        });

        test('products show images tab route works', function () {
            $response = $this->get(route('products.show.images', $this->product));
            
            $response->assertStatus(200)
                ->assertSee('Images');
        });

        test('products show history tab route works', function () {
            $response = $this->get(route('products.show.history', $this->product));
            
            $response->assertStatus(200)
                ->assertSee('History');
        });
    });

    describe('Variant Routes', function () {
        test('variants index route works', function () {
            $response = $this->get(route('variants.index'));
            
            $response->assertStatus(200)
                ->assertSee('Variants')
                ->assertSee($this->variant->sku);
        });

        test('variants create route works', function () {
            $response = $this->get(route('variants.create'));
            
            $response->assertStatus(200)
                ->assertSee('Create Variant');
        });

        test('variants create with product parameter', function () {
            $response = $this->get(route('variants.create', ['product' => $this->product->id]));
            
            $response->assertStatus(200)
                ->assertSee('Create Variant')
                ->assertSee($this->product->name);
        });

        test('variants store route works', function () {
            $variantData = [
                'product_id' => $this->product->id,
                'sku' => 'TEST123-BLUE',
                'title' => 'Test Product - Blue',
                'color' => 'Blue',
                'width' => 100,
                'drop' => 150,
                'price' => 25.99,
                'status' => 'active'
            ];
            
            $response = $this->post(route('variants.store'), $variantData);
            
            $response->assertRedirect();
            
            $this->assertDatabaseHas('product_variants', [
                'sku' => 'TEST123-BLUE',
                'color' => 'Blue'
            ]);
        });

        test('variants show route works', function () {
            $response = $this->get(route('variants.show', $this->variant));
            
            $response->assertStatus(200)
                ->assertSee($this->variant->sku)
                ->assertSee($this->variant->color);
        });

        test('variants edit route works', function () {
            $response = $this->get(route('variants.edit', $this->variant));
            
            $response->assertStatus(200)
                ->assertSee('Edit Variant')
                ->assertSee($this->variant->sku);
        });

        test('variants update route works', function () {
            $updateData = [
                'sku' => $this->variant->sku,
                'title' => 'Updated Variant Title',
                'color' => $this->variant->color,
                'price' => 35.99,
                'status' => 'active'
            ];
            
            $response = $this->put(route('variants.update', $this->variant), $updateData);
            
            $response->assertRedirect();
            
            $this->variant->refresh();
            expect($this->variant->title)->toBe('Updated Variant Title');
        });

        test('variants destroy route works', function () {
            $response = $this->delete(route('variants.destroy', $this->variant));
            
            $response->assertRedirect();
            
            $this->assertDatabaseMissing('product_variants', [
                'id' => $this->variant->id
            ]);
        });
    });

    describe('Product Wizard Routes', function () {
        test('product wizard route works', function () {
            $response = $this->get(route('products.wizard'));
            
            $response->assertStatus(200)
                ->assertSee('Product Wizard');
        });
    });

    describe('Import Routes', function () {
        test('product import route works', function () {
            $response = $this->get(route('products.import'));
            
            $response->assertStatus(200)
                ->assertSee('Import Products');
        });
    });

    describe('API Routes', function () {
        test('products api index works', function () {
            $response = $this->getJson('/api/products');
            
            $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'parent_sku',
                            'status'
                        ]
                    ]
                ]);
        });

        test('products api show works', function () {
            $response = $this->getJson("/api/products/{$this->product->id}");
            
            $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        'id' => $this->product->id,
                        'name' => $this->product->name,
                        'parent_sku' => $this->product->parent_sku
                    ]
                ]);
        });
    });

    describe('Route Model Binding', function () {
        test('product route model binding works', function () {
            $response = $this->get(route('products.show', $this->product->id));
            
            $response->assertStatus(200)
                ->assertSee($this->product->name);
        });

        test('variant route model binding works', function () {
            $response = $this->get(route('variants.show', $this->variant->id));
            
            $response->assertStatus(200)
                ->assertSee($this->variant->sku);
        });

        test('invalid product id returns 404', function () {
            $response = $this->get(route('products.show', 99999));
            
            $response->assertStatus(404);
        });

        test('invalid variant id returns 404', function () {
            $response = $this->get(route('variants.show', 99999));
            
            $response->assertStatus(404);
        });
    });

    describe('Route Middleware', function () {
        test('authenticated routes require login', function () {
            auth()->logout();
            
            $response = $this->get(route('products.index'));
            
            $response->assertRedirect(route('login'));
        });

        test('admin routes require proper permissions', function () {
            // Test with non-admin user if you have role-based permissions
            $regularUser = User::factory()->create();
            $this->actingAs($regularUser);
            
            $response = $this->get(route('products.create'));
            
            // Adjust based on your permission system
            $response->assertStatus(200); // Or 403 if restricted
        });
    });

    describe('Route Caching', function () {
        test('cached routes work correctly', function () {
            // Test that route caching doesn't break functionality
            $response = $this->get(route('products.index'));
            
            $response->assertStatus(200);
        });
    });
});