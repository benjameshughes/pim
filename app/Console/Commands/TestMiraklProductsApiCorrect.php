<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;

/**
 * 🔍 TEST MIRAKL PRODUCTS API - CORRECT FORMAT
 *
 * Test the Mirakl Products API with correct formats based on initial findings
 */
class TestMiraklProductsApiCorrect extends Command
{
    protected $signature = 'test:mirakl-products-correct {marketplace=freemans}';

    protected $description = 'Test Mirakl Products API with correct parameter formats';

    public function handle(): int
    {
        $marketplace = $this->argument('marketplace');

        echo "🔍 Testing Mirakl Products API with Correct Formats for {$marketplace}\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

        $config = $this->getMarketplaceConfig($marketplace);
        if (! $config) {
            echo "❌ Unknown marketplace: {$marketplace}\n";

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

        // Test with correct formats
        $this->testCorrectFormats($client, $config);

        return 0;
    }

    protected function testCorrectFormats(Client $client, array $config): void
    {
        echo "🧪 Testing Products API with Correct Parameter Formats\n\n";

        // Test 1: Get product with correct product_references format
        $this->testGetProduct($client, $config, '011-023|SHOP_SKU');
        $this->testGetProduct($client, $config, '011-023|SKU');
        $this->testGetProduct($client, $config, '011-023|PRODUCT_ID');

        // Test 2: Get multiple products
        $this->testGetProduct($client, $config, '011-023|SHOP_SKU,005-104|SHOP_SKU');

        // Test 3: Check import files mechanism
        $this->testImportMechanisms($client, $config);

        // Test 4: Check available endpoints for product management
        $this->testProductManagementEndpoints($client, $config);
    }

    protected function testGetProduct(Client $client, array $config, string $productReferences): void
    {
        try {
            echo "   🔍 Testing product_references: {$productReferences}\n";

            $response = $client->request('GET', '/api/products', [
                'query' => [
                    'shop' => $config['store_id'],
                    'product_references' => $productReferences,
                ],
            ]);

            $responseBody = $response->getBody()->getContents();
            $responseData = json_decode($responseBody, true);

            echo "   ✅ HTTP {$response->getStatusCode()} - Success\n";

            if (isset($responseData['products'])) {
                $productCount = count($responseData['products']);
                echo "   📦 Products found: {$productCount}\n";

                foreach ($responseData['products'] as $product) {
                    echo '   🏷️  Product: '.($product['shop_sku'] ?? 'N/A').' - '.(substr($product['title'] ?? 'No title', 0, 50))."\n";
                    echo '      📋 Fields: '.implode(', ', array_keys($product))."\n";
                }
            }

        } catch (GuzzleException $e) {
            echo "   ❌ HTTP {$e->getCode()} - {$e->getMessage()}\n";

            if ($e->hasResponse()) {
                $errorBody = $e->getResponse()->getBody()->getContents();
                echo '   💬 Error: '.substr($errorBody, 0, 200)."...\n";
            }
        }

        echo "\n";
    }

    protected function testImportMechanisms(Client $client, array $config): void
    {
        echo "📁 Testing Product Import Mechanisms\n\n";

        // Test import status endpoint
        try {
            echo "   🔍 Checking product import history...\n";

            $response = $client->request('GET', '/api/products/imports', [
                'query' => ['shop' => $config['store_id']],
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            echo '   ✅ Import history found: '.($responseData['total_count'] ?? 0)." imports\n";

            if (! empty($responseData['product_import_trackings'])) {
                echo "   📋 Recent imports:\n";
                foreach (array_slice($responseData['product_import_trackings'], 0, 3) as $import) {
                    $importId = $import['import_id'] ?? 'N/A';
                    $status = $import['import_status'] ?? 'N/A';
                    $date = $import['date_created'] ?? 'N/A';
                    echo "      • Import ID: {$importId} | Status: {$status} | Date: {$date}\n";
                }
            }

        } catch (GuzzleException $e) {
            echo "   ❌ Import history check failed: {$e->getMessage()}\n";
        }

        echo "\n";

        // Test file upload endpoint variations
        $this->testFileUploadEndpoints($client, $config);
    }

    protected function testFileUploadEndpoints(Client $client, array $config): void
    {
        echo "📤 Testing File Upload Endpoints\n";

        $endpoints = [
            '/api/products/imports',
            '/api/products/import',
            '/api/products/upload',
            '/api/product-imports',
        ];

        foreach ($endpoints as $endpoint) {
            try {
                echo "   🔍 Testing: {$endpoint}\n";

                // Test with multipart/form-data (file upload)
                $response = $client->request('POST', $endpoint, [
                    'query' => ['shop' => $config['store_id']],
                    'multipart' => [
                        [
                            'name' => 'file',
                            'contents' => "shop_sku,title,description,brand\nTEST-001,Test Product,Test Description,Test Brand",
                            'filename' => 'test_products.csv',
                            'headers' => ['Content-Type' => 'text/csv'],
                        ],
                    ],
                ]);

                echo "   ✅ HTTP {$response->getStatusCode()} - File upload accepted\n";
                $responseBody = $response->getBody()->getContents();
                echo '   📋 Response: '.substr($responseBody, 0, 200)."...\n";

            } catch (GuzzleException $e) {
                $statusCode = $e->getCode();
                if ($statusCode === 404) {
                    echo "   ⚠️  Endpoint not found\n";
                } elseif ($statusCode === 415) {
                    echo "   ⚠️  Media type issue - trying different format\n";
                } else {
                    echo "   ❌ HTTP {$statusCode} - {$e->getMessage()}\n";
                }
            }

            echo "\n";
        }
    }

    protected function testProductManagementEndpoints(Client $client, array $config): void
    {
        echo "🛠️  Testing Product Management Endpoints\n";

        $endpoints = [
            'GET /api/catalog' => '/api/catalog',
            'GET /api/categories' => '/api/categories',
            'GET /api/product-data-sheets' => '/api/product-data-sheets',
            'GET /api/products/exports' => '/api/products/exports',
        ];

        foreach ($endpoints as $description => $endpoint) {
            try {
                echo "   🔍 Testing: {$description}\n";

                $response = $client->request('GET', $endpoint, [
                    'query' => ['shop' => $config['store_id']],
                ]);

                echo "   ✅ HTTP {$response->getStatusCode()} - Available\n";
                $responseData = json_decode($response->getBody()->getContents(), true);

                if (is_array($responseData)) {
                    $keys = array_keys($responseData);
                    echo '   📊 Response keys: '.implode(', ', array_slice($keys, 0, 5))."\n";
                }

            } catch (GuzzleException $e) {
                if ($e->getCode() === 404) {
                    echo "   ⚠️  Not available\n";
                } else {
                    echo "   ❌ HTTP {$e->getCode()} - {$e->getMessage()}\n";
                }
            }

            echo "\n";
        }
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
