<?php

namespace App\Services;

use App\Models\SyncAccount;
use App\Services\Marketplace\API\AbstractMarketplaceService;
use App\Services\Marketplace\API\MarketplaceClient;
use Exception;

/**
 * 🔄 SHOPIFY MIGRATION HELPER
 *
 * Helper class to ease migration from hardcoded ShopifyConnectService
 * to the new dynamic marketplace account system.
 *
 * This provides drop-in replacement methods and backward compatibility
 * while you gradually migrate your codebase.
 */
class ShopifyMigrationHelper
{
    private static ?AbstractMarketplaceService $shopifyClient = null;

    private static ?SyncAccount $defaultAccount = null;

    /**
     * 🚀 Get the Shopify client using the migrated SyncAccount
     *
     * This replaces: new ShopifyConnectService()
     */
    public static function getClient(?int $syncAccountId = null): AbstractMarketplaceService
    {
        if (self::$shopifyClient && ! $syncAccountId) {
            return self::$shopifyClient;
        }

        $account = $syncAccountId
            ? SyncAccount::findOrFail($syncAccountId)
            : self::getDefaultShopifyAccount();

        self::$shopifyClient = MarketplaceClient::for('shopify')
            ->withAccount($account)
            ->enableDebugMode()
            ->withRetryPolicy(3, 1000)
            ->build();

        return self::$shopifyClient;
    }

    /**
     * 🏪 Get the default (migrated) Shopify account
     */
    public static function getDefaultShopifyAccount(): SyncAccount
    {
        if (self::$defaultAccount) {
            return self::$defaultAccount;
        }

        self::$defaultAccount = SyncAccount::where('channel', 'shopify')
            ->where('is_active', true)
            ->first();

        if (! self::$defaultAccount) {
            throw new Exception(
                'No active Shopify sync account found. '.
                'Please run: php artisan shopify:migrate-to-sync-account'
            );
        }

        return self::$defaultAccount;
    }

    /**
     * 🔄 Backward compatibility method for createProduct
     *
     * Drop-in replacement for: $shopifyService->createProduct($data)
     */
    public static function createProduct(array $productData): array
    {
        return self::getClient()->createProduct($productData);
    }

    /**
     * 🔄 Backward compatibility method for updateProduct
     */
    public static function updateProduct(string $productId, array $productData): array
    {
        return self::getClient()->updateProduct($productId, $productData);
    }

    /**
     * 🔄 Backward compatibility method for getProducts
     */
    public static function getProducts(array $filters = []): \Illuminate\Support\Collection
    {
        return self::getClient()->getProducts($filters);
    }

    /**
     * 🔄 Backward compatibility method for getOrders
     */
    public static function getOrders(array $filters = []): \Illuminate\Support\Collection
    {
        return self::getClient()->getOrders($filters);
    }

    /**
     * 🧪 Test the connection
     */
    public static function testConnection(): array
    {
        return self::getClient()->testConnection();
    }

    /**
     * 📊 Get account information
     */
    public static function getAccountInfo(): array
    {
        $account = self::getDefaultShopifyAccount();
        $client = self::getClient();

        return [
            'sync_account_id' => $account->id,
            'account_name' => $account->name,
            'store_url' => $account->credentials['store_url'] ?? null,
            'api_version' => $account->credentials['api_version'] ?? null,
            'connection_status' => $client->testConnection(),
        ];
    }

    /**
     * 🔧 Clear cached client (useful for testing or switching accounts)
     */
    public static function clearCache(): void
    {
        self::$shopifyClient = null;
        self::$defaultAccount = null;
    }
}
