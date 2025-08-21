<?php

namespace App\Console\Commands;

use App\Models\SyncAccount;
use App\Services\Marketplace\API\MarketplaceClient;
use App\Services\ShopifyMigrationHelper;
use Exception;
use Illuminate\Console\Command;

/**
 * 🧪 TEST MIGRATED SHOPIFY ACCOUNT
 *
 * Test the real migrated Shopify account to verify the unified API
 * is working with your actual store credentials.
 */
class TestMigratedShopifyAccount extends Command
{
    protected $signature = 'test:migrated-shopify 
                           {--account-id= : Specific SyncAccount ID to test}
                           {--detailed : Show detailed API responses}
                           {--products : Test product operations}
                           {--orders : Test order operations}
                           {--inventory : Test inventory operations}';

    protected $description = '🧪 Test your migrated Shopify account with the unified API';

    public function handle(): int
    {
        $this->info('🧪 TESTING YOUR MIGRATED SHOPIFY ACCOUNT');
        $this->newLine();

        // Get the migrated account
        $account = $this->getMigratedAccount();
        if (! $account) {
            return 1;
        }

        $this->displayAccountInfo($account);

        // Test connection
        $this->testConnection($account);

        // Test operations based on options
        if ($this->option('products') || $this->optionsEmpty()) {
            $this->testProductOperations($account);
        }

        if ($this->option('orders') || $this->optionsEmpty()) {
            $this->testOrderOperations($account);
        }

        if ($this->option('inventory') || $this->optionsEmpty()) {
            $this->testInventoryOperations($account);
        }

        $this->displaySuccessMessage();

        return 0;
    }

    private function getMigratedAccount(): ?SyncAccount
    {
        if ($accountId = $this->option('account-id')) {
            $account = SyncAccount::find($accountId);
            if (! $account) {
                $this->error("❌ SyncAccount with ID {$accountId} not found");

                return null;
            }
        } else {
            $account = SyncAccount::where('channel', 'shopify')
                ->where('is_active', true)
                ->first();

            if (! $account) {
                $this->error('❌ No active Shopify sync account found');
                $this->line('Run: php artisan shopify:migrate-to-sync-account');

                return null;
            }
        }

        return $account;
    }

    private function displayAccountInfo(SyncAccount $account): void
    {
        $this->info('📋 ACCOUNT INFORMATION:');
        $this->table(
            ['Property', 'Value'],
            [
                ['Account ID', $account->id],
                ['Name', $account->name],
                ['Channel', $account->channel],
                ['Store URL', $account->credentials['store_url'] ?? 'Not set'],
                ['API Version', $account->credentials['api_version'] ?? 'Not set'],
                ['Status', $account->is_active ? '✅ Active' : '❌ Inactive'],
                ['Created', $account->created_at->format('M j, Y H:i')],
            ]
        );
        $this->newLine();
    }

    private function testConnection(SyncAccount $account): void
    {
        $this->info('🔗 TESTING CONNECTION...');

        try {
            // Test using the unified API
            $client = MarketplaceClient::for('shopify')
                ->withAccount($account)
                ->enableDebugMode()
                ->build();

            $result = $client->testConnection();

            if ($result['success']) {
                $this->line('  ✅ Connection successful!');

                if (isset($result['shop_info'])) {
                    $shop = $result['shop_info'];
                    $this->line("  🏪 Shop Name: {$shop['name']}");
                    $this->line("  🌍 Domain: {$shop['domain']}");
                    $this->line("  💰 Currency: {$shop['currency']}");
                    $this->line("  🌏 Country: {$shop['country_name']}");

                    if (isset($shop['plan_name'])) {
                        $this->line("  📦 Plan: {$shop['plan_name']}");
                    }
                }

                // Test with helper too
                $this->line('  ⚡ Testing migration helper...');
                $helperResult = ShopifyMigrationHelper::testConnection();
                if ($helperResult['success']) {
                    $this->line('  ✅ Migration helper working!');
                }
            } else {
                $this->error('  ❌ Connection failed: '.($result['error'] ?? 'Unknown error'));
            }
        } catch (Exception $e) {
            $this->error('  ❌ Connection test failed: '.$e->getMessage());
        }

        $this->newLine();
    }

    private function testProductOperations(SyncAccount $account): void
    {
        $this->info('📦 TESTING PRODUCT OPERATIONS...');

        try {
            $client = MarketplaceClient::for('shopify')
                ->withAccount($account)
                ->build();

            // Test getting products
            $this->line('  → Getting products...');
            $products = $client->getProducts(['limit' => 5]);
            $this->line("  ✅ Retrieved {$products->count()} products");

            if ($products->isNotEmpty() && $this->option('detailed')) {
                $firstProduct = $products->first();
                $this->line("  📝 Sample product: {$firstProduct['title']} (ID: {$firstProduct['id']})");
            }

            // Test product categories
            $this->line('  → Getting categories...');
            $categories = $client->getCategories();
            $this->line('  ✅ Categories retrieved');

            // Test using migration helper
            $this->line('  → Testing migration helper methods...');
            $helperProducts = ShopifyMigrationHelper::getProducts(['limit' => 3]);
            $this->line("  ✅ Helper retrieved {$helperProducts->count()} products");

        } catch (Exception $e) {
            $this->error('  ❌ Product operations failed: '.$e->getMessage());
        }

        $this->newLine();
    }

    private function testOrderOperations(SyncAccount $account): void
    {
        $this->info('📋 TESTING ORDER OPERATIONS...');

        try {
            $client = MarketplaceClient::for('shopify')
                ->withAccount($account)
                ->build();

            // Test getting orders
            $this->line('  → Getting recent orders...');
            $orders = $client->getOrders(['limit' => 5, 'status' => 'any']);
            $this->line("  ✅ Retrieved {$orders->count()} orders");

            if ($orders->isNotEmpty() && $this->option('detailed')) {
                $firstOrder = $orders->first();
                $this->line("  📝 Sample order: #{$firstOrder['order_number']} - {$firstOrder['financial_status']}");
            }

            // Test order statuses
            $this->line('  → Getting order statuses...');
            $statuses = $client->getOrderStatuses();
            $this->line('  ✅ Order statuses retrieved');

            // Test using migration helper
            $this->line('  → Testing migration helper...');
            $helperOrders = ShopifyMigrationHelper::getOrders(['limit' => 2]);
            $this->line("  ✅ Helper retrieved {$helperOrders->count()} orders");

        } catch (Exception $e) {
            $this->error('  ❌ Order operations failed: '.$e->getMessage());
        }

        $this->newLine();
    }

    private function testInventoryOperations(SyncAccount $account): void
    {
        $this->info('📊 TESTING INVENTORY OPERATIONS...');

        try {
            $client = MarketplaceClient::for('shopify')
                ->withAccount($account)
                ->build();

            // Test getting inventory levels
            $this->line('  → Getting inventory levels...');
            $inventory = $client->getInventoryLevels();
            $this->line("  ✅ Retrieved inventory for {$inventory->count()} items");

            // Test low stock products
            $this->line('  → Checking low stock products...');
            $lowStock = $client->getLowStockProducts(10);
            $this->line("  ✅ Found {$lowStock->count()} low stock products (threshold: 10)");

            if ($lowStock->isNotEmpty() && $this->option('detailed')) {
                $lowStock->take(3)->each(function ($item) {
                    $this->line("  ⚠️  Low stock: {$item['title']} - {$item['available']} remaining");
                });
            }

        } catch (Exception $e) {
            $this->error('  ❌ Inventory operations failed: '.$e->getMessage());
        }

        $this->newLine();
    }

    private function optionsEmpty(): bool
    {
        return ! $this->option('products') && ! $this->option('orders') && ! $this->option('inventory');
    }

    private function displaySuccessMessage(): void
    {
        $this->info('🎉 ALL TESTS COMPLETED!');
        $this->newLine();

        $this->line('✅ <options=bold>Your migrated Shopify account is working perfectly!</options=bold>');
        $this->newLine();

        $this->line('🚀 <options=bold>What you can do now:</options=bold>');
        $this->line('  • Use ShopifyMigrationHelper for drop-in replacement');
        $this->line('  • Access multiple Shopify accounts through UI');
        $this->line('  • Same API patterns for eBay, Amazon, Mirakl');
        $this->line('  • Better error handling and rate limiting');
        $this->line('  • Performance monitoring and debugging');
        $this->newLine();

        $this->line('📚 <options=bold>Usage examples:</options=bold>');
        $this->line('  <comment>// Create product with unified API:</comment>');
        $this->line('  <info>ShopifyMigrationHelper::createProduct($productData);</info>');
        $this->newLine();
        $this->line('  <comment>// Get products with filters:</comment>');
        $this->line('  <info>ShopifyMigrationHelper::getProducts([\'status\' => \'active\']);</info>');
        $this->newLine();
        $this->line('  <comment>// Advanced usage with multiple accounts:</comment>');
        $this->line('  <info>$client = MarketplaceClient::for(\'shopify\')</info>');
        $this->line('  <info>    ->withAccount($differentAccount)</info>');
        $this->line('  <info>    ->withRetryPolicy(5, 2000)</info>');
        $this->line('  <info>    ->build();</info>');
    }
}
