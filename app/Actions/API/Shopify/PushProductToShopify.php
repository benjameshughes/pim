<?php

namespace App\Actions\API\Shopify;

use App\Models\Product;
use App\Models\ShopifyProductSync;
use App\Services\ShopifyConnectService;
use Exception;
use Illuminate\Support\Facades\Log;

class PushProductToShopify
{
    public function __construct(
        private GroupVariantsByColor $groupVariantsByColor,
        private BuildShopifyProductData $buildShopifyProductData,
        private ShopifyConnectService $shopifyService
    ) {}

    /**
     * Push a Laravel product to Shopify with color-based parent splitting
     *
     * Takes a single Laravel product and creates multiple Shopify products,
     * one for each color variant (e.g., "Blackout Blind - Black", "Blackout Blind - White")
     */
    public function execute(Product $product): array
    {
        $results = [];

        Log::info("Starting Shopify push for product: {$product->name}", [
            'product_id' => $product->id,
            'variants_count' => $product->variants->count(),
        ]);

        // Group variants by color to create separate Shopify products
        $colorGroups = $this->groupVariantsByColor->execute($product);

        foreach ($colorGroups as $color => $variants) {
            try {
                // Check if this product/color combination is already synced
                $syncRecord = ShopifyProductSync::getSyncRecord($product->id, $color);
                $isAlreadySynced = $syncRecord && $syncRecord->sync_status === 'synced';

                // Build the Shopify product data structure
                $productData = $this->buildShopifyProductData->execute($product, $color, $variants);

                // Create sync data for comparison
                $syncData = [
                    'title' => $productData['title'],
                    'body_html' => $productData['body_html'],
                    'variants_count' => count($variants),
                    'price_range' => $this->getPriceRange($variants),
                ];

                $shopifyResponse = null;
                $action = 'created';

                if ($isAlreadySynced) {
                    // Check if data has changed
                    if ($syncRecord->hasDataChanged($syncData)) {
                        Log::info('Updating existing Shopify product', [
                            'product' => $product->name,
                            'color' => $color,
                            'shopify_id' => $syncRecord->shopify_product_id,
                        ]);

                        // Update existing product
                        $restProductData = ['product' => array_filter([
                            'title' => $productData['title'],
                            'body_html' => $productData['body_html'],
                            'vendor' => $productData['vendor'],
                            'product_type' => $productData['product_type'],
                            'status' => $productData['status'],
                            'options' => $productData['options'],
                            'variants' => $productData['variants'],
                            'images' => $productData['images'] ?? [],
                        ])];

                        $shopifyResponse = $this->shopifyService->updateProduct(
                            (int) $syncRecord->shopify_product_id,
                            $restProductData
                        );
                        $action = 'updated';
                    } else {
                        Log::info('Skipping sync - no changes detected', [
                            'product' => $product->name,
                            'color' => $color,
                            'shopify_id' => $syncRecord->shopify_product_id,
                        ]);

                        $shopifyResponse = [
                            'success' => true,
                            'product_id' => $syncRecord->shopify_product_id,
                            'response' => ['product' => ['id' => $syncRecord->shopify_product_id]],
                        ];
                        $action = 'skipped';
                    }
                } else {
                    // Create new product
                    Log::info('Creating new Shopify product', [
                        'product' => $product->name,
                        'color' => $color,
                    ]);

                    $restProductData = ['product' => array_filter([
                        'title' => $productData['title'],
                        'body_html' => $productData['body_html'],
                        'vendor' => $productData['vendor'],
                        'product_type' => $productData['product_type'],
                        'status' => $productData['status'],
                        'options' => $productData['options'],
                        'variants' => $productData['variants'],
                        'images' => $productData['images'] ?? [],
                    ])];

                    $shopifyResponse = $this->shopifyService->createProduct($restProductData);
                    $action = 'created';
                }

                // If successful, add metafields and category
                if ($shopifyResponse['success'] && ! empty($shopifyResponse['product_id'])) {
                    $productId = $shopifyResponse['product_id'];
                    $productGid = "gid://shopify/Product/{$productId}";

                    // Add/update metafields (only if created or data changed)
                    if ($action !== 'skipped' && ! empty($productData['metafields'])) {
                        $metafieldResponse = $this->shopifyService->createProductMetafields($productId, $productData['metafields']);
                        Log::info('Added metafields to product', [
                            'product_id' => $productId,
                            'metafields_success' => $metafieldResponse['success'],
                        ]);
                    }

                    // Set category (only if created or data changed)
                    if ($action !== 'skipped' && ! empty($productData['category'])) {
                        $categoryResponse = $this->shopifyService->updateProductWithCategory($productGid, $productData['category']);
                        Log::info('Set product category', [
                            'product_id' => $productId,
                            'category_success' => $categoryResponse['success'],
                        ]);
                    }

                    // Update sync tracking record
                    ShopifyProductSync::updateSyncRecord(
                        $product->id,
                        $color,
                        (string) $productId,
                        $syncData,
                        'synced',
                        $shopifyResponse['response']['product']['handle'] ?? null
                    );
                }

                // Extract product ID from response
                $productId = $shopifyResponse['product_id'] ?? null;

                $results[] = [
                    'color' => $color,
                    'variants_count' => count($variants),
                    'success' => $shopifyResponse['success'],
                    'shopify_product_id' => $productId,
                    'action' => $action,
                    'response' => $shopifyResponse['response'] ?? null,
                    'error' => $shopifyResponse['error'] ?? null,
                ];

                if ($shopifyResponse['success']) {
                    $actionVerb = $action === 'created' ? 'created' : ($action === 'updated' ? 'updated' : 'skipped');

                    Log::info("Successfully {$actionVerb} Shopify product for color", [
                        'original_product' => $product->name,
                        'color' => $color,
                        'shopify_product_id' => $productId,
                        'variants_count' => count($variants),
                        'action' => $action,
                    ]);
                } else {
                    Log::error('Failed to sync Shopify product for color', [
                        'original_product' => $product->name,
                        'color' => $color,
                        'action' => $action,
                        'error' => $shopifyResponse['error'],
                    ]);
                }

                // Add delay to respect Shopify rate limits (40 requests per minute)
                usleep(1600000); // 1.6 seconds between requests

            } catch (Exception $e) {
                Log::error('Exception creating Shopify product for color', [
                    'original_product' => $product->name,
                    'color' => $color,
                    'error' => $e->getMessage(),
                ]);

                $results[] = [
                    'color' => $color,
                    'variants_count' => count($variants),
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Get price range for variants to detect changes
     */
    private function getPriceRange(array $variants): array
    {
        $prices = [];

        foreach ($variants as $variant) {
            $pricing = $variant->pricing()->first();
            if ($pricing && $pricing->price > 0) {
                $prices[] = (float) $pricing->price;
            }
        }

        if (empty($prices)) {
            return ['min' => 0, 'max' => 0];
        }

        return [
            'min' => min($prices),
            'max' => max($prices),
        ];
    }
}
