<?php

namespace App\Console\Commands;

use App\Models\SalesChannel;
use App\Models\SyncAccount;
use Exception;
use Illuminate\Console\Command;

/**
 * 🔄 SHOPIFY MIGRATION COMMAND
 *
 * Migrates hardcoded Shopify configuration from config/services.php
 * to the new dynamic SyncAccount system.
 *
 * This command will:
 * 1. Read your current Shopify configuration from env/config
 * 2. Create a SalesChannel for Shopify
 * 3. Create a SyncAccount with your existing credentials
 * 4. Test the connection to verify it works
 * 5. Provide next steps for updating your code
 */
class MigrateShopifyToSyncAccount extends Command
{
    protected $signature = 'shopify:migrate-to-sync-account 
                           {--name=Main Shopify Store : Name for the sync account}
                           {--test : Test connection after migration}
                           {--force : Skip confirmation prompts}';

    protected $description = '🔄 Migrate hardcoded Shopify config to dynamic SyncAccount system';

    public function handle(): int
    {
        $this->info('🚀 SHOPIFY CONFIGURATION MIGRATION');
        $this->newLine();

        // Step 1: Check current configuration
        $this->info('📋 Checking current Shopify configuration...');
        $currentConfig = $this->getCurrentShopifyConfig();

        if (! $currentConfig) {
            $this->error('❌ No Shopify configuration found in config/services.php');
            $this->line('Please ensure you have the following environment variables set:');
            $this->line('  - SHOPIFY_STORE_URL');
            $this->line('  - SHOPIFY_ACCESS_TOKEN');

            return 1;
        }

        $this->displayCurrentConfig($currentConfig);

        // Step 2: Confirmation
        if (! $this->option('force')) {
            if (! $this->confirm('Do you want to migrate this configuration to a SyncAccount?')) {
                $this->info('Migration cancelled.');

                return 0;
            }
        }

        // Step 3: Create SalesChannel
        $this->info('🏪 Creating Shopify sales channel...');
        $salesChannel = $this->createSalesChannel();
        $this->line("  ✅ Created sales channel: {$salesChannel->name} (ID: {$salesChannel->id})");

        // Step 4: Create SyncAccount
        $this->info('🔗 Creating sync account with your existing credentials...');
        $syncAccount = $this->createSyncAccount($salesChannel, $currentConfig);
        $this->line("  ✅ Created sync account: {$syncAccount->name} (ID: {$syncAccount->id})");

        // Step 5: Test connection (optional)
        if ($this->option('test')) {
            $this->testConnection($syncAccount);
        }

        // Step 6: Migration success and next steps
        $this->displayMigrationSuccess($syncAccount);

        return 0;
    }

    private function getCurrentShopifyConfig(): ?array
    {
        $storeUrl = config('services.shopify.store_url');
        $accessToken = config('services.shopify.access_token');
        $apiVersion = config('services.shopify.api_version');

        if (empty($storeUrl) || empty($accessToken)) {
            return null;
        }

        return [
            'store_url' => $storeUrl,
            'access_token' => $accessToken,
            'api_version' => $apiVersion ?? '2024-07',
            'webhook_secret' => config('services.shopify.webhook_secret'),
            'api_key' => config('services.shopify.api_key'),
            'api_secret' => config('services.shopify.api_secret'),
        ];
    }

    private function displayCurrentConfig(array $config): void
    {
        $this->table(
            ['Setting', 'Value'],
            [
                ['Store URL', $config['store_url']],
                ['Access Token', '***'.substr($config['access_token'], -8)],
                ['API Version', $config['api_version']],
                ['Webhook Secret', $config['webhook_secret'] ? 'Configured' : 'Not set'],
                ['API Key', $config['api_key'] ? 'Configured' : 'Not set'],
                ['API Secret', $config['api_secret'] ? 'Configured' : 'Not set'],
            ]
        );
    }

    private function createSalesChannel(): SalesChannel
    {
        return SalesChannel::firstOrCreate([
            'name' => 'Shopify',
        ], [
            'name' => 'Shopify',
            'display_name' => 'Shopify Store',
            'slug' => 'shopify',
            'type' => 'shopify',
            'is_active' => true,
        ]);
    }

    private function createSyncAccount(SalesChannel $salesChannel, array $config): SyncAccount
    {
        // Check if sync account already exists
        $existing = SyncAccount::where('channel', 'shopify')->first();
        if ($existing) {
            $this->warn("  ⚠️  Shopify sync account already exists (ID: {$existing->id})");
            if (! $this->option('force') && ! $this->confirm('Update existing sync account?')) {
                return $existing;
            }
        }

        $accountName = $this->option('name');

        // Prepare credentials (only include what's actually configured)
        $credentials = [
            'store_url' => $config['store_url'],
            'access_token' => $config['access_token'],
            'api_version' => $config['api_version'],
        ];

        if (! empty($config['webhook_secret'])) {
            $credentials['webhook_secret'] = $config['webhook_secret'];
        }

        if (! empty($config['api_key'])) {
            $credentials['api_key'] = $config['api_key'];
        }

        if (! empty($config['api_secret'])) {
            $credentials['api_secret'] = $config['api_secret'];
        }

        if ($existing) {
            $existing->update([
                'name' => $accountName,
                'display_name' => $accountName,
                'credentials' => $credentials,
                'settings' => [
                    'migrated_from_hardcoded' => true,
                    'migration_date' => now()->toISOString(),
                ],
                'is_active' => true,
            ]);

            return $existing;
        }

        return SyncAccount::create([
            'name' => $accountName,
            'channel' => 'shopify',
            'display_name' => $accountName,
            'credentials' => $credentials,
            'settings' => [
                'migrated_from_hardcoded' => true,
                'migration_date' => now()->toISOString(),
            ],
            'is_active' => true,
        ]);
    }

    private function testConnection(SyncAccount $syncAccount): void
    {
        $this->info('🧪 Testing connection to Shopify...');

        try {
            // Use the new marketplace abstraction layer
            $client = \App\Services\Marketplace\API\MarketplaceClient::for('shopify')
                ->withAccount($syncAccount)
                ->build();

            $result = $client->testConnection();

            if ($result['success']) {
                $this->line('  ✅ Connection test successful!');
                if (isset($result['shop_info'])) {
                    $this->line("  🏪 Shop: {$result['shop_info']['name']}");
                    $this->line("  🌍 Domain: {$result['shop_info']['domain']}");
                }
            } else {
                $this->error('  ❌ Connection test failed: '.($result['error'] ?? 'Unknown error'));
            }
        } catch (Exception $e) {
            $this->error('  ❌ Connection test failed: '.$e->getMessage());
        }
    }

    private function displayMigrationSuccess(SyncAccount $syncAccount): void
    {
        $this->newLine();
        $this->info('🎉 MIGRATION COMPLETED SUCCESSFULLY!');
        $this->newLine();

        $this->line('📋 <options=bold>Migration Summary:</options=bold>');
        $this->line("  • SyncAccount ID: {$syncAccount->id}");
        $this->line("  • Account Name: {$syncAccount->name}");
        $this->line("  • Channel: {$syncAccount->channel}");
        $this->line('  • Status: '.($syncAccount->is_active ? 'Active' : 'Inactive'));
        $this->newLine();

        $this->line('🔄 <options=bold>Next Steps - Update Your Code:</options=bold>');
        $this->newLine();

        $this->line('<options=bold>1. Replace old ShopifyConnectService usage:</options=bold>');
        $this->line('<comment>   // OLD (hardcoded):</comment>');
        $this->line('<comment>   $shopify = new ShopifyConnectService();</comment>');
        $this->line('');
        $this->line('<info>   // NEW (dynamic):</info>');
        $this->line("<info>   \$account = SyncAccount::find({$syncAccount->id});</info>");
        $this->line('<info>   $shopify = MarketplaceClient::for("shopify")->withAccount($account)->build();</info>');
        $this->newLine();

        $this->line('<options=bold>2. Update existing code patterns:</options=bold>');
        $this->line('<comment>   // OLD:</comment>');
        $this->line('<comment>   $shopify->createProduct($data);</comment>');
        $this->line('');
        $this->line('<info>   // NEW:</info>');
        $this->line('<info>   $shopify->createProduct($data); // Same method signature!</info>');
        $this->newLine();

        $this->line('<options=bold>3. Optional - Remove hardcoded config:</options=bold>');
        $this->line('   Once you\'ve updated all code, you can remove these from .env:');
        $this->line('   • SHOPIFY_STORE_URL');
        $this->line('   • SHOPIFY_ACCESS_TOKEN');
        $this->line('   • SHOPIFY_API_VERSION');
        $this->newLine();

        $this->line('<options=bold>4. Test the new integration:</options=bold>');
        $this->line('   php artisan test:marketplace-layer --marketplace=shopify');
        $this->newLine();

        $this->info('🚀 Your Shopify integration is now using the dynamic account system!');
        $this->line('You can manage multiple Shopify accounts through the UI marketplace integration wizard.');
    }
}
