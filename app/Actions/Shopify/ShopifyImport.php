<?php

namespace App\Actions\Shopify;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Shopify\ShopifySkuGrouper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Simple Shopify Import Action
 *
 * Takes cached Shopify product data and creates Products/ProductVariants
 * using direct mapping to existing database fields.
 * Now supports SKU-based grouping for proper product organization.
 */
class ShopifyImport
{
    protected ShopifySkuGrouper $skuGrouper;

    public function __construct()
    {
        $this->skuGrouper = new ShopifySkuGrouper;
    }

    /**
     * Import multiple Shopify products with SKU-based grouping
     */
    public function importProducts(array $shopifyProducts): array
    {
        try {
            // Group products by SKU patterns first
            $groups = $this->skuGrouper->groupProductsBySkuPattern(collect($shopifyProducts));

            $results = [];
            foreach ($groups as $group) {
                $result = $this->importProductGroup($group);
                $results[] = $result;
            }

            return [
                'success' => true,
                'groups_processed' => count($results),
                'results' => $results,
            ];

        } catch (\Exception $e) {
            Log::error('Shopify batch import failed', [
                'error' => $e->getMessage(),
                'product_count' => count($shopifyProducts),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'groups_processed' => 0,
                'results' => [],
            ];
        }
    }

    /**
     * Import a single Shopify product from cached data (legacy method)
     */
    public function importProduct(array $shopifyProduct): array
    {
        try {
            return DB::transaction(function () use ($shopifyProduct) {
                // Generate parent SKU from Shopify ID
                $parentSku = 'SH'.$shopifyProduct['id'];

                // Check if already imported
                if (Product::where('parent_sku', $parentSku)->exists()) {
                    return [
                        'success' => false,
                        'error' => 'Product already imported',
                        'product_id' => null,
                        'variants_created' => 0,
                    ];
                }

                // Create the product with simple field mapping
                $product = Product::create([
                    'name' => $shopifyProduct['title'] ?? 'Unknown Product',
                    'parent_sku' => $parentSku,
                    'description' => $this->cleanDescription($shopifyProduct['body_html'] ?? ''),
                    'status' => 'active',
                    'brand' => $shopifyProduct['vendor'] ?? 'Unknown',
                ]);

                // Create variants
                $variantsCreated = 0;
                $variants = $shopifyProduct['variants'] ?? [];

                foreach ($variants as $shopifyVariant) {
                    $variantData = $this->mapVariantData($product->id, $shopifyVariant, $shopifyProduct);

                    if ($variantData) {
                        ProductVariant::create($variantData);
                        $variantsCreated++;
                    }
                }

                Log::info('Shopify product imported successfully', [
                    'shopify_id' => $shopifyProduct['id'],
                    'product_id' => $product->id,
                    'parent_sku' => $parentSku,
                    'variants_created' => $variantsCreated,
                ]);

                return [
                    'success' => true,
                    'product_id' => $product->id,
                    'variants_created' => $variantsCreated,
                    'error' => null,
                ];
            });

        } catch (\Exception $e) {
            Log::error('Shopify import failed', [
                'shopify_id' => $shopifyProduct['id'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'product_id' => null,
                'variants_created' => 0,
            ];
        }
    }

    /**
     * Import a grouped set of Shopify products as one parent with multiple variants
     */
    private function importProductGroup(array $group): array
    {
        try {
            return DB::transaction(function () use ($group) {
                $parentSku = $group['parent_sku'];

                // Check if already imported
                if (Product::where('parent_sku', $parentSku)->exists()) {
                    return [
                        'success' => false,
                        'error' => 'Product group already imported',
                        'parent_sku' => $parentSku,
                        'product_id' => null,
                        'variants_created' => 0,
                    ];
                }

                // Create the parent product using the first product's data as base
                $firstProduct = $group['products']->first();
                $product = Product::create([
                    'name' => $group['base_name'],
                    'parent_sku' => $parentSku,
                    'description' => $this->cleanDescription($firstProduct['body_html'] ?? ''),
                    'status' => 'active',
                    'brand' => $firstProduct['vendor'] ?? 'Unknown',
                ]);

                // Generate sequential variant SKUs
                $allVariants = $group['products']->flatMap(fn ($p) => $p['variants'] ?? []);
                $variantSkus = $this->skuGrouper->generateVariantSkus($parentSku, $allVariants->count());

                // Create variants from all products in the group
                $variantsCreated = 0;
                $variantIndex = 0;

                foreach ($group['products'] as $shopifyProduct) {
                    foreach ($shopifyProduct['variants'] ?? [] as $shopifyVariant) {
                        $variantData = $this->mapGroupedVariantData(
                            $product->id,
                            $shopifyVariant,
                            $shopifyProduct,
                            $variantSkus[$variantIndex] ?? null
                        );

                        if ($variantData) {
                            ProductVariant::create($variantData);
                            $variantsCreated++;
                            $variantIndex++;
                        }
                    }
                }

                Log::info('Shopify product group imported successfully', [
                    'parent_sku' => $parentSku,
                    'product_id' => $product->id,
                    'products_in_group' => $group['products']->count(),
                    'variants_created' => $variantsCreated,
                ]);

                return [
                    'success' => true,
                    'parent_sku' => $parentSku,
                    'product_id' => $product->id,
                    'variants_created' => $variantsCreated,
                    'error' => null,
                ];
            });

        } catch (\Exception $e) {
            Log::error('Shopify product group import failed', [
                'parent_sku' => $group['parent_sku'],
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'parent_sku' => $group['parent_sku'],
                'product_id' => null,
                'variants_created' => 0,
            ];
        }
    }

    /**
     * Map Shopify variant data to our ProductVariant fields (for grouped imports)
     */
    private function mapGroupedVariantData(int $productId, array $shopifyVariant, array $shopifyProduct, ?string $newSku): ?array
    {
        // Extract color from original SKU or variant data
        $originalSku = $shopifyVariant['sku'] ?? '';
        $color = $this->extractColorFromOriginalSku($originalSku) ??
                 $this->extractColor($shopifyVariant, $shopifyProduct);

        $dimensions = $this->extractDimensions($shopifyVariant);

        return [
            'product_id' => $productId,
            'sku' => $newSku ?? $originalSku,
            'title' => $shopifyVariant['title'] ?? ($shopifyProduct['title'].' - '.$color),
            'color' => $color,
            'width' => $dimensions['width'],
            'drop' => $dimensions['drop'],
            'price' => (float) ($shopifyVariant['price'] ?? 0),
            'stock_level' => $shopifyVariant['inventory_quantity'] ?? 0,
            'status' => 'active',
        ];
    }

    /**
     * Extract color from original Shopify SKU
     */
    private function extractColorFromOriginalSku(string $sku): ?string
    {
        $colorMap = [
            'R' => 'Red', 'RED' => 'Red',
            'B' => 'Blue', 'BLUE' => 'Blue', 'BL' => 'Blue',
            'G' => 'Green', 'GREEN' => 'Green', 'GR' => 'Green',
            'W' => 'White', 'WHITE' => 'White', 'WH' => 'White',
            'BK' => 'Black', 'BLACK' => 'Black',
            'GY' => 'Grey', 'GREY' => 'Grey', 'GRAY' => 'Grey',
        ];

        foreach ($colorMap as $code => $color) {
            if (strpos(strtoupper($sku), $code) !== false) {
                return $color;
            }
        }

        return null;
    }

    /**
     * Map Shopify variant data to our ProductVariant fields
     */
    private function mapVariantData(int $productId, array $shopifyVariant, array $shopifyProduct): ?array
    {
        // Extract color and dimensions from variant title or options
        $color = $this->extractColor($shopifyVariant, $shopifyProduct);
        $dimensions = $this->extractDimensions($shopifyVariant);

        // Skip if we can't determine basic info
        if (! $color || ! $dimensions['width']) {
            return null;
        }

        return [
            'product_id' => $productId,
            'sku' => $shopifyVariant['sku'] ?? 'SH-'.($shopifyVariant['id'] ?? uniqid()),
            'title' => $shopifyVariant['title'] ?? ($shopifyProduct['title'].' - '.$color),
            'color' => $color,
            'width' => $dimensions['width'],
            'drop' => $dimensions['drop'],
            'price' => (float) ($shopifyVariant['price'] ?? 0),
            'stock_level' => $shopifyVariant['inventory_quantity'] ?? 0,
            'status' => 'active',
        ];
    }

    /**
     * Extract color from variant data
     */
    private function extractColor(array $shopifyVariant, array $shopifyProduct): ?string
    {
        // Try option1 first (usually color in Shopify)
        if (! empty($shopifyVariant['option1']) && ! is_numeric($shopifyVariant['option1'])) {
            return $shopifyVariant['option1'];
        }

        // Try extracting from variant title
        $title = $shopifyVariant['title'] ?? $shopifyProduct['title'] ?? '';
        if (preg_match('/\b(Red|Blue|Green|White|Black|Grey|Gray|Yellow|Orange|Purple|Pink|Brown)\b/i', $title, $matches)) {
            return ucfirst(strtolower($matches[1]));
        }

        // Default fallback
        return 'Default';
    }

    /**
     * Extract dimensions from variant data
     */
    private function extractDimensions(array $shopifyVariant): array
    {
        $width = null;
        $drop = null;

        // Try to extract from option fields
        $option1 = $shopifyVariant['option1'] ?? '';
        $option2 = $shopifyVariant['option2'] ?? '';
        $option3 = $shopifyVariant['option3'] ?? '';

        // Check all options for dimensions
        foreach ([$option1, $option2, $option3] as $option) {
            if (preg_match('/(\d+)\s*cm/i', $option, $matches)) {
                if ($width === null) {
                    $width = (int) $matches[1];
                } elseif ($drop === null) {
                    $drop = (int) $matches[1];
                }
            }
        }

        // Try extracting from variant title as fallback
        if ($width === null) {
            $title = $shopifyVariant['title'] ?? '';
            if (preg_match('/(\d+)\s*(?:cm|w|width)/i', $title, $matches)) {
                $width = (int) $matches[1];
            }
        }

        return [
            'width' => $width ?: 100, // Default width if not found
            'drop' => $drop ?: 150,   // Default drop if not found
        ];
    }

    /**
     * Clean HTML description
     */
    private function cleanDescription(string $html): string
    {
        return trim(strip_tags($html));
    }
}
