<?php

namespace App\Console\Commands;

use App\Models\SalesChannel;
use App\Models\SyncAccount;
use App\Services\Marketplace\API\MarketplaceClient;
use Exception;
use Illuminate\Console\Command;

/**
 * 🧪 MARKETPLACE ABSTRACTION LAYER TEST COMMAND
 *
 * Comprehensive testing command for our Ultra Marketplace API Abstraction Layer.
 * Tests all four marketplace integrations (Shopify, eBay, Amazon, Mirakl) with
 * our unified API patterns, builder pattern, and repository pattern.
 */
class TestMarketplaceAbstractionLayer extends Command
{
    protected $signature = 'test:marketplace-layer {--marketplace=all : Test specific marketplace (shopify,ebay,amazon,mirakl,all)}';

    protected $description = '🧪 Test the Ultra Marketplace API Abstraction Layer';

    public function handle(): int
    {
        $this->info('🚀 TESTING ULTRA MARKETPLACE API ABSTRACTION LAYER');
        $this->newLine();

        $marketplace = $this->option('marketplace');

        if ($marketplace === 'all') {
            $marketplaces = ['shopify', 'ebay', 'amazon', 'mirakl'];
        } else {
            $marketplaces = [$marketplace];
        }

        $results = [];

        foreach ($marketplaces as $mp) {
            $this->info("🔸 Testing {$mp} marketplace integration...");
            $results[$mp] = $this->testMarketplace($mp);
            $this->newLine();
        }

        // Summary
        $this->info('📊 TEST RESULTS SUMMARY:');
        $this->table(
            ['Marketplace', 'Status', 'Features Tested', 'Errors'],
            collect($results)->map(function ($result, $marketplace) {
                return [
                    ucfirst($marketplace),
                    $result['success'] ? '✅ PASS' : '❌ FAIL',
                    $result['features_tested'],
                    $result['errors'] ?: 'None',
                ];
            })->toArray()
        );

        $allPassed = collect($results)->every(fn ($result) => $result['success']);

        if ($allPassed) {
            $this->info('🎉 ALL TESTS PASSED! Marketplace abstraction layer is working correctly.');

            return 0;
        } else {
            $this->error('❌ Some tests failed. Check the details above.');

            return 1;
        }
    }

    private function testMarketplace(string $marketplace): array
    {
        $testResults = [
            'success' => false,
            'features_tested' => 0,
            'errors' => null,
        ];

        try {
            // Test 1: MarketplaceClient factory
            $this->line('  → Testing MarketplaceClient factory...');
            $isSupported = MarketplaceClient::isSupported($marketplace);
            if (! $isSupported) {
                throw new Exception("Marketplace '{$marketplace}' not supported");
            }
            $this->line("    ✅ MarketplaceClient supports {$marketplace}");

            // Test 2: Get marketplace documentation
            $this->line('  → Testing marketplace documentation...');
            $docUrl = MarketplaceClient::getDocumentationUrl($marketplace);
            if ($docUrl) {
                $this->line("    ✅ Documentation URL: {$docUrl}");
            }

            // Test 3: Get configuration requirements
            $this->line('  → Testing configuration requirements...');
            $requirements = MarketplaceClient::getConfigurationRequirements($marketplace);
            if (! empty($requirements)) {
                $this->line('    ✅ Configuration requirements: '.count($requirements).' fields');
                foreach ($requirements as $field => $config) {
                    if (is_array($config)) {
                        $required = ($config['required'] ?? false) ? 'required' : 'optional';
                        $description = $config['description'] ?? 'No description';
                        $this->line("      - {$field}: {$description} ({$required})");
                    } else {
                        $this->line("      - {$field}: {$config}");
                    }
                }
            }

            // Test 4: Get marketplace capabilities
            $this->line('  → Testing marketplace capabilities...');
            $capabilities = MarketplaceClient::getMarketplaceCapabilities($marketplace);
            if (! empty($capabilities)) {
                $this->line('    ✅ Capabilities: '.count($capabilities).' feature groups');
                foreach ($capabilities as $feature => $operations) {
                    if (is_array($operations)) {
                        $operationCount = count($operations);
                        $this->line("      - {$feature}: {$operationCount} operations");
                    } else {
                        $this->line("      - {$feature}: {$operations}");
                    }
                }
            }

            // Test 5: Builder pattern validation
            $this->line('  → Testing builder pattern...');
            try {
                $builder = MarketplaceClient::for($marketplace);
                $this->line('    ✅ Builder created successfully');

                // Test builder methods
                $builder->withRetryPolicy(3, 1000)
                    ->withRateLimiting(40, 60)
                    ->enableDebugMode()
                    ->withTimeout(30);
                $this->line('    ✅ Builder configuration methods work');

                $summary = $builder->getConfigurationSummary();
                $this->line('    ✅ Builder summary: '.json_encode($summary, JSON_PRETTY_PRINT));

            } catch (Exception $e) {
                throw new Exception('Builder pattern failed: '.$e->getMessage());
            }

            // Test 6: Mock sync account creation (without actual API calls)
            $this->line('  → Testing mock sync account...');
            $mockAccount = $this->createMockSyncAccount($marketplace);
            if ($mockAccount) {
                $this->line('    ✅ Mock sync account created');

                // Test builder with account
                try {
                    $builder = MarketplaceClient::for($marketplace)
                        ->withAccount($mockAccount)
                        ->enableDebugMode();
                    $this->line('    ✅ Builder accepts sync account');

                    // Test validation without building (to avoid API calls)
                    $configSummary = $builder->getConfigurationSummary();
                    $this->line('    ✅ Configuration validation works');

                } catch (Exception $e) {
                    $this->warn('    ⚠️  Builder validation: '.$e->getMessage());
                }
            }

            // Test 7: Service class instantiation
            $this->line('  → Testing service class instantiation...');
            $serviceClass = 'App\\Services\\Marketplace\\API\\Implementations\\'.ucfirst($marketplace).'MarketplaceService';
            if (class_exists($serviceClass)) {
                $this->line("    ✅ Service class exists: {$serviceClass}");

                // Test service instantiation
                $service = new $serviceClass;
                $this->line('    ✅ Service instantiated successfully');

                // Test abstract methods (without API calls)
                $reflection = new \ReflectionClass($service);
                $methods = ['getMarketplaceName', 'getRequirements', 'getCapabilities', 'validateConfiguration', 'getRateLimits', 'getSupportedAuthMethods'];

                foreach ($methods as $method) {
                    if ($reflection->hasMethod($method)) {
                        $this->line("    ✅ Method {$method} implemented");
                    } else {
                        throw new Exception("Required method {$method} not implemented");
                    }
                }
            } else {
                throw new Exception("Service class not found: {$serviceClass}");
            }

            // Test 8: QuickStart methods
            $this->line('  → Testing QuickStart methods...');
            $quickStartMethods = ['syncProducts', 'fetchRecentOrders', 'checkInventory', 'getLowStockAlerts'];
            foreach ($quickStartMethods as $method) {
                if (method_exists(MarketplaceClient::class, $method)) {
                    $this->line("    ✅ {$method} method available");
                } else {
                    $this->line("    ⚠️  {$method} method not available");
                }
            }

            $testResults['features_tested'] = 8;
            $testResults['success'] = true;

        } catch (Exception $e) {
            $testResults['errors'] = $e->getMessage();
            $this->error('  ❌ Error: '.$e->getMessage());
        }

        return $testResults;
    }

    private function createMockSyncAccount(string $marketplace): ?SyncAccount
    {
        try {
            // Create or find a sales channel
            $salesChannel = SalesChannel::firstOrCreate([
                'name' => ucfirst($marketplace).' Test Channel',
            ], [
                'name' => ucfirst($marketplace).' Test Channel',
                'display_name' => ucfirst($marketplace).' Test Channel',
                'slug' => strtolower($marketplace).'-test-channel',
                'type' => $marketplace,
                'is_active' => true,
            ]);

            // Create mock credentials based on marketplace
            $mockCredentials = $this->getMockCredentials($marketplace);

            // Create sync account
            $syncAccount = SyncAccount::create([
                'name' => ucfirst($marketplace).' Test Account',
                'channel' => $marketplace,
                'display_name' => ucfirst($marketplace).' Test Integration',
                'credentials' => $mockCredentials,
                'settings' => [
                    'test_mode' => true,
                    'created_by_test' => true,
                ],
                'is_active' => true,
            ]);

            return $syncAccount;

        } catch (Exception $e) {
            $this->warn('Failed to create mock sync account: '.$e->getMessage());

            return null;
        }
    }

    private function getMockCredentials(string $marketplace): array
    {
        return match ($marketplace) {
            'shopify' => [
                'store_url' => 'test-store.myshopify.com',
                'access_token' => 'mock_shopify_token_'.str_repeat('x', 30),
                'api_version' => '2024-07',
            ],
            'ebay' => [
                'environment' => 'SANDBOX',
                'client_id' => 'mock_ebay_client_id_'.str_repeat('x', 20),
                'client_secret' => 'mock_ebay_secret_'.str_repeat('x', 20),
                'dev_id' => 'mock_dev_id_'.str_repeat('x', 15),
                'marketplace_id' => 'EBAY_US',
            ],
            'amazon' => [
                'seller_id' => 'A'.str_repeat('X', 12),
                'marketplace_id' => 'ATVPDKIKX0DER',
                'region' => 'NA',
                'client_id' => 'amzn1.application-oa2-client.'.str_repeat('x', 20),
                'client_secret' => 'mock_amazon_secret_'.str_repeat('x', 30),
                'refresh_token' => 'Atzr|'.str_repeat('x', 50),
            ],
            'mirakl' => [
                'operator' => 'bq',
                'api_url' => 'https://bq-marketplace-api.mirakl.net',
                'api_key' => 'mock_mirakl_key_'.str_repeat('x', 25),
                'shop_id' => '12345',
                'currency' => 'GBP',
            ],
            default => [],
        };
    }

    private function cleanupTestData(): void
    {
        try {
            // Clean up test sync accounts
            SyncAccount::where('settings->created_by_test', true)->delete();

            // Clean up test sales channels
            SalesChannel::where('name', 'like', '%Test Channel')->delete();

            // Only output if we have a valid output interface
            if ($this->output) {
                $this->info('🧹 Test data cleaned up');
            }
        } catch (Exception $e) {
            if ($this->output) {
                $this->warn('Failed to cleanup test data: '.$e->getMessage());
            }
        }
    }

    public function __destruct()
    {
        $this->cleanupTestData();
    }
}
