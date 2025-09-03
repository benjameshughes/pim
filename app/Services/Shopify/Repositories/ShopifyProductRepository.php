<?php

namespace App\Services\Shopify\Repositories;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Shopify\API\Client\ShopifyClient;
use App\Services\Shopify\Builders\Products\ShopifyProductBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Repository for Shopify product operations
 *
 * Implements the Repository pattern for clean data access.
 * Handles all product-related operations between the app and Shopify.
 */
class ShopifyProductRepository
{
    protected ShopifyClient $client;

    protected ?ShopifyProductBuilder $productBuilder = null;

    public function __construct(?ShopifyClient $client = null)
    {
        $this->client = $client ?? ShopifyClient::fromEnv();
    }

    /**
     * Push a single product to Shopify
     */
    public function push(Product $product, array $options = []): array
    {
        try {
            Log::info('Pushing product to Shopify', [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'options' => $options,
            ]);

            // Build product data using builder if available
            $productData = $this->buildProductData($product, $options);

            // Check if product already exists in Shopify
            if ($product->shopify_product_id) {
                return $this->update($product->shopify_product_id, $productData);
            }

            // Create new product
            $result = $this->client->rest()->createProduct($productData);

            if ($result['success']) {
                // Update local product with Shopify ID
                $product->update([
                    'shopify_product_id' => $result['product_id'],
                    'shopify_synced_at' => now(),
                ]);

                Log::info('Product pushed successfully', [
                    'product_id' => $product->id,
                    'shopify_id' => $result['product_id'],
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Failed to push product to Shopify', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Pull a product from Shopify
     */
    public function pull(string $shopifyProductId): ?Product
    {
        try {
            Log::info('Pulling product from Shopify', [
                'shopify_id' => $shopifyProductId,
            ]);

            $result = $this->client->rest()->getProduct((int) $shopifyProductId);

            if (! $result['success']) {
                Log::error('Failed to pull product', [
                    'shopify_id' => $shopifyProductId,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);

                return null;
            }

            $shopifyData = $result['data'];

            // Find or create local product
            $product = Product::where('shopify_product_id', $shopifyProductId)->first();

            if (! $product) {
                $product = $this->createProductFromShopifyData($shopifyData);
            } else {
                $this->updateProductFromShopifyData($product, $shopifyData);
            }

            return $product;

        } catch (\Exception $e) {
            Log::error('Failed to pull product from Shopify', [
                'shopify_id' => $shopifyProductId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Push multiple products to Shopify
     */
    public function pushBulk(Collection $products, array $options = []): Collection
    {
        $results = collect();

        Log::info('Starting bulk product push', [
            'total_products' => $products->count(),
            'options' => $options,
        ]);

        foreach ($products as $product) {
            $result = $this->push($product, $options);
            $results->push([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'success' => $result['success'],
                'shopify_id' => $result['product_id'] ?? null,
                'error' => $result['error'] ?? null,
            ]);

            // Small delay to avoid rate limiting
            usleep(250000); // 250ms
        }

        Log::info('Bulk product push completed', [
            'total' => $results->count(),
            'successful' => $results->where('success', true)->count(),
            'failed' => $results->where('success', false)->count(),
        ]);

        return $results;
    }

    /**
     * Pull all products from Shopify
     */
    public function pullAll(array $filters = []): Collection
    {
        try {
            Log::info('Pulling all products from Shopify', [
                'filters' => $filters,
            ]);

            $params = array_merge(['limit' => 250], $filters);
            $allProducts = collect();
            $pageInfo = null;

            do {
                if ($pageInfo) {
                    $params = array_merge($params, ['page_info' => $pageInfo]);
                }

                $result = $this->client->rest()->getProducts($params);

                if (! $result['success']) {
                    Log::error('Failed to pull products', [
                        'error' => $result['error'] ?? 'Unknown error',
                    ]);
                    break;
                }

                foreach ($result['data'] as $shopifyProduct) {
                    $product = $this->createOrUpdateFromShopifyData($shopifyProduct);
                    if ($product) {
                        $allProducts->push($product);
                    }
                }

                // Check for next page
                $pageInfo = $result['page_info'] ?? null;

            } while ($pageInfo && $allProducts->count() < 10000); // Safety limit

            Log::info('Completed pulling products from Shopify', [
                'total_products' => $allProducts->count(),
            ]);

            return $allProducts;

        } catch (\Exception $e) {
            Log::error('Failed to pull all products from Shopify', [
                'error' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * Pull all products from Shopify for discovery (returns raw API data)
     */
    public function pullAllForDiscovery(array $filters = []): Collection
    {
        try {
            Log::info('Pulling products for discovery from Shopify', [
                'filters' => $filters,
            ]);

            $params = array_merge(['limit' => 50], $filters);
            $allProducts = collect();
            $pageInfo = null;

            do {
                if ($pageInfo) {
                    $params = array_merge($params, ['page_info' => $pageInfo]);
                }

                $result = $this->client->rest()->getProducts($params);

                Log::debug('Shopify API response for discovery', [
                    'success' => $result['success'],
                    'count' => isset($result['data']) ? count($result['data']) : 0,
                    'has_page_info' => isset($result['page_info']),
                ]);

                if (! $result['success']) {
                    Log::error('Failed to pull products for discovery', [
                        'error' => $result['error'] ?? 'Unknown error',
                        'params' => $params,
                    ]);
                    break;
                }

                // Return raw Shopify API data for discovery processing
                foreach ($result['data'] as $shopifyProduct) {
                    $allProducts->push($shopifyProduct);
                }

                // Check for next page
                $pageInfo = $result['page_info'] ?? null;

            } while ($pageInfo && $allProducts->count() < 200); // Lower limit for discovery

            Log::info('Completed pulling products for discovery', [
                'total_products' => $allProducts->count(),
                'sample_product_keys' => $allProducts->first() ? array_keys($allProducts->first()) : [],
            ]);

            return $allProducts;

        } catch (\Exception $e) {
            Log::error('Failed to pull products for discovery', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return collect();
        }
    }

    /**
     * Update a product in Shopify
     */
    public function update(string $shopifyProductId, array $data): array
    {
        try {
            Log::info('Updating product in Shopify', [
                'shopify_id' => $shopifyProductId,
            ]);

            $result = $this->client->rest()->updateProduct((int) $shopifyProductId, $data);

            if ($result['success']) {
                // Update local sync timestamp
                Product::where('shopify_product_id', $shopifyProductId)
                    ->update(['shopify_synced_at' => now()]);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Failed to update product in Shopify', [
                'shopify_id' => $shopifyProductId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete a product from Shopify
     */
    public function delete(string $shopifyProductId): bool
    {
        try {
            Log::info('Deleting product from Shopify', [
                'shopify_id' => $shopifyProductId,
            ]);

            $result = $this->client->rest()->deleteProduct((int) $shopifyProductId);

            if ($result['success']) {
                // Clear Shopify ID from local product
                Product::where('shopify_product_id', $shopifyProductId)
                    ->update([
                        'shopify_product_id' => null,
                        'shopify_synced_at' => null,
                    ]);

                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Failed to delete product from Shopify', [
                'shopify_id' => $shopifyProductId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Sync product with Shopify (bidirectional)
     */
    public function sync(Product $product, string $direction = 'push'): array
    {
        if ($direction === 'push') {
            return $this->push($product);
        }

        if ($product->shopify_product_id) {
            $pulledProduct = $this->pull($product->shopify_product_id);

            return [
                'success' => $pulledProduct !== null,
                'product' => $pulledProduct,
            ];
        }

        return [
            'success' => false,
            'error' => 'No Shopify product ID for pull operation',
        ];
    }

    /**
     * Check if product exists in Shopify
     */
    public function exists(string $shopifyProductId): bool
    {
        try {
            $result = $this->client->rest()->getProduct((int) $shopifyProductId);

            return $result['success'];
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Build product data for Shopify
     */
    protected function buildProductData(Product $product, array $options = []): array
    {
        // If we have a builder, use it
        if ($this->productBuilder) {
            return $this->productBuilder
                ->fromProduct($product)
                ->withOptions($options)
                ->build();
        }

        // Default data structure
        $data = [
            'title' => $product->name,
            'body_html' => $product->description ?? '',
            'vendor' => $product->vendor ?? config('app.name'),
            'product_type' => $product->product_type ?? 'Default',
            'status' => $options['status'] ?? 'active',
        ];

        // Add variants
        if ($product->variants && $product->variants->count() > 0) {
            $data['variants'] = $product->variants->map(function (ProductVariant $variant) {
                return [
                    'sku' => $variant->sku,
                    'price' => $variant->price ?? '0.00',
                    'barcode' => $variant->barcode?->barcode_number,
                    'inventory_quantity' => $variant->stock_level ?? 0,
                    'option1' => $variant->color,
                    'option2' => $variant->size,
                ];
            })->toArray();

            // Add options based on variants
            $hasColors = $product->variants->pluck('color')->filter()->unique()->count() > 0;
            $hasSizes = $product->variants->pluck('size')->filter()->unique()->count() > 0;

            if ($hasColors || $hasSizes) {
                $data['options'] = [];
                if ($hasColors) {
                    $data['options'][] = ['name' => 'Color'];
                }
                if ($hasSizes) {
                    $data['options'][] = ['name' => 'Size'];
                }
            }
        }

        // Add tags
        if ($product->tags && $product->tags->count() > 0) {
            $data['tags'] = $product->tags->pluck('name')->implode(', ');
        }

        return $data;
    }

    /**
     * Create product from Shopify data
     */
    protected function createProductFromShopifyData(array $shopifyData): Product
    {
        $product = Product::create([
            'name' => $shopifyData['title'],
            'description' => strip_tags($shopifyData['body_html'] ?? ''),
            'vendor' => $shopifyData['vendor'],
            'product_type' => $shopifyData['product_type'],
            'shopify_product_id' => $shopifyData['id'],
            'shopify_synced_at' => now(),
            'status' => $shopifyData['status'] === 'active' ? 'active' : 'inactive',
        ]);

        // Create variants
        foreach ($shopifyData['variants'] ?? [] as $variantData) {
            ProductVariant::create([
                'product_id' => $product->id,
                'sku' => $variantData['sku'],
                'color' => $variantData['option1'],
                'price' => $variantData['price'],
                'stock_level' => $variantData['inventory_quantity'] ?? 0,
            ]);
        }

        return $product;
    }

    /**
     * Update product from Shopify data
     */
    protected function updateProductFromShopifyData(Product $product, array $shopifyData): void
    {
        $product->update([
            'name' => $shopifyData['title'],
            'description' => strip_tags($shopifyData['body_html'] ?? ''),
            'vendor' => $shopifyData['vendor'],
            'product_type' => $shopifyData['product_type'],
            'shopify_synced_at' => now(),
            'status' => $shopifyData['status'] === 'active' ? 'active' : 'inactive',
        ]);

        // Update or create variants
        foreach ($shopifyData['variants'] ?? [] as $variantData) {
            ProductVariant::updateOrCreate(
                ['sku' => $variantData['sku']], // Use SKU since shopify_variant_id doesn't exist in schema
                [
                    'product_id' => $product->id,
                    'color' => $variantData['option1'],
                    'price' => $variantData['price'],
                    'stock_level' => $variantData['inventory_quantity'] ?? 0,
                ]
            );
        }
    }

    /**
     * Create or update product from Shopify data
     */
    protected function createOrUpdateFromShopifyData(array $shopifyData): ?Product
    {
        $product = Product::where('shopify_product_id', $shopifyData['id'])->first();

        if ($product) {
            $this->updateProductFromShopifyData($product, $shopifyData);
        } else {
            $product = $this->createProductFromShopifyData($shopifyData);
        }

        return $product;
    }

    /**
     * Set product builder
     */
    public function withBuilder(ShopifyProductBuilder $builder): static
    {
        $this->productBuilder = $builder;

        return $this;
    }

    /**
     * Get the underlying client
     */
    public function getClient(): ShopifyClient
    {
        return $this->client;
    }
}
