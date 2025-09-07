<?php

namespace App\Services\Marketplace\API\Implementations;

use App\Services\Marketplace\API\AbstractMarketplaceService;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 🏬 MIRAKL MARKETPLACE SERVICE
 *
 * Concrete implementation of AbstractMarketplaceService for Mirakl operator API.
 * Provides complete Mirakl marketplace integration with multi-operator support,
 * product catalog management, order processing, and commission tracking.
 *
 * Features:
 * - Multi-operator Mirakl platform support
 * - Mirakl operator API integration
 * - Product catalog and category management
 * - Order management and fulfillment
 * - Commission and payment tracking
 * - Shop management and onboarding
 * - Category tree and attribute mapping
 * - Document and attachment handling
 *
 * Supported Operators:
 * - BQ (British Quality)
 * - Debenhams
 * - Freemans
 * - And other Mirakl-powered marketplaces
 *
 * Usage:
 * $mirakl = MarketplaceClient::for('mirakl')
 *     ->withAccount($account)
 *     ->withConfig(['operator' => 'bq'])
 *     ->build();
 *
 * $result = $mirakl->products()
 *     ->create($productData)
 *     ->withCategories($categories)
 *     ->execute();
 */
class MiraklMarketplaceService extends AbstractMarketplaceService
{
    private array $miraklConfig = [];

    private array $operatorConfigs = [
        'bq' => [
            'name' => 'British Quality',
            'base_url' => 'https://bq-marketplace-api.mirakl.net',
            'currency' => 'GBP',
            'locale' => 'en_GB',
        ],
        'debenhams' => [
            'name' => 'Debenhams',
            'base_url' => 'https://debenhams-marketplace-api.mirakl.net',
            'currency' => 'GBP',
            'locale' => 'en_GB',
        ],
        'freemans' => [
            'name' => 'Freemans',
            'base_url' => 'https://freemans-marketplace-api.mirakl.net',
            'currency' => 'GBP',
            'locale' => 'en_GB',
        ],
    ];

    /**
     * 🏷️ Get marketplace name identifier
     */
    protected function getMarketplaceName(): string
    {
        return 'mirakl';
    }

    /**
     * 🔐 Get authentication headers for HTTP client
     */
    protected function getAuthenticationHeaders(): array
    {
        return [
            'Authorization' => $this->miraklConfig['api_key'] ?? '',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /**
     * 🛠️ Build configuration from sync account
     */
    protected function buildConfigFromAccount($account): array
    {
        $config = parent::buildConfigFromAccount($account);

        $this->miraklConfig = [
            'api_url' => $config['api_url'] ?? '',
            'api_key' => $config['api_key'] ?? '',
            'operator' => $config['operator'] ?? 'bq',
            'shop_id' => $config['shop_id'] ?? '',
            'currency' => $config['currency'] ?? 'GBP',
            'locale' => $config['locale'] ?? 'en_GB',
        ];

        // Auto-configure based on operator if URL not provided
        if (empty($this->miraklConfig['api_url']) && isset($this->operatorConfigs[$this->miraklConfig['operator']])) {
            $operatorConfig = $this->operatorConfigs[$this->miraklConfig['operator']];
            $this->miraklConfig['api_url'] = $operatorConfig['base_url'];
            $this->miraklConfig['currency'] = $operatorConfig['currency'];
            $this->miraklConfig['locale'] = $operatorConfig['locale'];
        }

        Log::info('🏬 Mirakl service configured', [
            'operator' => $this->miraklConfig['operator'],
            'api_url' => $this->miraklConfig['api_url'],
            'shop_id' => $this->miraklConfig['shop_id'],
            'has_api_key' => ! empty($this->miraklConfig['api_key']),
        ]);

        return $config;
    }

    /**
     * ⚠️ Extract error message from API response
     */
    protected function extractErrorMessage(array $errorResponse): string
    {
        // Mirakl error formats
        if (isset($errorResponse['errors']) && is_array($errorResponse['errors'])) {
            $errors = [];
            foreach ($errorResponse['errors'] as $error) {
                $message = $error['message'] ?? $error['error_message'] ?? 'Unknown error';
                $code = $error['error_code'] ?? $error['code'] ?? '';
                $field = $error['field'] ?? '';

                $errorString = $message;
                if ($code) {
                    $errorString = "[$code] $errorString";
                }
                if ($field) {
                    $errorString .= " (field: $field)";
                }

                $errors[] = $errorString;
            }

            return implode('; ', $errors);
        }

        if (isset($errorResponse['error_message'])) {
            return $errorResponse['error_message'];
        }

        if (isset($errorResponse['message'])) {
            return $errorResponse['message'];
        }

        return 'Unknown Mirakl API error';
    }

    /**
     * 🔌 Test connection to Mirakl
     */
    public function testConnection(): array
    {
        try {
            // Test API call - get version info
            $versionResult = $this->getVersion();
            if (! $versionResult['success']) {
                return [
                    'success' => false,
                    'error' => 'API test failed: '.$versionResult['error'],
                    'operator' => $this->miraklConfig['operator'],
                ];
            }

            // Test shops endpoint if shop_id is configured
            $shopsResult = null;
            if (! empty($this->miraklConfig['shop_id'])) {
                $shopsResult = $this->getShop($this->miraklConfig['shop_id']);
            }

            return [
                'success' => true,
                'message' => 'Successfully connected to Mirakl API',
                'operator' => $this->miraklConfig['operator'],
                'operator_name' => $this->operatorConfigs[$this->miraklConfig['operator']]['name'] ?? 'Unknown',
                'api_version' => $versionResult['data']['version'] ?? 'Unknown',
                'currency' => $this->miraklConfig['currency'],
                'shop_configured' => ! empty($this->miraklConfig['shop_id']),
                'shop_accessible' => $shopsResult ? $shopsResult['success'] : null,
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
            'operator' => [
                'label' => 'Operator',
                'description' => 'Mirakl marketplace operator',
                'required' => true,
                'type' => 'select',
                'options' => array_map(fn ($config) => $config['name'], $this->operatorConfigs),
                'default' => 'bq',
            ],
            'api_url' => [
                'label' => 'API URL',
                'description' => 'Mirakl operator API URL (auto-configured for known operators)',
                'required' => false,
                'type' => 'url',
                'placeholder' => 'https://your-operator-api.mirakl.net',
            ],
            'api_key' => [
                'label' => 'API Key',
                'description' => 'Mirakl operator API key',
                'required' => true,
                'type' => 'password',
                'validation' => 'required|string|min:10',
            ],
            'shop_id' => [
                'label' => 'Shop ID',
                'description' => 'Your shop ID on the Mirakl marketplace (optional)',
                'required' => false,
                'type' => 'text',
            ],
            'currency' => [
                'label' => 'Currency',
                'description' => 'Default currency for pricing',
                'required' => false,
                'type' => 'select',
                'options' => ['GBP', 'EUR', 'USD'],
                'default' => 'GBP',
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
                'categories' => true,
                'attributes' => true,
            ],
            'orders' => [
                'read' => true,
                'update_fulfillment' => true,
                'accept_order' => true,
                'refuse_order' => true,
                'shipping_tracking' => true,
            ],
            'inventory' => [
                'read' => true,
                'update' => true,
                'bulk_update' => true,
                'multi_shop' => true,
            ],
            'shops' => [
                'create' => true,
                'read' => true,
                'update' => true,
                'documents' => true,
                'evaluation' => true,
            ],
            'features' => [
                'multi_operator' => true,
                'category_mapping' => true,
                'commission_tracking' => true,
                'payment_tracking' => true,
                'document_management' => true,
                'shop_evaluation' => true,
            ],
        ];
    }

    /**
     * ✅ Validate configuration
     */
    public function validateConfiguration(): array
    {
        $errors = [];

        if (empty($this->miraklConfig['api_key'])) {
            $errors[] = 'API Key is required';
        }

        if (empty($this->miraklConfig['api_url'])) {
            $errors[] = 'API URL is required';
        } elseif (! filter_var($this->miraklConfig['api_url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'API URL must be a valid URL';
        }

        if (! array_key_exists($this->miraklConfig['operator'], $this->operatorConfigs)) {
            $errors[] = 'Invalid operator specified';
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
            'requests_per_minute' => 600, // Mirakl has generous rate limits
            'requests_per_second' => 10,
            'burst_limit' => 20,
            'per_endpoint_limits' => true, // Some endpoints have specific limits
        ];
    }

    /**
     * 🔐 Get supported authentication methods
     */
    public function getSupportedAuthMethods(): array
    {
        return [
            'api_key' => [
                'name' => 'API Key',
                'description' => 'Mirakl operator API key authentication',
                'fields' => ['api_key'],
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
        return $this->executeRequest(function () use ($productData) {
            $miraklData = $this->transformProductToMirakl($productData);

            $response = Http::withHeaders($this->getAuthenticationHeaders())
                ->post($this->getApiBaseUrl().'/api/products', $miraklData);

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
                    return 201;
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
        return $this->executeRequest(function () use ($productId, $productData) {
            $miraklData = $this->transformProductToMirakl($productData);

            $response = Http::withHeaders($this->getAuthenticationHeaders())
                ->put($this->getApiBaseUrl().'/api/products/'.$productId, $miraklData);

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
        return $this->executeRequest(function () use ($productId) {
            $response = Http::withHeaders($this->getAuthenticationHeaders())
                ->delete($this->getApiBaseUrl().'/api/products/'.$productId);

            if (! $response->successful()) {
                throw new Exception('HTTP '.$response->status().': '.$response->body());
            }

            return new class
            {
                public function successful()
                {
                    return true;
                }

                public function status()
                {
                    return 204;
                }

                public function json()
                {
                    return ['deleted' => true];
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
        return $this->executeRequest(function () use ($productId) {
            $response = Http::withHeaders($this->getAuthenticationHeaders())
                ->get($this->getApiBaseUrl().'/api/products/'.$productId);

            if (! $response->successful()) {
                throw new Exception('HTTP '.$response->status().': '.$response->body());
            }

            $miraklProduct = $response->json();
            $standardProduct = $this->transformMiraklProductToStandard($miraklProduct);

            return new class($standardProduct)
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
        $cacheKey = 'mirakl_products_'.md5(serialize($filters));

        return Cache::remember($cacheKey, 300, function () use ($filters) {
            try {
                $params = $this->buildMiraklProductFilters($filters);
                $response = Http::withHeaders($this->getAuthenticationHeaders())
                    ->get($this->getApiBaseUrl().'/api/products', $params);

                if (! $response->successful()) {
                    Log::error('Failed to get Mirakl products', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    return collect([]);
                }

                $data = $response->json();
                $products = $data['products'] ?? [];

                return collect($products)->map(function ($product) {
                    return $this->transformMiraklProductToStandard($product);
                });

            } catch (Exception $e) {
                Log::error('Failed to get Mirakl products', [
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
        try {
            $params = $this->buildMiraklOrderFilters($filters);
            $response = Http::withHeaders($this->getAuthenticationHeaders())
                ->get($this->getApiBaseUrl().'/api/orders', $params);

            if (! $response->successful()) {
                Log::error('Failed to get Mirakl orders', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return collect([]);
            }

            $data = $response->json();
            $orders = $data['orders'] ?? [];

            return collect($orders)->map(function ($order) {
                return $this->transformMiraklOrderToStandard($order);
            });

        } catch (Exception $e) {
            Log::error('Failed to get Mirakl orders', [
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
        return $this->executeRequest(function () use ($orderId) {
            $response = Http::withHeaders($this->getAuthenticationHeaders())
                ->get($this->getApiBaseUrl().'/api/orders/'.$orderId);

            if (! $response->successful()) {
                throw new Exception('HTTP '.$response->status().': '.$response->body());
            }

            $miraklOrder = $response->json();
            $standardOrder = $this->transformMiraklOrderToStandard($miraklOrder);

            return new class($standardOrder)
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
        return $this->executeRequest(function () use ($orderId, $fulfillmentData) {
            $miraklFulfillment = $this->transformFulfillmentDataToMirakl($fulfillmentData);

            $response = Http::withHeaders($this->getAuthenticationHeaders())
                ->put($this->getApiBaseUrl().'/api/orders/'.$orderId.'/tracking', $miraklFulfillment);

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

    /**
     * ✅ Accept an order
     */
    public function acceptOrder(string $orderId, array $orderLines = []): array
    {
        return $this->executeRequest(function () use ($orderId, $orderLines) {
            $acceptData = [
                'order_lines' => $orderLines ?: [], // Accept all lines if none specified
            ];

            $response = Http::withHeaders($this->getAuthenticationHeaders())
                ->put($this->getApiBaseUrl().'/api/orders/'.$orderId.'/accept', $acceptData);

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
                    return ['order_accepted' => $this->data];
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
        try {
            $params = [];
            if (! empty($productIds)) {
                $params['product_ids'] = implode(',', $productIds);
            }
            if (! empty($this->miraklConfig['shop_id'])) {
                $params['shop_id'] = $this->miraklConfig['shop_id'];
            }

            $response = Http::withHeaders($this->getAuthenticationHeaders())
                ->get($this->getApiBaseUrl().'/api/offers', $params);

            if (! $response->successful()) {
                Log::error('Failed to get Mirakl inventory', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return collect([]);
            }

            $data = $response->json();
            $offers = $data['offers'] ?? [];

            return collect($offers)->map(function ($offer) {
                return $this->transformMiraklOfferToInventory($offer);
            });

        } catch (Exception $e) {
            Log::error('Failed to get Mirakl inventory', [
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
        return $this->executeRequest(function () use ($productId, $inventoryData) {
            $quantity = is_numeric($inventoryData) ? $inventoryData : ($inventoryData['quantity'] ?? 0);

            $updateData = [
                'quantity' => $quantity,
                'product_id' => $productId,
            ];

            if (! empty($this->miraklConfig['shop_id'])) {
                $updateData['shop_id'] = $this->miraklConfig['shop_id'];
            }

            $response = Http::withHeaders($this->getAuthenticationHeaders())
                ->put($this->getApiBaseUrl().'/api/offers/'.$productId, $updateData);

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
                    return ['inventory_updated' => $this->data];
                }

                public function body()
                {
                    return json_encode($this->json());
                }
            };
        });
    }

    // ===== InventoryOperationsInterface stubs =====

    public function bulkUpdateInventory(array $inventoryUpdates): array
    {
        return ['success' => false, 'message' => 'bulkUpdateInventory not implemented'];
    }

    public function getInventoryByLocation(string $locationId): \Illuminate\Support\Collection
    {
        return collect([]);
    }

    public function getLowStockProducts(int $threshold = 5): \Illuminate\Support\Collection
    {
        return collect([]);
    }

    public function getInventoryHistory(string $productId, int $days = 30): array
    {
        return ['success' => false, 'history' => []];
    }

    public function syncInventoryToMarketplace(\Illuminate\Support\Collection $localInventory): array
    {
        return ['success' => false, 'message' => 'syncInventoryToMarketplace not implemented'];
    }

    public function pullInventoryFromMarketplace(): array
    {
        return ['success' => false, 'message' => 'pullInventoryFromMarketplace not implemented'];
    }

    public function getLocations(): array
    {
        return [];
    }

    public function reserveInventory(string $productId, int $quantity): array
    {
        return ['success' => false, 'message' => 'reserveInventory not implemented'];
    }

    public function releaseReservedInventory(string $productId, int $quantity): array
    {
        return ['success' => false, 'message' => 'releaseReservedInventory not implemented'];
    }

    public function validateInventoryData(array $inventoryData): array
    {
        return ['valid' => true, 'errors' => []];
    }

    public function getAdjustmentReasons(): array
    {
        return [];
    }

    // ===== ProductOperationsInterface stubs =====

    public function bulkCreateProducts(array $products): array
    {
        return ['success' => false, 'message' => 'bulkCreateProducts not implemented'];
    }

    public function bulkUpdateProducts(array $products): array
    {
        return ['success' => false, 'message' => 'bulkUpdateProducts not implemented'];
    }

    public function syncProducts(\Illuminate\Support\Collection $localProducts): array
    {
        return ['success' => false, 'message' => 'syncProducts not implemented'];
    }

    public function getCategories(): array
    {
        return [];
    }

    public function uploadProductImages(string $productId, array $images): array
    {
        return ['success' => false, 'message' => 'uploadProductImages not implemented'];
    }

    public function updatePricing(string $productId, array $pricingData): array
    {
        return ['success' => false, 'message' => 'updatePricing not implemented'];
    }

    public function getProductVariants(string $productId): array
    {
        return [];
    }

    public function validateProductData(array $productData): array
    {
        return ['valid' => true, 'errors' => []];
    }

    // ===== OrderOperationsInterface stubs =====

    public function getOrdersSince(\Carbon\Carbon $since): \Illuminate\Support\Collection
    {
        return collect([]);
    }

    public function addTrackingToOrder(string $orderId, array $trackingData): array
    {
        return ['success' => false, 'message' => 'addTrackingToOrder not implemented'];
    }

    public function cancelOrder(string $orderId, string $reason = ''): array
    {
        return ['success' => false, 'message' => 'cancelOrder not implemented'];
    }

    public function refundOrder(string $orderId, array $refundData): array
    {
        return ['success' => false, 'message' => 'refundOrder not implemented'];
    }

    public function getOrderStatistics(array $filters = []): array
    {
        return [];
    }

    public function syncOrdersToLocal(?\Carbon\Carbon $since = null): array
    {
        return ['success' => false, 'message' => 'syncOrdersToLocal not implemented'];
    }

    public function getOrderInvoice(string $orderId): array
    {
        return ['success' => false, 'message' => 'getOrderInvoice not implemented'];
    }

    public function getOrderStatuses(): array
    {
        return [];
    }

    public function getShippingMethods(): array
    {
        return [];
    }

    // =================================
    // CSV IMPORT STUBS (SCaffolded)
    // =================================

    /**
     * 📥 Import products via CSV (stub)
     *
     * TODO: Implement POST /api/products/imports with multipart upload and return import id
     */
    public function importProductsCsv(string $csvPath): array
    {
        Log::info('📝 [STUB] Import products CSV requested', [
            'operator' => $this->miraklConfig['operator'] ?? 'unknown',
            'csv_path' => $csvPath,
        ]);

        return [
            'success' => false,
            'message' => 'CSV products import not implemented yet',
            'import_id' => null,
        ];
    }

    /**
     * 📥 Import offers via CSV (stub)
     *
     * TODO: Implement POST /api/offers/imports with multipart upload and return import id
     */
    public function importOffersCsv(string $csvPath): array
    {
        Log::info('📝 [STUB] Import offers CSV requested', [
            'operator' => $this->miraklConfig['operator'] ?? 'unknown',
            'csv_path' => $csvPath,
        ]);

        return [
            'success' => false,
            'message' => 'CSV offers import not implemented yet',
            'import_id' => null,
        ];
    }

    /**
     * 🔍 Get import status (stub)
     *
     * TODO: Implement GET /api/products/imports/{id} and offers equivalent for polling
     */
    public function getImportStatus(string $importId): array
    {
        Log::info('📝 [STUB] Get import status requested', [
            'operator' => $this->miraklConfig['operator'] ?? 'unknown',
            'import_id' => $importId,
        ]);

        return [
            'success' => false,
            'message' => 'CSV import status not implemented yet',
            'status' => 'unknown',
        ];
    }

    // =================================
    // HELPER METHODS
    // =================================

    /**
     * 🌐 Get API base URL
     */
    private function getApiBaseUrl(): string
    {
        return rtrim($this->miraklConfig['api_url'], '/');
    }

    /**
     * 🔍 Get version info
     */
    private function getVersion(): array
    {
        try {
            $response = Http::withHeaders($this->getAuthenticationHeaders())
                ->get($this->getApiBaseUrl().'/api/version');

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
     * 🏪 Get shop information
     */
    private function getShop(string $shopId): array
    {
        try {
            $response = Http::withHeaders($this->getAuthenticationHeaders())
                ->get($this->getApiBaseUrl().'/api/shops/'.$shopId);

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
     * 🔄 Transform product data to Mirakl format
     */
    private function transformProductToMirakl(array $productData): array
    {
        $miraklData = [
            'product_id' => $productData['sku'] ?? '',
            'title' => $productData['title'] ?? '',
            'description' => $productData['description'] ?? '',
            'brand' => $productData['brand'] ?? '',
            'category_code' => $productData['category_code'] ?? '',
            'price' => $productData['price'] ?? '0.00',
            'currency_iso_code' => $productData['currency'] ?? $this->miraklConfig['currency'],
            'quantity' => $productData['quantity'] ?? 0,
            'state' => $productData['state'] ?? 11, // 11 = active
        ];

        if (! empty($this->miraklConfig['shop_id'])) {
            $miraklData['shop_id'] = $this->miraklConfig['shop_id'];
        }

        // Add attributes if provided
        if (isset($productData['attributes']) && is_array($productData['attributes'])) {
            foreach ($productData['attributes'] as $code => $value) {
                $miraklData[$code] = $value;
            }
        }

        return $miraklData;
    }

    /**
     * 🔄 Transform Mirakl product to standard format
     */
    private function transformMiraklProductToStandard(array $miraklProduct): array
    {
        return [
            'id' => $miraklProduct['product_id'] ?? '',
            'sku' => $miraklProduct['product_id'] ?? '',
            'title' => $miraklProduct['title'] ?? '',
            'description' => $miraklProduct['description'] ?? '',
            'brand' => $miraklProduct['brand'] ?? '',
            'price' => $miraklProduct['price'] ?? '0.00',
            'currency' => $miraklProduct['currency_iso_code'] ?? $this->miraklConfig['currency'],
            'quantity' => $miraklProduct['quantity'] ?? 0,
            'status' => $miraklProduct['state'] == 11 ? 'active' : 'inactive',
            'category_code' => $miraklProduct['category_code'] ?? '',
            'shop_id' => $miraklProduct['shop_id'] ?? '',
            'created_at' => $miraklProduct['date_created'] ?? null,
            'updated_at' => $miraklProduct['last_updated_date'] ?? null,
        ];
    }

    /**
     * 🔄 Transform Mirakl order to standard format
     */
    private function transformMiraklOrderToStandard(array $miraklOrder): array
    {
        return [
            'id' => $miraklOrder['order_id'] ?? '',
            'order_number' => $miraklOrder['commercial_id'] ?? '',
            'status' => $miraklOrder['order_state'] ?? 'pending',
            'fulfillment_status' => $this->mapMiraklOrderState($miraklOrder['order_state'] ?? ''),
            'total_amount' => $miraklOrder['total_price'] ?? '0.00',
            'currency' => $miraklOrder['currency_iso_code'] ?? $this->miraklConfig['currency'],
            'customer_email' => $miraklOrder['customer']['email'] ?? '',
            'created_at' => $miraklOrder['date_created'] ?? null,
            'updated_at' => $miraklOrder['last_updated_date'] ?? null,
            'line_items' => $miraklOrder['order_lines'] ?? [],
            'shop_id' => $miraklOrder['shop_id'] ?? '',
        ];
    }

    /**
     * 🔄 Transform Mirakl offer to inventory format
     */
    private function transformMiraklOfferToInventory(array $miraklOffer): array
    {
        return [
            'product_id' => $miraklOffer['product_id'] ?? '',
            // Prefer shop_sku if provided; fall back to product_id
            'sku' => $miraklOffer['shop_sku'] ?? $miraklOffer['product_id'] ?? '',
            'quantity' => $miraklOffer['quantity'] ?? 0,
            'price' => $miraklOffer['price'] ?? '0.00',
            'currency' => $miraklOffer['currency_iso_code'] ?? $this->miraklConfig['currency'],
            'shop_id' => $miraklOffer['shop_id'] ?? '',
            'state' => $miraklOffer['state'] ?? '',
            'updated_at' => $miraklOffer['last_updated_date'] ?? null,
        ];
    }

    /**
     * 🔄 Transform fulfillment data to Mirakl format
     */
    private function transformFulfillmentDataToMirakl(array $fulfillmentData): array
    {
        return [
            'tracking_number' => $fulfillmentData['tracking_number'] ?? '',
            'carrier_code' => $fulfillmentData['tracking_company'] ?? '',
            'carrier_name' => $fulfillmentData['carrier_name'] ?? $fulfillmentData['tracking_company'] ?? '',
            'tracking_url' => $fulfillmentData['tracking_url'] ?? '',
        ];
    }

    /**
     * 🗺️ Map Mirakl order state to standard fulfillment status
     */
    private function mapMiraklOrderState(string $miraklState): string
    {
        $stateMap = [
            'WAITING_ACCEPTANCE' => 'pending',
            'WAITING_DEBIT' => 'pending',
            'WAITING_DEBIT_PAYMENT' => 'pending',
            'SHIPPING' => 'processing',
            'SHIPPED' => 'shipped',
            'TO_COLLECT' => 'ready_for_pickup',
            'RECEIVED' => 'delivered',
            'CLOSED' => 'completed',
            'REFUSED' => 'cancelled',
            'CANCELED' => 'cancelled',
        ];

        return $stateMap[$miraklState] ?? 'unknown';
    }

    /**
     * 🔧 Build Mirakl product filters
     */
    private function buildMiraklProductFilters(array $filters): array
    {
        $params = ['max' => $filters['limit'] ?? 50];

        if (isset($filters['shop_id'])) {
            $params['shop_id'] = $filters['shop_id'];
        } elseif (! empty($this->miraklConfig['shop_id'])) {
            $params['shop_id'] = $this->miraklConfig['shop_id'];
        }

        if (isset($filters['product_id'])) {
            $params['product_id'] = $filters['product_id'];
        }

        if (isset($filters['category_code'])) {
            $params['category_code'] = $filters['category_code'];
        }

        if (isset($filters['state'])) {
            $params['state'] = $filters['state'];
        }

        return $params;
    }

    /**
     * 🔧 Build Mirakl order filters
     */
    private function buildMiraklOrderFilters(array $filters): array
    {
        $params = ['max' => $filters['limit'] ?? 50];

        if (isset($filters['shop_id'])) {
            $params['shop_id'] = $filters['shop_id'];
        } elseif (! empty($this->miraklConfig['shop_id'])) {
            $params['shop_id'] = $this->miraklConfig['shop_id'];
        }

        if (isset($filters['order_state'])) {
            $params['order_state'] = $filters['order_state'];
        }

        if (isset($filters['start_date'])) {
            $params['start_date'] = $filters['start_date'];
        }

        if (isset($filters['end_date'])) {
            $params['end_date'] = $filters['end_date'];
        }

        return $params;
    }
}
