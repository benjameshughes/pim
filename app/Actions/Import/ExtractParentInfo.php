<?php

namespace App\Actions\Import;

use Illuminate\Support\Facades\Log;

/**
 * 🎯 EXTRACT PARENT INFO ACTION
 *
 * Extracts parent product information from variant data
 * Handles SKU patterns, dimensions, and color extraction
 */
class ExtractParentInfo
{
    public function __construct(
        private ExtractDimensions $extractDimensions
    ) {}

    /**
     * Extract parent SKU and product information from variant data
     *
     * Supports patterns:
     * - Pattern 1: "45120RWST-White" → parent_sku: "45120RWST", color: "White"
     * - Pattern 2: "001-002-003" → parent_sku: "001-002", variant: "003"
     * - Pattern 3: "010-108" → parent_sku: "010", variant: "108"
     *
     * @param  array  $data  Row data containing SKU and title
     * @return array Extracted parent information
     */
    public function execute(array $data): array
    {
        $sku = $data['sku'] ?? '';
        $title = $data['title'] ?? '';

        // Detect SKU pattern and extract accordingly
        if (preg_match('/^(\d{3}-\d{3})-(\d{3})$/', $sku, $matches)) {
            // Pattern 2: 001-002-003 (three-part numeric pattern)
            $parentSku = $matches[1]; // 001-002
            $variantId = $matches[2]; // 003
            $color = $this->extractColorFromTitle($title);
        } elseif (preg_match('/^(\d{3})-(\d{3})$/', $sku, $matches)) {
            // Pattern 3: 010-108 (parent 010, variant 108)
            $parentSku = $matches[1]; // 010 (parent)
            $variantId = $matches[2]; // 108 (variant number)
            $color = $this->extractColorFromTitle($title);
        } else {
            // Pattern 1: RB45120-White (alphanumeric-color pattern)
            $parentSku = preg_replace('/-[A-Za-z]+$/', '', $sku);
            $skuParts = explode('-', $sku);
            // Only use SKU part if it looks like a color (contains letters)
            if (count($skuParts) > 1 && preg_match('/[A-Za-z]/', end($skuParts))) {
                $color = end($skuParts);
            } else {
                $color = $this->extractColorFromTitle($title);
            }
        }

        // Extract dimensions from title using dedicated action
        $dimensions = $this->extractDimensions->execute($title);
        $width = $dimensions['width'];
        $drop = $dimensions['drop'];

        // Generate product name by removing dimensions and color
        $baseName = preg_replace('/\d+cm( x \d+cm)?/', '', $title);
        $baseName = preg_replace('/\b'.preg_quote($color, '/').'\b/i', '', $baseName);
        $baseName = trim(preg_replace('/\s+/', ' ', $baseName));

        $result = [
            'parent_sku' => $parentSku,
            'product_name' => $baseName ?: 'Product '.$parentSku,
            'color' => $color,
            'width' => $width,
            'drop' => $drop,
        ];

        // Debug logging
        Log::debug('Extracted parent info', [
            'original_sku' => $sku,
            'original_title' => $title,
            'extracted_parent_sku' => $parentSku,
            'extracted_color' => $color,
            'extracted_width' => $width,
            'extracted_drop' => $drop,
            'sku_pattern_detected' => $this->detectSkuPattern($sku),
            'dimension_pattern_detected' => $this->extractDimensions->getPatternUsed($title),
        ]);

        return $result;
    }

    /**
     * Extract color from product title using common patterns
     */
    private function extractColorFromTitle(string $title): string
    {
        $commonColors = [
            // Compound colors first (longer matches take priority)
            'burnt orange', 'dark grey', 'light grey', 'dark gray', 'light gray',
            'dark blue', 'light blue', 'navy blue', 'royal blue', 'sky blue',
            'dark green', 'light green', 'forest green', 'lime green',
            'dark red', 'light red', 'bright red', 'deep red',
            'off white', 'cream white', 'pure white',

            // Single word colors
            'white', 'black', 'grey', 'gray', 'brown', 'beige', 'cream', 'ivory',
            'blue', 'navy', 'red', 'green', 'yellow', 'orange', 'pink', 'purple',
            'aubergine', 'charcoal', 'taupe', 'linen', 'natural',
        ];

        // Sort by length descending so longer color names are matched first
        usort($commonColors, function ($a, $b) {
            return strlen($b) - strlen($a);
        });

        foreach ($commonColors as $color) {
            // Use word boundaries to avoid partial matches (e.g., "black" in "blackout")
            if (preg_match('/\b'.preg_quote($color, '/').'\b/i', $title)) {
                return ucwords($color);  // Use ucwords for multi-word colors
            }
        }

        // Fallback: try to find any color-like word
        if (preg_match('/\b([A-Za-z]+)\s+\d+cm/', $title, $matches)) {
            return ucfirst($matches[1]);
        }

        return 'Default';
    }

    /**
     * Detect which SKU pattern is being used (for debugging)
     */
    private function detectSkuPattern(string $sku): string
    {
        if (preg_match('/^(\d{3}-\d{3})-(\d{3})$/', $sku)) {
            return 'three-part-numeric (001-002-003)';
        } elseif (preg_match('/^(\d{3})-(\d{3})$/', $sku)) {
            return 'two-part-numeric (010-108)';
        } else {
            return 'alphanumeric-color (RB45120-White)';
        }
    }
}
