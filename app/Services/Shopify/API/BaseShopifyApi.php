<?php

namespace App\Services\Shopify\API;

use App\Models\SyncAccount;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use PHPShopify\ShopifySDK;

/**
 * 🏗️ BASE SHOPIFY API CLIENT
 *
 * Foundation class for all Shopify API clients.
 * Provides common functionality, configuration, and utilities.
 */
abstract class BaseShopifyApi
{
    protected ShopifySDK $sdk;

    protected Client $httpClient;

    protected array $config;

    protected string $shopDomain;

    public function __construct(string $shopDomain)
    {
        $this->shopDomain = $shopDomain;
        $this->config = $this->getShopifyConfig($shopDomain);

        // Initialize Shopify SDK
        $this->sdk = new ShopifySDK([
            'ShopUrl' => $this->config['shop_domain'],
            'AccessToken' => $this->config['access_token'],
            'ApiVersion' => $this->config['api_version'] ?? '2025-07',
        ]);

        // Initialize HTTP client for direct API calls if needed
        $this->httpClient = new Client([
            'base_uri' => "https://{$this->config['shop_domain']}/admin/api/{$this->config['api_version']}/",
            'timeout' => 60,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Shopify-Access-Token' => $this->config['access_token'],
            ],
        ]);
    }

    /**
     * ⚙️ GET SHOPIFY CONFIG
     *
     * Enhanced to work with both SyncAccount format and legacy config
     */
    protected function getShopifyConfig(string $shopDomain): array
    {
        // Try to get from SyncAccount first
        try {
            $syncAccount = SyncAccount::where('channel', 'shopify')
                ->where(function ($query) use ($shopDomain) {
                    $query->where('marketplace_subtype', $shopDomain)
                        ->orWhereJsonContains('credentials->shop_domain', $shopDomain)
                        ->orWhereJsonContains('settings->auto_fetched_data->shop_domain', $shopDomain);
                })
                ->where('is_active', true)
                ->first();

            if ($syncAccount) {
                $config = [
                    'name' => $syncAccount->display_name,
                    'shop_domain' => $syncAccount->credentials['shop_domain'] ?? $shopDomain,
                    'access_token' => $syncAccount->credentials['access_token'] ?? '',
                    'api_version' => $syncAccount->credentials['api_version'] ?? '2025-07',
                ];

                // Get shop info from auto-fetched data if available
                $shopInfo = $syncAccount->settings['auto_fetched_data'] ?? [];
                if (! empty($shopInfo)) {
                    $config['shop_id'] = $shopInfo['shop_id'] ?? '';
                    $config['shop_name'] = $shopInfo['shop_name'] ?? '';
                    $config['currency'] = $shopInfo['currency'] ?? 'GBP';
                    $config['timezone'] = $shopInfo['timezone'] ?? 'UTC';
                }

                Log::info('✅ Using SyncAccount config for Shopify API client', [
                    'shop_domain' => $shopDomain,
                    'sync_account_id' => $syncAccount->id,
                    'display_name' => $syncAccount->display_name,
                    'channel' => $syncAccount->channel,
                    'marketplace_subtype' => $syncAccount->marketplace_subtype,
                    'api_version' => $config['api_version'],
                ]);

                return $config;
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get SyncAccount config for Shopify API client, falling back to legacy', [
                'shop_domain' => $shopDomain,
                'error' => $e->getMessage(),
            ]);
        }

        // Fallback to legacy config format
        Log::info('🔄 Using legacy config fallback for Shopify API client', ['shop_domain' => $shopDomain]);

        return [
            'name' => ucfirst(str_replace('.myshopify.com', '', $shopDomain)),
            'shop_domain' => $shopDomain,
            'access_token' => config("services.shopify.stores.{$shopDomain}.access_token") ?? config('services.shopify.access_token'),
            'api_version' => config('services.shopify.api_version', '2025-07'),
            'shop_id' => config("services.shopify.stores.{$shopDomain}.shop_id"),
            'currency' => config("services.shopify.stores.{$shopDomain}.currency", 'GBP'),
        ];
    }

    /**
     * 🏭 STATIC FACTORY METHOD
     *
     * Create API client for specific shop domain
     *
     * @param  string  $shopDomain  Shop domain (e.g., 'example.myshopify.com')
     * @return static API client instance
     */
    public static function for(string $shopDomain): static
    {
        return new static($shopDomain);
    }

    /**
     * 🔍 GET SHOP INFO
     *
     * Get basic information about the configured shop
     *
     * @return array<string, mixed>
     */
    public function getShopInfo(): array
    {
        return [
            'shop_domain' => $this->shopDomain,
            'name' => $this->config['name'] ?? '',
            'shop_id' => $this->config['shop_id'] ?? '',
            'shop_name' => $this->config['shop_name'] ?? '',
            'currency' => $this->config['currency'] ?? '',
            'timezone' => $this->config['timezone'] ?? '',
            'api_version' => $this->config['api_version'] ?? '2025-07',
        ];
    }

    /**
     * ✅ TEST API CONNECTION
     *
     * Test basic connectivity to the Shopify API
     *
     * @return array<string, mixed>
     */
    public function testConnection(): array
    {
        try {
            $shop = $this->sdk->Shop->get();

            if ($shop) {
                Log::info('✅ Shopify API connection successful', [
                    'shop_domain' => $this->shopDomain,
                    'shop_name' => $shop['name'] ?? '',
                    'shop_id' => $shop['id'] ?? '',
                ]);

                return [
                    'success' => true,
                    'message' => 'API connection successful',
                    'shop_domain' => $this->shopDomain,
                    'shop_data' => [
                        'id' => $shop['id'] ?? null,
                        'name' => $shop['name'] ?? '',
                        'domain' => $shop['domain'] ?? '',
                        'email' => $shop['email'] ?? '',
                        'currency' => $shop['currency'] ?? '',
                        'timezone' => $shop['timezone'] ?? '',
                        'shop_owner' => $shop['shop_owner'] ?? '',
                        'plan_name' => $shop['plan_name'] ?? '',
                    ],
                ];
            }
        } catch (\Exception $e) {
            Log::error('❌ Shopify API connection failed', [
                'shop_domain' => $this->shopDomain,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'shop_domain' => $this->shopDomain,
            ];
        }

        return [
            'success' => false,
            'error' => 'Unknown connection error',
            'shop_domain' => $this->shopDomain,
        ];
    }

    /**
     * 🚦 HANDLE RATE LIMITING
     *
     * Shopify has strict rate limits - this method handles them gracefully
     *
     * @param  callable  $apiCall  The API call to execute
     * @param  int  $maxRetries  Maximum number of retries
     * @return mixed
     */
    protected function withRateLimit(callable $apiCall, int $maxRetries = 3)
    {
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                return $apiCall();
            } catch (\Exception $e) {
                $attempt++;

                // Check if it's a rate limit error
                if (str_contains($e->getMessage(), '429') || str_contains($e->getMessage(), 'rate limit')) {
                    if ($attempt < $maxRetries) {
                        // Exponential backoff: 1s, 2s, 4s
                        $waitTime = pow(2, $attempt - 1);
                        Log::warning("Rate limit hit, waiting {$waitTime}s before retry", [
                            'shop_domain' => $this->shopDomain,
                            'attempt' => $attempt,
                            'max_retries' => $maxRetries,
                        ]);
                        sleep($waitTime);

                        continue;
                    }
                }

                // Re-throw if not rate limit error or max retries reached
                throw $e;
            }
        }

        throw new \Exception("Max retries ({$maxRetries}) reached for rate-limited API call");
    }

    /**
     * 📊 EXECUTE GRAPHQL QUERY
     *
     * Execute GraphQL query with error handling and rate limiting
     *
     * @param  string  $query  GraphQL query string
     * @param  array<string, mixed>  $variables  Query variables
     * @return array<string, mixed>
     */
    protected function graphql(string $query, array $variables = []): array
    {
        return $this->withRateLimit(function () use ($query, $variables) {
            $data = empty($variables)
                ? $query
                : ['query' => $query, 'variables' => $variables];

            $response = $this->sdk->GraphQL->post($data);

            if (isset($response['errors'])) {
                Log::warning('GraphQL query returned errors', [
                    'shop_domain' => $this->shopDomain,
                    'errors' => $response['errors'],
                ]);
            }

            return [
                'success' => ! isset($response['errors']),
                'data' => $response['data'] ?? [],
                'errors' => $response['errors'] ?? null,
            ];
        });
    }

    /**
     * 🔧 GET SDK INSTANCE
     *
     * Get the underlying Shopify SDK for advanced operations
     */
    public function getSdk(): ShopifySDK
    {
        return $this->sdk;
    }

    /**
     * 🌐 GET HTTP CLIENT
     *
     * Get the underlying HTTP client for direct API calls
     */
    public function getHttpClient(): Client
    {
        return $this->httpClient;
    }

    /**
     * 📋 VALIDATE CONFIGURATION
     *
     * Validate that all required configuration is present
     *
     * @return array<string, mixed>
     */
    public function validateConfiguration(): array
    {
        $errors = [];
        $warnings = [];

        // Check required fields
        if (empty($this->config['shop_domain'])) {
            $errors[] = 'Shop domain is required';
        }

        if (empty($this->config['access_token'])) {
            $errors[] = 'Access token is required';
        }

        if (empty($this->config['api_version'])) {
            $warnings[] = 'API version not specified, using default';
        }

        // Check domain format
        if (! empty($this->config['shop_domain']) && ! str_contains($this->config['shop_domain'], '.myshopify.com')) {
            $warnings[] = 'Shop domain should include .myshopify.com';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'config' => [
                'shop_domain' => $this->config['shop_domain'] ?? '',
                'has_access_token' => ! empty($this->config['access_token']),
                'api_version' => $this->config['api_version'] ?? '',
            ],
        ];
    }
}
