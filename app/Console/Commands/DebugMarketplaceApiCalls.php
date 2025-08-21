<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;

/**
 * 🔍 DEBUG MARKETPLACE API CALLS
 *
 * Makes actual API calls to each marketplace to debug integration issues
 */
class DebugMarketplaceApiCalls extends Command
{
    protected $signature = 'debug:marketplace-apis {--marketplace= : Specific marketplace to test (freemans, debenhams, bq)}';

    protected $description = 'Debug actual API calls to Mirakl marketplaces to verify integration';

    public function handle(): int
    {
        $this->info('🔍 Debugging Marketplace API Calls');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $marketplace = $this->option('marketplace');

        if ($marketplace) {
            $this->testMarketplace($marketplace);
        } else {
            $this->testMarketplace('freemans');
            $this->newLine();
            $this->testMarketplace('debenhams');
            $this->newLine();
            $this->testMarketplace('bq');
        }

        return 0;
    }

    protected function testMarketplace(string $marketplace): void
    {
        $this->info("🏬 Testing {$marketplace} marketplace API calls");
        $this->info('═══════════════════════════════════════════════');

        switch ($marketplace) {
            case 'freemans':
                $this->testFreemansApi();
                break;
            case 'debenhams':
                $this->testDebenhamsApi();
                break;
            case 'bq':
                $this->testBqApi();
                break;
            default:
                $this->error("Unknown marketplace: {$marketplace}");
        }
    }

    protected function testFreemansApi(): void
    {
        $baseUrl = config('services.mirakl_operators.freemans.base_url');
        $apiKey = config('services.mirakl_operators.freemans.api_key');
        $storeId = config('services.mirakl_operators.freemans.store_id');

        $this->info('📊 Freemans Configuration:');
        $this->info("   Base URL: {$baseUrl}");
        $this->info('   API Key: '.substr($apiKey, 0, 8).'...');
        $this->info("   Store ID: {$storeId}");

        $this->makeTestApiCalls('Freemans', $baseUrl, $apiKey, $storeId);
    }

    protected function testDebenhamsApi(): void
    {
        $baseUrl = config('services.mirakl_operators.debenhams.base_url');
        $apiKey = config('services.mirakl_operators.debenhams.api_key');
        $storeId = config('services.mirakl_operators.debenhams.store_id');

        $this->info('📊 Debenhams Configuration:');
        $this->info("   Base URL: {$baseUrl}");
        $this->info('   API Key: '.substr($apiKey, 0, 8).'...');
        $this->info("   Store ID: {$storeId}");

        $this->makeTestApiCalls('Debenhams', $baseUrl, $apiKey, $storeId);
    }

    protected function testBqApi(): void
    {
        $baseUrl = config('services.mirakl_operators.bq.base_url');
        $apiKey = config('services.mirakl_operators.bq.api_key');
        $storeId = config('services.mirakl_operators.bq.store_id');

        $this->info('📊 B&Q Configuration:');
        $this->info("   Base URL: {$baseUrl}");
        $this->info('   API Key: '.substr($apiKey, 0, 8).'...');
        $this->info("   Store ID: {$storeId}");

        $this->makeTestApiCalls('B&Q', $baseUrl, $apiKey, $storeId);
    }

    protected function makeTestApiCalls(string $marketplaceName, string $baseUrl, string $apiKey, string $storeId): void
    {
        $client = new Client([
            'base_uri' => $baseUrl,
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => $apiKey,
            ],
        ]);

        $this->newLine();
        $this->info("🌐 Making API calls to {$marketplaceName}:");

        // Test 1: Basic connectivity
        $this->testEndpoint($client, $marketplaceName, 'GET', '/', $storeId, 'Root endpoint');

        // Test 2: Offers endpoint
        $this->testEndpoint($client, $marketplaceName, 'GET', '/api/offers', $storeId, 'Offers API');

        // Test 3: Products endpoint
        $this->testEndpoint($client, $marketplaceName, 'GET', '/api/products', $storeId, 'Products API');

        // Test 4: Shop info
        $this->testEndpoint($client, $marketplaceName, 'GET', '/api/shops', $storeId, 'Shop info');

        // Test 5: Categories
        $this->testEndpoint($client, $marketplaceName, 'GET', '/api/categories', $storeId, 'Categories');

        // Test 6: Check for existing offers
        $this->testEndpoint($client, $marketplaceName, 'GET', '/api/offers?limit=10', $storeId, 'Existing offers');
    }

    protected function testEndpoint(Client $client, string $marketplace, string $method, string $endpoint, string $storeId, string $description): void
    {
        try {
            $options = [];

            // Add store_id query parameter for all requests
            if (! empty($storeId)) {
                $options['query'] = ['shop' => $storeId];
            }

            $startTime = microtime(true);
            $response = $client->request($method, $endpoint, $options);
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $responseData = json_decode($responseBody, true);

            $this->info("   ✅ {$description}: HTTP {$statusCode} ({$duration}ms)");

            // Show response structure
            if (is_array($responseData)) {
                $keys = array_keys($responseData);
                $this->info('      Response keys: '.implode(', ', array_slice($keys, 0, 5)));

                if (isset($responseData['total_count'])) {
                    $this->info("      Total count: {$responseData['total_count']}");
                }

                if (isset($responseData['offers'])) {
                    $offersCount = count($responseData['offers']);
                    $this->info("      Offers found: {$offersCount}");
                }

                if (isset($responseData['products'])) {
                    $productsCount = count($responseData['products']);
                    $this->info("      Products found: {$productsCount}");
                }
            } else {
                $this->info('      Response: '.substr($responseBody, 0, 100).'...');
            }

        } catch (GuzzleException $e) {
            $statusCode = $e->getCode();
            $this->error("   ❌ {$description}: HTTP {$statusCode} - {$e->getMessage()}");

            if ($e->hasResponse()) {
                $responseBody = $e->getResponse()->getBody()->getContents();
                $this->error('      Response: '.substr($responseBody, 0, 200));
            }
        } catch (\Exception $e) {
            $this->error("   ❌ {$description}: {$e->getMessage()}");
        }
    }
}
