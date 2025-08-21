<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;

/**
 * 🔍 TEST MIRAKL PRODUCTS API
 *
 * Research and test the Mirakl Products API endpoints to understand:
 * - How to check if products exist
 * - How to create new catalog entries
 * - How to update existing catalog entries
 */
class TestMiraklProductsApi extends Command
{
    protected $signature = 'test:mirakl-products-api {marketplace=freemans}';

    protected $description = 'Research and test Mirakl Products API endpoints for upsertOrCreate functionality';

    public function handle(): int
    {
        $marketplace = $this->argument('marketplace');

        $this->info("🔍 Testing Mirakl Products API for {$marketplace}");
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $config = $this->getMarketplaceConfig($marketplace);
        if (! $config) {
            $this->error("❌ Unknown marketplace: {$marketplace}");

            return 1;
        }

        $client = new Client([
            'base_uri' => $config['base_url'],
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => $config['api_key'],
            ],
        ]);

        // Test various products API endpoints
        $this->testProductsEndpoints($client, $config);

        return 0;
    }

    protected function testProductsEndpoints(Client $client, array $config): void
    {
        $this->info('🌐 Testing Products API Endpoints');
        $this->newLine();

        // Test 1: GET /api/products - List products
        $this->testEndpoint($client, $config, 'GET', '/api/products', [], 'List products');

        // Test 2: GET /api/products with specific parameters
        $this->testEndpoint($client, $config, 'GET', '/api/products', [
            'query' => ['limit' => 10],
        ], 'List products with limit');

        // Test 3: GET /api/products with SKU filter
        $this->testEndpoint($client, $config, 'GET', '/api/products', [
            'query' => ['product_references' => '011-023'],
        ], 'Get specific product by SKU');

        // Test 4: Try different product query methods
        $this->testEndpoint($client, $config, 'GET', '/api/products', [
            'query' => ['product_ids' => '011-023'],
        ], 'Get product by ID');

        // Test 5: Check catalog upload endpoints
        $this->testEndpoint($client, $config, 'GET', '/api/products/imports', [], 'Product imports endpoint');

        // Test 6: Check for product creation endpoint
        $this->info('📋 Testing product creation methods...');
        $this->testProductCreation($client, $config);
    }

    protected function testProductCreation(Client $client, array $config): void
    {
        // Test different potential product creation endpoints
        $testPayload = [
            'products' => [
                [
                    'shop_sku' => 'TEST-API-PRODUCT-001',
                    'title' => 'Test API Product',
                    'description' => 'Testing product creation via API',
                    'brand' => 'Test Brand',
                    'category_code' => 'H02',
                ],
            ],
        ];

        // Try POST to /api/products
        $this->testEndpoint($client, $config, 'POST', '/api/products', [
            'json' => $testPayload,
        ], 'Create product via POST /api/products');

        // Try POST to /api/products/imports
        $this->testEndpoint($client, $config, 'POST', '/api/products/imports', [
            'json' => $testPayload,
        ], 'Create product via POST /api/products/imports');

        // Check if there's a file upload mechanism
        $this->testEndpoint($client, $config, 'GET', '/api/products/import/files', [], 'Product import files endpoint');
    }

    protected function testEndpoint(Client $client, array $config, string $method, string $endpoint, array $options = [], string $description = ''): void
    {
        try {
            // Add store_id to all requests
            if (! isset($options['query'])) {
                $options['query'] = [];
            }
            $options['query']['shop'] = $config['store_id'];

            $this->info("   🔍 Testing: {$description}");
            $this->info("   📡 {$method} {$endpoint}");

            if (isset($options['json'])) {
                $this->info('   📋 Payload: '.json_encode($options['json'], JSON_PRETTY_PRINT));
            }

            $response = $client->request($method, $endpoint, $options);
            $responseBody = $response->getBody()->getContents();
            $responseData = json_decode($responseBody, true);

            $this->info("   ✅ HTTP {$response->getStatusCode()} - Success");

            if (is_array($responseData)) {
                $keys = array_keys($responseData);
                $this->info('   📊 Response keys: '.implode(', ', array_slice($keys, 0, 5)));

                if (isset($responseData['total_count'])) {
                    $this->info("   📈 Total count: {$responseData['total_count']}");
                }

                if (isset($responseData['products'])) {
                    $this->info('   📦 Products found: '.count($responseData['products']));

                    // Show first product structure
                    if (! empty($responseData['products'][0])) {
                        $firstProduct = $responseData['products'][0];
                        $productKeys = array_keys($firstProduct);
                        $this->info('   🏷️  Product fields: '.implode(', ', array_slice($productKeys, 0, 10)));
                    }
                }

                if (isset($responseData['import_id'])) {
                    $this->info("   🆔 Import ID: {$responseData['import_id']}");
                }
            } else {
                $this->info('   📄 Response: '.substr($responseBody, 0, 200).'...');
            }

        } catch (GuzzleException $e) {
            $statusCode = $e->getCode();
            $this->error("   ❌ HTTP {$statusCode} - {$e->getMessage()}");

            if ($e->hasResponse()) {
                $errorBody = $e->getResponse()->getBody()->getContents();
                $this->error('   💬 Error details: '.substr($errorBody, 0, 300).'...');
            }
        }

        $this->newLine();
    }

    protected function getMarketplaceConfig(string $marketplace): ?array
    {
        switch ($marketplace) {
            case 'freemans':
                return [
                    'base_url' => config('services.mirakl_operators.freemans.base_url'),
                    'api_key' => config('services.mirakl_operators.freemans.api_key'),
                    'store_id' => config('services.mirakl_operators.freemans.store_id'),
                ];
            case 'debenhams':
                return [
                    'base_url' => config('services.mirakl_operators.debenhams.base_url'),
                    'api_key' => config('services.mirakl_operators.debenhams.api_key'),
                    'store_id' => config('services.mirakl_operators.debenhams.store_id'),
                ];
            case 'bq':
                return [
                    'base_url' => config('services.mirakl_operators.bq.base_url'),
                    'api_key' => config('services.mirakl_operators.bq.api_key'),
                    'store_id' => config('services.mirakl_operators.bq.store_id'),
                ];
            default:
                return null;
        }
    }
}
