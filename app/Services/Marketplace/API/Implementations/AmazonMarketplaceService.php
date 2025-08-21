<?php

namespace App\Services\Marketplace\API\Implementations;

use App\Services\Marketplace\API\AbstractMarketplaceService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 📦 AMAZON MARKETPLACE SERVICE
 *
 * Concrete implementation of AbstractMarketplaceService for Amazon SP-API.
 * Provides complete Amazon Selling Partner API integration with OAuth 2.0,
 * product management, order processing, and inventory synchronization.
 *
 * Features:
 * - Amazon SP-API (Selling Partner API) integration
 * - LWA (Login with Amazon) OAuth 2.0 authentication
 * - Product catalog management
 * - Order management and fulfillment
 * - Inventory tracking and updates
 * - Multi-region support (NA, EU, FE)
 * - Rate limiting and request signing
 * - Error handling and retry logic
 *
 * Usage:
 * $amazon = MarketplaceClient::for('amazon')
 *     ->withAccount($account)
 *     ->withConfig(['region' => 'NA'])
 *     ->build();
 *
 * $result = $amazon->products()
 *     ->create($productData)
 *     ->withFulfillmentChannel('AMAZON')
 *     ->execute();
 */
class AmazonMarketplaceService extends AbstractMarketplaceService
{
    private ?string $accessToken = null;

    private array $amazonConfig = [];

    private array $spApiEndpoints = [
        'NA' => 'https://sellingpartnerapi-na.amazon.com',
        'EU' => 'https://sellingpartnerapi-eu.amazon.com',
        'FE' => 'https://sellingpartnerapi-fe.amazon.com',
    ];

    private array $marketplaceIds = [
        'US' => 'ATVPDKIKX0DER',
        'CA' => 'A2EUQ1WTGCTBG2',
        'MX' => 'A1AM78C64UM0Y8',
        'UK' => 'A1F83G8C2ARO7P',
        'DE' => 'A1PA6795UKMFR9',
        'FR' => 'A13V1IB3VIYZZH',
        'IT' => 'APJ6JRA9NG5V4',
        'ES' => 'A1RKKUPIHCS9HS',
        'JP' => 'A1VC38T7YXB528',
        'AU' => 'A39IBJ37TRP1C6',
    ];

    /**
     * 🏷️ Get marketplace name identifier
     */
    protected function getMarketplaceName(): string
    {
        return 'amazon';
    }

    /**
     * 🔐 Get authentication headers for HTTP client
     */
    protected function getAuthenticationHeaders(): array
    {
        if (! $this->accessToken) {
            $this->ensureAccessToken();
        }

        return $this->accessToken ? [
            'Authorization' => 'Bearer '.$this->accessToken,
            'x-amz-access-token' => $this->accessToken,
            'Content-Type' => 'application/json',
            'User-Agent' => 'LaravelPIM/1.0 (Language=PHP)',
        ] : [];
    }

    /**
     * 🛠️ Build configuration from sync account
     */
    protected function buildConfigFromAccount($account): array
    {
        $config = parent::buildConfigFromAccount($account);

        $this->amazonConfig = [
            'seller_id' => $config['seller_id'] ?? '',
            'marketplace_id' => $config['marketplace_id'] ?? $this->marketplaceIds['US'],
            'region' => $config['region'] ?? 'NA',
            'client_id' => $config['client_id'] ?? '',
            'client_secret' => $config['client_secret'] ?? '',
            'refresh_token' => $config['refresh_token'] ?? '',
            'access_key' => $config['access_key'] ?? '',
            'secret_key' => $config['secret_key'] ?? '',
            'role_arn' => $config['role_arn'] ?? '',
        ];

        Log::info('📦 Amazon service configured', [
            'seller_id' => $this->amazonConfig['seller_id'],
            'marketplace_id' => $this->amazonConfig['marketplace_id'],
            'region' => $this->amazonConfig['region'],
            'has_credentials' => ! empty($this->amazonConfig['client_id']),
        ]);

        return $config;
    }

    /**
     * ⚠️ Extract error message from API response
     */
    protected function extractErrorMessage(array $errorResponse): string
    {
        // Amazon SP-API error formats
        if (isset($errorResponse['errors']) && is_array($errorResponse['errors'])) {
            $errors = [];
            foreach ($errorResponse['errors'] as $error) {
                $message = $error['message'] ?? 'Unknown error';
                $code = $error['code'] ?? '';
                $errors[] = $code ? "[$code] $message" : $message;
            }

            return implode('; ', $errors);
        }

        if (isset($errorResponse['error_description'])) {
            return $errorResponse['error_description'];
        }

        if (isset($errorResponse['message'])) {
            return $errorResponse['message'];
        }

        return 'Unknown Amazon SP-API error';
    }

    /**
     * 🔌 Test connection to Amazon SP-API
     */
    public function testConnection(): array
    {
        try {
            // Test LWA token
            $tokenResult = $this->getAccessToken();
            if (! $tokenResult['success']) {
                return [
                    'success' => false,
                    'error' => 'LWA authentication failed: '.$tokenResult['error'],
                    'region' => $this->amazonConfig['region'],
                ];
            }

            // Test API call - get marketplace participations
            $marketplaceResult = $this->getMarketplaceParticipations();
            if (! $marketplaceResult['success']) {
                return [
                    'success' => false,
                    'error' => 'SP-API test failed: '.$marketplaceResult['error'],
                    'token_working' => true,
                ];
            }

            return [
                'success' => true,
                'message' => 'Successfully connected to Amazon SP-API',
                'region' => $this->amazonConfig['region'],
                'marketplace_id' => $this->amazonConfig['marketplace_id'],
                'seller_id' => $this->amazonConfig['seller_id'],
                'api_version' => 'SP-API v2021-06-30',
                'participations' => count($marketplaceResult['data']['payload'] ?? []),
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 📋 Get marketplace requirements
     */
    public function getRequirements(): array
    {
        return [
            'seller_id' => [
                'label' => 'Seller ID',
                'description' => 'Your Amazon seller ID (merchant identifier)',
                'required' => true,
                'type' => 'text',
                'validation' => 'required|string|min:10',
            ],
            'marketplace_id' => [
                'label' => 'Marketplace ID',
                'description' => 'Amazon marketplace identifier',
                'required' => true,
                'type' => 'select',
                'options' => $this->marketplaceIds,
                'default' => $this->marketplaceIds['US'],
            ],
            'region' => [
                'label' => 'Region',
                'description' => 'Amazon SP-API region',
                'required' => true,
                'type' => 'select',
                'options' => ['NA' => 'North America', 'EU' => 'Europe', 'FE' => 'Far East'],
                'default' => 'NA',
            ],
            'client_id' => [
                'label' => 'LWA Client ID',
                'description' => 'Login with Amazon (LWA) application client ID',
                'required' => true,
                'type' => 'text',
                'validation' => 'required|string',
            ],
            'client_secret' => [
                'label' => 'LWA Client Secret',
                'description' => 'Login with Amazon (LWA) application client secret',
                'required' => true,
                'type' => 'password',
                'validation' => 'required|string',
            ],
            'refresh_token' => [
                'label' => 'LWA Refresh Token',
                'description' => 'Login with Amazon (LWA) refresh token',
                'required' => true,
                'type' => 'password',
                'validation' => 'required|string',
            ],
            'access_key' => [
                'label' => 'AWS Access Key ID',
                'description' => 'AWS access key for SP-API requests (IAM user)',
                'required' => false,
                'type' => 'text',
            ],
            'secret_key' => [
                'label' => 'AWS Secret Key',
                'description' => 'AWS secret access key for SP-API requests',
                'required' => false,
                'type' => 'password',
            ],
            'role_arn' => [
                'label' => 'IAM Role ARN',
                'description' => 'AWS IAM role ARN for SP-API access (optional)',
                'required' => false,
                'type' => 'text',
            ],
        ];
    }

    /**
     * 🎛️ Get marketplace capabilities
     */
    public function getCapabilities(): array
    {
        return [
            'products' => [
                'create' => true,
                'read' => true,
                'update' => true,
                'delete' => false, // Amazon doesn't allow product deletion
                'bulk_operations' => true,
                'variations' => true,
                'images' => true,
                'categories' => true,
                'browse_nodes' => true,
            ],
            'orders' => [
                'read' => true,
                'update_fulfillment' => true,
                'shipping_address' => true,
                'buyer_info' => true,
            ],
            'inventory' => [
                'read' => true,
                'update' => true,
                'bulk_update' => true,
                'fba_inventory' => true,
                'fbm_inventory' => true,
                'reservations' => true,
            ],
            'fulfillment' => [
                'fba' => true, // Fulfillment by Amazon
                'fbm' => true, // Fulfillment by Merchant
                'prime' => true,
                'multi_channel' => true,
            ],
            'features' => [
                'sp_api' => true,
                'product_type_definitions' => true,
                'enhanced_content' => true,
                'advertising_api' => false, // Separate API
                'brand_registry' => true,
                'transparency' => true,
            ],
        ];
    }

    /**
     * ✅ Validate configuration
     */
    public function validateConfiguration(): array
    {
        $errors = [];

        if (empty($this->amazonConfig['seller_id'])) {
            $errors[] = 'Seller ID is required';
        }

        if (empty($this->amazonConfig['client_id'])) {
            $errors[] = 'LWA Client ID is required';
        }

        if (empty($this->amazonConfig['client_secret'])) {
            $errors[] = 'LWA Client Secret is required';
        }

        if (empty($this->amazonConfig['refresh_token'])) {
            $errors[] = 'LWA Refresh Token is required';
        }

        if (! in_array($this->amazonConfig['region'], ['NA', 'EU', 'FE'])) {
            $errors[] = 'Region must be NA, EU, or FE';
        }

        if (! in_array($this->amazonConfig['marketplace_id'], $this->marketplaceIds)) {
            $errors[] = 'Invalid marketplace ID';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * 🚦 Get rate limits
     */
    public function getRateLimits(): array
    {
        return [
            'requests_per_minute' => 200, // Amazon SP-API varies by endpoint
            'requests_per_second' => 5,
            'burst_limit' => 10,
            'quota_units' => 'per_endpoint', // Amazon uses different quotas per endpoint
        ];
    }

    /**
     * 🔐 Get supported authentication methods
     */
    public function getSupportedAuthMethods(): array
    {
        return [
            'lwa_refresh_token' => [
                'name' => 'LWA Refresh Token',
                'description' => 'Login with Amazon (LWA) refresh token flow',
                'fields' => ['client_id', 'client_secret', 'refresh_token'],
            ],
            'aws_iam' => [
                'name' => 'AWS IAM Role',
                'description' => 'AWS IAM role-based authentication with STS',
                'fields' => ['access_key', 'secret_key', 'role_arn'],
            ],
        ];
    }

    // =================================
    // AUTHENTICATION
    // =================================

    /**
     * 🔑 Get LWA access token
     */
    private function getAccessToken(): array
    {
        try {
            $response = Http::asForm()->post('https://api.amazon.com/auth/o2/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $this->amazonConfig['refresh_token'],
                'client_id' => $this->amazonConfig['client_id'],
                'client_secret' => $this->amazonConfig['client_secret'],
            ]);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'error' => 'HTTP '.$response->status().': '.$response->body(),
                ];
            }

            $data = $response->json();
            $this->accessToken = $data['access_token'] ?? null;

            if (! $this->accessToken) {
                return [
                    'success' => false,
                    'error' => 'No access token received',
                ];
            }

            return [
                'success' => true,
                'access_token' => $this->accessToken,
                'expires_in' => $data['expires_in'] ?? 3600,
                'token_type' => $data['token_type'] ?? 'Bearer',
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 🔑 Ensure we have a valid access token
     */
    private function ensureAccessToken(): void
    {
        if (! $this->accessToken) {
            $result = $this->getAccessToken();
            if (! $result['success']) {
                throw new Exception('Failed to obtain Amazon access token: '.$result['error']);
            }
        }
    }

    // =================================
    // PRODUCT OPERATIONS
    // =================================

    /**
     * 🛍️ Create a product
     */
    public function createProduct(array $productData): array
    {
        $this->ensureAccessToken();

        return $this->executeRequest(function () use ($productData) {
            // Amazon requires specific product type and feed-based submission
            $feed = $this->transformProductToAmazonFeed($productData);

            // Submit product feed
            $feedResult = $this->submitFeed('POST_PRODUCT_DATA', $feed);
            if (! $feedResult['success']) {
                throw new Exception('Failed to submit product feed: '.$feedResult['error']);
            }

            $responseData = [
                'product_id' => $productData['sku'] ?? 'UNKNOWN',
                'feed_id' => $feedResult['data']['feedId'] ?? '',
                'status' => 'submitted',
                'sku' => $productData['sku'] ?? '',
            ];

            return new class($responseData)
            {
                private $data;

                public function __construct($data)
                {
                    $this->data = $data;
                }

                public function successful()
                {
                    return true;
                }

                public function status()
                {
                    return 202;
                } // Accepted for processing

                public function json()
                {
                    return ['product' => $this->data];
                }

                public function body()
                {
                    return json_encode($this->json());
                }
            };
        });
    }

    /**
     * 📝 Update a product
     */
    public function updateProduct(string $productId, array $productData): array
    {
        $this->ensureAccessToken();

        return $this->executeRequest(function () use ($productId, $productData) {
            // Amazon updates also use feeds
            $feed = $this->transformProductToAmazonFeed($productData, 'update');

            $feedResult = $this->submitFeed('POST_PRODUCT_DATA', $feed);
            if (! $feedResult['success']) {
                throw new Exception('Failed to submit update feed: '.$feedResult['error']);
            }

            $responseData = [
                'product_id' => $productId,
                'feed_id' => $feedResult['data']['feedId'] ?? '',
                'status' => 'update_submitted',
                'updated' => true,
            ];

            return new class($responseData)
            {
                private $data;

                public function __construct($data)
                {
                    $this->data = $data;
                }

                public function successful()
                {
                    return true;
                }

                public function status()
                {
                    return 202;
                }

                public function json()
                {
                    return ['product' => $this->data];
                }

                public function body()
                {
                    return json_encode($this->json());
                }
            };
        });
    }

    /**
     * 🗑️ Delete a product (not supported by Amazon)
     */
    public function deleteProduct(string $productId): array
    {
        return [
            'success' => false,
            'error' => 'Amazon does not support product deletion via API. Products can only be made inactive.',
            'recommendation' => 'Use updateProduct() to set status to inactive instead.',
        ];
    }

    /**
     * 🔍 Get a single product
     */
    public function getProduct(string $productId): array
    {
        $this->ensureAccessToken();

        return $this->executeRequest(function () use ($productId) {
            $response = Http::withHeaders($this->getAuthenticationHeaders())
                ->get($this->getApiBaseUrl().'/catalog/v0/items/'.$productId, [
                    'MarketplaceId' => $this->amazonConfig['marketplace_id'],
                ]);

            if (! $response->successful()) {
                throw new Exception('HTTP '.$response->status().': '.$response->body());
            }

            $data = $response->json();
            $product = $this->transformAmazonProductToStandard($data['payload'] ?? []);

            return new class($product)
            {
                private $data;

                public function __construct($data)
                {
                    $this->data = $data;
                }

                public function successful()
                {
                    return true;
                }

                public function status()
                {
                    return 200;
                }

                public function json()
                {
                    return ['product' => $this->data];
                }

                public function body()
                {
                    return json_encode($this->json());
                }
            };
        });
    }

    /**
     * 📋 Get multiple products
     */
    public function getProducts(array $filters = []): Collection
    {
        $this->ensureAccessToken();

        $cacheKey = 'amazon_products_'.md5(serialize($filters));

        return Cache::remember($cacheKey, 300, function () use ($filters) {
            try {
                $params = $this->buildAmazonProductFilters($filters);
                $response = Http::withHeaders($this->getAuthenticationHeaders())
                    ->get($this->getApiBaseUrl().'/catalog/v0/items', $params);

                if (! $response->successful()) {
                    Log::error('Failed to get Amazon products', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    return collect([]);
                }

                $data = $response->json();
                $items = $data['payload']['Items'] ?? [];

                return collect($items)->map(function ($item) {
                    return $this->transformAmazonProductToStandard($item);
                });

            } catch (Exception $e) {
                Log::error('Failed to get Amazon products', [
                    'error' => $e->getMessage(),
                    'filters' => $filters,
                ]);

                return collect([]);
            }
        });
    }

    // =================================
    // ORDER OPERATIONS
    // =================================

    /**
     * 📦 Get orders
     */
    public function getOrders(array $filters = []): Collection
    {
        $this->ensureAccessToken();

        try {
            $params = $this->buildAmazonOrderFilters($filters);
            $response = Http::withHeaders($this->getAuthenticationHeaders())
                ->get($this->getApiBaseUrl().'/orders/v0/orders', $params);

            if (! $response->successful()) {
                Log::error('Failed to get Amazon orders', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return collect([]);
            }

            $data = $response->json();
            $orders = $data['payload']['Orders'] ?? [];

            return collect($orders)->map(function ($order) {
                return $this->transformAmazonOrderToStandard($order);
            });

        } catch (Exception $e) {
            Log::error('Failed to get Amazon orders', [
                'error' => $e->getMessage(),
                'filters' => $filters,
            ]);

            return collect([]);
        }
    }

    /**
     * 📦 Get a single order
     */
    public function getOrder(string $orderId): array
    {
        $this->ensureAccessToken();

        return $this->executeRequest(function () use ($orderId) {
            $response = Http::withHeaders($this->getAuthenticationHeaders())
                ->get($this->getApiBaseUrl().'/orders/v0/orders/'.$orderId);

            if (! $response->successful()) {
                throw new Exception('HTTP '.$response->status().': '.$response->body());
            }

            $data = $response->json();
            $order = $this->transformAmazonOrderToStandard($data['payload'] ?? []);

            return new class($order)
            {
                private $data;

                public function __construct($data)
                {
                    $this->data = $data;
                }

                public function successful()
                {
                    return true;
                }

                public function status()
                {
                    return 200;
                }

                public function json()
                {
                    return ['order' => $this->data];
                }

                public function body()
                {
                    return json_encode($this->json());
                }
            };
        });
    }

    /**
     * 🚚 Update order fulfillment
     */
    public function updateOrderFulfillment(string $orderId, array $fulfillmentData): array
    {
        $this->ensureAccessToken();

        return $this->executeRequest(function () use ($orderId, $fulfillmentData) {
            $amazonFulfillment = $this->transformFulfillmentDataToAmazon($fulfillmentData);

            $response = Http::withHeaders($this->getAuthenticationHeaders())
                ->post($this->getApiBaseUrl().'/orders/v0/orders/'.$orderId.'/shipment', $amazonFulfillment);

            if (! $response->successful()) {
                throw new Exception('HTTP '.$response->status().': '.$response->body());
            }

            $result = $response->json();

            return new class($result)
            {
                private $data;

                public function __construct($data)
                {
                    $this->data = $data;
                }

                public function successful()
                {
                    return true;
                }

                public function status()
                {
                    return 200;
                }

                public function json()
                {
                    return ['fulfillment' => $this->data];
                }

                public function body()
                {
                    return json_encode($this->json());
                }
            };
        });
    }

    // =================================
    // INVENTORY OPERATIONS
    // =================================

    /**
     * 📊 Get inventory levels
     */
    public function getInventoryLevels(array $productIds = []): Collection
    {
        $this->ensureAccessToken();

        try {
            $params = [
                'marketplaceIds' => $this->amazonConfig['marketplace_id'],
            ];

            if (! empty($productIds)) {
                $params['skus'] = implode(',', $productIds);
            }

            $response = Http::withHeaders($this->getAuthenticationHeaders())
                ->get($this->getApiBaseUrl().'/fba/inventory/v1/summaries', $params);

            if (! $response->successful()) {
                Log::error('Failed to get Amazon inventory', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return collect([]);
            }

            $data = $response->json();
            $inventories = $data['payload']['inventorySummaries'] ?? [];

            return collect($inventories)->map(function ($inventory) {
                return $this->transformAmazonInventoryToStandard($inventory);
            });

        } catch (Exception $e) {
            Log::error('Failed to get Amazon inventory', [
                'error' => $e->getMessage(),
                'product_ids' => $productIds,
            ]);

            return collect([]);
        }
    }

    /**
     * 📊 Update inventory
     */
    public function updateInventory(string $productId, $inventoryData): array
    {
        $this->ensureAccessToken();

        return $this->executeRequest(function () use ($productId, $inventoryData) {
            $quantity = is_numeric($inventoryData) ? $inventoryData : ($inventoryData['quantity'] ?? 0);

            // Amazon inventory updates typically use feeds
            $feed = $this->transformInventoryToAmazonFeed($productId, $quantity);
            $feedResult = $this->submitFeed('POST_INVENTORY_AVAILABILITY_DATA', $feed);

            if (! $feedResult['success']) {
                throw new Exception('Failed to submit inventory feed: '.$feedResult['error']);
            }

            $responseData = [
                'product_id' => $productId,
                'feed_id' => $feedResult['data']['feedId'] ?? '',
                'quantity' => $quantity,
                'status' => 'submitted',
            ];

            return new class($responseData)
            {
                private $data;

                public function __construct($data)
                {
                    $this->data = $data;
                }

                public function successful()
                {
                    return true;
                }

                public function status()
                {
                    return 202;
                }

                public function json()
                {
                    return ['inventory_update' => $this->data];
                }

                public function body()
                {
                    return json_encode($this->json());
                }
            };
        });
    }

    // =================================
    // HELPER METHODS
    // =================================

    /**
     * 🌐 Get SP-API base URL
     */
    private function getApiBaseUrl(): string
    {
        return $this->spApiEndpoints[$this->amazonConfig['region']] ?? $this->spApiEndpoints['NA'];
    }

    /**
     * 📋 Get marketplace participations
     */
    private function getMarketplaceParticipations(): array
    {
        try {
            $response = Http::withHeaders($this->getAuthenticationHeaders())
                ->get($this->getApiBaseUrl().'/sellers/v1/marketplaceParticipations');

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'error' => 'HTTP '.$response->status().': '.$response->body(),
                ];
            }

            return [
                'success' => true,
                'data' => $response->json(),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 📤 Submit feed to Amazon
     */
    private function submitFeed(string $feedType, string $feedContent): array
    {
        try {
            // Step 1: Create feed document
            $createDocResponse = Http::withHeaders($this->getAuthenticationHeaders())
                ->post($this->getApiBaseUrl().'/feeds/2021-06-30/documents', [
                    'contentType' => 'text/tab-separated-values; charset=utf-8',
                ]);

            if (! $createDocResponse->successful()) {
                return [
                    'success' => false,
                    'error' => 'Failed to create feed document: '.$createDocResponse->body(),
                ];
            }

            $docData = $createDocResponse->json();
            $uploadUrl = $docData['payload']['url'] ?? '';
            $feedDocumentId = $docData['payload']['feedDocumentId'] ?? '';

            // Step 2: Upload feed content
            $uploadResponse = Http::withHeaders([
                'Content-Type' => 'text/tab-separated-values; charset=utf-8',
            ])->put($uploadUrl, $feedContent);

            if (! $uploadResponse->successful()) {
                return [
                    'success' => false,
                    'error' => 'Failed to upload feed content: '.$uploadResponse->body(),
                ];
            }

            // Step 3: Submit feed
            $submitResponse = Http::withHeaders($this->getAuthenticationHeaders())
                ->post($this->getApiBaseUrl().'/feeds/2021-06-30/feeds', [
                    'feedType' => $feedType,
                    'marketplaceIds' => [$this->amazonConfig['marketplace_id']],
                    'inputFeedDocumentId' => $feedDocumentId,
                ]);

            if (! $submitResponse->successful()) {
                return [
                    'success' => false,
                    'error' => 'Failed to submit feed: '.$submitResponse->body(),
                ];
            }

            return [
                'success' => true,
                'data' => $submitResponse->json()['payload'] ?? [],
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 🔄 Transform product data to Amazon feed format
     */
    private function transformProductToAmazonFeed(array $productData, string $operation = 'create'): string
    {
        // Amazon feeds use tab-separated values
        $header = "sku\tproduct-id\tproduct-id-type\tprice\tminimum-seller-allowed-price\tmaximum-seller-allowed-price\titem-condition\tquantity\thandling-time\tproduct-tax-code\toperation-type\tsale-price\tsale-start-date\tsale-end-date\tbrowsenode\tcategory\tproduct-description";

        $sku = $productData['sku'] ?? 'UNKNOWN';
        $price = $productData['price'] ?? '0.00';
        $quantity = $productData['quantity'] ?? 0;
        $condition = $productData['condition'] ?? 'New';
        $description = str_replace(["\t", "\n", "\r"], ' ', $productData['description'] ?? '');

        $row = implode("\t", [
            $sku,                           // sku
            $productData['asin'] ?? '',     // product-id
            $productData['asin'] ? 'ASIN' : '', // product-id-type
            $price,                         // price
            '',                             // minimum-seller-allowed-price
            '',                             // maximum-seller-allowed-price
            $condition,                     // item-condition
            $quantity,                      // quantity
            $productData['handling_time'] ?? '2', // handling-time
            '',                             // product-tax-code
            $operation === 'create' ? 'Update' : 'Update', // operation-type
            '',                             // sale-price
            '',                             // sale-start-date
            '',                             // sale-end-date
            $productData['browse_node'] ?? '', // browsenode
            $productData['category'] ?? '',    // category
            $description,                   // product-description
        ]);

        return $header."\n".$row;
    }

    /**
     * 🔄 Transform inventory to Amazon feed format
     */
    private function transformInventoryToAmazonFeed(string $sku, int $quantity): string
    {
        $header = "sku\tquantity\tfulfillment-center-id";
        $row = implode("\t", [
            $sku,
            $quantity,
            'DEFAULT', // Use default fulfillment center
        ]);

        return $header."\n".$row;
    }

    /**
     * 🔄 Transform Amazon product to standard format
     */
    private function transformAmazonProductToStandard(array $amazonProduct): array
    {
        return [
            'id' => $amazonProduct['Identifiers']['MarketplaceASIN']['ASIN'] ?? '',
            'sku' => $amazonProduct['Identifiers']['SKUIdentifier']['SellerSKU'] ?? '',
            'title' => $amazonProduct['AttributeSets'][0]['Title'] ?? '',
            'description' => $amazonProduct['AttributeSets'][0]['Feature'][0] ?? '',
            'brand' => $amazonProduct['AttributeSets'][0]['Brand'] ?? '',
            'price' => $amazonProduct['Offers'][0]['BuyingPrice']['LandedPrice']['Amount'] ?? '0.00',
            'currency' => $amazonProduct['Offers'][0]['BuyingPrice']['LandedPrice']['CurrencyCode'] ?? 'USD',
            'condition' => $amazonProduct['Offers'][0]['SubCondition'] ?? 'New',
            'created_at' => null,
            'updated_at' => now(),
        ];
    }

    /**
     * 🔄 Transform Amazon order to standard format
     */
    private function transformAmazonOrderToStandard(array $amazonOrder): array
    {
        return [
            'id' => $amazonOrder['AmazonOrderId'] ?? '',
            'order_number' => $amazonOrder['AmazonOrderId'] ?? '',
            'status' => $amazonOrder['OrderStatus'] ?? 'pending',
            'fulfillment_status' => $amazonOrder['FulfillmentChannel'] === 'AFN' ? 'fba' : 'fbm',
            'total_amount' => $amazonOrder['OrderTotal']['Amount'] ?? '0.00',
            'currency' => $amazonOrder['OrderTotal']['CurrencyCode'] ?? 'USD',
            'customer_email' => $amazonOrder['BuyerEmail'] ?? 'protected@amazon.com',
            'created_at' => $amazonOrder['PurchaseDate'] ?? null,
            'updated_at' => $amazonOrder['LastUpdateDate'] ?? null,
            'fulfillment_channel' => $amazonOrder['FulfillmentChannel'] ?? 'MFN',
        ];
    }

    /**
     * 🔄 Transform Amazon inventory to standard format
     */
    private function transformAmazonInventoryToStandard(array $amazonInventory): array
    {
        return [
            'product_id' => $amazonInventory['asin'] ?? '',
            'sku' => $amazonInventory['sellerSku'] ?? '',
            'quantity' => $amazonInventory['totalQuantity'] ?? 0,
            'available_quantity' => $amazonInventory['inStockQuantity'] ?? 0,
            'reserved_quantity' => $amazonInventory['reservedQuantity'] ?? 0,
            'fulfillment_channel' => $amazonInventory['fulfillmentChannelSku'] ?? 'AMAZON',
            'updated_at' => now(),
        ];
    }

    /**
     * 🔄 Transform fulfillment data to Amazon format
     */
    private function transformFulfillmentDataToAmazon(array $fulfillmentData): array
    {
        return [
            'packageTrackingDetails' => [
                'packageNumber' => 1,
                'trackingNumber' => $fulfillmentData['tracking_number'] ?? '',
                'carrierCode' => $fulfillmentData['tracking_company'] ?? 'UPS',
                'carrierURL' => $fulfillmentData['tracking_url'] ?? '',
            ],
        ];
    }

    /**
     * 🔧 Build Amazon product filters
     */
    private function buildAmazonProductFilters(array $filters): array
    {
        $params = [
            'MarketplaceId' => $this->amazonConfig['marketplace_id'],
        ];

        if (isset($filters['query'])) {
            $params['Query'] = $filters['query'];
        }

        if (isset($filters['sku'])) {
            $params['SellerSKU'] = $filters['sku'];
        }

        return $params;
    }

    /**
     * 🔧 Build Amazon order filters
     */
    private function buildAmazonOrderFilters(array $filters): array
    {
        $params = [
            'MarketplaceIds' => $this->amazonConfig['marketplace_id'],
            'CreatedAfter' => $filters['created_after'] ?? Carbon::now()->subDays(7)->toISOString(),
        ];

        if (isset($filters['status'])) {
            $params['OrderStatuses'] = is_array($filters['status']) ? $filters['status'] : [$filters['status']];
        }

        if (isset($filters['fulfillment_channels'])) {
            $params['FulfillmentChannels'] = $filters['fulfillment_channels'];
        }

        return $params;
    }
}
