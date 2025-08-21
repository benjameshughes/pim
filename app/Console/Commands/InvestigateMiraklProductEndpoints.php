<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;

/**
 * 🔍 INVESTIGATE MIRAKL PRODUCT ENDPOINTS
 *
 * Deep investigation of potential JSON-based product creation endpoints
 */
class InvestigateMiraklProductEndpoints extends Command
{
    protected $signature = 'investigate:mirakl-products {marketplace=freemans}';

    protected $description = 'Investigate all possible JSON-based product creation endpoints';

    public function handle(): int
    {
        $marketplace = $this->argument('marketplace');

        echo "🔍 Deep Investigation of Mirakl Product Endpoints for {$marketplace}\n";
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

        // Test all possible product-related endpoints
        $this->investigateAllEndpoints($client, $config);

        return 0;
    }

    protected function investigateAllEndpoints(Client $client, array $config): void
    {
        echo "🧪 Testing All Possible Product Management Endpoints\n\n";

        // Test variations of product creation endpoints
        $productEndpoints = [
            'POST /api/products' => [
                'method' => 'POST',
                'endpoint' => '/api/products',
                'test_json' => true,
            ],
            'POST /api/product' => [
                'method' => 'POST',
                'endpoint' => '/api/product',
                'test_json' => true,
            ],
            'POST /api/catalog/products' => [
                'method' => 'POST',
                'endpoint' => '/api/catalog/products',
                'test_json' => true,
            ],
            'POST /api/products/create' => [
                'method' => 'POST',
                'endpoint' => '/api/products/create',
                'test_json' => true,
            ],
            'PUT /api/products' => [
                'method' => 'PUT',
                'endpoint' => '/api/products',
                'test_json' => true,
            ],
            'PATCH /api/products' => [
                'method' => 'PATCH',
                'endpoint' => '/api/products',
                'test_json' => true,
            ],
        ];

        foreach ($productEndpoints as $description => $config_data) {
            $this->testProductEndpoint($client, $config, $description, $config_data);
        }

        // Test bulk endpoints
        echo "\n📦 Testing Bulk Product Endpoints\n";
        $bulkEndpoints = [
            'POST /api/products/bulk' => [
                'method' => 'POST',
                'endpoint' => '/api/products/bulk',
                'test_json' => true,
            ],
            'POST /api/bulk/products' => [
                'method' => 'POST',
                'endpoint' => '/api/bulk/products',
                'test_json' => true,
            ],
            'POST /api/products/batch' => [
                'method' => 'POST',
                'endpoint' => '/api/products/batch',
                'test_json' => true,
            ],
        ];

        foreach ($bulkEndpoints as $description => $config_data) {
            $this->testProductEndpoint($client, $config, $description, $config_data);
        }

        // Test catalog management endpoints
        echo "\n📋 Testing Catalog Management Endpoints\n";
        $catalogEndpoints = [
            'POST /api/catalog' => [
                'method' => 'POST',
                'endpoint' => '/api/catalog',
                'test_json' => true,
            ],
            'POST /api/catalog/entries' => [
                'method' => 'POST',
                'endpoint' => '/api/catalog/entries',
                'test_json' => true,
            ],
            'POST /api/shop/products' => [
                'method' => 'POST',
                'endpoint' => '/api/shop/products',
                'test_json' => true,
            ],
        ];

        foreach ($catalogEndpoints as $description => $config_data) {
            $this->testProductEndpoint($client, $config, $description, $config_data);
        }

        // Test with different content types
        echo "\n🎭 Testing Different Content-Type Headers\n";
        $this->testContentTypes($client, $config);
    }

    protected function testProductEndpoint(Client $client, array $config, string $description, array $endpointConfig): void
    {
        $method = $endpointConfig['method'];
        $endpoint = $endpointConfig['endpoint'];

        // Sample product data for testing
        $testPayload = [
            'shop_sku' => 'INVESTIGATION-TEST-001',
            'title' => 'Investigation Test Product',
            'description' => 'Testing product creation endpoint',
            'brand' => 'Test Brand',
            'category_code' => 'H02',
        ];

        // For bulk endpoints, wrap in products array
        if (strpos($endpoint, 'bulk') !== false || strpos($endpoint, 'batch') !== false) {
            $testPayload = ['products' => [$testPayload]];
        }

        try {
            echo "   🔍 Testing: {$description}\n";

            $options = [
                'query' => ['shop' => $config['store_id']],
            ];

            if ($endpointConfig['test_json'] ?? false) {
                $options['json'] = $testPayload;
            }

            $response = $client->request($method, $endpoint, $options);
            $responseBody = $response->getBody()->getContents();

            echo "   ✅ HTTP {$response->getStatusCode()} - ENDPOINT EXISTS!\n";
            echo '   📋 Response: '.substr($responseBody, 0, 200)."...\n";

            // If we get a successful response, this might be the endpoint we need!
            if ($response->getStatusCode() < 400) {
                echo "   🎉 POTENTIAL WINNER! This endpoint accepts JSON product data\n";
            }

        } catch (GuzzleException $e) {
            $statusCode = $e->getCode();

            if ($statusCode === 405) {
                echo "   ⚠️  Method not allowed (endpoint exists but wrong method)\n";
            } elseif ($statusCode === 404) {
                echo "   ❌ Not found\n";
            } elseif ($statusCode === 400) {
                echo "   ⚠️  Bad request (endpoint exists but wrong payload format)\n";
                if ($e->hasResponse()) {
                    $errorBody = $e->getResponse()->getBody()->getContents();
                    echo '   💬 Error: '.substr($errorBody, 0, 100)."...\n";
                }
            } elseif ($statusCode === 415) {
                echo "   ⚠️  Unsupported media type (endpoint exists but needs different content-type)\n";
            } else {
                echo "   ❌ HTTP {$statusCode} - {$e->getMessage()}\n";
            }
        }

        echo "\n";
    }

    protected function testContentTypes(Client $client, array $config): void
    {
        $contentTypes = [
            'application/json',
            'application/vnd.mirakl+json',
            'application/vnd.api+json',
            'text/json',
        ];

        $testPayload = [
            'shop_sku' => 'CONTENT-TYPE-TEST-001',
            'title' => 'Content Type Test Product',
            'description' => 'Testing different content types',
        ];

        foreach ($contentTypes as $contentType) {
            try {
                echo "   🔍 Testing Content-Type: {$contentType}\n";

                $response = $client->request('POST', '/api/products', [
                    'headers' => [
                        'Content-Type' => $contentType,
                        'Accept' => 'application/json',
                        'Authorization' => $config['api_key'],
                    ],
                    'body' => json_encode($testPayload),
                    'query' => ['shop' => $config['store_id']],
                ]);

                echo "   ✅ HTTP {$response->getStatusCode()} - Content-Type accepted!\n";
                $responseBody = $response->getBody()->getContents();
                echo '   📋 Response: '.substr($responseBody, 0, 150)."...\n";

            } catch (GuzzleException $e) {
                $statusCode = $e->getCode();
                echo "   ❌ HTTP {$statusCode} - Not accepted\n";
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
