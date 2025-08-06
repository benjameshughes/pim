<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ShopifyExportService
{
    /**
     * Export products to Shopify CSV format
     * Groups variants by color into separate products
     */
    public function exportProducts(Collection $products = null): array
    {
        if ($products === null) {
            $products = Product::with(['variants', 'categories', 'productImages'])->get();
        }

        $shopifyProducts = [];
        
        foreach ($products as $product) {
            $colorGroups = $this->groupVariantsByColor($product);
            
            foreach ($colorGroups as $color => $variants) {
                $shopifyProducts[] = $this->createShopifyProduct($product, $color, $variants);
            }
        }

        return $shopifyProducts;
    }

    /**
     * Group product variants by color
     */
    private function groupVariantsByColor(Product $product): array
    {
        $colorGroups = [];
        
        foreach ($product->variants as $variant) {
            $color = $variant->color ?: 'No Color';
            
            if (!isset($colorGroups[$color])) {
                $colorGroups[$color] = [];
            }
            
            $colorGroups[$color][] = $variant;
        }
        
        return $colorGroups;
    }

    /**
     * Create a Shopify product from a color group
     */
    private function createShopifyProduct(Product $product, string $color, array $variants): array
    {
        $colorName = $color === 'No Color' ? '' : $color;
        $productName = $colorName ? "{$colorName} {$product->name}" : $product->name;
        
        // Get primary category for product type
        $primaryCategory = $product->primaryCategory();
        $productType = $primaryCategory ? $primaryCategory->full_name : 'Window Treatments';
        
        // Build collections from categories
        $collections = $this->buildCollections($product, $color);
        
        // Main product row
        $shopifyProduct = [
            'Handle' => $this->generateHandle($productName),
            'Title' => $productName,
            'Body (HTML)' => $this->buildDescription($product, $color),
            'Vendor' => config('app.name', 'Window Blinds Store'),
            'Product Category' => 'Home & Garden > Decor > Window Treatments',
            'Type' => $productType,
            'Tags' => $this->buildTags($product, $color),
            'Published' => $product->status === 'active' ? 'TRUE' : 'FALSE',
            'Option1 Name' => 'Size',
            'Option1 Value' => '', // Will be filled per variant
            'Option2 Name' => '',
            'Option2 Value' => '',
            'Option3 Name' => '',
            'Option3 Value' => '',
            'Variant SKU' => '',
            'Variant Grams' => '',
            'Variant Inventory Tracker' => 'shopify',
            'Variant Inventory Qty' => '',
            'Variant Inventory Policy' => 'deny',
            'Variant Fulfillment Service' => 'manual',
            'Variant Price' => '',
            'Variant Compare At Price' => '',
            'Variant Requires Shipping' => 'TRUE',
            'Variant Taxable' => 'TRUE',
            'Variant Barcode' => '',
            'Image Src' => '',
            'Image Position' => '',
            'Image Alt Text' => '',
            'Gift Card' => 'FALSE',
            'SEO Title' => '',
            'SEO Description' => '',
            'Google Shopping / Google Product Category' => 'Home & Garden > Decor > Window Treatments',
            'Google Shopping / Gender' => '',
            'Google Shopping / Age Group' => '',
            'Google Shopping / MPN' => '',
            'Google Shopping / AdWords Grouping' => '',
            'Google Shopping / AdWords Labels' => '',
            'Google Shopping / Condition' => 'new',
            'Google Shopping / Custom Product' => 'TRUE',
            'Google Shopping / Custom Label 0' => $color,
            'Google Shopping / Custom Label 1' => '',
            'Google Shopping / Custom Label 2' => '',
            'Google Shopping / Custom Label 3' => '',
            'Google Shopping / Custom Label 4' => '',
            'Variant Image' => '',
            'Variant Weight Unit' => 'kg',
            'Variant Tax Code' => '',
            'Cost per item' => '',
            'Status' => $product->status === 'active' ? 'active' : 'draft',
        ];

        $rows = [$shopifyProduct];
        
        // Add variant rows
        foreach ($variants as $index => $variant) {
            $variantRow = $this->createVariantRow($variant, $index === 0);
            $rows[] = $variantRow;
        }

        return $rows;
    }

    /**
     * Create a variant row for Shopify
     */
    private function createVariantRow($variant, bool $isFirstVariant = false): array
    {
        $sizeOption = $this->buildSizeOption($variant);
        
        return [
            'Handle' => $isFirstVariant ? '' : '', // Empty for variant rows
            'Title' => '',
            'Body (HTML)' => '',
            'Vendor' => '',
            'Product Category' => '',
            'Type' => '',
            'Tags' => '',
            'Published' => '',
            'Option1 Name' => $isFirstVariant ? 'Size' : '',
            'Option1 Value' => $sizeOption,
            'Option2 Name' => '',
            'Option2 Value' => '',
            'Option3 Name' => '',
            'Option3 Value' => '',
            'Variant SKU' => $variant->sku,
            'Variant Grams' => $variant->package_weight ? ($variant->package_weight * 1000) : '', // Convert kg to grams
            'Variant Inventory Tracker' => 'shopify',
            'Variant Inventory Qty' => $variant->stock_level ?: 0,
            'Variant Inventory Policy' => 'deny',
            'Variant Fulfillment Service' => 'manual',
            'Variant Price' => $this->getVariantPrice($variant),
            'Variant Compare At Price' => '',
            'Variant Requires Shipping' => 'TRUE',
            'Variant Taxable' => 'TRUE',
            'Variant Barcode' => $this->getVariantBarcode($variant),
            'Image Src' => '',
            'Image Position' => '',
            'Image Alt Text' => '',
            'Gift Card' => 'FALSE',
            'SEO Title' => '',
            'SEO Description' => '',
            'Google Shopping / Google Product Category' => '',
            'Google Shopping / Gender' => '',
            'Google Shopping / Age Group' => '',
            'Google Shopping / MPN' => $variant->sku,
            'Google Shopping / AdWords Grouping' => '',
            'Google Shopping / AdWords Labels' => '',
            'Google Shopping / Condition' => 'new',
            'Google Shopping / Custom Product' => '',
            'Google Shopping / Custom Label 0' => '',
            'Google Shopping / Custom Label 1' => '',
            'Google Shopping / Custom Label 2' => '',
            'Google Shopping / Custom Label 3' => '',
            'Google Shopping / Custom Label 4' => '',
            'Variant Image' => '',
            'Variant Weight Unit' => 'kg',
            'Variant Tax Code' => '',
            'Cost per item' => '',
            'Status' => '',
        ];
    }

    /**
     * Build size option from width and drop
     */
    private function buildSizeOption($variant): string
    {
        $parts = [];
        
        if ($variant->width) {
            $parts[] = "W: {$variant->width}";
        }
        
        if ($variant->drop) {
            $parts[] = "D: {$variant->drop}";
        }
        
        return implode(' x ', $parts) ?: 'Standard';
    }

    /**
     * Generate Shopify handle (URL-friendly identifier)
     */
    private function generateHandle(string $productName): string
    {
        return Str::slug($productName);
    }

    /**
     * Build product description with color-specific information
     */
    private function buildDescription(Product $product, string $color): string
    {
        $description = "<p>{$product->description}</p>";
        
        if ($color && $color !== 'No Color') {
            $description .= "<p><strong>Color:</strong> {$color}</p>";
        }
        
        // Add features if available
        $features = [];
        for ($i = 1; $i <= 5; $i++) {
            $feature = $product->{"product_features_{$i}"};
            if ($feature) {
                $features[] = $feature;
            }
        }
        
        if (!empty($features)) {
            $description .= "<p><strong>Features:</strong></p><ul>";
            foreach ($features as $feature) {
                $description .= "<li>{$feature}</li>";
            }
            $description .= "</ul>";
        }
        
        return $description;
    }

    /**
     * Build collections from product categories
     */
    private function buildCollections(Product $product, string $color): string
    {
        $collections = [];
        
        // Add category-based collections
        foreach ($product->categories as $category) {
            $collections[] = $category->name;
            
            // Add parent categories too
            $ancestors = $category->getAllAncestors();
            foreach ($ancestors as $ancestor) {
                $collections[] = $ancestor->name;
            }
        }
        
        // Add color-based collection
        if ($color && $color !== 'No Color') {
            $collections[] = "{$color} Window Treatments";
        }
        
        return implode(', ', array_unique($collections));
    }

    /**
     * Build tags for SEO and organization
     */
    private function buildTags(Product $product, string $color): string
    {
        $tags = [];
        
        // Add categories as tags
        foreach ($product->categories as $category) {
            $tags[] = $category->name;
        }
        
        // Add color tag
        if ($color && $color !== 'No Color') {
            $tags[] = $color;
        }
        
        // Add generic tags
        $tags[] = 'window treatments';
        $tags[] = 'blinds';
        
        return implode(', ', array_unique($tags));
    }

    /**
     * Get variant price from pricing table
     */
    private function getVariantPrice($variant): string
    {
        // Get the retail price, defaulting to 0 if not found
        $pricing = $variant->pricing()->where('sales_channel_id', 1)->first(); // Assuming 1 = retail
        return $pricing ? number_format($pricing->price, 2, '.', '') : '0.00';
    }

    /**
     * Get variant barcode
     */
    private function getVariantBarcode($variant): string
    {
        $barcode = $variant->primaryBarcode();
        return $barcode ? $barcode->barcode : '';
    }

    /**
     * Generate CSV content from Shopify products
     */
    public function generateCSV(array $shopifyProducts): string
    {
        if (empty($shopifyProducts)) {
            return '';
        }

        // Flatten the products array (each product can have multiple rows)
        $allRows = [];
        foreach ($shopifyProducts as $productRows) {
            $allRows = array_merge($allRows, $productRows);
        }

        if (empty($allRows)) {
            return '';
        }

        // Get headers from first row
        $headers = array_keys($allRows[0]);
        
        // Start with CSV header
        $csv = implode(',', array_map([$this, 'escapeCsvField'], $headers)) . "\n";
        
        // Add data rows
        foreach ($allRows as $row) {
            $values = array_map([$this, 'escapeCsvField'], array_values($row));
            $csv .= implode(',', $values) . "\n";
        }
        
        return $csv;
    }

    /**
     * Escape CSV field values
     */
    private function escapeCsvField($field): string
    {
        $field = str_replace('"', '""', $field);
        return '"' . $field . '"';
    }
}