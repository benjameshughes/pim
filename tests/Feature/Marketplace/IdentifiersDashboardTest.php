<?php

use App\Livewire\Marketplace\IdentifiersDashboard;
use App\Models\SyncAccount;
use App\Models\User;
use App\Services\Shopify\API\Client\ShopifyClient;
use Livewire\Livewire;

it('sets up Shopify identifiers using account credentials and stores details in settings', function () {
    $user = User::factory()->withPermissions(['manage-marketplace-connections'])->create();
    $this->actingAs($user);

    // Create a Shopify sync account with credentials
    $account = SyncAccount::create([
        'name' => 'main',
        'display_name' => 'Main Shop',
        'channel' => 'shopify',
        'is_active' => true,
        'credentials' => [
            'ShopUrl' => 'test-shop.myshopify.com',
            'AccessToken' => 'shpat_test',
            'ApiVersion' => '2024-07',
        ],
        'settings' => [],
    ]);

    // Bind a fake Shopify client to return shop info
    $fake = new class() extends ShopifyClient {
        public function __construct(?array $config = null) {}
        public function getShopInfo(): array
        {
            return [
                'success' => true,
                'data' => [
                    'id' => 123,
                    'name' => 'Test Shop',
                    'domain' => 'test-shop.myshopify.com',
                    'myshopify_domain' => 'test-shop.myshopify.com',
                    'email' => 'owner@test.com',
                    'plan_name' => 'basic',
                    'timezone' => 'UTC',
                    'currency' => 'GBP',
                    'country' => 'GB',
                    'products_count' => 42,
                ],
            ];
        }
    };
    $this->app->instance(ShopifyClient::class, $fake);

    Livewire::test(IdentifiersDashboard::class)
        ->call('setupIdentifiers', $account->id)
        ->assertDispatched('success');

    $account->refresh();
    $details = $account->getMarketplaceDetails();

    expect($account->isIdentifierSetupComplete())->toBeTrue()
        ->and($details['shop_name'] ?? null)->toBe('Test Shop')
        ->and($details['shop_domain'] ?? null)->toBe('test-shop.myshopify.com')
        ->and($details['products_count'] ?? null)->toBe(42);
});

it('emits an error when Shopify identifier setup fails', function () {
    $user = User::factory()->withPermissions(['manage-marketplace-connections'])->create();
    $this->actingAs($user);

    $account = SyncAccount::create([
        'name' => 'main',
        'display_name' => 'Main Shop',
        'channel' => 'shopify',
        'is_active' => true,
        'credentials' => [
            'ShopUrl' => 'bad-shop.myshopify.com',
            'AccessToken' => 'invalid',
            'ApiVersion' => '2024-07',
        ],
    ]);

    // Fake client returns failure
    $fake = new class() extends ShopifyClient {
        public function __construct(?array $config = null) {}
        public function getShopInfo(): array
        {
            return [
                'success' => false,
                'error' => 'Invalid API key',
            ];
        }
    };
    $this->app->instance(ShopifyClient::class, $fake);

    Livewire::test(IdentifiersDashboard::class)
        ->call('setupIdentifiers', $account->id)
        ->assertDispatched('error');
});

