<?php

use App\Models\SyncAccount;
use App\Services\Marketplace\Facades\Sync;
use App\Services\Shopify\API\Client\ShopifyClient;

it('returns shopify account info via Sync facade chaining', function () {
    // Arrange: create a Shopify sync account
    $account = SyncAccount::create([
        'name' => 'main',
        'display_name' => 'Main Shop',
        'channel' => 'shopify',
        'is_active' => true,
        'credentials' => [
            'shop_domain' => 'test-shop.myshopify.com',
            'access_token' => 'shpat_test',
            'api_version' => '2024-07',
        ],
    ]);

    // Bind a fake Shopify client that returns deterministic data
    $fake = new class() extends ShopifyClient {
        public function __construct(?array $config = null) {}
        public function getShopInfo(): array
        {
            return [
                'success' => true,
                'data' => [
                    'id' => 999,
                    'name' => 'Chained Test Shop',
                    'domain' => 'test-shop.myshopify.com',
                    'myshopify_domain' => 'test-shop.myshopify.com',
                    'plan_name' => 'basic',
                    'timezone' => 'UTC',
                    'currency' => 'GBP',
                    'country' => 'GB',
                    'products_count' => 7,
                ],
            ];
        }
    };
    $this->app->instance(ShopifyClient::class, $fake);

    // Act: call the fluent chain
    $result = Sync::marketplace('shopify')->account('main')->info();

    // Assert
    expect($result['success'])->toBeTrue()
        ->and($result['marketplace_details']['shop_details']['shop_name'])->toBe('Chained Test Shop')
        ->and($result['marketplace_details']['shop_details']['shop_domain'])->toBe('test-shop.myshopify.com')
        ->and($result['marketplace_details']['shop_details']['products_count'])->toBe(7);
});

