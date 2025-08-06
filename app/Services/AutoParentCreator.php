<?php

namespace App\Services;

use App\Models\Product;
use App\Services\ProductAttributeExtractorV2;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AutoParentCreator
{
    /**
     * Auto-create parent product from variant data using hybrid approach
     */
    public static function createParentFromVariant(array $variantData): Product
    {
        Log::info("Auto-creating parent for variant", ['sku' => $variantData['variant_sku'] ?? 'N/A']);

        // Method 1: SKU-based parent extraction (primary)
        if (!empty($variantData['variant_sku'])) {
            $parentProduct = self::createParentFromSKU($variantData);
            if ($parentProduct) {
                Log::info("Created parent from SKU pattern", ['parent_name' => $parentProduct->name]);
                return $parentProduct;
            }
        }

        // Method 2: Name-based parent extraction (fallback)
        if (!empty($variantData['product_name'])) {
            $parentProduct = self::createParentFromName($variantData);
            if ($parentProduct) {
                Log::info("Created parent from name extraction", ['parent_name' => $parentProduct->name]);
                return $parentProduct;
            }
        }

        // Method 3: Generic fallback
        $parentProduct = self::createGenericParent($variantData);
        Log::info("Created generic parent", ['parent_name' => $parentProduct->name]);
        return $parentProduct;
    }

    /**
     * Extract parent from SKU pattern: 001-001 → parent: 001, variant: 001
     */
    private static function createParentFromSKU(array $variantData): ?Product
    {
        $variantSku = $variantData['variant_sku'];
        
        // Check if SKU matches pattern: XXX-XXX (3 digits - 3 digits)
        if (!preg_match('/^(\d{3})-(\d{3})$/', $variantSku, $matches)) {
            Log::info("SKU doesn't match 001-001 pattern", ['sku' => $variantSku]);
            return null;
        }

        $parentSku = $matches[1]; // First 3 digits
        $variantCode = $matches[2]; // Second 3 digits

        // Check if parent already exists
        $existingParent = Product::where('parent_sku', $parentSku)->first();
        if ($existingParent) {
            Log::info("Parent already exists", ['parent_sku' => $parentSku, 'parent_name' => $existingParent->name]);
            return $existingParent;
        }

        // Generate parent name from variant name (remove variant-specific parts)
        $parentName = self::extractParentNameFromVariantName($variantData['product_name'] ?? "Product {$parentSku}");

        // Create parent product
        return Product::create([
            'name' => $parentName,
            'slug' => Str::slug($parentName),
            'parent_sku' => $parentSku,
            'description' => $variantData['description'] ?? "Auto-generated parent for SKU {$parentSku}",
            'status' => 'active',
            'auto_generated' => true,
        ]);
    }

    /**
     * Extract parent from variant name by removing colors, sizes, and variant-specific words
     */
    private static function createParentFromName(array $variantData): ?Product
    {
        $variantName = $variantData['product_name'];
        
        // Use our new action for generating parent names
        $parentName = app(\App\Actions\Import\GenerateParentName::class)
            ->execute($variantName, $variantData['variant_sku'] ?? null);

        // Skip if extraction didn't work well (too short or same as original)
        if (strlen($parentName) < 5 || $parentName === $variantName) {
            return null;
        }

        // Check if parent already exists by name
        $existingParent = Product::where('name', $parentName)->first();
        if ($existingParent) {
            return $existingParent;
        }

        // Generate parent SKU from variant SKU if available
        $parentSku = null;
        if (!empty($variantData['variant_sku']) && preg_match('/^(\d{3})-\d{3}$/', $variantData['variant_sku'], $matches)) {
            $parentSku = $matches[1];
        }

        return Product::create([
            'name' => $parentName,
            'slug' => Str::slug($parentName),
            'parent_sku' => $parentSku,
            'description' => $variantData['description'] ?? "Auto-generated parent for {$parentName}",
            'status' => 'active',
            'auto_generated' => true,
        ]);
    }

    /**
     * Extract parent name from variant name using advanced multi-pass algorithm
     */
    public static function extractParentNameFromVariantName(string $variantName): string
    {
        $originalName = $variantName;
        
        // Use improved ProductAttributeExtractorV2 which has sophisticated parent name generation
        $attributes = ProductAttributeExtractorV2::extractAttributes($variantName);
        
        // The V2 extractor already generates a cleaned parent name using 6-pass algorithm
        if (!empty($attributes['parent_name']) && strlen($attributes['parent_name']) >= 3) {
            Log::info("Using V2 extracted parent name", [
                'original' => $originalName,
                'extracted_parent' => $attributes['parent_name'],
                'confidence' => $attributes['confidence'] ?? 0
            ]);
            return $attributes['parent_name'];
        }
        
        Log::info("V2 extractor didn't generate parent name, using fallback", [
            'original' => $originalName,
            'attributes' => $attributes
        ]);
        
        // Fallback: Conservative approach - remove last 1-2 words (often size/color)
        $words = explode(' ', trim($originalName));
        if (count($words) > 2) {
            array_pop($words); // Remove last word
            $fallbackName = implode(' ', $words);
            
            // If still reasonable length, use it
            if (strlen($fallbackName) >= 5) {
                return $fallbackName;
            }
        }

        return $originalName; // Keep original if nothing worked
    }

    /**
     * Create a generic parent when all else fails
     */
    private static function createGenericParent(array $variantData): Product
    {
        $baseName = $variantData['product_name'] ?? 'Product';
        
        // Extract parent SKU if possible
        $parentSku = null;
        if (!empty($variantData['variant_sku']) && preg_match('/^(\d{3})-\d{3}$/', $variantData['variant_sku'], $matches)) {
            $parentSku = $matches[1];
            $baseName = "Product {$parentSku}";
        }

        return Product::create([
            'name' => $baseName,
            'slug' => Str::slug($baseName),
            'parent_sku' => $parentSku,
            'description' => 'Auto-generated parent product',
            'status' => 'active',
            'auto_generated' => true,
        ]);
    }

    /**
     * Smart grouping: Find or create parent for multiple variants with similar names
     */
    public static function createParentFromVariantGroup(array $variantDataArray): Product
    {
        if (empty($variantDataArray)) {
            throw new \InvalidArgumentException('Variant data array cannot be empty');
        }

        // Extract common parent name from all variants
        $names = array_column($variantDataArray, 'product_name');
        $commonName = self::findCommonParentName($names);

        // Check for common parent SKU pattern
        $parentSku = null;
        $skus = array_filter(array_column($variantDataArray, 'variant_sku'));
        if (!empty($skus)) {
            $parentSkus = [];
            foreach ($skus as $sku) {
                if (preg_match('/^(\d{3})-\d{3}$/', $sku, $matches)) {
                    $parentSkus[] = $matches[1];
                }
            }
            
            // If all variants share the same parent SKU, use it
            if (count(array_unique($parentSkus)) === 1) {
                $parentSku = $parentSkus[0];
            }
        }

        // Check if parent already exists
        if ($parentSku) {
            $existingParent = Product::where('parent_sku', $parentSku)->first();
            if ($existingParent) {
                return $existingParent;
            }
        }

        return Product::create([
            'name' => $commonName,
            'slug' => Str::slug($commonName),
            'parent_sku' => $parentSku,
            'description' => "Auto-generated parent for variant group",
            'status' => 'active',
            'auto_generated' => true,
        ]);
    }

    /**
     * Find common parent name from multiple variant names
     */
    private static function findCommonParentName(array $names): string
    {
        if (empty($names)) {
            return 'Product Group';
        }

        if (count($names) === 1) {
            return self::extractParentNameFromVariantName($names[0]);
        }

        // Find common words across all names
        $wordSets = array_map(function($name) {
            return array_filter(explode(' ', strtolower(trim($name))));
        }, $names);

        $commonWords = array_intersect(...$wordSets);
        
        if (!empty($commonWords)) {
            // Preserve original capitalization by finding the common words in the first name
            $firstName = $names[0];
            $result = [];
            foreach ($commonWords as $commonWord) {
                // Find the word in the original text to preserve capitalization
                if (preg_match('/\b(' . preg_quote($commonWord, '/') . ')\b/i', $firstName, $match)) {
                    $result[] = $match[1];
                } else {
                    $result[] = ucfirst($commonWord);
                }
            }
            return implode(' ', $result);
        }

        // Fallback: use the first name and extract parent
        return self::extractParentNameFromVariantName($names[0]);
    }
}