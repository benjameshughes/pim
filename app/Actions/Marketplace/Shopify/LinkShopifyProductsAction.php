<?php

namespace App\Actions\Marketplace\Shopify;

use App\Models\Product;
use App\Models\SyncAccount;
use App\Services\Marketplace\Shopify\ShopifyGraphQLClient;
use App\Services\Marketplace\ValueObjects\SyncResult;
use Carbon\Carbon;

/**
 * 🔗 LINK SHOPIFY PRODUCTS ACTION
 *
 * Links existing Shopify products to local products by matching SKUs.
 * Searches Shopify for products containing local variant SKUs and stores the mapping.
 * Enables management of pre-existing Shopify products without recreating them.
 */
class LinkShopifyProductsAction
{
    /**
     * Link existing Shopify products to local product
     *
     * @param  int  $productId  Local product ID
     * @param  SyncAccount  $syncAccount  Shopify account
     * @return SyncResult Link operation result
     */
    public function execute(int $productId, SyncAccount $syncAccount): SyncResult
    {
        try {
            $product = Product::find($productId);

            if (! $product) {
                return SyncResult::failure("Product with ID {$productId} not found");
            }

            // Check if already linked
            $existingStatus = $product->getSmartAttributeValue('shopify_status');
            if ($existingStatus) {
                return SyncResult::failure("Product is already linked to Shopify (status: {$existingStatus})");
            }

            // Get all variant SKUs to search for
            $localVariants = $product->variants;
            if ($localVariants->isEmpty()) {
                return SyncResult::failure('Product has no variants to link');
            }

            $localSkus = $localVariants->pluck('sku')->filter()->toArray();
            if (empty($localSkus)) {
                return SyncResult::failure('Product variants have no SKUs to search with');
            }

            // Search Shopify for products containing these SKUs
            $client = new ShopifyGraphQLClient($syncAccount);
            $shopifyProducts = $client->searchProductsBySku($localSkus);

            if (empty($shopifyProducts)) {
                return SyncResult::failure(
                    'No matching products found in Shopify',
                    ['searched_skus' => $localSkus]
                );
            }

            // Build SKU mapping and analyze matches
            $linkResult = $this->analyzeLinkPossibilities($product, $shopifyProducts, $localSkus);

            if (! $linkResult['success']) {
                return SyncResult::failure(
                    $linkResult['message'],
                    $linkResult['data']
                );
            }

            // Store the links in product attributes
            $this->storeLinkData($product, $linkResult['data'], $syncAccount);

            return SyncResult::success(
                $linkResult['message'],
                $linkResult['data']
            );

        } catch (\Exception $e) {
            return SyncResult::failure(
                'Link operation failed: '.$e->getMessage(),
                [],
                [$e->getMessage()]
            );
        }
    }

    /**
     * Analyze Shopify products to determine if linking is viable
     */
    protected function analyzeLinkPossibilities(Product $localProduct, array $shopifyProducts, array $localSkus): array
    {
        $foundSkus = [];
        $colorGroups = [];
        $variantMappings = [];
        $skuDuplicates = [];

        // Build comprehensive mapping
        foreach ($shopifyProducts as $shopifyProduct) {
            foreach ($shopifyProduct['variants'] as $shopifyVariant) {
                $sku = $shopifyVariant['sku'];

                if (! in_array($sku, $localSkus)) {
                    continue; // Skip variants that don't match our local SKUs
                }

                // Track duplicate SKUs across products
                if (isset($foundSkus[$sku])) {
                    $skuDuplicates[$sku][] = $shopifyProduct['id'];
                } else {
                    $foundSkus[$sku] = true;
                }

                // Extract color from Shopify product title (e.g., "Product Name - Red")
                $color = $this->extractColorFromTitle($shopifyProduct['title'], $localProduct->name);

                if (! isset($colorGroups[$color])) {
                    $colorGroups[$color] = [
                        'shopify_product_id' => $shopifyProduct['id'],
                        'shopify_title' => $shopifyProduct['title'],
                        'variant_count' => 0,
                        'variants' => [],
                    ];
                }

                $colorGroups[$color]['variant_count']++;
                $colorGroups[$color]['variants'][] = [
                    'sku' => $sku,
                    'shopify_variant_id' => $shopifyVariant['id'],
                    'shopify_price' => $shopifyVariant['price'],
                    'local_variant_id' => $this->findLocalVariantId($localProduct, $sku),
                ];

                $variantMappings[$sku] = [
                    'shopify_product_id' => $shopifyProduct['id'],
                    'shopify_variant_id' => $shopifyVariant['id'],
                    'color_group' => $color,
                ];
            }
        }

        // Analyze results
        $totalLocalSkus = count($localSkus);
        $foundSkuCount = count($foundSkus);
        $coveragePercent = round(($foundSkuCount / $totalLocalSkus) * 100, 1);

        // Validation rules
        if ($foundSkuCount === 0) {
            return [
                'success' => false,
                'message' => 'No matching SKUs found in Shopify',
                'data' => [
                    'searched_skus' => $localSkus,
                    'shopify_products_found' => count($shopifyProducts),
                ],
            ];
        }

        if ($coveragePercent < 50) {
            return [
                'success' => false,
                'message' => "Low SKU coverage: only {$coveragePercent}% of variants found ({$foundSkuCount}/{$totalLocalSkus})",
                'data' => [
                    'coverage_percent' => $coveragePercent,
                    'found_skus' => $foundSkuCount,
                    'total_skus' => $totalLocalSkus,
                    'missing_skus' => array_diff($localSkus, array_keys($foundSkus)),
                    'color_groups' => $colorGroups,
                ],
            ];
        }

        if (! empty($skuDuplicates)) {
            return [
                'success' => false,
                'message' => 'Duplicate SKUs found across multiple Shopify products',
                'data' => [
                    'duplicate_skus' => $skuDuplicates,
                    'message' => 'Each SKU should only exist in one Shopify product',
                ],
            ];
        }

        // Success case
        $message = $coveragePercent === 100
            ? "Perfect match: All {$foundSkuCount} variants found across ".count($colorGroups).' color groups'
            : "Partial match: {$foundSkuCount}/{$totalLocalSkus} variants found ({$coveragePercent}% coverage)";

        return [
            'success' => true,
            'message' => $message,
            'data' => [
                'coverage_percent' => $coveragePercent,
                'found_skus' => $foundSkuCount,
                'total_skus' => $totalLocalSkus,
                'color_groups_count' => count($colorGroups),
                'color_groups' => $colorGroups,
                'variant_mappings' => $variantMappings,
                'shopify_products' => array_map(fn ($p) => [
                    'id' => $p['id'],
                    'title' => $p['title'],
                    'handle' => $p['handle'],
                    'status' => $p['status'],
                ], $shopifyProducts),
            ],
        ];
    }

    /**
     * Extract color from Shopify product title
     */
    protected function extractColorFromTitle(string $shopifyTitle, string $baseProductName): string
    {
        // Expected format: "Product Name - Color"
        $pattern = '/^'.preg_quote($baseProductName, '/').'\s*-\s*(.+)$/i';

        if (preg_match($pattern, $shopifyTitle, $matches)) {
            return trim($matches[1]);
        }

        // Fallback: use the full title if pattern doesn't match
        return $shopifyTitle;
    }

    /**
     * Find local variant ID by SKU
     */
    protected function findLocalVariantId(Product $product, string $sku): ?int
    {
        $variant = $product->variants->where('sku', $sku)->first();

        return $variant ? $variant->id : null;
    }

    /**
     * Store link data in product attributes
     */
    protected function storeLinkData(Product $product, array $linkData, SyncAccount $syncAccount): void
    {
        // Build shopify_product_ids structure: {"Red": "gid://shopify/Product/123", "Blue": "gid://..."}
        $shopifyProductIds = [];
        foreach ($linkData['color_groups'] as $color => $colorData) {
            $shopifyProductIds[$color] = $colorData['shopify_product_id'];
        }

        // Store all the attributes (same as create action)
        // NOTE: JSON attributes need to be JSON-encoded strings, not PHP arrays
        $product->setAttributeValue('shopify_product_ids', json_encode($shopifyProductIds));
        $product->setAttributeValue('shopify_sync_account_id', $syncAccount->id);
        $product->setAttributeValue('shopify_synced_at', Carbon::now()->toISOString());
        $product->setAttributeValue('shopify_status', 'synced');

        // Store additional metadata about the link
        $metadata = [
            'linked_at' => Carbon::now()->toISOString(),
            'link_method' => 'sku_search',
            'coverage_percent' => $linkData['coverage_percent'],
            'color_groups_linked' => $linkData['color_groups_count'],
            'variants_found' => $linkData['found_skus'],
            'total_variants' => $linkData['total_skus'],
            'sync_account_name' => $syncAccount->name,
        ];

        $product->setAttributeValue('shopify_metadata', json_encode($metadata));
    }
}
