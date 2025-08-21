<?php

namespace Database\Seeders;

use App\Models\SyncAccount;
use Illuminate\Database\Seeder;

/**
 * 🏢 SYNC ACCOUNT SEEDER
 *
 * Seeds the unified sync accounts for all integrations.
 * Supports multi-account setup (eBay UK/US/DE, multiple Shopify stores)
 */
class SyncAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            // Shopify Accounts
            [
                'name' => 'main',
                'channel' => 'shopify',
                'display_name' => 'Main Shopify Store',
                'is_active' => true,
                'credentials' => [
                    'store_url' => env('SHOPIFY_STORE_URL'),
                    'access_token' => env('SHOPIFY_ACCESS_TOKEN'),
                    'api_version' => '2024-07',
                ],
                'settings' => [
                    'auto_sync' => true,
                    'sync_variants' => true,
                    'sync_inventory' => true,
                    'sync_pricing' => true,
                    'use_color_separation' => true,
                    'use_taxonomy' => true,
                ],
            ],

            // eBay Accounts (Multi-region)
            [
                'name' => 'uk',
                'channel' => 'ebay',
                'display_name' => 'eBay UK',
                'is_active' => true,
                'credentials' => [
                    'client_id' => env('EBAY_CLIENT_ID'),
                    'client_secret' => env('EBAY_CLIENT_SECRET'),
                    'environment' => env('EBAY_ENVIRONMENT', 'SANDBOX'),
                    'site_id' => 3, // UK
                ],
                'settings' => [
                    'default_listing_type' => 'fixed_price',
                    'default_duration' => 30,
                    'auto_sync' => true,
                    'best_offer_enabled' => true,
                    'currency' => 'GBP',
                ],
            ],
            [
                'name' => 'us',
                'channel' => 'ebay',
                'display_name' => 'eBay US',
                'is_active' => false, // Disabled by default
                'credentials' => [
                    'client_id' => env('EBAY_US_CLIENT_ID'),
                    'client_secret' => env('EBAY_US_CLIENT_SECRET'),
                    'environment' => env('EBAY_ENVIRONMENT', 'SANDBOX'),
                    'site_id' => 0, // US
                ],
                'settings' => [
                    'default_listing_type' => 'fixed_price',
                    'default_duration' => 30,
                    'auto_sync' => false,
                    'best_offer_enabled' => true,
                    'currency' => 'USD',
                ],
            ],
            [
                'name' => 'de',
                'channel' => 'ebay',
                'display_name' => 'eBay Germany',
                'is_active' => false, // Disabled by default
                'credentials' => [
                    'client_id' => env('EBAY_DE_CLIENT_ID'),
                    'client_secret' => env('EBAY_DE_CLIENT_SECRET'),
                    'environment' => env('EBAY_ENVIRONMENT', 'SANDBOX'),
                    'site_id' => 77, // Germany
                ],
                'settings' => [
                    'default_listing_type' => 'fixed_price',
                    'default_duration' => 30,
                    'auto_sync' => false,
                    'best_offer_enabled' => false,
                    'currency' => 'EUR',
                ],
            ],

            // Amazon Accounts
            [
                'name' => 'uk',
                'channel' => 'amazon',
                'display_name' => 'Amazon UK',
                'is_active' => false, // Disabled until implemented
                'credentials' => [
                    'seller_id' => env('AMAZON_SELLER_ID'),
                    'marketplace_id' => 'A1F83G8C2ARO7P', // UK
                    'access_key' => env('AMAZON_ACCESS_KEY'),
                    'secret_key' => env('AMAZON_SECRET_KEY'),
                ],
                'settings' => [
                    'auto_sync' => false,
                    'fulfillment_method' => 'FBM', // Fulfilled by Merchant
                    'currency' => 'GBP',
                ],
            ],

            // Mirakl Connect Account
            [
                'name' => 'main',
                'channel' => 'mirakl',
                'display_name' => 'Main Mirakl Connect Account',
                'is_active' => true,
                'credentials' => [
                    'base_url' => env('MIRAKL_BASE_URL'),
                    'client_id' => env('MIRAKL_CLIENT_ID'),
                    'client_secret' => env('MIRAKL_CLIENT_SECRET'),
                ],
                'settings' => [
                    'auto_sync' => true,
                    'sync_inventory' => true,
                    'sync_pricing' => true,
                    'currency' => 'GBP',
                ],
            ],

            // Mirakl Operator Accounts
            [
                'name' => 'freemans',
                'channel' => 'mirakl_freemans',
                'display_name' => 'Freemans (Frasers Group)',
                'is_active' => true,
                'credentials' => [
                    'base_url' => env('MIRAKL_FREEMANS_BASE_URL'),
                    'api_key' => env('MIRAKL_FREEMANS_API_KEY'),
                    'store_id' => env('MIRAKL_FREEMANS_STORE_ID'),
                ],
                'settings' => [
                    'auto_sync' => true,
                    'sync_inventory' => true,
                    'sync_pricing' => true,
                    'sync_catalog' => true,
                    'currency' => 'GBP',
                    'logistic_class' => 'DL',
                    'leadtime_to_ship' => 2,
                    'default_state' => '11',
                    'category_code' => 'H02',
                ],
            ],
            [
                'name' => 'bq',
                'channel' => 'mirakl_bq',
                'display_name' => 'B&Q (Kingfisher Group)',
                'is_active' => true, // ✅ ACTIVATED - API credentials configured
                'credentials' => [
                    'base_url' => env('MIRAKL_BQ_BASE_URL'),
                    'api_key' => env('MIRAKL_BQ_API_KEY'),
                    'store_id' => env('MIRAKL_BQ_STORE_ID'),
                ],
                'settings' => [
                    'auto_sync' => true,
                    'sync_inventory' => true,
                    'sync_pricing' => true,
                    'sync_catalog' => true,
                    'currency' => 'GBP',
                    'logistic_class' => 'STD',
                    'leadtime_to_ship' => 5,
                    'default_state' => '11',
                    'category_code' => 'GARDEN01',
                    'required_performance_score' => 98,
                    'min_reviews' => 50,
                    'marketplace_type' => 'diy_home_improvement',
                ],
            ],
            [
                'name' => 'debenhams',
                'channel' => 'mirakl_debenhams',
                'display_name' => 'Debenhams (Frasers Group)',
                'is_active' => true, // ✅ ACTIVATED - API credentials configured
                'credentials' => [
                    'base_url' => env('MIRAKL_DEBENHAMS_BASE_URL'),
                    'api_key' => env('MIRAKL_DEBENHAMS_API_KEY'),
                    'store_id' => env('MIRAKL_DEBENHAMS_STORE_ID'),
                ],
                'settings' => [
                    'auto_sync' => true,
                    'sync_inventory' => true,
                    'sync_pricing' => true,
                    'sync_catalog' => true,
                    'currency' => 'GBP',
                    'logistic_class' => 'STD',
                    'leadtime_to_ship' => 3,
                    'default_state' => '11',
                    'category_code' => 'HOME02',
                    'required_performance_score' => 95,
                    'min_reviews' => 100,
                ],
            ],
        ];

        foreach ($accounts as $accountData) {
            SyncAccount::updateOrCreate(
                [
                    'name' => $accountData['name'],
                    'channel' => $accountData['channel'],
                ],
                $accountData
            );
        }

        $this->command->info('✅ Sync accounts seeded successfully!');
        $this->command->info('   📊 '.count($accounts).' sync accounts configured');
        $this->command->info('   🛍️ Shopify: 1 account (main)');
        $this->command->info('   🏪 eBay: 3 accounts (uk, us, de)');
        $this->command->info('   📦 Amazon: 1 account (uk)');
        $this->command->info('   🌐 Mirakl Connect: 1 account (main)');
        $this->command->info('   🏬 Mirakl Operators: 3 accounts (freemans, bq, debenhams)');
    }
}
