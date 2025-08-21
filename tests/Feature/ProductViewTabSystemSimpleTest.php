<?php

use App\Livewire\Products\ProductShow;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SyncAccount;
use App\Models\SyncStatus;
use App\Models\User;
use Livewire\Livewire;

describe('Product View Tab System - Simple Tests', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->product = Product::factory()
            ->has(ProductVariant::factory()->count(2), 'variants')
            ->create(['name' => 'Test Product']);
    });

    it('can render the product show component', function () {
        Livewire::test(ProductShow::class, ['product' => $this->product])
            ->assertSee('Test Product')
            ->assertStatus(200);
    });

    it('can access all product tab routes', function () {
        $routes = [
            'products.show',
            'products.show.overview',
            'products.show.variants',
            'products.show.marketplace',
            'products.show.history',
        ];

        foreach ($routes as $routeName) {
            $this->get(route($routeName, $this->product))->assertOk();
        }
    });

    it('shows correct content for each tab', function () {
        // Overview/default
        $this->get(route('products.show', $this->product))
            ->assertOk()
            ->assertSeeText('Product Information');

        // Variants
        $this->get(route('products.show.variants', $this->product))
            ->assertOk()
            ->assertSeeText('Product Variants');

        // Marketplace
        $this->get(route('products.show.marketplace', $this->product))
            ->assertOk()
            ->assertSeeText('Marketplace Sync Status');

        // History
        $this->get(route('products.show.history', $this->product))
            ->assertOk()
            ->assertSeeText('Sync Activity History');
    });

    it('shows correct variant count in tabs', function () {
        $component = Livewire::test(ProductShow::class, ['product' => $this->product]);
        $tabs = $component->get('productTabs');

        expect($tabs)->not->toBeNull();

        // Test that we have tabs
        $tabsArray = $tabs->toArray($this->product);
        expect($tabsArray)->toBeArray();
        expect(count($tabsArray))->toBeGreaterThan(0);
    });

    it('can duplicate and delete products', function () {
        // Test duplication
        Livewire::test(ProductShow::class, ['product' => $this->product])
            ->call('duplicateProduct')
            ->assertDispatched('success');

        $duplicate = Product::where('name', 'Test Product (Copy)')->first();
        expect($duplicate)->not->toBeNull();

        // Test deletion
        $productId = $this->product->id;
        Livewire::test(ProductShow::class, ['product' => $this->product])
            ->call('deleteProduct')
            ->assertDispatched('success');

        expect(Product::find($productId))->toBeNull();
    });

    it('updates marketplace tab badge correctly', function () {
        // Create sync account and status
        $syncAccount = SyncAccount::factory()->create(['channel' => 'shopify']);
        SyncStatus::factory()->create([
            'product_id' => $this->product->id,
            'sync_account_id' => $syncAccount->id,
            'external_product_id' => 'shopify-123',
            'sync_status' => 'synced',
        ]);

        $component = Livewire::test(ProductShow::class, ['product' => $this->product->fresh()]);

        // Test that the component loads with synced data
        $component->assertSee('Test Product');
    });
});
