<?php

namespace App\Actions\API\Shopify;

use App\Models\Barcode;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantAttribute;
use Illuminate\Support\Facades\Log;

class ImportShopifyProduct
{
    /**
     * Import a Shopify product into Laravel PIM as a separate product
     * This creates new products without affecting existing ones
     */
    public function execute(array $shopifyProduct, bool $dryRun = false): array
    {
        $results = [
            'success' => false,
            'action' => 'skipped',
            'product_id' => null,
            'variants_imported' => 0,
            'errors' => [],
        ];

        try {
            // Extract base product info
            $productInfo = $this->extractProductInfo($shopifyProduct);

            // Check if we should skip this product
            if ($this->shouldSkipProduct($shopifyProduct, $productInfo)) {
                $results['action'] = 'skipped';
                $results['errors'][] = 'Product already exists or invalid data';

                return $results;
            }

            if ($dryRun) {
                $results['action'] = 'would_create';
                $results['variants_imported'] = count($shopifyProduct['variants']);
                $results['success'] = true;

                return $results;
            }

            // Create the parent product
            $product = $this->createProduct($productInfo);

            // Import variants
            $variantResults = $this->importVariants($product, $shopifyProduct['variants']);

            $results['success'] = true;
            $results['action'] = 'created';
            $results['product_id'] = $product->id;
            $results['variants_imported'] = $variantResults['imported'];

            if (! empty($variantResults['errors'])) {
                $results['errors'] = $variantResults['errors'];
            }

            Log::info('Successfully imported Shopify product', [
                'shopify_id' => $shopifyProduct['id'],
                'laravel_product_id' => $product->id,
                'title' => $shopifyProduct['title'],
                'variants_imported' => $variantResults['imported'],
            ]);

        } catch (\Exception $e) {
            $results['errors'][] = $e->getMessage();
            Log::error('Failed to import Shopify product', [
                'shopify_id' => $shopifyProduct['id'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
        }

        return $results;
    }

    /**
     * Extract product information from Shopify data
     */
    private function extractProductInfo(array $shopifyProduct): array
    {
        // Extract base name and color from title
        $title = $shopifyProduct['title'];
        $baseName = $title;
        $color = null;

        // Pattern: "Blackout Roller Blind Color" -> "Blackout Roller Blind" + "Color"
        if (preg_match('/^(.+?)\s+([A-Za-z\s]+)$/', $title, $matches)) {
            $baseName = trim($matches[1]);
            $color = trim($matches[2]);
        }

        return [
            'shopify_id' => $shopifyProduct['id'],
            'title' => $title,
            'base_name' => $baseName,
            'color' => $color,
            'handle' => $shopifyProduct['handle'],
            'description' => strip_tags($shopifyProduct['body_html'] ?? ''),
            'product_type' => $shopifyProduct['product_type'] ?? 'Roller Blind',
            'vendor' => $shopifyProduct['vendor'] ?? 'Blinds Outlet',
            'tags' => $shopifyProduct['tags'] ?? '',
            'status' => $shopifyProduct['status'] ?? 'active',
        ];
    }

    /**
     * Check if we should skip importing this product
     */
    private function shouldSkipProduct(array $shopifyProduct, array $productInfo): bool
    {
        // Skip if we already imported this Shopify product by checking parent_sku pattern
        // We'll use a prefix + shopify_id pattern for parent_sku
        $shopifyParentSku = 'SH'.$shopifyProduct['id'];
        $existingProduct = Product::where('parent_sku', $shopifyParentSku)->first();
        if ($existingProduct) {
            return true;
        }

        // Skip if no variants
        if (empty($shopifyProduct['variants'])) {
            return true;
        }

        return false;
    }

    /**
     * Create the parent product
     */
    private function createProduct(array $productInfo): Product
    {
        $product = Product::create([
            'name' => $productInfo['title'],
            'description' => $productInfo['description'],
            'status' => $this->mapStatus($productInfo['status']),
            'parent_sku' => null, // Will be generated later if needed
            'metadata' => [
                'shopify_id' => $productInfo['shopify_id'],
                'shopify_handle' => $productInfo['handle'],
                'shopify_product_type' => $productInfo['product_type'],
                'shopify_vendor' => $productInfo['vendor'],
                'shopify_tags' => $productInfo['tags'],
                'base_name' => $productInfo['base_name'],
                'color' => $productInfo['color'],
                'imported_from' => 'shopify',
                'imported_at' => now()->toISOString(),
            ],
        ]);

        // Generate a parent SKU if needed
        if (! $product->parent_sku) {
            $product->update(['parent_sku' => 'SH'.str_pad($product->id, 3, '0', STR_PAD_LEFT)]);
        }

        return $product;
    }

    /**
     * Import product variants
     */
    private function importVariants(Product $product, array $shopifyVariants): array
    {
        $imported = 0;
        $errors = [];

        foreach ($shopifyVariants as $shopifyVariant) {
            try {
                $variant = $this->createVariant($product, $shopifyVariant);
                $this->createVariantAttributes($variant, $shopifyVariant);
                $this->createVariantBarcode($variant, $shopifyVariant);
                $this->createVariantPricing($variant, $shopifyVariant);

                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Variant {$shopifyVariant['sku']}: ".$e->getMessage();
            }
        }

        return [
            'imported' => $imported,
            'errors' => $errors,
        ];
    }

    /**
     * Create product variant
     */
    private function createVariant(Product $product, array $shopifyVariant): ProductVariant
    {
        return ProductVariant::create([
            'product_id' => $product->id,
            'sku' => $shopifyVariant['sku'],
            'stock_level' => $shopifyVariant['inventory_quantity'] ?? 0,
            'status' => 'active',
            'metadata' => [
                'shopify_variant_id' => $shopifyVariant['id'],
                'shopify_title' => $shopifyVariant['title'],
                'shopify_position' => $shopifyVariant['position'] ?? 1,
                'imported_from' => 'shopify',
            ],
        ]);
    }

    /**
     * Create variant attributes (size, color, etc.)
     */
    private function createVariantAttributes(ProductVariant $variant, array $shopifyVariant): void
    {
        // Size/Width from option1 (e.g., "60cm", "90cm")
        if (! empty($shopifyVariant['option1'])) {
            $size = $shopifyVariant['option1'];

            // Extract numeric width if it contains "cm"
            if (str_contains($size, 'cm')) {
                VariantAttribute::create([
                    'product_variant_id' => $variant->id,
                    'attribute_key' => 'width',
                    'attribute_value' => $size,
                ]);
            } else {
                VariantAttribute::create([
                    'product_variant_id' => $variant->id,
                    'attribute_key' => 'size',
                    'attribute_value' => $size,
                ]);
            }
        }

        // Color from parent product metadata
        $product = $variant->product;
        if ($product->metadata && isset($product->metadata['color'])) {
            VariantAttribute::create([
                'product_variant_id' => $variant->id,
                'attribute_key' => 'color',
                'attribute_value' => $product->metadata['color'],
            ]);
        }

        // Add other options if available
        if (! empty($shopifyVariant['option2'])) {
            VariantAttribute::create([
                'product_variant_id' => $variant->id,
                'attribute_key' => 'option2',
                'attribute_value' => $shopifyVariant['option2'],
            ]);
        }

        if (! empty($shopifyVariant['option3'])) {
            VariantAttribute::create([
                'product_variant_id' => $variant->id,
                'attribute_key' => 'option3',
                'attribute_value' => $shopifyVariant['option3'],
            ]);
        }
    }

    /**
     * Create variant barcode
     */
    private function createVariantBarcode(ProductVariant $variant, array $shopifyVariant): void
    {
        if (! empty($shopifyVariant['barcode'])) {
            // Remove any quotes from barcode
            $barcode = trim($shopifyVariant['barcode'], "'\"");

            Barcode::create([
                'product_variant_id' => $variant->id,
                'barcode' => $barcode,
                'barcode_type' => 'EAN13', // Assume EAN13 for UK products
                'is_primary' => true,
                'source' => 'shopify_import',
            ]);
        }
    }

    /**
     * Create variant pricing
     */
    private function createVariantPricing(ProductVariant $variant, array $shopifyVariant): void
    {
        if (! empty($shopifyVariant['price'])) {
            $price = (float) $shopifyVariant['price'];

            Pricing::create([
                'product_variant_id' => $variant->id,
                'sales_channel_id' => null, // Default channel
                'retail_price' => $price,
                'currency' => 'GBP',
                'vat_rate' => 20.0, // UK VAT
                'vat_inclusive' => true,
                'source' => 'shopify_import',
            ]);
        }
    }

    /**
     * Map Shopify status to Laravel status
     */
    private function mapStatus(string $shopifyStatus): string
    {
        return match ($shopifyStatus) {
            'active' => 'active',
            'draft' => 'inactive',
            'archived' => 'discontinued',
            default => 'active'
        };
    }
}
