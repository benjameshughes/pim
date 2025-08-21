<?php

namespace App\Services\Marketplace;

use App\Models\SyncAccount;
use App\Services\Shopify\API\Client\ShopifyClient;
use Illuminate\Support\Facades\Log;

/**
 * 🏷️ MARKETPLACE IDENTIFIER SERVICE 🏷️
 *
 * Manages marketplace-specific identifiers and account details.
 * Automatically extracts and stores marketplace information during integration setup.
 */
class MarketplaceIdentifierService
{
    /**
     * 🚀 SETUP MARKETPLACE IDENTIFIERS
     *
     * One-time setup to extract and store marketplace details
     */
    public function setupMarketplaceIdentifiers(SyncAccount $syncAccount): array
    {
        Log::info("🏷️ Setting up marketplace identifiers for {$syncAccount->channel} account: {$syncAccount->name}");

        try {
            $result = match ($syncAccount->channel) {
                'shopify' => $this->setupShopifyIdentifiers($syncAccount),
                'ebay' => $this->setupEbayIdentifiers($syncAccount),
                'amazon' => $this->setupAmazonIdentifiers($syncAccount),
                'mirakl' => $this->setupMiraklIdentifiers($syncAccount),
                default => ['success' => false, 'error' => "Unsupported channel: {$syncAccount->channel}"]
            };

            if ($result['success']) {
                // Update the sync account with extracted details
                $this->updateSyncAccountSettings($syncAccount, $result['marketplace_details']);

                Log::info("✅ Successfully setup identifiers for {$syncAccount->channel}");
            }

            return $result;

        } catch (\Exception $e) {
            Log::error("❌ Failed to setup identifiers for {$syncAccount->channel}: {$e->getMessage()}");

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'recovery_suggestions' => [
                    'Verify API credentials are correct',
                    'Check network connectivity to marketplace API',
                    'Ensure account has required permissions',
                ],
            ];
        }
    }

    /**
     * 🛍️ SETUP SHOPIFY IDENTIFIERS
     */
    private function setupShopifyIdentifiers(SyncAccount $syncAccount): array
    {
        try {
            $client = app(ShopifyClient::class);

            // Get shop details
            $shopInfo = $client->getShopInfo();
            if (! $shopInfo['success']) {
                throw new \Exception('Failed to fetch Shopify shop information: '.$shopInfo['error']);
            }

            $shop = $shopInfo['data'];

            // Extract key identifiers
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
                    'api_version' => config('services.shopify.api_version', '2024-07'),
                    'extracted_at' => now()->toISOString(),
                    'extraction_method' => 'shopify_api',
                ],
            ];

            return [
                'success' => true,
                'marketplace_details' => $identifiers,
                'summary' => "Shopify shop '{$shop['name']}' configured with identifier management",
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => "Shopify setup failed: {$e->getMessage()}",
            ];
        }
    }

    /**
     * 🏪 SETUP EBAY IDENTIFIERS
     */
    private function setupEbayIdentifiers(SyncAccount $syncAccount): array
    {
        // Placeholder for eBay identifier setup
        $identifiers = [
            'account_details' => [
                'user_id' => 'TBD', // Extract from eBay API
                'account_type' => 'business', // business/individual
                'marketplace' => 'EBAY_US', // EBAY_US, EBAY_UK, etc.
            ],
            'identifier_types' => [
                'listing_id' => 'eBay Listing ID',
                'item_id' => 'eBay Item ID',
                'sku' => 'eBay Inventory SKU',
                'offer_id' => 'eBay Offer ID',
            ],
            'api_info' => [
                'environment' => config('services.ebay.environment', 'SANDBOX'),
                'extracted_at' => now()->toISOString(),
                'extraction_method' => 'ebay_api',
            ],
        ];

        return [
            'success' => true,
            'marketplace_details' => $identifiers,
            'summary' => 'eBay account configured with identifier management',
        ];
    }

    /**
     * 📦 SETUP AMAZON IDENTIFIERS
     */
    private function setupAmazonIdentifiers(SyncAccount $syncAccount): array
    {
        // Placeholder for Amazon identifier setup
        $identifiers = [
            'account_details' => [
                'seller_id' => 'TBD', // Extract from Amazon SP-API
                'marketplace_id' => 'ATVPDKIKX0DER', // US marketplace
            ],
            'identifier_types' => [
                'asin' => 'Amazon Standard Identification Number',
                'seller_sku' => 'Amazon Seller SKU',
                'fnsku' => 'Fulfillment Network SKU (FBA)',
                'listing_id' => 'Amazon Listing ID',
            ],
            'api_info' => [
                'extracted_at' => now()->toISOString(),
                'extraction_method' => 'amazon_sp_api',
            ],
        ];

        return [
            'success' => true,
            'marketplace_details' => $identifiers,
            'summary' => 'Amazon account configured with identifier management',
        ];
    }

    /**
     * 🌐 SETUP MIRAKL IDENTIFIERS
     */
    private function setupMiraklIdentifiers(SyncAccount $syncAccount): array
    {
        try {
            // Determine the operator type based on account name or settings
            $operator = $this->detectMiraklOperatorFromAccount($syncAccount);

            if ($operator) {
                // Use auto-detected operator information from the sync account
                $operatorDetails = $this->getOperatorDetailsFromAccount($syncAccount, $operator);

                $identifiers = [
                    'operator_details' => [
                        'operator_type' => $operator,
                        'operator_name' => $operatorDetails['display_name'] ?? ucfirst($operator),
                        'platform' => 'Mirakl',
                        'currency' => $operatorDetails['currency'] ?? 'GBP',
                        'categories' => $operatorDetails['categories'] ?? null,
                        'base_url' => $syncAccount->credentials['base_url'] ?? null,
                        'shop_name' => $syncAccount->credentials['shop_name'] ?? null,
                        'shop_id' => $syncAccount->credentials['shop_id'] ?? null,
                    ],
                    'identifier_types' => [
                        'offer_id' => 'Mirakl Offer ID - Unique identifier for marketplace offers',
                        'product_id' => 'Mirakl Product ID - Product identifier within Mirakl catalog',
                        'variant_sku' => 'Variant SKU - Product variant stock keeping unit',
                        'shop_sku' => 'Shop SKU - Seller-specific product identifier',
                        'category_id' => 'Category ID - Marketplace category classification',
                        'barcode' => 'Product Barcode - EAN, UPC, or other barcode standard',
                    ],
                    'api_info' => [
                        'operator' => $operator,
                        'extracted_at' => now()->toISOString(),
                        'extraction_method' => 'mirakl_auto_detection',
                        'auto_detection_confidence' => $syncAccount->settings['operator_detection']['confidence'] ?? 'unknown',
                    ],
                ];

                return [
                    'success' => true,
                    'marketplace_details' => $identifiers,
                    'summary' => 'Mirakl '.($operatorDetails['display_name'] ?? ucfirst($operator)).' configured with operator-specific identifiers',
                ];
            } else {
                // Generic Mirakl setup when operator cannot be determined
                $identifiers = [
                    'operator_details' => [
                        'operator_type' => 'generic',
                        'operator_name' => 'Generic Mirakl',
                        'platform' => 'Mirakl',
                        'currency' => 'GBP',
                        'base_url' => $syncAccount->credentials['base_url'] ?? null,
                        'shop_name' => $syncAccount->credentials['shop_name'] ?? null,
                        'shop_id' => $syncAccount->credentials['shop_id'] ?? null,
                    ],
                    'identifier_types' => [
                        'offer_id' => 'Mirakl Offer ID - Unique identifier for marketplace offers',
                        'product_id' => 'Mirakl Product ID - Product identifier within Mirakl catalog',
                        'sku' => 'Mirakl SKU - Product stock keeping unit',
                        'shop_sku' => 'Shop SKU - Seller-specific product identifier',
                    ],
                    'api_info' => [
                        'extracted_at' => now()->toISOString(),
                        'extraction_method' => 'mirakl_generic',
                    ],
                ];

                return [
                    'success' => true,
                    'marketplace_details' => $identifiers,
                    'summary' => 'Mirakl account configured with generic identifier management',
                ];
            }

        } catch (\Exception $e) {
            Log::error('Mirakl identifier setup failed', [
                'account_id' => $syncAccount->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => "Mirakl setup failed: {$e->getMessage()}",
            ];
        }
    }

    /**
     * 🔍 DETECT MIRAKL OPERATOR FROM SYNC ACCOUNT
     */
    private function detectMiraklOperatorFromAccount(SyncAccount $syncAccount): ?string
    {
        $accountName = strtolower($syncAccount->name);
        $displayName = strtolower($syncAccount->display_name);

        // Check for B&Q
        if (str_contains($accountName, 'bq') || str_contains($displayName, 'b&q')) {
            return 'bq';
        }

        // Check for Debenhams
        if (str_contains($accountName, 'debenhams') || str_contains($displayName, 'debenhams')) {
            return 'debenhams';
        }

        // Check for Freemans
        if (str_contains($accountName, 'freemans') || str_contains($displayName, 'freemans') ||
            str_contains($accountName, 'frasers') || str_contains($displayName, 'frasers')) {
            return 'freemans';
        }

        // Check in marketplace_subtype field (set during auto-detection)
        if ($syncAccount->marketplace_subtype) {
            return $syncAccount->marketplace_subtype;
        }

        return null; // Generic Mirakl account
    }

    /**
     * 🏷️ GET OPERATOR DETAILS FROM ACCOUNT
     */
    private function getOperatorDetailsFromAccount(SyncAccount $syncAccount, string $operator): array
    {
        return match ($operator) {
            'freemans' => [
                'display_name' => 'Freemans (Frasers Group)',
                'currency' => 'GBP',
                'categories' => ['Home & Garden', 'Fashion', 'Electronics'],
            ],
            'bq' => [
                'display_name' => 'B&Q Marketplace',
                'currency' => 'GBP',
                'categories' => ['Home Improvement', 'Garden', 'Tools'],
            ],
            'debenhams' => [
                'display_name' => 'Debenhams Marketplace',
                'currency' => 'GBP',
                'categories' => ['Fashion', 'Beauty', 'Home'],
            ],
            default => [
                'display_name' => 'Unknown Mirakl Operator',
                'currency' => 'GBP',
                'categories' => [],
            ],
        };
    }

    /**
     * 💾 UPDATE SYNC ACCOUNT SETTINGS
     */
    private function updateSyncAccountSettings(SyncAccount $syncAccount, array $marketplaceDetails): void
    {
        // Use the SyncAccount's built-in method for updating marketplace identifiers
        $syncAccount->updateMarketplaceIdentifiers($marketplaceDetails);
    }

    /**
     * 🔍 GET MARKETPLACE IDENTIFIERS
     */
    public function getMarketplaceIdentifiers(SyncAccount $syncAccount): array
    {
        return $syncAccount->getMarketplaceIdentifiers();
    }

    /**
     * 📋 GET AVAILABLE IDENTIFIER TYPES
     */
    public function getAvailableIdentifierTypes(string $channel): array
    {
        return match ($channel) {
            'shopify' => [
                'product_id' => 'Shopify Product ID',
                'variant_id' => 'Shopify Variant ID',
                'handle' => 'Product Handle',
                'sku' => 'Variant SKU',
            ],
            'ebay' => [
                'listing_id' => 'eBay Listing ID',
                'item_id' => 'eBay Item ID',
                'sku' => 'Inventory SKU',
                'offer_id' => 'Offer ID',
            ],
            'amazon' => [
                'asin' => 'ASIN',
                'seller_sku' => 'Seller SKU',
                'fnsku' => 'FNSKU',
                'listing_id' => 'Listing ID',
            ],
            'mirakl' => [
                'offer_id' => 'Offer ID',
                'product_id' => 'Product ID',
                'sku' => 'SKU',
            ],
            default => []
        };
    }

    /**
     * ✅ CHECK IF IDENTIFIERS ARE SETUP
     */
    public function isIdentifierSetupComplete(SyncAccount $syncAccount): bool
    {
        return $syncAccount->isIdentifierSetupComplete();
    }

    /**
     * 🔄 REFRESH MARKETPLACE IDENTIFIERS
     */
    public function refreshMarketplaceIdentifiers(SyncAccount $syncAccount): array
    {
        Log::info("🔄 Refreshing marketplace identifiers for {$syncAccount->channel}");

        // Re-run the setup process to get latest marketplace details
        return $this->setupMarketplaceIdentifiers($syncAccount);
    }
}
