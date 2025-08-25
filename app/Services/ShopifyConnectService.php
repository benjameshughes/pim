<?php

namespace App\Services;

use App\Services\Shopify\API\ShopifyApiClient;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/**
 * 🛍️ SHOPIFY CONNECT SERVICE
 *
 * Main service for Shopify connection management, testing, and basic operations.
 * Serves as the primary interface for Shopify integration.
 */
class ShopifyConnectService
{
    protected ShopifyApiClient $apiClient;

    protected array $config;

    public function __construct()
    {
        $this->config = [
            'store_url' => config('services.shopify.store_url'),
            'access_token' => config('services.shopify.access_token'),
            'api_version' => config('services.shopify.api_version', '2024-07'),
            'api_key' => config('services.shopify.api_key'),
            'api_secret' => config('services.shopify.api_secret'),
        ];

        // Initialize the API client if we have a store URL
        if ($this->config['store_url']) {
            $this->apiClient = ShopifyApiClient::for($this->config['store_url']);
        }
    }

    /**
     * ✅ TEST CONNECTION
     *
     * Comprehensive connection test for Shopify API
     *
     * @return array<string, mixed>
     */
    public function testConnection(): array
    {
        try {
            if (! $this->isConfigured()) {
                return [
                    'success' => false,
                    'error' => 'Shopify configuration is incomplete. Check your environment variables.',
                    'missing_config' => $this->getMissingConfig(),
                ];
            }

            // Use the API client's comprehensive test
            if (isset($this->apiClient)) {
                $apiTest = $this->apiClient->testAllConnections();
                // Fix the overall success calculation
                if (isset($apiTest['overall_success'])) {
                    $apiTest['success'] = $apiTest['overall_success'];
                }

                return $apiTest;
            }

            // Fallback to basic HTTP test
            return $this->basicConnectionTest();

        } catch (\Exception $e) {
            Log::error('Shopify connection test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 🏪 GET SHOP INFO
     *
     * Retrieve basic shop information
     *
     * @return array<string, mixed>
     */
    public function getShopInfo(): array
    {
        try {
            $client = new Client;
            $response = $client->get("https://{$this->config['store_url']}/admin/api/{$this->config['api_version']}/shop.json", [
                'headers' => [
                    'X-Shopify-Access-Token' => $this->config['access_token'],
                    'Content-Type' => 'application/json',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (! isset($data['shop'])) {
                throw new \Exception('Invalid response from Shopify API');
            }

            $shop = $data['shop'];

            return [
                'success' => true,
                'data' => [
                    'id' => $shop['id'],
                    'name' => $shop['name'],
                    'domain' => $shop['domain'],
                    'email' => $shop['email'],
                    'shop_owner' => $shop['shop_owner'],
                    'plan_name' => $shop['plan_name'],
                    'currency' => $shop['currency'],
                    'timezone' => $shop['timezone'],
                    'products_count' => $shop['products_count'] ?? null,
                ],
                'shop_info' => [
                    'name' => $shop['name'],
                    'domain' => $shop['domain'],
                ],
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 📦 CREATE PRODUCT
     *
     * Create a product in Shopify
     *
     * @param  array<string, mixed>  $productData
     * @return array<string, mixed>
     */
    public function createProduct(array $productData): array
    {
        try {
            $client = new Client;
            $response = $client->post("https://{$this->config['store_url']}/admin/api/{$this->config['api_version']}/products.json", [
                'headers' => [
                    'X-Shopify-Access-Token' => $this->config['access_token'],
                    'Content-Type' => 'application/json',
                ],
                'json' => $productData,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (! isset($data['product'])) {
                throw new \Exception('Product creation failed - invalid response');
            }

            return [
                'success' => true,
                'product' => $data['product'],
                'message' => 'Product created successfully',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 🔍 GET PRODUCT
     *
     * Retrieve a product from Shopify
     *
     * @return array<string, mixed>
     */
    public function getProduct(int $productId): array
    {
        try {
            $client = new Client;
            $response = $client->get("https://{$this->config['store_url']}/admin/api/{$this->config['api_version']}/products/{$productId}.json", [
                'headers' => [
                    'X-Shopify-Access-Token' => $this->config['access_token'],
                    'Content-Type' => 'application/json',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (! isset($data['product'])) {
                throw new \Exception('Product not found');
            }

            return [
                'success' => true,
                'data' => $data['product'],
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * ⚙️ IS CONFIGURED
     *
     * Check if Shopify is properly configured
     */
    public function isConfigured(): bool
    {
        return ! empty($this->config['store_url']) &&
               ! empty($this->config['access_token']) &&
               ! empty($this->config['api_version']);
    }

    /**
     * 🔧 GET MISSING CONFIG
     *
     * Get list of missing configuration items
     *
     * @return array<string>
     */
    public function getMissingConfig(): array
    {
        $missing = [];

        if (empty($this->config['store_url'])) {
            $missing[] = 'SHOPIFY_STORE_URL';
        }

        if (empty($this->config['access_token'])) {
            $missing[] = 'SHOPIFY_ACCESS_TOKEN';
        }

        if (empty($this->config['api_version'])) {
            $missing[] = 'SHOPIFY_API_VERSION';
        }

        return $missing;
    }

    /**
     * 🔧 GET CONFIGURATION STATUS
     *
     * Get comprehensive configuration status
     *
     * @return array<string, mixed>
     */
    public function getConfigurationStatus(): array
    {
        return [
            'is_configured' => $this->isConfigured(),
            'missing_config' => $this->getMissingConfig(),
            'config_items' => [
                'store_url' => ! empty($this->config['store_url']),
                'access_token' => ! empty($this->config['access_token']),
                'api_version' => ! empty($this->config['api_version']),
                'api_key' => ! empty($this->config['api_key']),
                'api_secret' => ! empty($this->config['api_secret']),
            ],
        ];
    }

    /**
     * 🧪 BASIC CONNECTION TEST
     *
     * Fallback connection test using HTTP client
     *
     * @return array<string, mixed>
     */
    protected function basicConnectionTest(): array
    {
        try {
            $shopInfo = $this->getShopInfo();

            if ($shopInfo['success']) {
                return [
                    'success' => true,
                    'message' => 'Shopify connection successful',
                    'shop_info' => $shopInfo['shop_info'] ?? [],
                    'tests' => [
                        'basic_connection' => [
                            'success' => true,
                            'message' => 'Shop info retrieved successfully',
                        ],
                    ],
                ];
            }

            return [
                'success' => false,
                'error' => $shopInfo['error'] ?? 'Connection test failed',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 📊 GET CONNECTION SUMMARY
     *
     * Get comprehensive connection and configuration summary
     *
     * @return array<string, mixed>
     */
    public function getConnectionSummary(): array
    {
        $configStatus = $this->getConfigurationStatus();

        if (! $configStatus['is_configured']) {
            return [
                'status' => 'not_configured',
                'message' => 'Shopify is not properly configured',
                'configuration' => $configStatus,
                'connection' => null,
            ];
        }

        $connectionTest = $this->testConnection();

        return [
            'status' => $connectionTest['success'] ? 'connected' : 'connection_failed',
            'message' => $connectionTest['success']
                ? 'Shopify is configured and connected'
                : 'Shopify is configured but connection failed',
            'configuration' => $configStatus,
            'connection' => $connectionTest,
        ];
    }

    /**
     * 🚀 GET API CLIENT
     *
     * Get the underlying API client for advanced operations
     */
    public function getApiClient(): ?ShopifyApiClient
    {
        return $this->apiClient ?? null;
    }
}
