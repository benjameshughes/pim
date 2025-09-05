<?php

namespace App\Services\Marketplace;

use App\Models\SyncAccount;
use App\Services\Shopify\API\Client\ShopifyClient;

/**
 * Account-level operations exposed via Sync facade chaining:
 * Sync::marketplace('shopify')->account()->info()
 */
class AccountOperations
{
    public function __construct(
        private readonly SyncAccount $syncAccount,
        private readonly string $marketplace
    ) {}

    /**
     * Query marketplace API for account/shop details (non-mutating).
     * Returns a consistent structure used by identifier setup.
     *
     * @return array{success:bool, marketplace_details?:array, error?:string, summary?:string}
     */
    public function info(): array
    {
        return match ($this->marketplace) {
            'shopify' => $this->shopifyInfo(),
            'ebay' => $this->ebayInfo(),
            'mirakl' => $this->miraklInfo(),
            default => [
                'success' => false,
                'error' => "Account info not implemented for '{$this->marketplace}'",
            ],
        };
    }

    /**
     * Orchestrate identifier setup via actions while keeping facade chain ergonomic.
     * Returns the same structure as calling the actions directly.
     */
    public function setupIdentifiers(): array
    {
        return app(\App\Actions\Marketplace\Identifiers\SetupMarketplaceIdentifiers::class)
            ->execute($this->syncAccount);
    }

    /**
     * Orchestrate identifier refresh via actions.
     */
    public function refreshIdentifiers(): array
    {
        return app(\App\Actions\Marketplace\Identifiers\RefreshMarketplaceIdentifiers::class)
            ->execute($this->syncAccount);
    }

    private function shopifyInfo(): array
    {
        // Map credentials from account -> Shopify SDK config (with env fallbacks)
        $cred = $this->syncAccount->credentials ?? [];
        $config = [
            'ShopUrl' => $cred['shop_domain'] ?? $cred['ShopUrl'] ?? config('services.shopify.store_url'),
            'AccessToken' => $cred['access_token'] ?? $cred['AccessToken'] ?? config('services.shopify.access_token'),
            'ApiVersion' => $cred['api_version'] ?? $cred['ApiVersion'] ?? config('services.shopify.api_version', '2024-07'),
        ];

        // Resolve client via container if a fake is bound (tests), otherwise new with config
        $client = app()->bound(ShopifyClient::class)
            ? app(ShopifyClient::class)
            : new ShopifyClient($config);

        $shopInfo = $client->getShopInfo();
        if (!($shopInfo['success'] ?? false)) {
            return [
                'success' => false,
                'error' => $shopInfo['error'] ?? 'Failed to fetch Shopify shop information',
            ];
        }

        $shop = $shopInfo['data'];

        $identifiers = [
            'shop_details' => [
                'shop_name' => $shop['name'],
                'shop_domain' => $shop['domain'],
                'myshopify_domain' => $shop['myshopify_domain'],
                'shop_id' => $shop['id'],
                'plan' => $shop['plan_name'] ?? 'unknown',
                'timezone' => $shop['timezone'] ?? 'UTC',
                'currency' => $shop['currency'] ?? 'USD',
                'country' => $shop['country'] ?? 'Unknown',
                'email' => $shop['email'] ?? 'Unknown',
                'shop_owner' => $shop['shop_owner'] ?? 'Unknown',
                'products_count' => $shop['products_count'] ?? 0,
            ],
            'identifier_types' => [
                'product_id' => 'Shopify Product ID (gid://shopify/Product/ID)',
                'variant_id' => 'Shopify Variant ID (gid://shopify/ProductVariant/ID)',
                'handle' => 'Shopify Product Handle (URL slug)',
                'sku' => 'Shopify Variant SKU',
            ],
            'api_info' => [
                'api_version' => $config['ApiVersion'],
                'extracted_at' => now()->toISOString(),
                'extraction_method' => 'shopify_api',
            ],
        ];

        return [
            'success' => true,
            'marketplace_details' => $identifiers,
            'summary' => "Shopify shop '{$shop['name']}' info queried",
        ];
    }

    private function ebayInfo(): array
    {
        try {
            // For now, rely on env-backed EbayConnectService; future: accept per-account config
            $svc = app(\App\Services\EbayConnectService::class);
            $test = $svc->testConnection();
            if (!($test['success'] ?? false)) {
                return [
                    'success' => false,
                    'error' => $test['error'] ?? 'Failed to connect to eBay API',
                ];
            }

            $identifiers = [
                'account_details' => [
                    'environment' => $test['environment'] ?? 'SANDBOX',
                ],
                'identifier_types' => [
                    'listing_id' => 'eBay Listing ID',
                    'item_id' => 'eBay Item ID',
                    'sku' => 'Inventory SKU',
                    'offer_id' => 'Offer ID',
                ],
                'api_info' => [
                    'extracted_at' => now()->toISOString(),
                    'extraction_method' => 'ebay_api',
                ],
            ];

            return [
                'success' => true,
                'marketplace_details' => $identifiers,
                'summary' => 'eBay account info queried',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function miraklInfo(): array
    {
        // Detect operator
        $operator = $this->syncAccount->marketplace_subtype
            ?: $this->detectMiraklOperator($this->syncAccount);

        if (!$operator) {
            return [
                'success' => false,
                'error' => 'Unable to determine Mirakl operator for this account',
            ];
        }

        $client = \App\Services\Mirakl\API\MiraklApiClient::for($operator);
        $info = $client->getOperatorInfo();

        // Optional statistics (best effort)
        $stats = null;
        try { $stats = $client->getCompleteStatistics(); } catch (\Throwable $e) { $stats = null; }

        $identifiers = [
            'operator_details' => [
                'operator_type' => $operator,
                'operator_name' => $info['name'] ?? ucfirst($operator),
                'platform' => 'Mirakl',
                'currency' => $this->syncAccount->settings['auto_fetched_data']['currency'] ?? 'GBP',
                'base_url' => $info['base_url'] ?? null,
                'shop_name' => $info['shop_name'] ?? ($this->syncAccount->display_name ?? null),
                'shop_id' => $info['store_id'] ?? ($this->syncAccount->settings['auto_fetched_data']['shop_id'] ?? null),
            ],
            'identifier_types' => [
                'offer_id' => 'Mirakl Offer ID',
                'product_id' => 'Mirakl Product ID',
                'sku' => 'SKU',
                'shop_sku' => 'Shop SKU',
            ],
            'api_info' => [
                'extracted_at' => now()->toISOString(),
                'extraction_method' => 'mirakl_api',
            ],
            'statistics' => $stats,
        ];

        return [
            'success' => true,
            'marketplace_details' => $identifiers,
            'summary' => 'Mirakl operator info queried',
        ];
    }

    private function detectMiraklOperator(SyncAccount $account): ?string
    {
        $name = strtolower($account->name);
        $disp = strtolower($account->display_name);
        if (str_contains($name, 'bq') || str_contains($disp, 'b&q')) return 'bq';
        if (str_contains($name, 'debenhams') || str_contains($disp, 'debenhams')) return 'debenhams';
        if (str_contains($name, 'freemans') || str_contains($disp, 'freemans') || str_contains($name, 'frasers') || str_contains($disp, 'frasers')) return 'freemans';
        return null;
    }
}
