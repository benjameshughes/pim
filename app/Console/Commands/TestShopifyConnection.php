<?php

namespace App\Console\Commands;

use App\Services\ShopifyConnectService;
use Illuminate\Console\Command;

/**
 * 🛍️ SHOPIFY CONNECTION DIAGNOSTIC COMMAND
 *
 * Tests all aspects of Shopify integration to identify what's broken
 */
class TestShopifyConnection extends Command
{
    protected $signature = 'shopify:test {--detailed : Show detailed output}';

    protected $description = 'Test Shopify API connection and integration status';

    public function handle(): int
    {
        $this->info('🛍️ SHOPIFY INTEGRATION DIAGNOSTIC TEST');
        $this->newLine();

        // Test 1: Configuration Check
        $this->info('1️⃣ Testing Configuration...');
        $configResult = $this->testConfiguration();

        if (! $configResult) {
            $this->error('❌ Configuration test failed! Check your .env variables.');

            return 1;
        }

        $this->info('✅ Configuration looks good!');
        $this->newLine();

        // Test 2: Service Initialization
        $this->info('2️⃣ Testing Service Initialization...');
        try {
            $shopifyService = app(ShopifyConnectService::class);
            $this->info('✅ ShopifyConnectService initialized successfully!');
        } catch (\Exception $e) {
            $this->error('❌ Service initialization failed: '.$e->getMessage());

            return 1;
        }
        $this->newLine();

        // Test 3: API Connection Test
        $this->info('3️⃣ Testing API Connection...');
        $connectionResult = $this->testApiConnection($shopifyService);

        if (! $connectionResult) {
            $this->error('❌ API connection failed!');

            return 1;
        }

        $this->info('✅ API connection successful!');
        $this->newLine();

        // Test 4: Basic Operations
        $this->info('4️⃣ Testing Basic Operations...');
        $operationsResult = $this->testBasicOperations($shopifyService);

        if (! $operationsResult) {
            $this->error('❌ Basic operations test failed!');

            return 1;
        }

        $this->info('✅ Basic operations working!');
        $this->newLine();

        // Test 5: Product Creation Test
        $this->info('5️⃣ Testing Product Creation (Test Mode)...');
        $productResult = $this->testProductCreation($shopifyService);

        if (! $productResult) {
            $this->error('❌ Product creation test failed!');

            return 1;
        }

        $this->info('✅ Product creation test successful!');
        $this->newLine();

        $this->info('🎉 ALL SHOPIFY TESTS PASSED! Integration is working correctly.');

        return 0;
    }

    private function testConfiguration(): bool
    {
        $storeUrl = config('services.shopify.store_url');
        $accessToken = config('services.shopify.access_token');
        $apiVersion = config('services.shopify.api_version');

        if ($this->option('detailed')) {
            $this->line('   Store URL: '.($storeUrl ? '✓ Set' : '✗ Missing'));
            $this->line('   Access Token: '.($accessToken ? '✓ Set ('.substr($accessToken, 0, 10).'...)' : '✗ Missing'));
            $this->line('   API Version: '.($apiVersion ?: '✗ Missing'));
        }

        return ! empty($storeUrl) && ! empty($accessToken) && ! empty($apiVersion);
    }

    private function testApiConnection(ShopifyConnectService $service): bool
    {
        try {
            $result = $service->testConnection();

            if ($this->option('detailed')) {
                $this->line('   Connection Status: '.(($result['success'] ?? false) ? '✅ Success' : '❌ Failed'));
                if (isset($result['shop_info'])) {
                    $this->line('   Shop Name: '.($result['shop_info']['name'] ?? 'Unknown'));
                    $this->line('   Shop Domain: '.($result['shop_info']['domain'] ?? 'Unknown'));
                }
                if (isset($result['error'])) {
                    $this->line('   Error: '.$result['error']);
                }
                if (isset($result['tests'])) {
                    foreach ($result['tests'] as $testName => $testResult) {
                        $status = ($testResult['success'] ?? false) ? '✅' : '❌';
                        $this->line("   {$testName}: {$status} ".($testResult['message'] ?? ''));
                    }
                }
            }

            return $result['success'] ?? false;
        } catch (\Exception $e) {
            if ($this->option('detailed')) {
                $this->line('   Error: '.$e->getMessage());
            }

            return false;
        }
    }

    private function testBasicOperations(ShopifyConnectService $service): bool
    {
        try {
            // Test shop info retrieval
            $shopInfo = $service->getShopInfo();

            if ($this->option('verbose') && $shopInfo['success']) {
                $this->line('   Shop Info Retrieved: ✅');
                $this->line('   Product Count: '.($shopInfo['data']['products_count'] ?? 'Unknown'));
            }

            return $shopInfo['success'];
        } catch (\Exception $e) {
            if ($this->option('detailed')) {
                $this->line('   Basic operations error: '.$e->getMessage());
            }

            return false;
        }
    }

    private function testProductCreation(ShopifyConnectService $service): bool
    {
        $testProduct = [
            'product' => [
                'title' => 'TEST PRODUCT - DELETE ME',
                'body_html' => '<p>This is a test product created by diagnostic command. Safe to delete.</p>',
                'vendor' => 'Test Vendor',
                'product_type' => 'Test',
                'status' => 'draft', // Keep as draft so it doesn't appear in store
                'variants' => [
                    [
                        'title' => 'Test Variant',
                        'price' => '9.99',
                        'sku' => 'TEST-001',
                        'inventory_management' => 'shopify',
                        'inventory_quantity' => 0,
                    ],
                ],
            ],
        ];

        try {
            $result = $service->createProduct($testProduct);

            if ($this->option('detailed')) {
                $this->line('   Product Creation: '.($result['success'] ? '✅ Success' : '❌ Failed'));
                if ($result['success'] && isset($result['product']['id'])) {
                    $this->line('   Test Product ID: '.$result['product']['id']);
                    $this->line('   🗑️ You can delete this test product from your Shopify admin');
                }
            }

            return $result['success'];
        } catch (\Exception $e) {
            if ($this->option('detailed')) {
                $this->line('   Product creation error: '.$e->getMessage());
            }

            return false;
        }
    }
}
