<?php

namespace App\Actions\Marketplace;

use App\Models\SyncAccount;
use App\ValueObjects\ConnectionTestResult;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * 🧪 TEST MARKETPLACE CONNECTION ACTION
 *
 * Tests connectivity to marketplace APIs to verify credentials and configuration.
 * Follows single responsibility principle.
 */
class TestMarketplaceConnectionAction extends BaseMarketplaceAction
{
    /**
     * 🧪 EXECUTE: Test marketplace connection
     */
    public function execute(SyncAccount $account): ConnectionTestResult
    {
        $this->logActivity('connection_test_started', [
            'account_id' => $account->id,
            'marketplace_type' => $account->marketplace_type,
        ]);

        $startTime = microtime(true);

        try {
            // Get marketplace credentials
            $credentials = $account->getMarketplaceCredentials();

            if (! $credentials) {
                return $this->createFailureResult('No credentials found for this marketplace integration.');
            }

            // Test connection based on marketplace type
            $result = match ($account->marketplace_type) {
                'shopify' => $this->testShopifyConnection($credentials),
                'ebay' => $this->testEbayConnection($credentials),
                'amazon' => $this->testAmazonConnection($credentials),
                'mirakl' => $this->testMiraklConnection($credentials),
                default => $this->createFailureResult("Unsupported marketplace type: {$account->marketplace_type}")
            };

            $responseTime = round((microtime(true) - $startTime) * 1000); // Convert to milliseconds

            // Add response time to result
            if ($result->success) {
                $result = ConnectionTestResult::success(
                    $result->message,
                    $result->details,
                    $responseTime,
                    $result->endpoint
                );
            }

            $this->logActivity('connection_test_completed', [
                'account_id' => $account->id,
                'success' => $result->success,
                'response_time' => $responseTime,
                'message' => $result->message,
            ]);

            return $result;

        } catch (\Exception $e) {
            $responseTime = round((microtime(true) - $startTime) * 1000);

            $this->logActivity('connection_test_failed', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
                'response_time' => $responseTime,
            ], 'error');

            return $this->createFailureResult(
                'Connection test failed: '.$e->getMessage(),
                ['exception' => get_class($e), 'response_time' => $responseTime]
            );
        }
    }

    /**
     * 🛍️ TEST SHOPIFY CONNECTION
     */
    private function testShopifyConnection($credentials): ConnectionTestResult
    {
        $storeUrl = $credentials->getCredential('store_url');
        $accessToken = $credentials->getCredential('access_token');
        $apiVersion = $credentials->getCredential('api_version', '2024-07');

        $endpoint = "{$storeUrl}/admin/api/{$apiVersion}/shop.json";

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
                'Content-Type' => 'application/json',
            ])->timeout(10)->get($endpoint);

            if ($response->successful()) {
                $shopData = $response->json('shop');

                return ConnectionTestResult::success(
                    'Successfully connected to Shopify store',
                    [
                        'shop_name' => $shopData['name'] ?? 'Unknown',
                        'shop_domain' => $shopData['domain'] ?? 'Unknown',
                        'plan_name' => $shopData['plan_name'] ?? 'Unknown',
                        'api_version' => $apiVersion,
                    ],
                    null,
                    $endpoint
                );
            }

            return $this->createFailureResult(
                'Shopify API returned error: '.$response->status(),
                ['status_code' => $response->status(), 'response' => $response->body()]
            );

        } catch (RequestException $e) {
            return $this->createFailureResult(
                'Failed to connect to Shopify: '.$e->getMessage(),
                ['endpoint' => $endpoint]
            );
        }
    }

    /**
     * 📦 TEST EBAY CONNECTION
     */
    private function testEbayConnection($credentials): ConnectionTestResult
    {
        $environment = $credentials->getCredential('environment', 'SANDBOX');
        $clientId = $credentials->getCredential('client_id');
        $clientSecret = $credentials->getCredential('client_secret');

        // Determine base URL based on environment
        $baseUrl = $environment === 'PRODUCTION'
            ? 'https://api.ebay.com'
            : 'https://api.sandbox.ebay.com';

        $endpoint = "{$baseUrl}/sell/account/v1/privilege";

        try {
            // First, get OAuth token
            $tokenResponse = Http::asForm()->post("{$baseUrl}/identity/v1/oauth2/token", [
                'grant_type' => 'client_credentials',
                'scope' => 'https://api.ebay.com/oauth/api_scope',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

            if (! $tokenResponse->successful()) {
                return $this->createFailureResult(
                    'Failed to obtain eBay OAuth token',
                    ['token_response' => $tokenResponse->body()]
                );
            }

            $accessToken = $tokenResponse->json('access_token');

            // Test API access
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ])->timeout(10)->get($endpoint);

            if ($response->successful()) {
                return ConnectionTestResult::success(
                    'Successfully connected to eBay API',
                    [
                        'environment' => $environment,
                        'privileges' => $response->json('privileges') ?? [],
                    ],
                    null,
                    $endpoint
                );
            }

            return $this->createFailureResult(
                'eBay API returned error: '.$response->status(),
                ['status_code' => $response->status(), 'response' => $response->body()]
            );

        } catch (RequestException $e) {
            return $this->createFailureResult(
                'Failed to connect to eBay: '.$e->getMessage(),
                ['endpoint' => $endpoint]
            );
        }
    }

    /**
     * 📦 TEST AMAZON CONNECTION
     */
    private function testAmazonConnection($credentials): ConnectionTestResult
    {
        // Amazon SP-API connection test is more complex and requires proper AWS signing
        // For now, return a placeholder that validates credentials format

        $sellerId = $credentials->getCredential('seller_id');
        $marketplaceId = $credentials->getCredential('marketplace_id');
        $accessKey = $credentials->getCredential('access_key');
        $secretKey = $credentials->getCredential('secret_key');
        $region = $credentials->getCredential('region');

        // Basic validation
        if (empty($sellerId) || empty($marketplaceId) || empty($accessKey) || empty($secretKey)) {
            return $this->createFailureResult(
                'Missing required Amazon credentials',
                ['required_fields' => ['seller_id', 'marketplace_id', 'access_key', 'secret_key']]
            );
        }

        // TODO: Implement proper Amazon SP-API connection test with AWS signing
        return ConnectionTestResult::success(
            'Amazon credentials validated (full API test not yet implemented)',
            [
                'seller_id' => $sellerId,
                'marketplace_id' => $marketplaceId,
                'region' => $region,
                'note' => 'Full SP-API connection test coming soon',
            ]
        );
    }

    /**
     * 🏢 TEST MIRAKL CONNECTION
     */
    private function testMiraklConnection($credentials): ConnectionTestResult
    {
        $baseUrl = $credentials->getCredential('base_url');
        $apiKey = $credentials->getCredential('api_key');
        $operatorType = $credentials->getOperator();

        if (empty($baseUrl)) {
            return $this->createFailureResult(
                'Missing base_url credential for Mirakl connection test'
            );
        }

        if (empty($apiKey)) {
            return $this->createFailureResult(
                'Missing api_key credential for Mirakl connection test'
            );
        }

        $endpoint = rtrim($baseUrl, '/').'/api/account';

        try {
            $response = Http::withHeaders([
                'Authorization' => $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(10)->get($endpoint);

            if ($response->successful()) {
                $accountData = $response->json();

                return ConnectionTestResult::success(
                    'Successfully connected to Mirakl API',
                    [
                        'operator_type' => $operatorType,
                        'base_url' => $baseUrl,
                        'shop_id' => $accountData['shop_id'] ?? 'Unknown',
                        'shop_name' => $accountData['shop_name'] ?? 'Unknown',
                        'currency' => $accountData['currency_iso_code'] ?? 'Unknown',
                        'shop_state' => $accountData['shop_state'] ?? 'Unknown',
                    ],
                    null,
                    $endpoint
                );
            }

            return $this->createFailureResult(
                'Mirakl API returned error: '.$response->status(),
                ['status_code' => $response->status(), 'response' => $response->body()]
            );

        } catch (RequestException $e) {
            return $this->createFailureResult(
                'Failed to connect to Mirakl: '.$e->getMessage(),
                ['endpoint' => $endpoint, 'operator' => $operatorType]
            );
        }
    }

    /**
     * ❌ CREATE FAILURE RESULT
     */
    private function createFailureResult(string $message, array $details = []): ConnectionTestResult
    {
        return ConnectionTestResult::failure($message, $details);
    }
}
