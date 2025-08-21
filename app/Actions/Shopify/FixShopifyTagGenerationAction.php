<?php

namespace App\Actions\Shopify;

use App\Actions\Base\BaseAction;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * 🏷️ FIX SHOPIFY TAG GENERATION ACTION
 * 
 * Centralized action to fix Shopify tag generation issues.
 * Addresses empty brand fields and other tag-related problems.
 * Can be used for both individual products and bulk operations.
 */
class FixShopifyTagGenerationAction extends BaseAction
{
    /**
     * Execute tag generation fixes
     * 
     * @param Product|null $product Specific product to fix, or null for all products
     * @param array $options Fix options
     * @return array
     */
    protected function performAction(...$params): array
    {
        $product = $params[0] ?? null;
        $options = $params[1] ?? [];

        $startTime = microtime(true);
        $fixed = [];
        $errors = [];

        try {
            if ($product instanceof Product) {
                // Fix single product
                $result = $this->fixSingleProduct($product, $options);
                if ($result['success']) {
                    $fixed[] = $result['product'];
                } else {
                    $errors[] = $result['error'];
                }
            } else {
                // Fix all products with empty brands
                $result = $this->fixAllProductsWithEmptyBrands($options);
                $fixed = $result['fixed'];
                $errors = $result['errors'];
            }

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            if (empty($errors)) {
                return $this->success(
                    'Successfully fixed ' . count($fixed) . ' product(s) tag generation',
                    [
                        'fixed_count' => count($fixed),
                        'fixed_products' => $fixed,
                        'duration_ms' => $duration,
                    ]
                );
            } else {
                return $this->failure(
                    'Fixed ' . count($fixed) . ' products but encountered ' . count($errors) . ' errors',
                    [
                        'fixed_count' => count($fixed),
                        'error_count' => count($errors),
                        'fixed_products' => $fixed,
                        'errors' => $errors,
                        'duration_ms' => $duration,
                    ]
                );
            }

        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            return $this->failure(
                'Tag generation fix failed: ' . $e->getMessage(),
                [
                    'error' => $e->getMessage(),
                    'duration_ms' => $duration,
                ]
            );
        }
    }

    /**
     * Fix a single product's tag generation issues
     */
    protected function fixSingleProduct(Product $product, array $options = []): array
    {
        $fixes = [];
        
        // Fix empty brand
        if (empty($product->brand) || trim($product->brand) === '') {
            $defaultBrand = $options['default_brand'] ?? 'Window Treatments Co';
            
            $product->brand = $defaultBrand;
            $product->save();
            
            $fixes[] = "Set brand to '{$defaultBrand}'";
        }

        // Clean up empty marketplace attributes
        $deletedAttributes = DB::table('marketplace_product_attributes')
            ->where('product_id', $product->id)
            ->whereNull('attribute_value')
            ->orWhere('attribute_value', '')
            ->delete();

        if ($deletedAttributes > 0) {
            $fixes[] = "Deleted {$deletedAttributes} empty marketplace attributes";
        }

        return [
            'success' => true,
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'parent_sku' => $product->parent_sku,
                'brand' => $product->brand,
                'fixes_applied' => $fixes,
            ],
        ];
    }

    /**
     * Fix all products with empty brands
     */
    protected function fixAllProductsWithEmptyBrands(array $options = []): array
    {
        $fixed = [];
        $errors = [];
        
        $defaultBrand = $options['default_brand'] ?? 'Window Treatments Co';
        
        // Get products with empty brands
        $productsToFix = Product::where(function ($query) {
            $query->whereNull('brand')
                  ->orWhere('brand', '')
                  ->orWhere('brand', ' ');
        })->get();

        foreach ($productsToFix as $product) {
            try {
                $result = $this->fixSingleProduct($product, $options);
                if ($result['success']) {
                    $fixed[] = $result['product'];
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'product_id' => $product->id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Clean up empty marketplace attributes for all products
        $totalDeletedAttributes = DB::table('marketplace_product_attributes')
            ->whereNull('attribute_value')
            ->orWhere('attribute_value', '')
            ->delete();

        return [
            'fixed' => $fixed,
            'errors' => $errors,
            'additional_info' => [
                'total_empty_attributes_deleted' => $totalDeletedAttributes,
                'default_brand_used' => $defaultBrand,
            ],
        ];
    }

    /**
     * Validate that a product won't cause tag generation errors
     */
    public function validateProductForShopifyTagGeneration(Product $product): array
    {
        $issues = [];
        
        // Check brand field
        if (empty($product->brand) || trim($product->brand) === '') {
            $issues[] = [
                'type' => 'empty_brand',
                'message' => 'Brand field is empty, will cause "Brand: " tag generation error',
                'severity' => 'high',
                'fix' => 'Set a valid brand value',
            ];
        }

        // Check for empty marketplace attributes
        $emptyAttributeCount = DB::table('marketplace_product_attributes')
            ->where('product_id', $product->id)
            ->whereNull('attribute_value')
            ->orWhere('attribute_value', '')
            ->count();

        if ($emptyAttributeCount > 0) {
            $issues[] = [
                'type' => 'empty_marketplace_attributes',
                'message' => "Product has {$emptyAttributeCount} empty marketplace attributes",
                'severity' => 'medium',
                'fix' => 'Clean up empty marketplace attribute records',
            ];
        }

        return [
            'is_valid' => empty($issues),
            'issues' => $issues,
            'product_id' => $product->id,
            'product_name' => $product->name,
        ];
    }

    /**
     * Get summary of products that need tag generation fixes
     */
    public function getFixingSummary(): array
    {
        // Products with empty brands
        $emptyBrandCount = Product::where(function ($query) {
            $query->whereNull('brand')
                  ->orWhere('brand', '')
                  ->orWhere('brand', ' ');
        })->count();

        // Empty marketplace attributes
        $emptyAttributeCount = DB::table('marketplace_product_attributes')
            ->whereNull('attribute_value')
            ->orWhere('attribute_value', '')
            ->count();

        // Products with marketplace sync issues
        $productsWithSyncIssues = DB::table('marketplace_product_attributes')
            ->join('products', 'marketplace_product_attributes.product_id', '=', 'products.id')
            ->whereNull('marketplace_product_attributes.attribute_value')
            ->orWhere('marketplace_product_attributes.attribute_value', '')
            ->distinct('products.id')
            ->count();

        return [
            'products_with_empty_brands' => $emptyBrandCount,
            'empty_marketplace_attributes' => $emptyAttributeCount,
            'products_with_sync_issues' => $productsWithSyncIssues,
            'total_issues' => $emptyBrandCount + $productsWithSyncIssues,
            'estimated_fix_time_ms' => ($emptyBrandCount + $productsWithSyncIssues) * 50, // Rough estimate
        ];
    }
}