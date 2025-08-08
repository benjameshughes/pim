<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ProductNameGrouping
{
    /**
     * Find groups of similar product names and extract common base name
     * Prioritizes SKU-based grouping over name similarity
     */
    public static function groupSimilarProducts(array $productData): array
    {
        $groups = [];
        $processed = [];

        // First pass: Group by multiple SKU patterns
        $skuGroups = [];
        foreach ($productData as $index => $data) {
            $variantSku = $data['variant_sku'] ?? '';
            $parentSku = null;

            // Pattern 1: 001-001 → parent: 001
            if (preg_match('/^(\d{3})-\d{3}$/', $variantSku, $matches)) {
                $parentSku = $matches[1];
            }
            // Pattern 2: MTMSAV225 → parent: MTMSAV (letters + numbers)
            elseif (preg_match('/^([A-Z]+)[A-Z]*(\d+)$/', $variantSku, $matches)) {
                $parentSku = $matches[1];
            }
            // Pattern 3: 45120RWST-White → parent: RWST-White (numbers + letters + suffix)
            elseif (preg_match('/^\d+([A-Z]+-.+)$/', $variantSku, $matches)) {
                $parentSku = $matches[1];
            }
            // Pattern 4: 45120RWST → parent: RWST (numbers + letters)
            elseif (preg_match('/^\d+([A-Z]+)$/', $variantSku, $matches)) {
                $parentSku = $matches[1];
            }

            if ($parentSku) {
                if (! isset($skuGroups[$parentSku])) {
                    $skuGroups[$parentSku] = [];
                }
                $skuGroups[$parentSku][] = ['index' => $index, 'data' => $data];
                $processed[$index] = true;
            }
        }

        // Create groups from SKU patterns
        foreach ($skuGroups as $parentSku => $products) {
            $parentInfo = self::extractParentInfoForSkuGroup($products);
            $groups[] = [
                'parent_info' => $parentInfo,
                'products' => $products,
            ];

            Log::info('SKU-based product group created', [
                'parent_name' => $parentInfo['name'],
                'parent_sku' => $parentInfo['sku'],
                'product_count' => count($products),
            ]);
        }

        // Second pass: Group remaining products by name similarity
        foreach ($productData as $index => $data) {
            if (isset($processed[$index])) {
                continue;
            }

            $currentName = $data['product_name'] ?? '';
            if (empty($currentName)) {
                continue;
            }

            // Find similar products among unprocessed items
            $similarProducts = [];
            $similarProducts[] = ['index' => $index, 'data' => $data];
            $processed[$index] = true;

            // Compare with remaining unprocessed products
            foreach ($productData as $compareIndex => $compareData) {
                if (isset($processed[$compareIndex]) || $compareIndex === $index) {
                    continue;
                }

                $compareName = $compareData['product_name'] ?? '';
                if (empty($compareName)) {
                    continue;
                }

                if (self::areProductsSimilar($currentName, $compareName)) {
                    $similarProducts[] = ['index' => $compareIndex, 'data' => $compareData];
                    $processed[$compareIndex] = true;
                }
            }

            // Extract common parent info for this group
            $parentInfo = self::extractParentInfo($similarProducts);
            $groups[] = [
                'parent_info' => $parentInfo,
                'products' => $similarProducts,
            ];

            Log::info('Name-based product group created', [
                'parent_name' => $parentInfo['name'],
                'parent_sku' => $parentInfo['sku'],
                'product_count' => count($similarProducts),
            ]);
        }

        return $groups;
    }

    /**
     * Determine if two product names are similar enough to be grouped
     */
    private static function areProductsSimilar(string $name1, string $name2): bool
    {
        // Quick exact match
        if ($name1 === $name2) {
            return true;
        }

        // Normalize for comparison
        $normalized1 = self::normalizeForComparison($name1);
        $normalized2 = self::normalizeForComparison($name2);

        // Find common base after removing variants
        $base1 = self::removeVariantInfo($normalized1);
        $base2 = self::removeVariantInfo($normalized2);

        // Check if bases are similar
        if ($base1 === $base2 && strlen($base1) > 3) {
            return true;
        }

        // Calculate similarity using multiple methods
        $wordSimilarity = self::calculateWordSimilarity($normalized1, $normalized2);
        $stringSimilarity = self::calculateStringSimilarity($normalized1, $normalized2);

        // Require high similarity for grouping
        $isSimilar = $wordSimilarity >= 0.7 || ($stringSimilarity >= 0.8 && $wordSimilarity >= 0.5);

        if ($isSimilar) {
            Log::debug('Products grouped as similar', [
                'name1' => $name1,
                'name2' => $name2,
                'base1' => $base1,
                'base2' => $base2,
                'word_similarity' => $wordSimilarity,
                'string_similarity' => $stringSimilarity,
            ]);
        }

        return $isSimilar;
    }

    /**
     * Extract parent product information from a SKU-based group (more conservative)
     */
    private static function extractParentInfoForSkuGroup(array $productGroup): array
    {
        $names = array_column(array_column($productGroup, 'data'), 'product_name');
        $skus = array_filter(array_column(array_column($productGroup, 'data'), 'variant_sku'));

        // For SKU-based groups, find common words but be conservative about removal
        $commonName = self::findLongestCommonPrefixConservative($names);

        // Extract parent SKU from variant SKUs
        $parentSku = self::extractParentSku($skus);

        // Use first product's data as base for other parent attributes
        $firstProduct = $productGroup[0]['data'];

        return [
            'name' => $commonName,
            'sku' => $parentSku,
            'description' => $firstProduct['description'] ?? "Parent product for {$commonName}",
            'base_data' => $firstProduct, // For copying features, details, etc.
        ];
    }

    /**
     * Extract parent product information from a group of similar products
     */
    private static function extractParentInfo(array $productGroup): array
    {
        $names = array_column(array_column($productGroup, 'data'), 'product_name');
        $skus = array_filter(array_column(array_column($productGroup, 'data'), 'variant_sku'));

        // Find the longest common prefix in product names
        $commonName = self::findLongestCommonPrefix($names);

        // Clean up the common name
        $parentName = self::cleanParentName($commonName, $names);

        // Extract parent SKU from variant SKUs
        $parentSku = self::extractParentSku($skus);

        // Use first product's data as base for other parent attributes
        $firstProduct = $productGroup[0]['data'];

        return [
            'name' => $parentName,
            'sku' => $parentSku,
            'description' => $firstProduct['description'] ?? "Parent product for {$parentName}",
            'base_data' => $firstProduct, // For copying features, details, etc.
        ];
    }

    /**
     * Find the longest common prefix among product names
     */
    private static function findLongestCommonPrefix(array $names): string
    {
        if (empty($names)) {
            return 'Product Group';
        }

        if (count($names) === 1) {
            return self::removeVariantInfo($names[0]);
        }

        // Use word-based longest common subsequence for better results
        $wordSets = array_map(function ($name) {
            return explode(' ', strtolower(trim($name)));
        }, $names);

        // Find common words that appear in ALL names
        $commonWords = array_intersect(...$wordSets);

        if (! empty($commonWords)) {
            // Preserve original capitalization from first name
            $firstName = $names[0];
            $result = [];

            foreach ($commonWords as $commonWord) {
                // Find the word in the original text to preserve capitalization
                if (preg_match('/\b('.preg_quote($commonWord, '/').')\b/i', $firstName, $match)) {
                    $result[] = $match[1];
                }
            }

            $commonName = implode(' ', $result);

            // If we got a good result, use it
            if (strlen($commonName) > 3) {
                return trim($commonName);
            }
        }

        // Fallback: Use character-based longest common prefix
        $prefix = $names[0];
        foreach ($names as $name) {
            $prefix = self::longestCommonSubstring($prefix, $name);
        }

        // Clean up the prefix
        $prefix = trim($prefix);
        if (strlen($prefix) < 3) {
            // If prefix is too short, use the first significant words
            $words = explode(' ', $names[0]);
            $prefix = implode(' ', array_slice($words, 0, min(3, count($words))));
        }

        return self::removeVariantInfo($prefix);
    }

    /**
     * Find common prefix for SKU-based groups (more conservative - only removes sizes, not colors)
     */
    private static function findLongestCommonPrefixConservative(array $names): string
    {
        if (empty($names)) {
            return 'Product Group';
        }

        if (count($names) === 1) {
            return self::removeVariantInfoConservative($names[0]);
        }

        // Use word-based longest common subsequence for better results
        $wordSets = array_map(function ($name) {
            return explode(' ', strtolower(trim($name)));
        }, $names);

        // Find common words that appear in ALL names
        $commonWords = array_intersect(...$wordSets);

        if (! empty($commonWords)) {
            // Preserve original capitalization from first name
            $firstName = $names[0];
            $result = [];

            foreach ($commonWords as $commonWord) {
                // Find the word in the original text to preserve capitalization
                if (preg_match('/\b('.preg_quote($commonWord, '/').')\b/i', $firstName, $match)) {
                    $result[] = $match[1];
                }
            }

            $commonName = implode(' ', $result);

            // If we got a good result, use it
            if (strlen($commonName) > 3) {
                return trim($commonName);
            }
        }

        // Fallback: Use character-based longest common prefix
        $prefix = $names[0];
        foreach ($names as $name) {
            $prefix = self::longestCommonSubstring($prefix, $name);
        }

        // Clean up the prefix conservatively
        $prefix = trim($prefix);
        if (strlen($prefix) < 3) {
            // If prefix is too short, use the first significant words
            $words = explode(' ', $names[0]);
            $prefix = implode(' ', array_slice($words, 0, min(3, count($words))));
        }

        return self::removeVariantInfoConservative($prefix);
    }

    /**
     * Remove variant info conservatively (only sizes and measurements, keep colors)
     */
    private static function removeVariantInfoConservative(string $name): string
    {
        // Only remove sizes and measurements, NOT colors
        $variantPatterns = [
            // Sizes only
            '/\b(xs|sm|small|md|medium|lg|large|xl|xxl|xxxl|\d+cm|\d+mm|\d+inch|\d+")\b/i',
            // Measurements
            '/\d+(?:\.\d+)?\s*(?:ml|l|kg|g|oz|lb|cl|dl|cm|mm|inch|ft|m)\b/i',
            // Common variant suffixes
            '/\b(pack|set|piece|unit|pcs)\b/i',
            // Size descriptors (but not colors)
            '/\b(mini|extra|super|king|queen|single|double)\b/i',
        ];

        $cleaned = $name;
        foreach ($variantPatterns as $pattern) {
            $cleaned = preg_replace($pattern, '', $cleaned);
        }

        // Clean up extra spaces
        $cleaned = preg_replace('/\s+/', ' ', trim($cleaned));

        return $cleaned;
    }

    /**
     * Find longest common substring between two strings
     */
    private static function longestCommonSubstring(string $str1, string $str2): string
    {
        $len1 = strlen($str1);
        $len2 = strlen($str2);
        $longest = '';

        for ($i = 0; $i < $len1; $i++) {
            for ($j = 0; $j < $len2; $j++) {
                $k = 0;
                while (($i + $k < $len1) && ($j + $k < $len2) &&
                       (strtolower($str1[$i + $k]) === strtolower($str2[$j + $k]))) {
                    $k++;
                }

                if ($k > strlen($longest)) {
                    $longest = substr($str1, $i, $k);
                }
            }
        }

        return trim($longest);
    }

    /**
     * Extract parent SKU from variant SKUs using multiple patterns
     */
    private static function extractParentSku(array $skus): ?string
    {
        if (empty($skus)) {
            return null;
        }

        $parentSkus = [];
        foreach ($skus as $sku) {
            $parentSku = null;

            // Pattern 1: 001-001 → parent: 001
            if (preg_match('/^(\d{3})-\d{3}$/', $sku, $matches)) {
                $parentSku = $matches[1];
            }
            // Pattern 2: MTMSAV225 → parent: MTMSAV
            elseif (preg_match('/^([A-Z]+)[A-Z]*(\d+)$/', $sku, $matches)) {
                $parentSku = $matches[1];
            }
            // Pattern 3: 45120RWST-White → parent: RWST-White
            elseif (preg_match('/^\d+([A-Z]+-.+)$/', $sku, $matches)) {
                $parentSku = $matches[1];
            }
            // Pattern 4: 45120RWST → parent: RWST
            elseif (preg_match('/^\d+([A-Z]+)$/', $sku, $matches)) {
                $parentSku = $matches[1];
            }

            if ($parentSku) {
                $parentSkus[] = $parentSku;
            }
        }

        // If all variants share the same parent SKU, use it
        if (! empty($parentSkus)) {
            $uniqueParentSkus = array_unique($parentSkus);
            if (count($uniqueParentSkus) === 1) {
                return $uniqueParentSkus[0];
            }
        }

        return null;
    }

    /**
     * Clean and finalize parent name
     */
    private static function cleanParentName(string $commonName, array $allNames): string
    {
        // Remove trailing variant-specific info
        $cleaned = self::removeVariantInfo($commonName);

        // If cleaned name is too short, try a different approach
        if (strlen($cleaned) < 3) {
            // Use the most frequent words from all names
            $wordFreq = [];
            foreach ($allNames as $name) {
                $words = explode(' ', strtolower($name));
                foreach ($words as $word) {
                    if (strlen($word) > 2 && ! self::isVariantWord($word)) {
                        $wordFreq[$word] = ($wordFreq[$word] ?? 0) + 1;
                    }
                }
            }

            // Get most common words
            arsort($wordFreq);
            $topWords = array_slice(array_keys($wordFreq), 0, 3);

            if (! empty($topWords)) {
                // Find these words in the original first name to preserve case
                $result = [];
                $firstName = $allNames[0];
                foreach ($topWords as $word) {
                    if (preg_match('/\b('.preg_quote($word, '/').')\b/i', $firstName, $match)) {
                        $result[] = $match[1];
                    }
                }
                $cleaned = implode(' ', $result);
            }
        }

        return trim($cleaned) ?: 'Product Group';
    }

    /**
     * Remove variant-specific information from product name
     */
    private static function removeVariantInfo(string $name): string
    {
        // Remove colors, sizes, and common variant indicators
        $variantPatterns = [
            // Colors (basic)
            '/\b(red|blue|green|yellow|black|white|grey|gray|brown|pink|purple|orange|silver|gold)\b/i',
            // Sizes
            '/\b(xs|sm|small|md|medium|lg|large|xl|xxl|xxxl|\d+cm|\d+mm|\d+inch|\d+")\b/i',
            // Measurements
            '/\d+(?:\.\d+)?\s*(?:ml|l|kg|g|oz|lb|cl|dl|cm|mm|inch|ft|m)\b/i',
            // Common variant suffixes
            '/\b(pack|set|piece|unit|pcs)\b/i',
            // Size descriptors
            '/\b(mini|small|medium|large|extra|super|king|queen|single|double)\b/i',
        ];

        $cleaned = $name;
        foreach ($variantPatterns as $pattern) {
            $cleaned = preg_replace($pattern, '', $cleaned);
        }

        // Clean up extra spaces
        $cleaned = preg_replace('/\s+/', ' ', trim($cleaned));

        return $cleaned;
    }

    /**
     * Check if a word is typically variant-specific
     */
    private static function isVariantWord(string $word): bool
    {
        $variantWords = [
            'red', 'blue', 'green', 'yellow', 'black', 'white', 'grey', 'gray', 'brown',
            'pink', 'purple', 'orange', 'silver', 'gold', 'small', 'medium', 'large',
            'xs', 'sm', 'md', 'lg', 'xl', 'xxl', 'mini', 'pack', 'set', 'piece', 'unit',
        ];

        return in_array(strtolower($word), $variantWords);
    }

    /**
     * Normalize text for comparison
     */
    private static function normalizeForComparison(string $text): string
    {
        return strtolower(trim(preg_replace('/[^\w\s]/', '', $text)));
    }

    /**
     * Calculate word-based similarity
     */
    private static function calculateWordSimilarity(string $text1, string $text2): float
    {
        $words1 = array_filter(explode(' ', $text1));
        $words2 = array_filter(explode(' ', $text2));

        if (empty($words1) || empty($words2)) {
            return 0.0;
        }

        $intersection = array_intersect($words1, $words2);
        $union = array_unique(array_merge($words1, $words2));

        return count($intersection) / count($union);
    }

    /**
     * Calculate string-based similarity
     */
    private static function calculateStringSimilarity(string $text1, string $text2): float
    {
        similar_text($text1, $text2, $percent);

        return $percent / 100;
    }
}
