<?php

namespace App\Actions\Marketplace\Shopify;

use App\Models\Product;

/**
 * 🎨 SPLIT PRODUCT BY COLOUR ACTION
 *
 * Shopify-specific action that groups product variants by color.
 * This enables creating separate Shopify products for each color variant.
 *
 * Example:
 * Input: "Blackout Blind" with Black, White, Grey variants
 * Output: 3 groups - one for each color with their size variants
 */
class SplitProductByColourAction
{
    /**
     * Execute the color splitting logic
     *
     * @param  Product  $product  The product to split
     * @return array Color groups keyed by color name
     */
    public function execute(Product $product): array
    {
        $variants = $product->variants;

        if ($variants->isEmpty()) {
            return [];
        }

        // Group variants by color
        $colorGroups = [];

        foreach ($variants as $variant) {
            $color = $this->extractColor($variant);

            if (! isset($colorGroups[$color])) {
                $colorGroups[$color] = [];
            }

            $colorGroups[$color][] = $variant;
        }

        // Sort colors for consistent ordering
        ksort($colorGroups);

        return $colorGroups;
    }

    /**
     * Extract color from variant
     *
     * This method handles your specific color extraction logic.
     * You may need to adjust this based on how colors are stored in your variants.
     */
    protected function extractColor($variant): string
    {
        // Method 1: If color is stored directly on variant
        if (! empty($variant->color)) {
            return $variant->color;
        }

        // Method 2: If color is in variant name/title
        if (! empty($variant->name)) {
            // Try to extract color from variant name
            $color = $this->parseColorFromName($variant->name);
            if ($color) {
                return $color;
            }
        }

        // Method 3: If color is in attributes/options
        if (! empty($variant->attributes)) {
            $attributes = is_string($variant->attributes)
                ? json_decode($variant->attributes, true)
                : $variant->attributes;

            if (isset($attributes['color'])) {
                return $attributes['color'];
            }
        }

        // Fallback: Use 'Default' if no color found
        return 'Default';
    }

    /**
     * Parse color from variant name
     *
     * This handles cases where color is embedded in the variant name
     * Example: "Blackout Blind - Black - 150x200cm" -> "Black"
     */
    protected function parseColorFromName(string $name): ?string
    {
        // Common color patterns in variant names
        $commonColors = [
            'Black', 'White', 'Grey', 'Gray', 'Red', 'Blue', 'Green', 'Yellow',
            'Brown', 'Pink', 'Purple', 'Orange', 'Navy', 'Cream', 'Beige',
            'Natural', 'Ivory', 'Silver', 'Gold', 'Bronze',
        ];

        foreach ($commonColors as $color) {
            if (stripos($name, $color) !== false) {
                return $color;
            }
        }

        // Try to extract color from patterns like "- ColorName -" or "ColorName "
        if (preg_match('/(?:^|\s|-)([A-Za-z]+)(?:\s|-|$)/', $name, $matches)) {
            $possibleColor = ucfirst(strtolower($matches[1]));

            // Check if it looks like a color (not a size or other attribute)
            if ($this->isLikelyColor($possibleColor)) {
                return $possibleColor;
            }
        }

        return null;
    }

    /**
     * Check if a word is likely to be a color
     */
    protected function isLikelyColor(string $word): bool
    {
        // Skip words that are likely sizes or other attributes
        $nonColorWords = [
            'cm', 'mm', 'inch', 'small', 'medium', 'large', 'xl', 'xxl',
            'wide', 'narrow', 'thick', 'thin', 'standard', 'premium',
        ];

        return ! in_array(strtolower($word), $nonColorWords) && strlen($word) > 2;
    }
}
