<?php

namespace App\Services\Shopify\API\Client;

use Exception;
use Illuminate\Support\Facades\Log;
use PHPShopify\ShopifySDK;

/**
 * Shopify REST API client
 *
 * Handles all REST API operations with the Shopify Admin API.
 * Provides clean methods for common operations and advanced features.
 */
class ShopifyRestClient
{
    protected ShopifySDK $sdk;

    protected array $config;

    public function __construct(ShopifySDK $sdk, array $config)
    {
        $this->sdk = $sdk;
        $this->config = $config;
    }

    /**
     * Get products with optional filters
     */
    public function getProducts(array $params = []): array
    {
        try {
            $defaultParams = ['limit' => 50];
            $params = array_merge($defaultParams, $params);

            $products = $this->sdk->Product->get($params);

            return [
                'success' => true,
                'data' => $products,
                'count' => count($products),
            ];
        } catch (Exception $e) {
            Log::error('Failed to get products', [
                'error' => $e->getMessage(),
                'params' => $params,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get a single product by ID
     */
    public function getProduct(int $productId): array
    {
        try {
            $product = $this->sdk->Product($productId)->get();

            return [
                'success' => true,
                'data' => $product,
            ];
        } catch (Exception $e) {
            Log::error('Failed to get product', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create a new product
     */
    public function createProduct(array $productData): array
    {
        try {
            Log::info('Creating Shopify product', [
                'title' => $productData['title'] ?? 'Unknown',
                'variants_count' => count($productData['variants'] ?? []),
            ]);

            $product = $this->sdk->Product->post($productData);

            return [
                'success' => true,
                'product_id' => $product['id'] ?? null,
                'data' => $product,
            ];
        } catch (Exception $e) {
            Log::error('Product creation failed', [
                'error' => $e->getMessage(),
                'product_data' => $productData,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update an existing product
     */
    public function updateProduct(int $productId, array $productData): array
    {
        try {
            $product = $this->sdk->Product($productId)->put($productData);

            return [
                'success' => true,
                'product_id' => $product['id'] ?? null,
                'data' => $product,
            ];
        } catch (Exception $e) {
            Log::error('Product update failed', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete a product
     */
    public function deleteProduct(int $productId): array
    {
        try {
            $result = $this->sdk->Product($productId)->delete();

            return [
                'success' => true,
                'response' => $result,
            ];
        } catch (Exception $e) {
            Log::error('Product deletion failed', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get product variants
     */
    public function getVariants(int $productId): array
    {
        try {
            $variants = $this->sdk->Product($productId)->Variant->get();

            return [
                'success' => true,
                'data' => $variants,
                'count' => count($variants),
            ];
        } catch (Exception $e) {
            Log::error('Failed to get variants', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create a variant for a product
     */
    public function createVariant(int $productId, array $variantData): array
    {
        try {
            $variant = $this->sdk->Product($productId)->Variant->post($variantData);

            return [
                'success' => true,
                'variant_id' => $variant['id'] ?? null,
                'data' => $variant,
            ];
        } catch (Exception $e) {
            Log::error('Variant creation failed', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update a variant
     */
    public function updateVariant(int $variantId, array $variantData): array
    {
        try {
            $variant = $this->sdk->Variant($variantId)->put($variantData);

            return [
                'success' => true,
                'variant_id' => $variant['id'] ?? null,
                'data' => $variant,
            ];
        } catch (Exception $e) {
            Log::error('Variant update failed', [
                'variant_id' => $variantId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create metafields for a product
     */
    public function createProductMetafields(int $productId, array $metafields): array
    {
        try {
            $results = [];

            foreach ($metafields as $metafield) {
                $result = $this->sdk->Product($productId)->Metafield->post($metafield);
                $results[] = $result;
            }

            return [
                'success' => true,
                'data' => $results,
            ];
        } catch (Exception $e) {
            Log::error('Failed to create metafields', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Execute a custom REST request
     */
    public function request(string $method, string $path, array $params = []): array
    {
        try {
            $method = strtolower($method);

            // Parse the path to determine resource and ID
            $parts = explode('/', trim($path, '/'));
            $resource = ucfirst($parts[0]);

            $result = match ($method) {
                'get' => $this->sdk->$resource->get($params),
                'post' => $this->sdk->$resource->post($params),
                'put' => $this->sdk->$resource->put($params),
                'delete' => $this->sdk->$resource->delete(),
                default => throw new Exception("Unsupported method: $method"),
            };

            return [
                'success' => true,
                'data' => $result,
            ];
        } catch (Exception $e) {
            Log::error('Custom REST request failed', [
                'method' => $method,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
