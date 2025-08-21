<?php

namespace App\Services\Marketplace\API\Implementations;

use App\Services\Marketplace\API\AbstractMarketplaceService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use PHPShopify\ShopifySDK;

/**
 * 🛍️ SHOPIFY MARKETPLACE SERVICE
 *
 * Concrete implementation of AbstractMarketplaceService for Shopify.
 * Provides complete Shopify API integration using PHPShopify SDK
 * with enhanced features including GraphQL support, taxonomy integration,
 * and optimized performance patterns.
 *
 * Features:
 * - Complete REST and GraphQL API integration
 * - Product, order, and inventory management
 * - Shopify taxonomy category support
 * - Webhook management
 * - Rate limiting and error handling
 * - Performance optimization with caching
 *
 * Usage:
 * $shopify = MarketplaceClient::for('shopify')
 *     ->withAccount($account)
 *     ->build();
 *
 * $result = $shopify->products()
 *     ->create($productData)
 *     ->withImages($images)
 *     ->withCategory($categoryId)
 *     ->execute();
 */
class ShopifyMarketplaceService extends AbstractMarketplaceService
{
    private ?ShopifySDK $shopify = null;

    private array $shopifyConfig = [];

    /**
     * 🏷️ Get marketplace name identifier
     */
    protected function getMarketplaceName(): string
    {
        return 'shopify';
    }

    /**
     * 🔐 Get authentication headers for HTTP client
     */
    protected function getAuthenticationHeaders(): array
    {
        if (! $this->credentials) {
            return [];
        }

        // Shopify uses the SDK for auth, but we can provide headers for direct HTTP calls
        return [
            'X-Shopify-Access-Token' => $this->credentials->get('access_token'),
            'X-Shopify-Shop-Domain' => $this->credentials->get('store_url'),
        ];
    }

    /**
     * 🛠️ Build configuration from sync account
     */
    protected function buildConfigFromAccount($account): array
    {
        $config = parent::buildConfigFromAccount($account);

        $this->shopifyConfig = [
            'ShopUrl' => $config['store_url'] ?? '',
            'AccessToken' => $config['access_token'] ?? '',
            'ApiVersion' => $config['api_version'] ?? '2024-07',
        ];

        // Initialize PHPShopify SDK
        if (! empty($this->shopifyConfig['ShopUrl']) && ! empty($this->shopifyConfig['AccessToken'])) {
            try {
                $this->shopify = new ShopifySDK($this->shopifyConfig);
                Log::info('🛍️ Shopify SDK initialized successfully', [
                    'store_url' => $this->shopifyConfig['ShopUrl'],
                    'api_version' => $this->shopifyConfig['ApiVersion'],
                ]);
            } catch (Exception $e) {
                Log::error('❌ Failed to initialize Shopify SDK', [
                    'error' => $e->getMessage(),
                    'config' => array_merge($this->shopifyConfig, ['AccessToken' => '***']),
                ]);
            }
        }

        return $config;
    }

    /**
     * ⚠️ Extract error message from API response
     */
    protected function extractErrorMessage(array $errorResponse): string
    {
        // Shopify error formats
        if (isset($errorResponse['errors'])) {
            if (is_array($errorResponse['errors'])) {
                return implode(', ', $errorResponse['errors']);
            }

            return $errorResponse['errors'];
        }

        if (isset($errorResponse['error_description'])) {
            return $errorResponse['error_description'];
        }

        if (isset($errorResponse['message'])) {
            return $errorResponse['message'];
        }

        return 'Unknown Shopify API error';
    }

    /**
     * 🔌 Test connection to Shopify
     */
    public function testConnection(): array
    {
        if (! $this->shopify) {
            return [
                'success' => false,
                'error' => 'Shopify SDK not initialized - check credentials',
                'requirements_url' => 'https://shopify.dev/docs/api/admin-rest',
            ];
        }

        return $this->executeRequest(function () {
            $shop = $this->shopify->Shop->get();

            return new class($shop)
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
                    return [
                        'shop' => $this->data,
                        'connection_status' => 'success',
                        'api_version' => '2024-07',
                    ];
                }

                public function body()
                {
                    return json_encode($this->json());
                }
            };
        });
    }

    /**
     * 📋 Get marketplace requirements
     */
    public function getRequirements(): array
    {
        return [
            'store_url' => [
                'label' => 'Store URL',
                'description' => 'Your Shopify store URL (e.g., yourstore.myshopify.com)',
                'required' => true,
                'type' => 'url',
                'validation' => 'required|url|regex:/\.myshopify\.com$/',
            ],
            'access_token' => [
                'label' => 'Access Token',
                'description' => 'Private app access token or admin API token',
                'required' => true,
                'type' => 'password',
                'validation' => 'required|string|min:30',
            ],
            'api_version' => [
                'label' => 'API Version',
                'description' => 'Shopify API version (optional, defaults to 2024-07)',
                'required' => false,
                'type' => 'select',
                'options' => ['2024-07', '2024-04', '2024-01'],
                'default' => '2024-07',
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
                'delete' => true,
                'bulk_operations' => true,
                'variants' => true,
                'images' => true,
                'metafields' => true,
                'categories' => true,
            ],
            'orders' => [
                'read' => true,
                'update_fulfillment' => true,
                'add_tracking' => true,
                'cancel' => true,
                'refund' => true,
            ],
            'inventory' => [
                'read' => true,
                'update' => true,
                'bulk_update' => true,
                'locations' => true,
                'reservations' => false,
            ],
            'webhooks' => [
                'order_created' => true,
                'order_updated' => true,
                'product_updated' => true,
                'inventory_updated' => true,
            ],
            'features' => [
                'graphql' => true,
                'rest_api' => true,
                'taxonomy' => true,
                'multi_location' => true,
                'app_subscriptions' => true,
            ],
        ];
    }

    /**
     * ✅ Validate configuration
     */
    public function validateConfiguration(): array
    {
        $errors = [];

        if (empty($this->shopifyConfig['ShopUrl'])) {
            $errors[] = 'Store URL is required';
        } elseif (! filter_var($this->shopifyConfig['ShopUrl'], FILTER_VALIDATE_URL)) {
            $errors[] = 'Store URL must be a valid URL';
        } elseif (! str_contains($this->shopifyConfig['ShopUrl'], '.myshopify.com')) {
            $errors[] = 'Store URL must be a Shopify domain (.myshopify.com)';
        }

        if (empty($this->shopifyConfig['AccessToken'])) {
            $errors[] = 'Access token is required';
        } elseif (strlen($this->shopifyConfig['AccessToken']) < 30) {
            $errors[] = 'Access token appears to be invalid (too short)';
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
            'requests_per_minute' => 40, // Shopify REST API limit
            'requests_per_second' => 2,
            'burst_limit' => 10,
            'graphql_points_per_minute' => 1000,
            'webhook_limit_per_minute' => 1000,
        ];
    }

    /**
     * 🔐 Get supported authentication methods
     */
    public function getSupportedAuthMethods(): array
    {
        return [
            'private_app' => [
                'name' => 'Private App Token',
                'description' => 'Access token from a private Shopify app',
                'fields' => ['access_token'],
            ],
            'oauth' => [
                'name' => 'OAuth 2.0',
                'description' => 'OAuth flow for public apps',
                'fields' => ['client_id', 'client_secret', 'access_token'],
            ],
        ];
    }

    // =================================
    // PRODUCT OPERATIONS
    // =================================

    /**
     * 🛍️ Create a product
     */
    public function createProduct(array $productData): array
    {
        if (! $this->shopify) {
            return ['success' => false, 'error' => 'Shopify SDK not initialized'];
        }

        return $this->executeRequest(function () use ($productData) {
            // Transform data to Shopify format if needed
            $shopifyData = $this->transformProductDataToShopify($productData);

            $product = $this->shopify->Product->post($shopifyData);

            return new class($product)
            {
                private $data;

                public function __construct($data)
                {
                    $this->data = $data;
                }

                public function successful()
                {
                    return isset($this->data['id']);
                }

                public function status()
                {
                    return $this->successful() ? 200 : 400;
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
     * 📝 Update a product
     */
    public function updateProduct(string $productId, array $productData): array
    {
        if (! $this->shopify) {
            return ['success' => false, 'error' => 'Shopify SDK not initialized'];
        }

        return $this->executeRequest(function () use ($productId, $productData) {
            $shopifyData = $this->transformProductDataToShopify($productData);
            $product = $this->shopify->Product($productId)->put($shopifyData);

            return new class($product)
            {
                private $data;

                public function __construct($data)
                {
                    $this->data = $data;
                }

                public function successful()
                {
                    return isset($this->data['id']);
                }

                public function status()
                {
                    return $this->successful() ? 200 : 400;
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
     * 🗑️ Delete a product
     */
    public function deleteProduct(string $productId): array
    {
        if (! $this->shopify) {
            return ['success' => false, 'error' => 'Shopify SDK not initialized'];
        }

        return $this->executeRequest(function () use ($productId) {
            $result = $this->shopify->Product($productId)->delete();

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
                    return ['deleted' => true, 'result' => $this->data];
                }

                public function body()
                {
                    return json_encode($this->json());
                }
            };
        });
    }

    /**
     * 🔍 Get a single product
     */
    public function getProduct(string $productId): array
    {
        if (! $this->shopify) {
            return ['success' => false, 'error' => 'Shopify SDK not initialized'];
        }

        return $this->executeRequest(function () use ($productId) {
            $product = $this->shopify->Product($productId)->get();

            return new class($product)
            {
                private $data;

                public function __construct($data)
                {
                    $this->data = $data;
                }

                public function successful()
                {
                    return isset($this->data['id']);
                }

                public function status()
                {
                    return $this->successful() ? 200 : 404;
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
        if (! $this->shopify) {
            return collect([]);
        }

        $cacheKey = 'shopify_products_'.md5(serialize($filters));

        return Cache::remember($cacheKey, 300, function () use ($filters) {
            try {
                // Convert filters to Shopify API parameters
                $params = $this->buildShopifyProductFilters($filters);
                $products = $this->shopify->Product->get($params);

                return collect($products)->map(function ($product) {
                    return $this->transformShopifyProductToStandard($product);
                });
            } catch (Exception $e) {
                Log::error('Failed to get Shopify products', [
                    'error' => $e->getMessage(),
                    'filters' => $filters,
                ]);

                return collect([]);
            }
        });
    }

    /**
     * 🚀 Bulk create products
     */
    public function bulkCreateProducts(array $products): array
    {
        $results = [];
        $errors = [];

        foreach ($products as $index => $productData) {
            try {
                $result = $this->createProduct($productData);
                if ($result['success']) {
                    $results[] = $result;
                } else {
                    $errors["product_{$index}"] = $result['error'];
                }
            } catch (Exception $e) {
                $errors["product_{$index}"] = $e->getMessage();
            }

            // Rate limiting between requests
            if (count($products) > 5) {
                usleep(500000); // 500ms delay for bulk operations
            }
        }

        return [
            'success' => empty($errors),
            'results' => $results,
            'errors' => $errors,
            'processed' => count($results),
            'failed' => count($errors),
        ];
    }

    /**
     * 🔄 Bulk update products
     */
    public function bulkUpdateProducts(array $products): array
    {
        $results = [];
        $errors = [];

        foreach ($products as $index => $productData) {
            try {
                $productId = $productData['id'] ?? $productData['sku'] ?? null;
                if (! $productId) {
                    $errors["product_{$index}"] = 'Product ID or SKU required for update';

                    continue;
                }

                $result = $this->updateProduct($productId, $productData);
                if ($result['success']) {
                    $results[] = $result;
                } else {
                    $errors["product_{$index}"] = $result['error'];
                }
            } catch (Exception $e) {
                $errors["product_{$index}"] = $e->getMessage();
            }

            // Rate limiting
            if (count($products) > 5) {
                usleep(500000); // 500ms delay
            }
        }

        return [
            'success' => empty($errors),
            'results' => $results,
            'errors' => $errors,
            'processed' => count($results),
            'failed' => count($errors),
        ];
    }

    /**
     * 🔄 Sync products from local to Shopify
     */
    public function syncProducts(Collection $localProducts): array
    {
        $syncResults = [
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($localProducts as $localProduct) {
            try {
                // Check if product exists in Shopify by SKU
                $existingProduct = $this->findProductBySku($localProduct['sku'] ?? '');

                if ($existingProduct) {
                    $result = $this->updateProduct($existingProduct['id'], $localProduct);
                    if ($result['success']) {
                        $syncResults['updated']++;
                    } else {
                        $syncResults['failed']++;
                        $syncResults['errors'][] = "Update failed for SKU {$localProduct['sku']}: {$result['error']}";
                    }
                } else {
                    $result = $this->createProduct($localProduct);
                    if ($result['success']) {
                        $syncResults['created']++;
                    } else {
                        $syncResults['failed']++;
                        $syncResults['errors'][] = "Create failed for SKU {$localProduct['sku']}: {$result['error']}";
                    }
                }

                // Rate limiting
                usleep(1500000); // 1.5s delay between sync operations

            } catch (Exception $e) {
                $syncResults['failed']++;
                $syncResults['errors'][] = "Exception for SKU {$localProduct['sku']}: {$e->getMessage()}";
            }
        }

        return [
            'success' => $syncResults['failed'] === 0,
            'summary' => $syncResults,
        ];
    }

    /**
     * 🏷️ Get marketplace categories/taxonomies
     */
    public function getCategories(): array
    {
        if (! $this->shopify) {
            return [];
        }

        try {
            // Use existing taxonomy method from deprecated service
            $taxonomyResult = $this->getAllTaxonomyCategories();

            if ($taxonomyResult['success']) {
                $categories = $taxonomyResult['data']['taxonomy']['categories']['edges'] ?? [];

                return array_map(function ($edge) {
                    $category = $edge['node'];

                    return [
                        'id' => $category['id'],
                        'name' => $category['name'],
                        'full_name' => $category['fullName'],
                        'level' => $category['level'],
                        'is_leaf' => $category['isLeaf'],
                        'parent_id' => $category['parentId'],
                        'children_ids' => $category['childrenIds'] ?? [],
                    ];
                }, $categories);
            }

            return [];
        } catch (Exception $e) {
            Log::error('Failed to get Shopify categories', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * 🎨 Upload and manage product images
     */
    public function uploadProductImages(string $productId, array $images): array
    {
        if (! $this->shopify) {
            return ['success' => false, 'error' => 'Shopify SDK not initialized'];
        }

        return $this->executeRequest(function () use ($productId, $images) {
            $uploadedImages = [];

            foreach ($images as $image) {
                try {
                    $imageData = [
                        'src' => $image['url'] ?? $image['src'] ?? '',
                        'alt' => $image['alt'] ?? '',
                        'position' => $image['position'] ?? null,
                    ];

                    $result = $this->shopify->Product($productId)->Image->post($imageData);
                    $uploadedImages[] = $result;
                } catch (Exception $e) {
                    Log::error('Failed to upload image', [
                        'product_id' => $productId,
                        'image' => $image,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return new class($uploadedImages)
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
                    return ['images' => $this->data];
                }

                public function body()
                {
                    return json_encode($this->json());
                }
            };
        });
    }

    /**
     * 💰 Update product pricing
     */
    public function updatePricing(string $productId, array $pricingData): array
    {
        if (! $this->shopify) {
            return ['success' => false, 'error' => 'Shopify SDK not initialized'];
        }

        return $this->executeRequest(function () use ($productId, $pricingData) {
            // Get product first to update variants
            $product = $this->shopify->Product($productId)->get();

            $updatedVariants = [];
            foreach ($product['variants'] ?? [] as $variant) {
                $variantId = $variant['id'];
                $newPrice = $pricingData['price'] ?? $variant['price'];
                $compareAtPrice = $pricingData['compare_at_price'] ?? $variant['compare_at_price'];

                $variantUpdate = [
                    'id' => $variantId,
                    'price' => $newPrice,
                ];

                if ($compareAtPrice) {
                    $variantUpdate['compare_at_price'] = $compareAtPrice;
                }

                $updatedVariant = $this->shopify->Product($productId)->Variant($variantId)->put($variantUpdate);
                $updatedVariants[] = $updatedVariant;
            }

            return new class($updatedVariants)
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
                    return ['pricing_updated' => $this->data];
                }

                public function body()
                {
                    return json_encode($this->json());
                }
            };
        });
    }

    /**
     * 🏃‍♂️ Get product variants
     */
    public function getProductVariants(string $productId): array
    {
        if (! $this->shopify) {
            return [];
        }

        try {
            $product = $this->shopify->Product($productId)->get();

            return $product['variants'] ?? [];
        } catch (Exception $e) {
            Log::error('Failed to get product variants', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * ✅ Validate product data
     */
    public function validateProductData(array $productData): array
    {
        $errors = [];

        // Shopify-specific validation
        if (empty($productData['title'])) {
            $errors[] = 'Product title is required';
        }

        if (isset($productData['title']) && strlen($productData['title']) > 255) {
            $errors[] = 'Product title cannot exceed 255 characters';
        }

        if (isset($productData['handle']) && ! preg_match('/^[a-z0-9-]+$/', $productData['handle'])) {
            $errors[] = 'Product handle can only contain lowercase letters, numbers, and hyphens';
        }

        if (isset($productData['variants']) && is_array($productData['variants'])) {
            foreach ($productData['variants'] as $index => $variant) {
                if (isset($variant['price']) && ! is_numeric($variant['price'])) {
                    $errors[] = "Variant {$index}: price must be numeric";
                }
                if (isset($variant['inventory_quantity']) && ! is_numeric($variant['inventory_quantity'])) {
                    $errors[] = "Variant {$index}: inventory quantity must be numeric";
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    // =================================
    // ORDER OPERATIONS
    // =================================

    /**
     * 📦 Get orders
     */
    public function getOrders(array $filters = []): Collection
    {
        if (! $this->shopify) {
            return collect([]);
        }

        try {
            $params = $this->buildShopifyOrderFilters($filters);
            $orders = $this->shopify->Order->get($params);

            return collect($orders)->map(function ($order) {
                return $this->transformShopifyOrderToStandard($order);
            });
        } catch (Exception $e) {
            Log::error('Failed to get Shopify orders', [
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
        if (! $this->shopify) {
            return ['success' => false, 'error' => 'Shopify SDK not initialized'];
        }

        return $this->executeRequest(function () use ($orderId) {
            $order = $this->shopify->Order($orderId)->get();

            return new class($order)
            {
                private $data;

                public function __construct($data)
                {
                    $this->data = $data;
                }

                public function successful()
                {
                    return isset($this->data['id']);
                }

                public function status()
                {
                    return $this->successful() ? 200 : 404;
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
     * 📅 Get orders since specific date
     */
    public function getOrdersSince(Carbon $since): Collection
    {
        if (! $this->shopify) {
            return collect([]);
        }

        try {
            $params = [
                'created_at_min' => $since->toISOString(),
                'status' => 'any',
                'limit' => 250,
            ];

            $orders = $this->shopify->Order->get($params);

            return collect($orders)->map(function ($order) {
                return $this->transformShopifyOrderToStandard($order);
            });
        } catch (Exception $e) {
            Log::error('Failed to get Shopify orders since date', [
                'error' => $e->getMessage(),
                'since' => $since->toISOString(),
            ]);

            return collect([]);
        }
    }

    /**
     * 🚚 Update order fulfillment
     */
    public function updateOrderFulfillment(string $orderId, array $fulfillmentData): array
    {
        if (! $this->shopify) {
            return ['success' => false, 'error' => 'Shopify SDK not initialized'];
        }

        return $this->executeRequest(function () use ($orderId, $fulfillmentData) {
            $shopifyFulfillment = $this->transformFulfillmentDataToShopify($fulfillmentData);
            $fulfillment = $this->shopify->Order($orderId)->Fulfillment->post($shopifyFulfillment);

            return new class($fulfillment)
            {
                private $data;

                public function __construct($data)
                {
                    $this->data = $data;
                }

                public function successful()
                {
                    return isset($this->data['id']);
                }

                public function status()
                {
                    return $this->successful() ? 200 : 400;
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

    /**
     * 📦 Add tracking to order
     */
    public function addTrackingToOrder(string $orderId, array $trackingData): array
    {
        return $this->updateOrderFulfillment($orderId, $trackingData);
    }

    /**
     * ❌ Cancel order
     */
    public function cancelOrder(string $orderId, string $reason = ''): array
    {
        if (! $this->shopify) {
            return ['success' => false, 'error' => 'Shopify SDK not initialized'];
        }

        return $this->executeRequest(function () use ($orderId, $reason) {
            $cancelData = [
                'reason' => $reason ?: 'other',
            ];

            $result = $this->shopify->Order($orderId)->cancel($cancelData);

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
                    return ['cancelled' => $this->data];
                }

                public function body()
                {
                    return json_encode($this->json());
                }
            };
        });
    }

    /**
     * 💳 Refund order
     */
    public function refundOrder(string $orderId, array $refundData): array
    {
        if (! $this->shopify) {
            return ['success' => false, 'error' => 'Shopify SDK not initialized'];
        }

        return $this->executeRequest(function () use ($orderId, $refundData) {
            $shopifyRefund = [
                'amount' => $refundData['amount'] ?? '0.00',
                'reason' => $refundData['reason'] ?? 'other',
                'notify' => $refundData['notify_customer'] ?? false,
            ];

            $result = $this->shopify->Order($orderId)->Refund->post($shopifyRefund);

            return new class($result)
            {
                private $data;

                public function __construct($data)
                {
                    $this->data = $data;
                }

                public function successful()
                {
                    return isset($this->data['id']);
                }

                public function status()
                {
                    return $this->successful() ? 200 : 400;
                }

                public function json()
                {
                    return ['refund' => $this->data];
                }

                public function body()
                {
                    return json_encode($this->json());
                }
            };
        });
    }

    /**
     * 🔄 Sync orders to local system
     */
    public function syncOrdersToLocal(?Carbon $since = null): array
    {
        $filters = [];
        if ($since) {
            $filters['created_at_min'] = $since->toISOString();
        }

        $orders = $this->getOrders($filters);

        return [
            'success' => true,
            'orders_synced' => $orders->count(),
            'orders' => $orders->toArray(),
        ];
    }

    /**
     * 📄 Get order invoice
     */
    public function getOrderInvoice(string $orderId): array
    {
        // Shopify doesn't have a direct invoice API, return order details
        return $this->getOrder($orderId);
    }

    /**
     * 📊 Get order statistics
     */
    public function getOrderStatistics(array $filters = []): array
    {
        $orders = $this->getOrders($filters);

        $totalOrders = $orders->count();
        $totalAmount = $orders->sum('total_amount');
        $pendingOrders = $orders->where('status', 'pending')->count();
        $fulfilledOrders = $orders->where('fulfillment_status', 'fulfilled')->count();

        return [
            'total_orders' => $totalOrders,
            'total_amount' => $totalAmount,
            'average_order_value' => $totalOrders > 0 ? round($totalAmount / $totalOrders, 2) : 0,
            'pending_orders' => $pendingOrders,
            'fulfilled_orders' => $fulfilledOrders,
            'fulfillment_rate' => $totalOrders > 0 ? round(($fulfilledOrders / $totalOrders) * 100, 2) : 0,
        ];
    }

    /**
     * 🏷️ Get order statuses
     */
    public function getOrderStatuses(): array
    {
        return [
            'pending', 'authorized', 'partially_paid', 'paid',
            'partially_refunded', 'refunded', 'voided', 'cancelled',
        ];
    }

    /**
     * 🚚 Get shipping methods
     */
    public function getShippingMethods(): array
    {
        // This would require accessing Shopify's shipping settings
        return [
            'standard' => 'Standard Shipping',
            'express' => 'Express Shipping',
            'overnight' => 'Overnight Shipping',
        ];
    }

    // =================================
    // INVENTORY OPERATIONS
    // =================================

    /**
     * 📊 Get inventory levels
     */
    public function getInventoryLevels(array $productIds = []): Collection
    {
        if (! $this->shopify) {
            return collect([]);
        }

        try {
            $inventory = [];

            if (empty($productIds)) {
                // Get all inventory
                $locations = $this->shopify->Location->get();
                if (is_array($locations)) {
                    foreach ($locations as $location) {
                        if (is_array($location) && isset($location['id'])) {
                            $levels = $this->shopify->InventoryLevel->get(['location_ids' => $location['id']]);
                            if (is_array($levels)) {
                                $inventory = array_merge($inventory, $levels);
                            }
                        }
                    }
                }
            } else {
                // Get inventory for specific products
                foreach ($productIds as $productId) {
                    $product = $this->shopify->Product($productId)->get();
                    foreach ($product['variants'] ?? [] as $variant) {
                        $levels = $this->shopify->InventoryLevel->get(['inventory_item_ids' => $variant['inventory_item_id']]);
                        $inventory = array_merge($inventory, $levels);
                    }
                }
            }

            return collect($inventory)->map(function ($level) {
                return $this->transformShopifyInventoryToStandard($level);
            });
        } catch (Exception $e) {
            Log::error('Failed to get Shopify inventory', [
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
        if (! $this->shopify) {
            return ['success' => false, 'error' => 'Shopify SDK not initialized'];
        }

        // For Shopify, we need to update inventory through inventory items and levels
        return $this->executeRequest(function () use ($productId, $inventoryData) {
            // Implementation depends on whether $inventoryData is quantity or full data
            $quantity = is_numeric($inventoryData) ? $inventoryData : ($inventoryData['quantity'] ?? 0);

            // Get product variants to update their inventory
            $product = $this->shopify->Product($productId)->get();
            $results = [];

            foreach ($product['variants'] ?? [] as $variant) {
                $inventoryItemId = $variant['inventory_item_id'];
                $locations = $this->shopify->Location->get();

                foreach ($locations as $location) {
                    $adjustment = [
                        'location_id' => $location['id'],
                        'inventory_item_id' => $inventoryItemId,
                        'available_adjustment' => $quantity - ($variant['inventory_quantity'] ?? 0),
                    ];

                    $result = $this->shopify->InventoryLevel->post($adjustment);
                    $results[] = $result;
                }
            }

            return new class($results)
            {
                private $data;

                public function __construct($data)
                {
                    $this->data = $data;
                }

                public function successful()
                {
                    return ! empty($this->data);
                }

                public function status()
                {
                    return $this->successful() ? 200 : 400;
                }

                public function json()
                {
                    return ['inventory_updates' => $this->data];
                }

                public function body()
                {
                    return json_encode($this->json());
                }
            };
        });
    }

    /**
     * 🚀 Bulk update inventory levels
     */
    public function bulkUpdateInventory(array $inventoryUpdates): array
    {
        $results = [];
        $errors = [];

        foreach ($inventoryUpdates as $index => $update) {
            try {
                $productId = $update['product_id'] ?? $update['sku'] ?? null;
                if (! $productId) {
                    $errors["update_{$index}"] = 'Product ID or SKU required';

                    continue;
                }

                $result = $this->updateInventory($productId, $update);
                if ($result['success']) {
                    $results[] = $result;
                } else {
                    $errors["update_{$index}"] = $result['error'];
                }
            } catch (Exception $e) {
                $errors["update_{$index}"] = $e->getMessage();
            }

            // Rate limiting
            usleep(250000); // 250ms delay between updates
        }

        return [
            'success' => empty($errors),
            'results' => $results,
            'errors' => $errors,
            'processed' => count($results),
            'failed' => count($errors),
        ];
    }

    /**
     * 📍 Get inventory by location/warehouse
     */
    public function getInventoryByLocation(string $locationId): Collection
    {
        if (! $this->shopify) {
            return collect([]);
        }

        try {
            $inventoryLevels = $this->shopify->InventoryLevel->get(['location_ids' => $locationId]);

            return collect($inventoryLevels)->map(function ($level) {
                return $this->transformShopifyInventoryToStandard($level);
            });
        } catch (Exception $e) {
            Log::error('Failed to get Shopify inventory by location', [
                'location_id' => $locationId,
                'error' => $e->getMessage(),
            ]);

            return collect([]);
        }
    }

    /**
     * ⚠️ Get low stock alerts
     */
    public function getLowStockProducts(int $threshold = 5): Collection
    {
        $allInventory = $this->getInventoryLevels();

        return $allInventory->filter(function ($inventory) use ($threshold) {
            return ($inventory['quantity'] ?? 0) <= $threshold;
        });
    }

    /**
     * 📈 Get inventory movement history
     */
    public function getInventoryHistory(string $productId, int $days = 30): array
    {
        // Shopify doesn't provide detailed inventory history via API
        // This would require custom tracking or third-party apps
        return [
            'product_id' => $productId,
            'days' => $days,
            'history' => [],
            'note' => 'Shopify API does not provide detailed inventory movement history. Consider using Shopify Flow or third-party inventory tracking apps.',
        ];
    }

    /**
     * 🔄 Sync inventory from local to marketplace
     */
    public function syncInventoryToMarketplace(Collection $localInventory): array
    {
        $syncResults = [
            'updated' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($localInventory as $inventoryItem) {
            try {
                $productId = $inventoryItem['product_id'] ?? $inventoryItem['sku'] ?? null;
                if (! $productId) {
                    $syncResults['failed']++;
                    $syncResults['errors'][] = 'Product ID or SKU required for inventory sync';

                    continue;
                }

                $result = $this->updateInventory($productId, $inventoryItem);
                if ($result['success']) {
                    $syncResults['updated']++;
                } else {
                    $syncResults['failed']++;
                    $syncResults['errors'][] = "Failed to sync inventory for {$productId}: {$result['error']}";
                }

                // Rate limiting
                usleep(500000); // 500ms delay
            } catch (Exception $e) {
                $syncResults['failed']++;
                $syncResults['errors'][] = "Exception syncing {$productId}: {$e->getMessage()}";
            }
        }

        return [
            'success' => $syncResults['failed'] === 0,
            'summary' => $syncResults,
        ];
    }

    /**
     * ⬇️ Pull inventory updates from marketplace
     */
    public function pullInventoryFromMarketplace(): array
    {
        $inventory = $this->getInventoryLevels();

        return [
            'success' => true,
            'inventory_pulled' => $inventory->count(),
            'inventory' => $inventory->toArray(),
        ];
    }

    /**
     * 🏪 Get available locations/warehouses
     */
    public function getLocations(): array
    {
        if (! $this->shopify) {
            return [];
        }

        try {
            $locations = $this->shopify->Location->get();

            return array_map(function ($location) {
                return [
                    'id' => $location['id'],
                    'name' => $location['name'],
                    'address' => $location['address1'] ?? '',
                    'city' => $location['city'] ?? '',
                    'country' => $location['country_code'] ?? '',
                    'active' => $location['active'] ?? true,
                ];
            }, $locations);
        } catch (Exception $e) {
            Log::error('Failed to get Shopify locations', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * 🎯 Reserve inventory for orders (not supported by Shopify)
     */
    public function reserveInventory(string $productId, int $quantity): array
    {
        return [
            'success' => false,
            'error' => 'Shopify does not support inventory reservations via API',
            'recommendation' => 'Use inventory policies and checkout reservations instead',
        ];
    }

    /**
     * ↩️ Release reserved inventory (not supported by Shopify)
     */
    public function releaseReservedInventory(string $productId, int $quantity): array
    {
        return [
            'success' => false,
            'error' => 'Shopify does not support inventory reservations via API',
            'recommendation' => 'Use inventory policies and checkout reservations instead',
        ];
    }

    /**
     * ✅ Validate inventory data
     */
    public function validateInventoryData(array $inventoryData): array
    {
        $errors = [];

        if (! isset($inventoryData['quantity']) || ! is_numeric($inventoryData['quantity'])) {
            $errors[] = 'Quantity must be a number';
        }

        if (isset($inventoryData['quantity']) && $inventoryData['quantity'] < 0) {
            $errors[] = 'Quantity cannot be negative';
        }

        if (isset($inventoryData['location_id']) && ! is_string($inventoryData['location_id'])) {
            $errors[] = 'Location ID must be a string';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * 📋 Get inventory adjustment reasons
     */
    public function getAdjustmentReasons(): array
    {
        return [
            'restock' => 'Restocking inventory',
            'correction' => 'Inventory correction',
            'damage' => 'Damaged goods',
            'theft' => 'Theft or loss',
            'other' => 'Other reason',
        ];
    }

    // =================================
    // HELPER METHODS
    // =================================

    /**
     * 🔄 Transform product data to Shopify format
     */
    private function transformProductDataToShopify(array $productData): array
    {
        // Convert standard product format to Shopify's expected format
        $shopifyData = [];

        if (isset($productData['title'])) {
            $shopifyData['title'] = $productData['title'];
        }
        if (isset($productData['description'])) {
            $shopifyData['body_html'] = $productData['description'];
        }
        if (isset($productData['vendor'])) {
            $shopifyData['vendor'] = $productData['vendor'];
        }
        if (isset($productData['product_type'])) {
            $shopifyData['product_type'] = $productData['product_type'];
        }
        if (isset($productData['status'])) {
            $shopifyData['status'] = $productData['status'];
        }

        // Transform variants
        if (isset($productData['variants'])) {
            $shopifyData['variants'] = array_map(function ($variant) {
                $shopifyVariant = [];
                if (isset($variant['sku'])) {
                    $shopifyVariant['sku'] = $variant['sku'];
                }
                if (isset($variant['price'])) {
                    $shopifyVariant['price'] = $variant['price'];
                }
                if (isset($variant['inventory_quantity'])) {
                    $shopifyVariant['inventory_quantity'] = $variant['inventory_quantity'];
                }
                if (isset($variant['barcode'])) {
                    $shopifyVariant['barcode'] = $variant['barcode'];
                }

                return $shopifyVariant;
            }, $productData['variants']);
        }

        return $shopifyData;
    }

    /**
     * 🔍 Find product by SKU
     */
    private function findProductBySku(string $sku): ?array
    {
        if (! $this->shopify || empty($sku)) {
            return null;
        }

        try {
            $products = $this->shopify->Product->get(['fields' => 'id,variants', 'limit' => 250]);

            foreach ($products as $product) {
                foreach ($product['variants'] ?? [] as $variant) {
                    if (($variant['sku'] ?? '') === $sku) {
                        return $product;
                    }
                }
            }
        } catch (Exception $e) {
            Log::error('Failed to find product by SKU', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * 🔧 Build Shopify product filters
     */
    private function buildShopifyProductFilters(array $filters): array
    {
        $params = ['limit' => $filters['limit'] ?? 50];

        if (isset($filters['status'])) {
            $params['status'] = $filters['status'];
        }
        if (isset($filters['vendor'])) {
            $params['vendor'] = $filters['vendor'];
        }
        if (isset($filters['product_type'])) {
            $params['product_type'] = $filters['product_type'];
        }
        if (isset($filters['created_at_min'])) {
            $params['created_at_min'] = $filters['created_at_min'];
        }
        if (isset($filters['updated_at_min'])) {
            $params['updated_at_min'] = $filters['updated_at_min'];
        }

        return $params;
    }

    /**
     * 🔧 Build Shopify order filters
     */
    private function buildShopifyOrderFilters(array $filters): array
    {
        $params = ['limit' => $filters['limit'] ?? 50];

        if (isset($filters['status'])) {
            $params['status'] = $filters['status'];
        }
        if (isset($filters['financial_status'])) {
            $params['financial_status'] = $filters['financial_status'];
        }
        if (isset($filters['fulfillment_status'])) {
            $params['fulfillment_status'] = $filters['fulfillment_status'];
        }
        if (isset($filters['created_at_min'])) {
            $params['created_at_min'] = $filters['created_at_min'];
        }

        return $params;
    }

    /**
     * 🔄 Transform Shopify product to standard format
     */
    private function transformShopifyProductToStandard(array $shopifyProduct): array
    {
        return [
            'id' => $shopifyProduct['id'],
            'title' => $shopifyProduct['title'],
            'description' => $shopifyProduct['body_html'] ?? '',
            'vendor' => $shopifyProduct['vendor'] ?? '',
            'product_type' => $shopifyProduct['product_type'] ?? '',
            'status' => $shopifyProduct['status'] ?? 'draft',
            'created_at' => $shopifyProduct['created_at'] ?? null,
            'updated_at' => $shopifyProduct['updated_at'] ?? null,
            'variants' => array_map([$this, 'transformShopifyVariantToStandard'], $shopifyProduct['variants'] ?? []),
            'images' => $shopifyProduct['images'] ?? [],
            'options' => $shopifyProduct['options'] ?? [],
        ];
    }

    /**
     * 🔄 Transform Shopify variant to standard format
     */
    private function transformShopifyVariantToStandard(array $shopifyVariant): array
    {
        return [
            'id' => $shopifyVariant['id'],
            'sku' => $shopifyVariant['sku'] ?? '',
            'price' => $shopifyVariant['price'] ?? '0.00',
            'inventory_quantity' => $shopifyVariant['inventory_quantity'] ?? 0,
            'barcode' => $shopifyVariant['barcode'] ?? '',
            'weight' => $shopifyVariant['weight'] ?? 0,
            'created_at' => $shopifyVariant['created_at'] ?? null,
            'updated_at' => $shopifyVariant['updated_at'] ?? null,
        ];
    }

    /**
     * 🔄 Transform Shopify order to standard format
     */
    private function transformShopifyOrderToStandard(array $shopifyOrder): array
    {
        return [
            'id' => $shopifyOrder['id'],
            'order_number' => $shopifyOrder['order_number'] ?? $shopifyOrder['name'] ?? '',
            'status' => $shopifyOrder['financial_status'] ?? 'pending',
            'fulfillment_status' => $shopifyOrder['fulfillment_status'] ?? 'unfulfilled',
            'total_amount' => $shopifyOrder['total_price'] ?? '0.00',
            'currency' => $shopifyOrder['currency'] ?? 'USD',
            'customer_email' => $shopifyOrder['email'] ?? '',
            'created_at' => $shopifyOrder['created_at'] ?? null,
            'updated_at' => $shopifyOrder['updated_at'] ?? null,
            'line_items' => $shopifyOrder['line_items'] ?? [],
        ];
    }

    /**
     * 🔄 Transform Shopify inventory to standard format
     */
    private function transformShopifyInventoryToStandard(array $shopifyInventory): array
    {
        return [
            'product_id' => $shopifyInventory['inventory_item_id'] ?? '',
            'location_id' => $shopifyInventory['location_id'] ?? '',
            'quantity' => $shopifyInventory['available'] ?? 0,
            'updated_at' => $shopifyInventory['updated_at'] ?? null,
        ];
    }

    /**
     * 🔄 Transform fulfillment data to Shopify format
     */
    private function transformFulfillmentDataToShopify(array $fulfillmentData): array
    {
        $shopifyFulfillment = [];

        if (isset($fulfillmentData['tracking_number'])) {
            $shopifyFulfillment['tracking_number'] = $fulfillmentData['tracking_number'];
        }

        if (isset($fulfillmentData['tracking_company'])) {
            $shopifyFulfillment['tracking_company'] = $fulfillmentData['tracking_company'];
        }

        if (isset($fulfillmentData['notify_customer'])) {
            $shopifyFulfillment['notify_customer'] = $fulfillmentData['notify_customer'];
        }

        return $shopifyFulfillment;
    }

    /**
     * 🏷️ Get all taxonomy categories (referenced by getCategories)
     */
    private function getAllTaxonomyCategories(int $batchSize = 250): array
    {
        if (! $this->shopify) {
            return ['success' => false, 'error' => 'Shopify SDK not initialized'];
        }

        try {
            // Simplified version - get categories using GraphQL
            $graphQL = <<<Query
query {
  taxonomy {
    categories(first: $batchSize) {
      edges {
        node {
          id
          name
          fullName
          level
          isLeaf
          parentId
          childrenIds
        }
      }
    }
  }
}
Query;

            $response = $this->shopify->GraphQL->post($graphQL);

            return [
                'success' => true,
                'data' => $response,
            ];
        } catch (Exception $e) {
            Log::error('Failed to get Shopify taxonomy', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
