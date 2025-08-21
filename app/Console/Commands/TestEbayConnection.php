<?php

namespace App\Console\Commands;

use App\Services\EbayConnectService;
use Illuminate\Console\Command;

/**
 * 🏪 EBAY CONNECTION DIAGNOSTIC COMMAND
 *
 * Tests all aspects of eBay integration to identify what's broken
 */
class TestEbayConnection extends Command
{
    protected $signature = 'ebay:test {--detailed : Show detailed output}';

    protected $description = 'Test eBay API connection and integration status';

    public function handle(): int
    {
        $this->info('🏪 EBAY INTEGRATION DIAGNOSTIC TEST');
        $this->newLine();

        // Test 1: Configuration Check
        $this->info('1️⃣ Testing Configuration...');
        $configResult = $this->testConfiguration();

        if (! $configResult) {
            $this->error('❌ Configuration test failed! Check your .env eBay variables.');

            return 1;
        }

        $this->info('✅ Configuration looks good!');
        $this->newLine();

        // Test 2: Service Initialization
        $this->info('2️⃣ Testing Service Initialization...');
        try {
            $ebayService = app(EbayConnectService::class);
            $this->info('✅ EbayConnectService initialized successfully!');
        } catch (\Exception $e) {
            $this->error('❌ Service initialization failed: '.$e->getMessage());

            return 1;
        }
        $this->newLine();

        // Test 3: OAuth Token Test
        $this->info('3️⃣ Testing OAuth Token Generation...');
        $tokenResult = $this->testOAuthToken($ebayService);

        if (! $tokenResult) {
            $this->error('❌ OAuth token generation failed!');

            return 1;
        }

        $this->info('✅ OAuth token generation successful!');
        $this->newLine();

        // Test 4: API Connection Test
        $this->info('4️⃣ Testing API Connection...');
        $connectionResult = $this->testApiConnection($ebayService);

        if (! $connectionResult) {
            $this->error('❌ API connection failed!');

            return 1;
        }

        $this->info('✅ API connection successful!');
        $this->newLine();

        // Test 5: Basic Operations
        $this->info('5️⃣ Testing Basic Operations...');
        $operationsResult = $this->testBasicOperations($ebayService);

        if (! $operationsResult) {
            $this->error('❌ Basic operations test failed!');

            return 1;
        }

        $this->info('✅ Basic operations working!');
        $this->newLine();

        // Test 6: Inventory Item Test
        $this->info('6️⃣ Testing Inventory Item Creation (Dry Run)...');
        $inventoryResult = $this->testInventoryCreation($ebayService);

        if (! $inventoryResult) {
            $this->error('❌ Inventory creation test failed!');

            return 1;
        }

        $this->info('✅ Inventory creation test successful!');
        $this->newLine();

        $this->info('🎉 ALL EBAY TESTS PASSED! Integration is working correctly.');

        return 0;
    }

    private function testConfiguration(): bool
    {
        $clientId = config('services.ebay.client_id');
        $clientSecret = config('services.ebay.client_secret');
        $devId = config('services.ebay.dev_id');
        $environment = config('services.ebay.environment');

        if ($this->option('detailed')) {
            $this->line('   Environment: '.($environment ?: 'SANDBOX (default)'));
            $this->line('   Client ID: '.($clientId ? '✓ Set ('.substr($clientId, 0, 10).'...)' : '✗ Missing'));
            $this->line('   Client Secret: '.($clientSecret ? '✓ Set' : '✗ Missing'));
            $this->line('   Dev ID: '.($devId ? '✓ Set' : '✗ Missing'));
        }

        return ! empty($clientId) && ! empty($clientSecret) && ! empty($devId);
    }

    private function testOAuthToken(EbayConnectService $service): bool
    {
        try {
            $result = $service->getClientCredentialsToken();

            if ($this->option('detailed')) {
                $this->line('   Token Generation: '.($result['success'] ? '✅ Success' : '❌ Failed'));
                if ($result['success']) {
                    $this->line('   Token Type: '.($result['token_type'] ?? 'Bearer'));
                    $this->line('   Expires In: '.($result['expires_in'] ?? 'Unknown').' seconds');
                } else {
                    $this->line('   Error: '.($result['error'] ?? 'Unknown error'));
                }
            }

            return $result['success'];
        } catch (\Exception $e) {
            if ($this->option('detailed')) {
                $this->line('   Token error: '.$e->getMessage());
            }

            return false;
        }
    }

    private function testApiConnection(EbayConnectService $service): bool
    {
        try {
            $result = $service->testConnection();

            if ($this->option('detailed')) {
                $this->line('   Connection Status: '.($result['success'] ? '✅ Success' : '❌ Failed'));
                if ($result['success']) {
                    $this->line('   Environment: '.($result['environment'] ?? 'Unknown'));
                    $this->line('   Marketplaces Found: '.($result['marketplace_count'] ?? 0));
                } else {
                    $this->line('   Error: '.($result['error'] ?? 'Unknown error'));
                }
            }

            return $result['success'];
        } catch (\Exception $e) {
            if ($this->option('detailed')) {
                $this->line('   Connection error: '.$e->getMessage());
            }

            return false;
        }
    }

    private function testBasicOperations(EbayConnectService $service): bool
    {
        try {
            // Test account info retrieval
            $accountInfo = $service->getAccountInfo();

            if ($this->option('detailed') && $accountInfo['success']) {
                $this->line('   Account Info Retrieved: ✅');
                $this->line('   Environment: '.($accountInfo['data']['environment'] ?? 'Unknown'));
                if (isset($accountInfo['data']['note'])) {
                    $this->line('   Note: '.$accountInfo['data']['note']);
                }
            }

            return $accountInfo['success'];
        } catch (\Exception $e) {
            if ($this->option('detailed')) {
                $this->line('   Basic operations error: '.$e->getMessage());
            }

            return false;
        }
    }

    private function testInventoryCreation(EbayConnectService $service): bool
    {
        try {
            $result = $service->testInventoryItemCreation();

            if ($this->option('detailed')) {
                $this->line('   Inventory Test: '.($result['success'] ? '✅ Success' : '❌ Failed'));
                if ($result['success']) {
                    $this->line('   Test SKU: '.($result['test_sku'] ?? 'Unknown'));
                    $this->line('   Payload Valid: '.($result['payload_validated'] ? 'Yes' : 'No'));
                } else {
                    $this->line('   Error: '.($result['error'] ?? 'Unknown error'));
                }
            }

            return $result['success'];
        } catch (\Exception $e) {
            if ($this->option('detailed')) {
                $this->line('   Inventory creation error: '.$e->getMessage());
            }

            return false;
        }
    }
}
