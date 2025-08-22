<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\SyncAccount;
use App\Models\SyncLog;
use App\Models\SyncStatus;
use App\Services\Marketplace\MarketplaceSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

describe('Marketplace Sync System', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        
        $this->shopifyAccount = SyncAccount::factory()->create(['channel' => 'shopify']);
        $this->ebayAccount = SyncAccount::factory()->create(['channel' => 'ebay']);
    });

    it('can sync product to multiple marketplaces', function () {
        Http::fake();

        $product = Product::factory()->create();
        ProductVariant::factory()->create(['product_id' => $product->id]);

        $syncService = new MarketplaceSyncService();
        $results = $syncService->syncToAllChannels($product, [
            $this->shopifyAccount,
            $this->ebayAccount,
        ]);

        expect($results)->toHaveCount(2);
        expect(SyncLog::count())->toBe(2);
    });

    it('creates sync status records', function () {
        $product = Product::factory()->create();

        $component = Livewire::test('marketplace.marketplace-sync-cards', ['product' => $product])
            ->call('syncToChannel', $this->shopifyAccount->id);

        expect(SyncStatus::where('product_id', $product->id)->count())->toBe(1);
        $syncStatus = SyncStatus::first();
        expect($syncStatus->channel)->toBe('shopify');
        expect($syncStatus->status)->toBeIn(['pending', 'syncing', 'completed', 'failed']);
    });

    it('handles sync failures gracefully', function () {
        Http::fake([
            '*' => Http::response(['error' => 'Service unavailable'], 503),
        ]);

        $product = Product::factory()->create();
        
        $syncService = new MarketplaceSyncService();
        $result = $syncService->syncToChannel($product, $this->shopifyAccount);

        expect($result['success'])->toBeFalse();
        expect(SyncLog::where('status', 'failed')->count())->toBe(1);
    });

    it('can retry failed syncs', function () {
        $product = Product::factory()->create();
        
        // Create failed sync log
        $syncLog = SyncLog::factory()->create([
            'product_id' => $product->id,
            'channel' => 'shopify',
            'status' => 'failed',
            'error_message' => 'Connection timeout',
        ]);

        Http::fake();

        $component = Livewire::test('marketplace.marketplace-sync-cards', ['product' => $product])
            ->call('retrySyncLog', $syncLog->id);

        $syncLog->refresh();
        expect($syncLog->status)->toBe('completed');
    });

    it('tracks sync performance metrics', function () {
        $products = Product::factory()->count(3)->create();
        
        Http::fake([
            '*shopify*' => Http::response(['success' => true], 200)->delay(100),
            '*ebay*' => Http::response(['success' => true], 200)->delay(200),
        ]);

        $syncService = new MarketplaceSyncService();
        
        foreach ($products as $product) {
            $syncService->syncToChannel($product, $this->shopifyAccount);
        }

        $logs = SyncLog::where('channel', 'shopify')->get();
        expect($logs)->toHaveCount(3);
        expect($logs->avg('duration_ms'))->toBeGreaterThan(0);
    });

    it('can bulk sync products', function () {
        $products = Product::factory()->count(5)->create();
        
        Http::fake();

        $component = Livewire::test('bulk-operations.bulk-operations-center')
            ->set('selectedProducts', $products->pluck('id')->toArray())
            ->set('selectedChannels', [$this->shopifyAccount->id])
            ->call('bulkSync');

        expect(SyncLog::count())->toBe(5);
        expect(SyncStatus::count())->toBe(5);
    });

    it('handles marketplace-specific validation', function () {
        $product = Product::factory()->create(['name' => '']); // Invalid for most marketplaces
        
        $syncService = new MarketplaceSyncService();
        $result = $syncService->syncToChannel($product, $this->shopifyAccount);

        expect($result['success'])->toBeFalse();
        expect($result['errors'])->toContain('Product name is required');
    });

    it('can sync pricing updates', function () {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        
        // Create existing sync status
        SyncStatus::factory()->create([
            'product_id' => $product->id,
            'channel' => 'shopify',
            'external_id' => '12345',
            'status' => 'synced',
        ]);

        Http::fake();

        $component = Livewire::test('pricing.pricing-form', ['variant' => $variant])
            ->set('pricing.retail_price', 39.99)
            ->call('save')
            ->call('syncPricingToMarketplaces');

        expect(SyncLog::where('sync_type', 'pricing')->count())->toBeGreaterThan(0);
    });

    it('provides sync status dashboard', function () {
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();

        SyncStatus::factory()->create(['product_id' => $product1->id, 'status' => 'synced']);
        SyncStatus::factory()->create(['product_id' => $product2->id, 'status' => 'failed']);

        $component = Livewire::test('marketplace.marketplace-sync-cards', ['product' => $product1]);

        expect($component->get('syncStatuses'))->toHaveCount(1);
        expect($component->get('syncStatuses')->first()->status)->toBe('synced');
    });
});