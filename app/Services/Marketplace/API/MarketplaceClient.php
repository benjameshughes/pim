<?php

namespace App\Services\Marketplace\API;

use App\Services\Marketplace\API\Builders\MarketplaceClientBuilder;

/**
 * 🌟 MARKETPLACE CLIENT FACADE
 *
 * Simple facade providing unified access to marketplace API operations.
 * This is the main entry point for all marketplace integrations.
 *
 * Usage Examples:
 *
 * // Basic product operations
 * $client = MarketplaceClient::for('shopify')->withAccount($account)->build();
 * $result = $client->products()->create($productData)->execute();
 *
 * // Fluent repository operations
 * $products = $client->productRepository()->byCategory('electronics')->get();
 *
 * // Advanced builder pattern
 * $syncResult = MarketplaceClient::for('ebay')
 *     ->withAccount($ebayAccount)
 *     ->enableSandboxMode()
 *     ->withRetryPolicy(5, 2000)
 *     ->build()
 *     ->products()
 *     ->sync($localProducts)
 *     ->withBatchSize(25)
 *     ->validateBeforeSubmit()
 *     ->execute();
 */
class MarketplaceClient
{
    /**
     * 🏗️ Create marketplace client builder
     *
     * This is the main factory method for creating marketplace clients.
     *
     * @param  string  $marketplace  The marketplace type (shopify, ebay, amazon, mirakl)
     * @return MarketplaceClientBuilder Fluent builder for configuring the client
     */
    public static function for(string $marketplace): MarketplaceClientBuilder
    {
        return new MarketplaceClientBuilder($marketplace);
    }

    /**
     * 📋 Get list of supported marketplaces
     *
     * @return array<string, string> Array of marketplace IDs and names
     */
    public static function getSupportedMarketplaces(): array
    {
        return MarketplaceClientBuilder::getSupportedMarketplaces();
    }

    /**
     * ✅ Validate marketplace name
     *
     * @param  string  $marketplace  The marketplace to validate
     * @return bool True if marketplace is supported
     */
    public static function isSupported(string $marketplace): bool
    {
        return array_key_exists(strtolower($marketplace), static::getSupportedMarketplaces());
    }

    /**
     * 📖 Get marketplace documentation URL
     *
     * @param  string  $marketplace  The marketplace type
     * @return string|null URL to marketplace documentation
     */
    public static function getDocumentationUrl(string $marketplace): ?string
    {
        $urls = [
            'shopify' => 'https://shopify.dev/docs/api',
            'ebay' => 'https://developer.ebay.com/api-docs',
            'amazon' => 'https://developer-docs.amazon.com/sp-api',
            'mirakl' => 'https://developers.mirakl.com/platform-operator/',
        ];

        return $urls[strtolower($marketplace)] ?? null;
    }

    /**
     * 🔧 Get marketplace configuration requirements
     *
     * @param  string  $marketplace  The marketplace type
     * @return array Configuration requirements and field descriptions
     */
    public static function getConfigurationRequirements(string $marketplace): array
    {
        $requirements = [
            'shopify' => [
                'store_url' => 'Your Shopify store URL (e.g., yourstore.myshopify.com)',
                'access_token' => 'Private app access token or admin API token',
                'api_version' => 'Shopify API version (optional, defaults to latest)',
            ],
            'ebay' => [
                'environment' => 'SANDBOX or PRODUCTION',
                'client_id' => 'Your eBay application client ID',
                'client_secret' => 'Your eBay application client secret',
                'dev_id' => 'Your eBay developer ID',
                'redirect_uri' => 'OAuth redirect URI (for user tokens)',
            ],
            'amazon' => [
                'seller_id' => 'Your Amazon seller ID',
                'marketplace_id' => 'Amazon marketplace ID (e.g., ATVPDKIKX0DER for US)',
                'access_key' => 'AWS access key ID',
                'secret_key' => 'AWS secret access key',
                'region' => 'AWS region (NA, EU, FE)',
            ],
            'mirakl' => [
                'api_url' => 'Mirakl operator API URL',
                'api_key' => 'Mirakl API key',
                'operator' => 'Operator identifier (bq, debenhams, freemans)',
            ],
        ];

        return $requirements[strtolower($marketplace)] ?? [];
    }

    /**
     * 📊 Get marketplace capabilities
     *
     * @param  string  $marketplace  The marketplace type
     * @return array Supported features and operations
     */
    public static function getMarketplaceCapabilities(string $marketplace): array
    {
        $capabilities = [
            'shopify' => [
                'products' => ['create', 'read', 'update', 'delete', 'bulk_operations'],
                'orders' => ['read', 'update_fulfillment', 'add_tracking'],
                'inventory' => ['read', 'update', 'bulk_update', 'locations'],
                'webhooks' => ['order_created', 'order_updated', 'product_updated'],
                'features' => ['variants', 'metafields', 'categories', 'images'],
            ],
            'ebay' => [
                'products' => ['create', 'read', 'update', 'delete'],
                'orders' => ['read', 'update_fulfillment'],
                'inventory' => ['read', 'update', 'reservations'],
                'features' => ['variations', 'business_policies', 'categories'],
            ],
            'amazon' => [
                'products' => ['create', 'read', 'update', 'delete'],
                'orders' => ['read', 'update_fulfillment', 'shipping'],
                'inventory' => ['read', 'update', 'fba_inventory'],
                'features' => ['variations', 'categories', 'enhanced_content'],
            ],
            'mirakl' => [
                'products' => ['create', 'read', 'update'],
                'orders' => ['read', 'update_fulfillment'],
                'inventory' => ['read', 'update'],
                'features' => ['multi_operator', 'categories', 'attributes'],
            ],
        ];

        return $capabilities[strtolower($marketplace)] ?? [];
    }

    /**
     * 🛍️ Quick product sync
     */
    public static function syncProducts(string $marketplace, $account, $products): array
    {
        return static::for($marketplace)
            ->withAccount($account)
            ->build()
            ->syncProducts(collect($products));
    }

    /**
     * 📦 Quick order fetch
     */
    public static function fetchRecentOrders(string $marketplace, $account, int $days = 7): array
    {
        return static::for($marketplace)
            ->withAccount($account)
            ->build()
            ->getOrders(['created_after' => now()->subDays($days)])
            ->toArray();
    }

    /**
     * 📊 Quick inventory check
     */
    public static function checkInventory(string $marketplace, $account, array $productIds = []): array
    {
        $client = static::for($marketplace)
            ->withAccount($account)
            ->build();

        return $client->getInventoryLevels($productIds)->toArray();
    }

    /**
     * ⚠️ Quick low stock alert
     */
    public static function getLowStockAlerts(string $marketplace, $account, int $threshold = 5): array
    {
        $client = static::for($marketplace)
            ->withAccount($account)
            ->build();

        return $client->inventoryRepository()
            ->lowStock($threshold)
            ->toArray();
    }
}
