<?php

use App\Livewire\Products\ProductShow;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SyncAccount;
use App\Models\SyncLog;
use App\Models\SyncStatus;
use Livewire\Livewire;

describe('ProductShow Livewire Component', function () {
    beforeEach(function () {
        $this->product = Product::factory()->create([
            'name' => 'Test Product',
            'parent_sku' => 'TEST123',
            'status' => 'active',
        ]);

        $this->variants = ProductVariant::factory(3)->create([
            'product_id' => $this->product->id,
            'status' => 'active',
        ]);
    });

    test('component mounts and loads product data', function () {
        Livewire::test(ProductShow::class, ['product' => $this->product])
            ->assertSet('product.id', $this->product->id)
            ->assertSet('product.name', 'Test Product')
            ->assertSee('Test Product');
    });

    test('eager loads required relationships on mount', function () {
        Livewire::test(ProductShow::class, ['product' => $this->product]);

        // Check that relationships are loaded
        expect($this->product->relationLoaded('variants'))->toBeTrue()
            ->and($this->product->relationLoaded('syncStatuses'))->toBeTrue()
            ->and($this->product->relationLoaded('syncLogs'))->toBeTrue();
    });

    test('delete product method works correctly', function () {
        Livewire::test(ProductShow::class, ['product' => $this->product])
            ->call('deleteProduct')
            ->assertRedirect(route('products.index'))
            ->assertDispatched('success');

        expect(Product::find($this->product->id))->toBeNull();
    });

    test('duplicate product method creates copy', function () {
        Livewire::test(ProductShow::class, ['product' => $this->product])
            ->call('duplicateProduct')
            ->assertDispatched('success');

        $duplicatedProduct = Product::where('parent_sku', 'TEST123-COPY')->first();

        expect($duplicatedProduct)->not()->toBeNull()
            ->and($duplicatedProduct->name)->toBe('Test Product (Copy)')
            ->and($duplicatedProduct->parent_sku)->toBe('TEST123-COPY');
    });

    test('product tabs property returns correct tab configuration', function () {
        $component = Livewire::test(ProductShow::class, ['product' => $this->product]);

        $tabs = $component->instance()->getProductTabsProperty();

        expect($tabs)->not()->toBeNull();

        // Test that tabs are properly configured
        $tabsArray = $tabs->toArray($this->product);

        expect($tabsArray)->toBeArray()
            ->and(collect($tabsArray)->pluck('label')->toArray())
            ->toContain('Overview', 'Variants', 'Marketplace', 'Attributes');
    });

    test('variants tab shows correct badge count using relationship method', function () {
        $component = Livewire::test(ProductShow::class, ['product' => $this->product]);

        $tabs = $component->instance()->getProductTabsProperty();
        $tabsArray = $tabs->toArray($this->product);

        $variantsTab = collect($tabsArray)->firstWhere('label', 'Variants');

        expect($variantsTab['badge'])->toBe(3); // Should match the 3 variants created
    });

    test('marketplace tab shows sync status correctly', function () {
        // Create sync status for the product
        $syncAccount = SyncAccount::factory()->create(['channel' => 'shopify']);
        SyncStatus::factory()->create([
            'product_id' => $this->product->id,
            'sync_account_id' => $syncAccount->id,
            'external_product_id' => 'shopify-123',
            'sync_status' => 'synced',
        ]);

        $component = Livewire::test(ProductShow::class, ['product' => $this->product->load(['syncStatuses.syncAccount'])]);

        $linkedCount = $component->instance()->getLinkedAccountsCount();
        expect($linkedCount)->toBe(1);
    });

    test('attributes count is calculated correctly', function () {
        $component = Livewire::test(ProductShow::class, ['product' => $this->product]);

        // Mock some attributes by calling the method
        $attributesCount = $component->instance()->getAttributesCount();
        expect($attributesCount)->toBeInt()->toBeGreaterThanOrEqual(0);
    });

    test('recent activity count works', function () {
        // Create some sync logs
        $syncAccount = SyncAccount::factory()->create();
        SyncLog::factory(2)->create([
            'product_id' => $this->product->id,
            'sync_account_id' => $syncAccount->id,
            'created_at' => now()->subDays(3), // Within 7 days
        ]);

        SyncLog::factory()->create([
            'product_id' => $this->product->id,
            'sync_account_id' => $syncAccount->id,
            'created_at' => now()->subDays(10), // Outside 7 days
        ]);

        $component = Livewire::test(ProductShow::class, ['product' => $this->product->load('syncLogs')]);

        $recentCount = $component->instance()->getRecentActivityCount();
        expect($recentCount)->toBe(2); // Only the 2 recent logs
    });

    test('shopify pricing update method validates sync account', function () {
        $invalidSyncAccount = SyncAccount::factory()->create(['channel' => 'amazon']);

        Livewire::test(ProductShow::class, ['product' => $this->product])
            ->call('updateShopifyPricing', $invalidSyncAccount->id)
            ->assertDispatched('error', 'Invalid Shopify account selected');
    });

    test('shopify pricing update requires marketplace links', function () {
        $shopifySyncAccount = SyncAccount::factory()->create(['channel' => 'shopify']);

        Livewire::test(ProductShow::class, ['product' => $this->product])
            ->call('updateShopifyPricing', $shopifySyncAccount->id)
            ->assertDispatched('error', 'No color links found for this Shopify account. Link colors first.');
    });

    test('has shopify links method works correctly', function () {
        $component = Livewire::test(ProductShow::class, ['product' => $this->product]);

        expect($component->instance()->hasShopifyLinks())->toBeFalse();

        // Create shopify sync account and marketplace link
        $shopifySyncAccount = SyncAccount::factory()->create(['channel' => 'shopify']);

        $this->product->marketplaceLinks()->create([
            'sync_account_id' => $shopifySyncAccount->id,
            'link_level' => 'product',
            'marketplace_data' => ['color_filter' => 'red'],
        ]);

        $componentWithLinks = Livewire::test(ProductShow::class, ['product' => $this->product->load('marketplaceLinks.syncAccount')]);
        expect($componentWithLinks->instance()->hasShopifyLinks())->toBeTrue();
    });

    test('marketplace badge color reflects sync status', function () {
        $component = Livewire::test(ProductShow::class, ['product' => $this->product]);

        // No links - should be gray
        expect($component->instance()->getMarketplaceBadgeColor())->toBe('gray');

        // Add successful sync
        $syncAccount = SyncAccount::factory()->create();
        SyncStatus::factory()->create([
            'product_id' => $this->product->id,
            'sync_account_id' => $syncAccount->id,
            'external_product_id' => 'ext-123',
            'sync_status' => 'synced',
        ]);

        $componentWithSync = Livewire::test(ProductShow::class, ['product' => $this->product->load(['syncStatuses'])]);
        expect($componentWithSync->instance()->getMarketplaceBadgeColor())->toBe('green');

        // Add failed sync
        SyncStatus::factory()->create([
            'product_id' => $this->product->id,
            'sync_account_id' => $syncAccount->id,
            'external_product_id' => 'ext-124',
            'sync_status' => 'failed',
        ]);

        $componentWithFailure = Livewire::test(ProductShow::class, ['product' => $this->product->fresh()->load(['syncStatuses'])]);
        expect($componentWithFailure->instance()->getMarketplaceBadgeColor())->toBe('red');
    });

    test('activity badge color reflects recent failures', function () {
        $syncAccount = SyncAccount::factory()->create();

        // Create successful recent log
        SyncLog::factory()->create([
            'product_id' => $this->product->id,
            'sync_account_id' => $syncAccount->id,
            'status' => 'success',
            'created_at' => now()->subDays(2),
        ]);

        $component = Livewire::test(ProductShow::class, ['product' => $this->product->load('syncLogs')]);
        expect($component->instance()->getActivityBadgeColor())->toBe('blue');

        // Add recent failure
        SyncLog::factory()->create([
            'product_id' => $this->product->id,
            'sync_account_id' => $syncAccount->id,
            'status' => 'failed',
            'created_at' => now()->subDays(1),
        ]);

        $componentWithFailure = Livewire::test(ProductShow::class, ['product' => $this->product->fresh()->load('syncLogs')]);
        expect($componentWithFailure->instance()->getActivityBadgeColor())->toBe('red');
    });

    test('component renders without errors', function () {
        Livewire::test(ProductShow::class, ['product' => $this->product])
            ->assertStatus(200)
            ->assertSee($this->product->name)
            ->assertSee($this->product->parent_sku);
    });
});
