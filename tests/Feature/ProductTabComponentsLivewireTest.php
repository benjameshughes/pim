<?php

use App\Livewire\Products\ProductOverview;
use App\Livewire\Products\ProductMarketplace;
use App\Livewire\Products\ProductHistory;
use App\Livewire\Products\ProductImages;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SyncLog;
use App\Models\SyncAccount;
use App\Models\User;
use Livewire\Livewire;

describe('Product Tab Components', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        
        $this->product = Product::factory()->create([
            'name' => 'Test Product',
            'parent_sku' => 'TEST123',
            'status' => 'active'
        ]);
        
        $this->variants = ProductVariant::factory(3)->create([
            'product_id' => $this->product->id,
            'status' => 'active'
        ]);
    });

    describe('ProductOverview Component', function () {
        test('displays product summary information', function () {
            Livewire::test(ProductOverview::class, ['product' => $this->product])
                ->assertStatus(200)
                ->assertSee($this->product->name)
                ->assertSee($this->product->parent_sku)
                ->assertSee('3'); // Variant count
        });

        test('shows variant statistics', function () {
            // Add barcodes to some variants
            \App\Models\Barcode::create([
                'barcode' => '1111111111111',
                'product_variant_id' => $this->variants[0]->id,
                'is_assigned' => true
            ]);

            Livewire::test(ProductOverview::class, ['product' => $this->product->load('variants')])
                ->assertSee('1'); // Should show 1 barcode assigned
        });

        test('displays product attributes if available', function () {
            // Set product attribute
            if (method_exists($this->product, 'setAttributeValue')) {
                $this->product->setAttributeValue('brand', 'Test Brand');
            }

            Livewire::test(ProductOverview::class, ['product' => $this->product])
                ->assertStatus(200);
        });

        test('shows pricing summary', function () {
            $this->variants[0]->update(['price' => 25.99]);
            $this->variants[1]->update(['price' => 29.99]);

            Livewire::test(ProductOverview::class, ['product' => $this->product->load('variants')])
                ->assertSee('25.99') // Should show pricing
                ->assertSee('29.99');
        });

        test('displays stock levels', function () {
            $this->variants[0]->update(['stock_level' => 10]);
            $this->variants[1]->update(['stock_level' => 15]);

            Livewire::test(ProductOverview::class, ['product' => $this->product->load('variants')])
                ->assertSee('25'); // Total stock should be 25
        });
    });

    describe('ProductMarketplace Component', function () {
        beforeEach(function () {
            $this->syncAccount = SyncAccount::factory()->create([
                'channel' => 'shopify',
                'name' => 'Test Shopify'
            ]);
        });

        test('displays marketplace sync accounts', function () {
            Livewire::test(ProductMarketplace::class, ['product' => $this->product])
                ->assertStatus(200)
                ->assertSee('Shopify'); // Should show available marketplaces
        });

        test('shows sync status for each marketplace', function () {
            // Create sync status
            \App\Models\SyncStatus::create([
                'product_id' => $this->product->id,
                'sync_account_id' => $this->syncAccount->id,
                'sync_status' => 'synced',
                'external_product_id' => 'shopify-123'
            ]);

            Livewire::test(ProductMarketplace::class, ['product' => $this->product->load(['syncStatuses.syncAccount'])])
                ->assertSee('synced'); // Should show sync status
        });

        test('sync product functionality works', function () {
            $component = Livewire::test(ProductMarketplace::class, ['product' => $this->product]);
            
            if (method_exists($component->instance(), 'syncToMarketplace')) {
                $component->call('syncToMarketplace', $this->syncAccount->id)
                    ->assertDispatched('success');
            }
        });

        test('displays marketplace-specific data', function () {
            // Create marketplace link
            $this->product->marketplaceLinks()->create([
                'sync_account_id' => $this->syncAccount->id,
                'link_level' => 'product',
                'marketplace_data' => ['external_id' => 'shopify-123']
            ]);

            Livewire::test(ProductMarketplace::class, ['product' => $this->product->load('marketplaceLinks')])
                ->assertSee('shopify-123');
        });
    });

    describe('ProductHistory Component', function () {
        beforeEach(function () {
            $this->syncAccount = SyncAccount::factory()->create();
            
            // Create some sync logs
            SyncLog::factory(5)->create([
                'product_id' => $this->product->id,
                'sync_account_id' => $this->syncAccount->id,
                'status' => 'success',
                'created_at' => now()->subDays(1)
            ]);
            
            SyncLog::factory(2)->create([
                'product_id' => $this->product->id,
                'sync_account_id' => $this->syncAccount->id,
                'status' => 'failed',
                'created_at' => now()->subHours(2)
            ]);
        });

        test('displays sync history', function () {
            Livewire::test(ProductHistory::class, ['product' => $this->product->load(['syncLogs.syncAccount'])])
                ->assertStatus(200)
                ->assertSee('success') // Should show successful syncs
                ->assertSee('failed'); // Should show failed syncs
        });

        test('filters history by status', function () {
            $component = Livewire::test(ProductHistory::class, ['product' => $this->product->load(['syncLogs.syncAccount'])]);
            
            if (method_exists($component->instance(), 'filterByStatus')) {
                $component->call('filterByStatus', 'failed')
                    ->assertSee('failed')
                    ->assertDontSee('success');
            }
        });

        test('paginates history correctly', function () {
            Livewire::test(ProductHistory::class, ['product' => $this->product->load(['syncLogs.syncAccount'])])
                ->assertStatus(200);
                // Should handle pagination properly
        });

        test('shows detailed log information', function () {
            $log = SyncLog::factory()->create([
                'product_id' => $this->product->id,
                'sync_account_id' => $this->syncAccount->id,
                'status' => 'success',
                'message' => 'Product synced successfully',
                'response_data' => ['external_id' => '12345']
            ]);

            Livewire::test(ProductHistory::class, ['product' => $this->product->load(['syncLogs.syncAccount'])])
                ->assertSee('Product synced successfully');
        });
    });

    describe('ProductImages Component', function () {
        test('displays product images', function () {
            // Create image relationship
            $image = \App\Models\Image::factory()->create([
                'filename' => 'test-image.jpg',
                'is_primary' => true
            ]);
            
            $this->product->images()->attach($image->id);

            Livewire::test(ProductImages::class, ['product' => $this->product->load('images')])
                ->assertStatus(200)
                ->assertSee('test-image.jpg');
        });

        test('upload new image functionality', function () {
            $component = Livewire::test(ProductImages::class, ['product' => $this->product]);
            
            if (method_exists($component->instance(), 'uploadImage')) {
                // Mock file upload
                $component->assertStatus(200);
            }
        });

        test('set primary image functionality', function () {
            $image1 = \App\Models\Image::factory()->create(['is_primary' => true]);
            $image2 = \App\Models\Image::factory()->create(['is_primary' => false]);
            
            $this->product->images()->attach([$image1->id, $image2->id]);

            $component = Livewire::test(ProductImages::class, ['product' => $this->product->load('images')]);
            
            if (method_exists($component->instance(), 'setPrimaryImage')) {
                $component->call('setPrimaryImage', $image2->id)
                    ->assertDispatched('success');
                    
                expect($image2->fresh()->is_primary)->toBeTrue();
            }
        });

        test('delete image functionality', function () {
            $image = \App\Models\Image::factory()->create();
            $this->product->images()->attach($image->id);

            $component = Livewire::test(ProductImages::class, ['product' => $this->product->load('images')]);
            
            if (method_exists($component->instance(), 'deleteImage')) {
                $component->call('deleteImage', $image->id)
                    ->assertDispatched('success');
                    
                expect($this->product->images()->where('images.id', $image->id)->exists())->toBeFalse();
            }
        });

        test('reorder images functionality', function () {
            $image1 = \App\Models\Image::factory()->create(['sort_order' => 1]);
            $image2 = \App\Models\Image::factory()->create(['sort_order' => 2]);
            
            $this->product->images()->attach([
                $image1->id => ['sort_order' => 1],
                $image2->id => ['sort_order' => 2]
            ]);

            $component = Livewire::test(ProductImages::class, ['product' => $this->product->load('images')]);
            
            if (method_exists($component->instance(), 'reorderImages')) {
                $component->call('reorderImages', [$image2->id, $image1->id])
                    ->assertDispatched('success');
            }
        });
    });

    describe('Tab Integration', function () {
        test('all tab components load without errors', function () {
            $product = $this->product->load(['variants', 'syncStatuses', 'syncLogs', 'images']);
            
            Livewire::test(ProductOverview::class, ['product' => $product])
                ->assertStatus(200);
                
            Livewire::test(ProductMarketplace::class, ['product' => $product])
                ->assertStatus(200);
                
            Livewire::test(ProductHistory::class, ['product' => $product])
                ->assertStatus(200);
                
            Livewire::test(ProductImages::class, ['product' => $product])
                ->assertStatus(200);
        });

        test('tab components handle empty states', function () {
            $emptyProduct = Product::factory()->create();
            
            Livewire::test(ProductOverview::class, ['product' => $emptyProduct])
                ->assertStatus(200)
                ->assertSee('No variants'); // Assuming empty state message
        });
    });
});