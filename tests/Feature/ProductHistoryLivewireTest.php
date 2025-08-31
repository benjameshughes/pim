<?php

use App\Livewire\Products\ProductHistory;
use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\SyncAccount;
use App\Models\SyncLog;
use App\Models\User;
use Livewire\Livewire;

it('requires view-product-history permission to mount', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    $this->actingAs($user);

    expect(fn () => Livewire::test(ProductHistory::class, ['product' => $product]))
        ->toThrow(Illuminate\Auth\Access\AuthorizationException::class);
});

it('mounts successfully with proper permissions', function () {
    $user = User::factory()->withPermissions(['view-product-history'])->create();
    $product = Product::factory()->create();

    $this->actingAs($user);

    Livewire::test(ProductHistory::class, ['product' => $product])
        ->assertSet('activeTab', 'activity')
        ->assertOk();
});

it('loads product with sync logs', function () {
    $user = User::factory()->withPermissions(['view-product-history'])->create();
    $product = Product::factory()->create();
    $syncAccount = SyncAccount::factory()->create();

    // Create some sync logs
    SyncLog::factory()->count(3)->create([
        'product_id' => $product->id,
        'sync_account_id' => $syncAccount->id,
    ]);

    $this->actingAs($user);

    $component = Livewire::test(ProductHistory::class, ['product' => $product]);

    expect($component->get('product')->syncLogs)->toHaveCount(3);
});

it('can change active tab', function () {
    $user = User::factory()->withPermissions(['view-product-history'])->create();
    $product = Product::factory()->create();

    $this->actingAs($user);

    Livewire::test(ProductHistory::class, ['product' => $product])
        ->call('setActiveTab', 'sync')
        ->assertSet('activeTab', 'sync');
});

it('gets activity logs for product', function () {
    $user = User::factory()->withPermissions(['view-product-history'])->create();
    $product = Product::factory()->create();

    // Create activity logs for this product
    ActivityLog::factory()->count(3)->create([
        'event' => 'product.updated',
        'data' => [
            'subject' => [
                'id' => $product->id,
                'type' => 'Product',
                'name' => $product->name,
            ],
        ],
    ]);

    // Create activity logs for other products
    ActivityLog::factory()->count(2)->create([
        'event' => 'product.created',
        'data' => [
            'subject' => [
                'id' => 999,
                'type' => 'Product',
                'name' => 'Other Product',
            ],
        ],
    ]);

    $this->actingAs($user);

    $component = Livewire::test(ProductHistory::class, ['product' => $product]);
    $activityLogs = $component->get('activityLogs');

    expect($activityLogs)->toHaveCount(3);
});

it('gets sync logs from product relationship', function () {
    $user = User::factory()->withPermissions(['view-product-history'])->create();
    $product = Product::factory()->create();
    $syncAccount = SyncAccount::factory()->create();

    SyncLog::factory()->count(5)->create([
        'product_id' => $product->id,
        'sync_account_id' => $syncAccount->id,
    ]);

    $this->actingAs($user);

    $component = Livewire::test(ProductHistory::class, ['product' => $product]);
    $syncLogs = $component->get('syncLogs');

    expect($syncLogs)->toHaveCount(5);
});

it('combines activity and sync logs correctly', function () {
    $user = User::factory()->withPermissions(['view-product-history'])->create();
    $product = Product::factory()->create();
    $syncAccount = SyncAccount::factory()->create(['channel' => 'shopify']);

    // Create activity logs
    ActivityLog::factory()->count(2)->create([
        'event' => 'product.updated',
        'user_id' => $user->id,
        'occurred_at' => now()->subHours(2),
        'data' => [
            'subject' => [
                'id' => $product->id,
                'type' => 'Product',
                'name' => $product->name,
            ],
            'description' => 'Product updated by user',
        ],
    ]);

    // Create sync logs
    SyncLog::factory()->count(3)->create([
        'product_id' => $product->id,
        'sync_account_id' => $syncAccount->id,
        'action' => 'product_sync',
        'message' => 'Synced to Shopify',
        'created_at' => now()->subHours(1),
    ]);

    $this->actingAs($user);

    $component = Livewire::test(ProductHistory::class, ['product' => $product]);
    $combinedHistory = $component->get('combinedHistory');

    expect($combinedHistory)->toHaveCount(5)
        ->and($combinedHistory->where('type', 'activity'))->toHaveCount(2)
        ->and($combinedHistory->where('type', 'sync'))->toHaveCount(3);
});

it('sorts combined history by timestamp descending', function () {
    $user = User::factory()->withPermissions(['view-product-history'])->create();
    $product = Product::factory()->create();
    $syncAccount = SyncAccount::factory()->create();

    // Create logs with different timestamps
    ActivityLog::factory()->create([
        'event' => 'product.created',
        'occurred_at' => now()->subHours(3),
        'data' => [
            'subject' => [
                'id' => $product->id,
                'type' => 'Product',
                'name' => $product->name,
            ],
        ],
    ]);

    SyncLog::factory()->create([
        'product_id' => $product->id,
        'sync_account_id' => $syncAccount->id,
        'action' => 'sync',
        'created_at' => now()->subHours(1), // Most recent
    ]);

    ActivityLog::factory()->create([
        'event' => 'product.updated',
        'occurred_at' => now()->subHours(2),
        'data' => [
            'subject' => [
                'id' => $product->id,
                'type' => 'Product',
                'name' => $product->name,
            ],
        ],
    ]);

    $this->actingAs($user);

    $component = Livewire::test(ProductHistory::class, ['product' => $product]);
    $combinedHistory = $component->get('combinedHistory');

    expect($combinedHistory->first()->type)->toBe('sync') // Most recent
        ->and($combinedHistory->last()->event)->toBe('product.created'); // Oldest
});

it('maps activity log properties correctly', function () {
    $user = User::factory()->withPermissions(['view-product-history'])->create(['name' => 'John Doe']);
    $product = Product::factory()->create();

    ActivityLog::factory()->create([
        'event' => 'product.updated',
        'user_id' => $user->id,
        'occurred_at' => $timestamp = now()->subHours(2),
        'data' => [
            'subject' => [
                'id' => $product->id,
                'type' => 'Product',
                'name' => $product->name,
            ],
            'description' => 'Updated product details',
            'changes' => ['name' => ['old' => 'Old Name', 'new' => 'New Name']],
            'custom' => 'data',
        ],
    ]);

    $this->actingAs($user);

    $component = Livewire::test(ProductHistory::class, ['product' => $product]);
    $combinedHistory = $component->get('combinedHistory');
    $activityItem = $combinedHistory->first();

    expect($activityItem->type)->toBe('activity')
        ->and($activityItem->timestamp->format('Y-m-d H:i:s'))->toBe($timestamp->format('Y-m-d H:i:s'))
        ->and($activityItem->user_name)->toBe('John Doe')
        ->and($activityItem->event)->toBe('product.updated')
        ->and($activityItem->description)->toBe('Updated product details')
        ->and($activityItem->changes)->toBe(['name' => ['old' => 'Old Name', 'new' => 'New Name']])
        ->and($activityItem->details->get('custom'))->toBe('data');
});

it('maps sync log properties correctly', function () {
    $user = User::factory()->withPermissions(['view-product-history'])->create();
    $product = Product::factory()->create();
    $syncAccount = SyncAccount::factory()->create(['channel' => 'shopify']);

    SyncLog::factory()->create([
        'product_id' => $product->id,
        'sync_account_id' => $syncAccount->id,
        'action' => 'product_update',
        'status' => 'success',
        'message' => 'Product synced successfully',
        'details' => ['external_id' => 'shopify_123'],
        'created_at' => $timestamp = now()->subHours(1),
    ]);

    $this->actingAs($user);

    $component = Livewire::test(ProductHistory::class, ['product' => $product]);
    $combinedHistory = $component->get('combinedHistory');
    $syncItem = $combinedHistory->first();

    expect($syncItem->type)->toBe('sync')
        ->and($syncItem->timestamp->format('Y-m-d H:i:s'))->toBe($timestamp->format('Y-m-d H:i:s'))
        ->and($syncItem->user_name)->toBe('System')
        ->and($syncItem->event)->toBe('sync.product_update')
        ->and($syncItem->description)->toBe('Product synced successfully')
        ->and($syncItem->channel)->toBe('shopify')
        ->and($syncItem->status)->toBe('success')
        ->and($syncItem->details->get('external_id'))->toBe('shopify_123');
});

it('limits combined history to 100 items', function () {
    $user = User::factory()->withPermissions(['view-product-history'])->create();
    $product = Product::factory()->create();
    $syncAccount = SyncAccount::factory()->create();

    // Create more than 100 logs
    ActivityLog::factory()->count(60)->create([
        'data' => [
            'subject' => [
                'id' => $product->id,
                'type' => 'Product',
                'name' => $product->name,
            ],
        ],
    ]);

    SyncLog::factory()->count(60)->create([
        'product_id' => $product->id,
        'sync_account_id' => $syncAccount->id,
    ]);

    $this->actingAs($user);

    $component = Livewire::test(ProductHistory::class, ['product' => $product]);
    $combinedHistory = $component->get('combinedHistory');

    expect($combinedHistory)->toHaveCount(100);
});

it('passes correct data to view', function () {
    $user = User::factory()->withPermissions(['view-product-history'])->create();
    $product = Product::factory()->create();

    $this->actingAs($user);

    Livewire::test(ProductHistory::class, ['product' => $product])
        ->assertViewHas('combinedHistory')
        ->assertViewHas('activityLogs')
        ->assertViewHas('syncLogs');
});

it('handles product without any logs gracefully', function () {
    $user = User::factory()->withPermissions(['view-product-history'])->create();
    $product = Product::factory()->create();

    $this->actingAs($user);

    $component = Livewire::test(ProductHistory::class, ['product' => $product]);

    expect($component->get('activityLogs'))->toHaveCount(0)
        ->and($component->get('syncLogs'))->toHaveCount(0)
        ->and($component->get('combinedHistory'))->toHaveCount(0);
});
