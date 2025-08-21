<?php

use App\Livewire\Products\ProductShow;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SyncAccount;
use App\Models\SyncLog;
use App\Models\SyncStatus;
use App\Models\User;
use Livewire\Livewire;

describe('Product View Tab System', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->product = Product::factory()
            ->has(ProductVariant::factory()->count(3), 'variants')
            ->create([
                'name' => 'Test Venetian Blind',
                'parent_sku' => 'VB-TEST-001',
            ]);
    });

    it('renders product show component with header', function () {
        Livewire::test(ProductShow::class, ['product' => $this->product])
            ->assertSee('Test Venetian Blind')
            ->assertSee('VB-TEST-001')
            ->assertSeeHtml('wire:navigate href="'.route('products.edit', $this->product).'"')
            ->assertSee('Edit')
            ->assertSee('Add Variant')
            ->assertSee('Duplicate Product')
            ->assertSee('Delete Product');
    });

    it('generates correct tab structure with badges', function () {
        $component = Livewire::test(ProductShow::class, ['product' => $this->product]);

        $tabs = $component->get('productTabs');

        expect($tabs->toArray($this->product))->toHaveCount(6);

        // Check that overview tab exists
        $overviewTab = collect($tabs->toArray($this->product))->firstWhere('route', 'overview');
        expect($overviewTab)->not->toBeNull();
        expect($overviewTab['label'])->toBe('Overview');
        expect($overviewTab['icon'])->toBe('home');

        // Check variants tab has correct badge
        $variantsTab = collect($tabs->toArray($this->product))->firstWhere('route', 'variants');
        expect($variantsTab['badge'])->toBe(3);
        expect($variantsTab['badgeColor'])->toBe('blue');
    });

    it('shows marketplace tab with no linked accounts initially', function () {
        $component = Livewire::test(ProductShow::class, ['product' => $this->product]);

        $tabs = $component->get('productTabs');
        $marketplaceTab = collect($tabs->toArray($this->product))->firstWhere('route', 'marketplace');

        expect($marketplaceTab['badge'])->toBe(0);
        expect($marketplaceTab['badgeColor'])->toBe('gray');
    });

    it('updates marketplace tab badge when accounts are linked', function () {
        // Create sync accounts and statuses
        $shopifyAccount = SyncAccount::factory()->create(['channel' => 'shopify']);
        $ebayAccount = SyncAccount::factory()->create(['channel' => 'ebay']);

        SyncStatus::factory()->create([
            'product_id' => $this->product->id,
            'sync_account_id' => $shopifyAccount->id,
            'external_product_id' => 'shopify-123',
            'sync_status' => 'synced',
        ]);

        SyncStatus::factory()->create([
            'product_id' => $this->product->id,
            'sync_account_id' => $ebayAccount->id,
            'external_product_id' => null, // Not linked
            'sync_status' => 'pending',
        ]);

        $component = Livewire::test(ProductShow::class, ['product' => $this->product->fresh()]);

        $tabs = $component->get('productTabs');
        $marketplaceTab = collect($tabs->toArray($this->product->fresh()))->firstWhere('route', 'marketplace');

        expect($marketplaceTab['badge'])->toBe(1); // Only one linked
        expect($marketplaceTab['badgeColor'])->toBe('green');
    });

    it('shows red badge color when marketplace sync fails', function () {
        $syncAccount = SyncAccount::factory()->create(['channel' => 'shopify']);

        SyncStatus::factory()->create([
            'product_id' => $this->product->id,
            'sync_account_id' => $syncAccount->id,
            'external_product_id' => 'shopify-123',
            'sync_status' => 'failed',
        ]);

        $component = Livewire::test(ProductShow::class, ['product' => $this->product->fresh()]);

        $tabs = $component->get('productTabs');
        $marketplaceTab = collect($tabs->toArray($this->product->fresh()))->firstWhere('route', 'marketplace');

        expect($marketplaceTab['badgeColor'])->toBe('red');
    });

    it('hides attributes and images tabs when counts are zero', function () {
        $component = Livewire::test(ProductShow::class, ['product' => $this->product]);

        $tabs = $component->get('productTabs');
        $tabsArray = $tabs->toArray($this->product);

        $attributesTab = collect($tabsArray)->firstWhere('route', 'attributes');
        $imagesTab = collect($tabsArray)->firstWhere('route', 'images');

        expect($attributesTab['hidden'])->toBe(true);
        expect($imagesTab['hidden'])->toBe(true);
    });

    it('shows history tab with recent activity badge', function () {
        $syncAccount = SyncAccount::factory()->create(['channel' => 'shopify']);

        // Create recent activity
        SyncLog::factory()->count(5)->create([
            'product_id' => $this->product->id,
            'sync_account_id' => $syncAccount->id,
            'created_at' => now()->subDays(2),
        ]);

        // Create old activity (should not count)
        SyncLog::factory()->create([
            'product_id' => $this->product->id,
            'sync_account_id' => $syncAccount->id,
            'created_at' => now()->subDays(10),
        ]);

        $component = Livewire::test(ProductShow::class, ['product' => $this->product->fresh()]);

        $tabs = $component->get('productTabs');
        $historyTab = collect($tabs->toArray($this->product->fresh()))->firstWhere('route', 'history');

        expect($historyTab['badge'])->toBe(5); // Only recent activity
        expect($historyTab['badgeColor'])->toBe('blue');
    });

    it('shows red history badge when recent failures exist', function () {
        $syncAccount = SyncAccount::factory()->create(['channel' => 'shopify']);

        SyncLog::factory()->create([
            'product_id' => $this->product->id,
            'sync_account_id' => $syncAccount->id,
            'status' => 'failed',
            'created_at' => now()->subDays(2),
        ]);

        $component = Livewire::test(ProductShow::class, ['product' => $this->product->fresh()]);

        $tabs = $component->get('productTabs');
        $historyTab = collect($tabs->toArray($this->product->fresh()))->firstWhere('route', 'history');

        expect($historyTab['badgeColor'])->toBe('red');
    });

    it('can duplicate product successfully', function () {
        Livewire::test(ProductShow::class, ['product' => $this->product])
            ->call('duplicateProduct')
            ->assertDispatched('success', 'Product duplicated successfully! ✨')
            ->assertRedirect(route('products.show', Product::where('name', 'Test Venetian Blind (Copy)')->first()));

        // Verify the duplicate was created
        $duplicate = Product::where('name', 'Test Venetian Blind (Copy)')->first();
        expect($duplicate)->not->toBeNull();
        expect($duplicate->parent_sku)->toBe('VB-TEST-001-COPY');
    });

    it('can delete product successfully', function () {
        $productId = $this->product->id;

        Livewire::test(ProductShow::class, ['product' => $this->product])
            ->call('deleteProduct')
            ->assertDispatched('success')
            ->assertRedirect(route('products.index'));

        // Verify the product was deleted
        expect(Product::find($productId))->toBeNull();
    });

    it('renders tab navigation correctly', function () {
        Livewire::test(ProductShow::class, ['product' => $this->product])
            ->assertSeeHtml('TAB NAVIGATION')
            ->assertSee('Overview')
            ->assertSee('Variants')
            ->assertSee('Marketplace')
            ->assertSee('History')
            ->assertSeeHtml('wire:navigate');
    });

    it('displays correct tab content based on route', function () {
        // Test overview tab (default)
        $response = $this->get(route('products.show', $this->product));
        $response->assertSeeText('Product Information');

        // Test variants tab
        $response = $this->get(route('products.show.variants', $this->product));
        $response->assertSeeText('Product Variants');

        // Test marketplace tab
        $response = $this->get(route('products.show.marketplace', $this->product));
        $response->assertSeeText('MARKETPLACE SYNC CARDS');

        // Test history tab
        $response = $this->get(route('products.show.history', $this->product));
        $response->assertSeeText('Sync Activity History');
    });
});

describe('Product Tab Components', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->product = Product::factory()
            ->has(ProductVariant::factory()->count(2), 'variants')
            ->create(['name' => 'Test Product']);
    });

    it('renders overview tab component correctly', function () {
        $this->get(route('products.show', $this->product))
            ->assertOk()
            ->assertSeeText('Test Product')
            ->assertSeeText('Product Information')
            ->assertSeeText('Color Palette')
            ->assertSeeText('Quick Stats');
    });

    it('renders variants tab component correctly', function () {
        $this->get(route('products.show.variants', $this->product))
            ->assertOk()
            ->assertSeeText('Product Variants')
            ->assertSeeText('Add Variant')
            ->assertSee('Variant')
            ->assertSee('Dimensions')
            ->assertSee('Price');
    });

    it('renders marketplace tab component correctly', function () {
        $this->get(route('products.show.marketplace', $this->product))
            ->assertOk()
            ->assertSeeText('MARKETPLACE SYNC CARDS')
            ->assertSeeText('MARKETPLACE ATTRIBUTES CARD');
    });

    it('renders history tab component correctly', function () {
        $this->get(route('products.show.history', $this->product))
            ->assertOk()
            ->assertSeeText('Sync Activity History')
            ->assertSeeText('No sync history');
    });
});

describe('Product Tab Route Integration', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->product = Product::factory()->create();
    });

    it('has correct route names and parameters', function () {
        expect(route('products.show', $this->product))->toBe("http://localhost/products/{$this->product->id}");
        expect(route('products.show.variants', $this->product))->toBe("http://localhost/products/{$this->product->id}/variants");
        expect(route('products.show.marketplace', $this->product))->toBe("http://localhost/products/{$this->product->id}/marketplace");
        expect(route('products.show.history', $this->product))->toBe("http://localhost/products/{$this->product->id}/history");
    });

    it('all product tab routes are accessible', function () {
        $routes = [
            'products.show',
            'products.show.variants',
            'products.show.marketplace',
            'products.show.history',
        ];

        foreach ($routes as $routeName) {
            $this->get(route($routeName, $this->product))->assertOk();
        }
    });
});
