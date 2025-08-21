<?php

use App\Livewire\Marketplace\MarketplaceSyncCards;
use App\Models\Product;
use App\Models\SyncAccount;
use App\Models\SyncLog;
use App\Models\SyncStatus;
use Livewire\Livewire;

beforeEach(function () {
    $this->product = Product::factory()->create([
        'name' => 'Test Window Shade',
        'parent_sku' => 'TEST-001',
    ]);

    $this->shopifyAccount = SyncAccount::factory()->create([
        'channel' => 'shopify',
        'name' => 'Main Store',
        'is_active' => true,
        'credentials' => [
            'store_url' => 'test-store.myshopify.com',
            'access_token' => 'test_token',
        ],
    ]);

    $this->ebayAccount = SyncAccount::factory()->create([
        'channel' => 'ebay',
        'name' => 'eBay UK',
        'is_active' => true,
    ]);
});

it('can render the component with a product', function () {
    Livewire::test(MarketplaceSyncCards::class, ['product' => $this->product])
        ->assertStatus(200)
        ->assertSee('Marketplace Sync Status')
        ->assertSee('2 Accounts'); // Two accounts created in beforeEach
});

it('displays available sync accounts', function () {
    Livewire::test(MarketplaceSyncCards::class, ['product' => $this->product])
        ->assertStatus(200)
        ->assertSee('2 Accounts')
        ->assertSee('Shopify')
        ->assertSee('Main Store')
        ->assertSee('Ebay')
        ->assertSee('eBay UK');
});

it('shows not synced status for accounts without sync status', function () {
    Livewire::test(MarketplaceSyncCards::class, ['product' => $this->product])
        ->assertStatus(200)
        ->assertSee('Not Synced')
        ->assertSee('Never');
});

it('displays sync status for synced products', function () {
    $syncStatus = SyncStatus::factory()->create([
        'product_id' => $this->product->id,
        'sync_account_id' => $this->shopifyAccount->id,
        'sync_status' => 'synced',
        'external_product_id' => '123456',
        'last_synced_at' => now()->subHour(),
    ]);

    Livewire::test(MarketplaceSyncCards::class, ['product' => $this->product])
        ->assertStatus(200)
        ->assertSee('Synced')
        ->assertSee('1 hour ago');
});

it('shows failed sync status with error message', function () {
    $syncStatus = SyncStatus::factory()->create([
        'product_id' => $this->product->id,
        'sync_account_id' => $this->shopifyAccount->id,
        'sync_status' => 'failed',
        'error_message' => 'API connection timeout',
    ]);

    Livewire::test(MarketplaceSyncCards::class, ['product' => $this->product])
        ->assertStatus(200)
        ->assertSee('Failed')
        ->assertSee('API connection timeout');
});

it('can trigger sync to marketplace', function () {
    $component = Livewire::test(MarketplaceSyncCards::class, ['product' => $this->product])
        ->call('syncToMarketplace', $this->shopifyAccount->id);

    // Just check that no exception was thrown and method was called successfully
    expect($component)->not->toBeNull();
});

it('can trigger update marketplace listing', function () {
    $syncStatus = SyncStatus::factory()->create([
        'product_id' => $this->product->id,
        'sync_account_id' => $this->shopifyAccount->id,
        'external_product_id' => '123456',
    ]);

    $component = Livewire::test(MarketplaceSyncCards::class, ['product' => $this->product])
        ->call('updateMarketplaceListing', $this->shopifyAccount->id);

    // Just check that no exception was thrown and method was called successfully
    expect($component)->not->toBeNull();
});

it('displays sync badge colors correctly', function () {
    $syncedStatus = SyncStatus::factory()->create([
        'product_id' => $this->product->id,
        'sync_account_id' => $this->shopifyAccount->id,
        'sync_status' => 'synced',
    ]);

    $failedStatus = SyncStatus::factory()->create([
        'product_id' => $this->product->id,
        'sync_account_id' => $this->ebayAccount->id,
        'sync_status' => 'failed',
    ]);

    $component = Livewire::test(MarketplaceSyncCards::class, ['product' => $this->product]);

    // Check that we get the right badge colors through computed property
    $availableAccounts = $component->instance()->getAvailableAccountsProperty();

    expect($availableAccounts)->toHaveCount(2);

    $shopifyAccount = $availableAccounts->firstWhere('account.id', $this->shopifyAccount->id);
    $ebayAccount = $availableAccounts->firstWhere('account.id', $this->ebayAccount->id);

    expect($shopifyAccount->status)->toBe('synced');
    expect($ebayAccount->status)->toBe('failed');
});

it('shows external product id when available', function () {
    $syncStatus = SyncStatus::factory()->create([
        'product_id' => $this->product->id,
        'sync_account_id' => $this->shopifyAccount->id,
        'external_product_id' => 'shopify_product_123456789',
    ]);

    Livewire::test(MarketplaceSyncCards::class, ['product' => $this->product])
        ->assertStatus(200)
        ->assertSee('External ID')
        ->assertSee('shopify_product_123456');  // Truncated
});

it('displays recent sync logs', function () {
    $syncLog = SyncLog::factory()->create([
        'product_id' => $this->product->id,
        'sync_account_id' => $this->shopifyAccount->id,
        'action' => 'push',
        'status' => 'success',
        'message' => 'Product synced successfully',
        'duration_ms' => 1250,
    ]);

    Livewire::test(MarketplaceSyncCards::class, ['product' => $this->product])
        ->assertStatus(200)
        ->assertSee('Recent Sync Activity')
        ->assertSee('Push')
        ->assertSee('Shopify')
        ->assertSee('Product synced successfully');
});

it('handles inactive sync accounts', function () {
    $this->shopifyAccount->update(['is_active' => false]);

    Livewire::test(MarketplaceSyncCards::class, ['product' => $this->product])
        ->assertStatus(200)
        ->assertSee('1 Account')  // Only eBay should show
        ->assertSee('Ebay')
        ->assertDontSee('Shopify');
});

it('shows empty state when no active accounts', function () {
    SyncAccount::query()->update(['is_active' => false]);

    Livewire::test(MarketplaceSyncCards::class, ['product' => $this->product])
        ->assertStatus(200)
        ->assertSee('No Marketplace Accounts')
        ->assertSee('Set up marketplace integrations');
});

it('correctly identifies accounts needing sync', function () {
    // Create different sync statuses
    SyncStatus::factory()->create([
        'product_id' => $this->product->id,
        'sync_account_id' => $this->shopifyAccount->id,
        'sync_status' => 'pending',
    ]);

    SyncStatus::factory()->create([
        'product_id' => $this->product->id,
        'sync_account_id' => $this->ebayAccount->id,
        'sync_status' => 'synced',
    ]);

    $component = Livewire::test(MarketplaceSyncCards::class, ['product' => $this->product]);
    $availableAccounts = $component->instance()->getAvailableAccountsProperty();

    $shopifyAccount = $availableAccounts->firstWhere('account.id', $this->shopifyAccount->id);
    $ebayAccount = $availableAccounts->firstWhere('account.id', $this->ebayAccount->id);

    expect($shopifyAccount->needsSync)->toBeTrue();
    expect($ebayAccount->needsSync)->toBeFalse();
});

it('handles sync account not found gracefully', function () {
    expect(function () {
        Livewire::test(MarketplaceSyncCards::class, ['product' => $this->product])
            ->call('syncToMarketplace', 999);
    })->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

it('loads product relationships correctly', function () {
    $syncStatus = SyncStatus::factory()->create([
        'product_id' => $this->product->id,
        'sync_account_id' => $this->shopifyAccount->id,
    ]);

    $syncLog = SyncLog::factory()->create([
        'product_id' => $this->product->id,
        'sync_account_id' => $this->shopifyAccount->id,
    ]);

    $component = Livewire::test(MarketplaceSyncCards::class, ['product' => $this->product]);

    // Verify the product has the required relationships loaded
    $product = $component->instance()->product;

    expect($product->relationLoaded('syncStatuses'))->toBeTrue();
    expect($product->relationLoaded('syncLogs'))->toBeTrue();
    expect($product->syncStatuses)->toHaveCount(1);
    expect($product->syncLogs)->toHaveCount(1);
});

it('shows correct button text based on sync status', function () {
    // Create a pending sync status
    SyncStatus::factory()->create([
        'product_id' => $this->product->id,
        'sync_account_id' => $this->shopifyAccount->id,
        'sync_status' => 'pending',
    ]);

    // Create a synced status
    SyncStatus::factory()->create([
        'product_id' => $this->product->id,
        'sync_account_id' => $this->ebayAccount->id,
        'sync_status' => 'synced',
    ]);

    $component = Livewire::test(MarketplaceSyncCards::class, ['product' => $this->product]);
    $availableAccounts = $component->instance()->getAvailableAccountsProperty();

    $shopifyAccount = $availableAccounts->firstWhere('account.id', $this->shopifyAccount->id);
    $ebayAccount = $availableAccounts->firstWhere('account.id', $this->ebayAccount->id);

    expect($shopifyAccount->needsSync)->toBeTrue(); // pending = needs sync = "Sync" button
    expect($ebayAccount->needsSync)->toBeFalse(); // synced = doesn't need sync = "Update" button
});

it('limits sync logs to latest 5 entries', function () {
    // Create 10 sync logs
    SyncLog::factory()->count(10)->create([
        'product_id' => $this->product->id,
        'sync_account_id' => $this->shopifyAccount->id,
    ]);

    $component = Livewire::test(MarketplaceSyncCards::class, ['product' => $this->product]);
    $product = $component->instance()->product;

    expect($product->syncLogs)->toHaveCount(5);
});

it('can show linking modal', function () {
    $component = Livewire::test(MarketplaceSyncCards::class, ['product' => $this->product])
        ->call('showLinkingModal', $this->shopifyAccount->id);

    expect($component->get('showLinkingModal'))->toBeTrue();
    expect($component->get('linkingAccountId'))->toBe($this->shopifyAccount->id);
});

it('can close linking modal', function () {
    $component = Livewire::test(MarketplaceSyncCards::class, ['product' => $this->product])
        ->set('showLinkingModal', true)
        ->set('linkingAccountId', $this->shopifyAccount->id)
        ->set('externalProductId', '123456')
        ->call('closeLinkingModal');

    expect($component->get('showLinkingModal'))->toBeFalse();
    expect($component->get('linkingAccountId'))->toBeNull();
    expect($component->get('externalProductId'))->toBe('');
});

it('can link product to marketplace', function () {
    $component = Livewire::test(MarketplaceSyncCards::class, ['product' => $this->product])
        ->set('linkingAccountId', $this->shopifyAccount->id)
        ->set('externalProductId', 'shopify_12345')
        ->call('linkToMarketplace');

    // Check that sync status was created/updated
    $syncStatus = $this->product->fresh()->syncStatuses
        ->where('sync_account_id', $this->shopifyAccount->id)
        ->first();

    expect($syncStatus)->not->toBeNull();
    expect($syncStatus->external_product_id)->toBe('shopify_12345');
    expect($syncStatus->sync_status)->toBe('synced');
    expect($syncStatus->metadata['linked_manually'])->toBeTrue();
});

it('requires external product id for linking', function () {
    Livewire::test(MarketplaceSyncCards::class, ['product' => $this->product])
        ->set('linkingAccountId', $this->shopifyAccount->id)
        ->set('externalProductId', '')
        ->call('linkToMarketplace')
        ->assertHasErrors(['externalProductId']);
});

it('can unlink product from marketplace', function () {
    // First create a linked sync status
    $syncStatus = SyncStatus::factory()->create([
        'product_id' => $this->product->id,
        'sync_account_id' => $this->shopifyAccount->id,
        'external_product_id' => 'shopify_12345',
        'sync_status' => 'synced',
    ]);

    $component = Livewire::test(MarketplaceSyncCards::class, ['product' => $this->product])
        ->call('unlinkFromMarketplace', $this->shopifyAccount->id);

    // Check that sync status was updated to unlinked
    $syncStatus = $syncStatus->fresh();

    expect($syncStatus->external_product_id)->toBeNull();
    expect($syncStatus->sync_status)->toBe('pending');
    expect($syncStatus->metadata['unlinked_manually'])->toBeTrue();
    expect($syncStatus->metadata['previous_external_id'])->toBe('shopify_12345');
});

it('shows linked status for linked products', function () {
    SyncStatus::factory()->create([
        'product_id' => $this->product->id,
        'sync_account_id' => $this->shopifyAccount->id,
        'external_product_id' => 'shopify_12345',
        'sync_status' => 'synced',
    ]);

    $component = Livewire::test(MarketplaceSyncCards::class, ['product' => $this->product]);
    $availableAccounts = $component->instance()->getAvailableAccountsProperty();

    $shopifyAccount = $availableAccounts->firstWhere('account.id', $this->shopifyAccount->id);

    expect($shopifyAccount->isLinked)->toBeTrue();
    expect($shopifyAccount->linkingType)->toBe('auto'); // Since linked_manually is not set
});
