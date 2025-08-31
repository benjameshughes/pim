<?php

namespace App\Actions\Marketplace\Shopify;

use App\Models\SyncAccount;
use App\Services\Marketplace\ValueObjects\SyncResult;

/**
 * 🧪 TEST SHOPIFY CONNECTION ACTION
 *
 * Tests the connection to Shopify using the provided credentials.
 * Uses a simple GraphQL query to verify authentication and access.
 */
class TestShopifyConnectionAction
{
    /**
     * Test Shopify connection using official SDK
     *
     * @param  SyncAccount  $syncAccount  Shopify account to test
     * @return SyncResult Connection test result
     */
    public function execute(SyncAccount $syncAccount): SyncResult
    {
        $client = new \App\Services\Marketplace\Shopify\ShopifyGraphQLClient($syncAccount);

        $shopData = $client->testConnection();
        $shop = $shopData['shop'] ?? null;

        if (! $shop) {
            return SyncResult::failure('No shop data returned from Shopify');
        }

        return SyncResult::success(
            message: "Successfully connected to Shopify store: {$shop['name']}",
            data: [
                'shop_id' => $shop['id'],
                'shop_name' => $shop['name'],
                'shop_domain' => $shop['myshopifyDomain'],
                'shop_email' => $shop['email'],
                'connection_time' => now()->toISOString(),
                'api_version' => '2024-07',
            ],
            metadata: [
                'test_type' => 'graphql_shop_query',
                'account_name' => $syncAccount->name,
                'sdk' => 'laravel_http_client',
            ]
        );
    }
}
