<?php

namespace App\Services\Shopify\API\Client;

use App\Services\Shopify\API\Credentials\ShopifyCredentialsBuilder;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use PHPShopify\ShopifySDK;

/**
 * Main Shopify API client
 *
 * Provides core connection management and delegates to specialized clients
 * for REST and GraphQL operations. Uses the Builder pattern for configuration.
 */
class ShopifyClient
{
    protected ShopifySDK $sdk;

    protected array $config;

    protected ?ShopifyRestClient $restClient = null;

    protected ?ShopifyGraphQLClient $graphqlClient = null;

    public function __construct(?array $config = null)
    {
        if ($config) {
            $this->config = $config;
        } else {
            // Build from environment by default
            $this->config = ShopifyCredentialsBuilder::fromEnv()->build();
        }

        $this->initializeSdk();
    }

    /**
     * Initialize the Shopify SDK
     */
    protected function initializeSdk(): void
    {
        $this->sdk = new ShopifySDK($this->config);

        Log::debug('Shopify SDK initialized', [
            'store_url' => $this->config['ShopUrl'],
            'api_version' => $this->config['ApiVersion'],
        ]);
    }

    /**
     * Get REST client instance
     */
    public function rest(): ShopifyRestClient
    {
        if (! $this->restClient) {
            $this->restClient = new ShopifyRestClient($this->sdk, $this->config);
        }

        return $this->restClient;
    }

    /**
     * Get GraphQL client instance
     */
    public function graphql(): ShopifyGraphQLClient
    {
        if (! $this->graphqlClient) {
            $this->graphqlClient = new ShopifyGraphQLClient($this->sdk, $this->config);
        }

        return $this->graphqlClient;
    }

    /**
     * Get the underlying SDK instance (for advanced usage)
     */
    public function getSdk(): ShopifySDK
    {
        return $this->sdk;
    }

    /**
     * Test connection to Shopify
     */
    public function testConnection(): array
    {
        try {
            $shop = $this->sdk->Shop->get();

            return [
                'success' => true,
                'message' => 'Successfully connected to Shopify store',
                'shop_info' => [
                    'name' => $shop['name'] ?? 'Unknown',
                    'domain' => $shop['domain'] ?? 'Unknown',
                    'id' => $shop['id'] ?? null,
                    'plan_name' => $shop['plan_name'] ?? 'Unknown',
                ],
            ];
        } catch (Exception $e) {
            Log::error('Shopify connection test failed', [
                'error' => $e->getMessage(),
                'store_url' => $this->config['ShopUrl'] ?? 'unknown',
            ]);

            return [
                'success' => false,
                'message' => 'Connection failed: '.$e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get shop information
     */
    public function getShopInfo(): array
    {
        try {
            $shop = $this->sdk->Shop->get();

            // Try to get product count for stats
            $productCount = 0;
            try {
                $productCountData = $this->sdk->Product->get(['limit' => 1, 'fields' => 'id']);
                $productCount = count($productCountData);
            } catch (Exception $e) {
                // Ignore product count errors
            }

            return [
                'success' => true,
                'data' => [
                    'id' => $shop['id'] ?? null,
                    'name' => $shop['name'] ?? 'Unknown',
                    'domain' => $shop['domain'] ?? 'Unknown',
                    'myshopify_domain' => $shop['myshopify_domain'] ?? $shop['domain'] ?? 'Unknown',
                    'email' => $shop['email'] ?? 'Unknown',
                    'plan_name' => $shop['plan_name'] ?? 'Unknown',
                    'timezone' => $shop['timezone'] ?? 'UTC',
                    'currency' => $shop['currency'] ?? 'USD',
                    'country' => $shop['country'] ?? 'Unknown',
                    'country_code' => $shop['country_code'] ?? 'Unknown',
                    'shop_owner' => $shop['shop_owner'] ?? 'Unknown',
                    'phone' => $shop['phone'] ?? null,
                    'address1' => $shop['address1'] ?? null,
                    'city' => $shop['city'] ?? null,
                    'province' => $shop['province'] ?? null,
                    'zip' => $shop['zip'] ?? null,
                    'products_count' => $productCount,
                    'created_at' => $shop['created_at'] ?? null,
                    'updated_at' => $shop['updated_at'] ?? null,
                ],
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create a new client with custom configuration
     */
    public static function withConfig(array $config): static
    {
        return new static($config);
    }

    /**
     * Create a client using the builder pattern
     */
    public static function build(): ShopifyCredentialsBuilder
    {
        return ShopifyCredentialsBuilder::create();
    }

    /**
     * Create a client from environment configuration
     */
    public static function fromEnv(): static
    {
        return new static(ShopifyCredentialsBuilder::fromEnv()->build());
    }

    /**
     * Fetch all products with variants for marketplace sync
     */
    public function fetchAllProductsWithVariants(?Carbon $since = null): \Illuminate\Support\Collection
    {
        try {
            $allProducts = collect();
            $limit = 50; // Shopify API limit per request
            $pageInfo = null;
            $requestCount = 0;
            $maxRequests = 100; // Safety limit

            Log::info('🛍️ Starting Shopify products fetch', [
                'since' => $since?->toISOString(),
                'limit_per_request' => $limit,
            ]);

            do {
                $requestCount++;

                // Build request parameters
                $params = [
                    'limit' => $limit,
                    'fields' => 'id,title,handle,status,product_type,vendor,created_at,updated_at,variants',
                ];

                // Add since parameter for incremental sync
                if ($since) {
                    $params['updated_at_min'] = $since->toISOString();
                }

                // Add pagination if we have page info
                if ($pageInfo) {
                    parse_str($pageInfo, $pageParams);
                    $params = array_merge($params, $pageParams);
                }

                Log::debug("Fetching products batch {$requestCount}", ['params' => $params]);

                // Fetch products from Shopify
                $products = $this->sdk->Product->get($params);

                if (empty($products)) {
                    Log::info('No more products to fetch');
                    break;
                }

                Log::info('Retrieved '.count($products)." products in batch {$requestCount}");

                // Process each product to ensure variants are included
                foreach ($products as $product) {
                    // Ensure we have variants data
                    if (! isset($product['variants']) || empty($product['variants'])) {
                        // Fetch variants separately if not included
                        try {
                            $variants = $this->sdk->Product($product['id'])->Variant->get();
                            $product['variants'] = $variants;
                        } catch (Exception $e) {
                            Log::warning("Failed to fetch variants for product {$product['id']}: {$e->getMessage()}");
                            $product['variants'] = [];
                        }
                    }

                    $allProducts->push($product);
                }

                // Check if there are more pages
                // Note: Shopify REST API doesn't provide explicit pagination info,
                // so we check if we got a full page of results
                $hasMorePages = count($products) === $limit;

                if ($hasMorePages && $requestCount < $maxRequests) {
                    // Use the last product's ID for pagination
                    $lastProduct = end($products);
                    $pageInfo = 'since_id='.$lastProduct['id'];

                    // Add small delay to be respectful to Shopify API
                    usleep(250000); // 250ms delay
                } else {
                    $hasMorePages = false;
                }

            } while ($hasMorePages && $requestCount < $maxRequests);

            if ($requestCount >= $maxRequests) {
                Log::warning("Hit maximum request limit ({$maxRequests}) - may not have fetched all products");
            }

            Log::info('🎉 Shopify products fetch complete', [
                'total_products' => $allProducts->count(),
                'total_requests' => $requestCount,
            ]);

            return $allProducts;

        } catch (Exception $e) {
            Log::error('Failed to fetch Shopify products with variants', [
                'error' => $e->getMessage(),
                'since' => $since?->toISOString(),
            ]);

            throw new Exception("Shopify API error: {$e->getMessage()}");
        }
    }
}
