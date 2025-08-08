<?php

namespace App\Services;

class ProductAttributeExtractor
{
    // Common product base terms that should be ignored when extracting colors/sizes
    private static $productTerms = [
        'blackout', 'black out', 'roller', 'rollers', 'blind', 'blinds', 'window', 'curtain', 'curtains',
        'shade', 'shades', 'thermal', 'venetian', 'roman', 'vertical', 'horizontal', 'fabric', 'material',
        'with', 'without', 'out', 'mtm', 'made', 'to', 'measure', 'drop', 'width', 'height', 'length',
        'size', 'dimension', 'measurement', 'standard', 'custom', 'bespoke',
    ];

    // Material terms that are often confused with colors but should be deprioritized
    private static $materialTerms = [
        'aluminium', 'aluminum', 'wood', 'wooden', 'metal', 'plastic', 'vinyl', 'pvc', 'steel',
        'bamboo', 'fabric', 'cotton', 'polyester', 'linen', 'silk', 'leather', 'glass', 'acrylic',
        'faux', 'grain', 'natural', 'composite', 'synthetic', 'artificial', 'imitation',
    ];

    // Color modifiers that can appear before color names
    private static $colorModifiers = [
        'dark', 'light', 'bright', 'deep', 'pale', 'soft', 'rich', 'vivid', 'matte', 'glossy',
        'burnt', 'royal', 'navy', 'forest', 'sky', 'powder', 'hot', 'off',
    ];

    // Common size indicators
    private static $sizeIndicators = [
        'cm', 'mm', 'm', 'inch', 'in', 'ft', 'foot', 'feet', '"', "'",
        'small', 'medium', 'large', 'mini', 'jumbo', 'king', 'queen',
        'xs', 'sm', 'md', 'lg', 'xl', 'xxl', 'x', '×',
    ];

    /**
     * Extract color and size from product name using smart algorithmic analysis
     */
    public static function extractAttributes(string $productName): array
    {
        $sizeInfo = self::extractSizeSmart($productName);

        return [
            'color' => self::extractColorSmart($productName),
            'size' => $sizeInfo['size'] ?? null,
            'width' => $sizeInfo['width'] ?? null,
            'drop' => $sizeInfo['drop'] ?? null,
        ];
    }

    /**
     * Smart color extraction using linguistic analysis and position detection
     */
    private static function extractColorSmart(string $text): ?string
    {
        $originalText = $text;
        $text = strtolower(trim($text));

        // Step 1: Remove product base terms to avoid false positives like "black" in "blackout"
        $cleanedText = self::removeProductTerms($text);

        // Step 2: Tokenize into segments for analysis
        $segments = self::analyzeTextSegments($cleanedText);

        // Step 3: Find color candidates using multiple strategies
        $colorCandidates = [];

        // Strategy 1: Look for color modifiers + color words (e.g., "Burnt Orange", "Royal Blue")
        $colorCandidates = array_merge($colorCandidates, self::findCompoundColors($cleanedText));

        // Strategy 2: Find standalone color words in appropriate positions
        $colorCandidates = array_merge($colorCandidates, self::findStandaloneColors($cleanedText));

        // Strategy 3: Use position-based analysis (colors often appear in specific positions)
        $colorCandidates = array_merge($colorCandidates, self::findPositionalColors($originalText));

        // Step 4: Score and rank candidates
        return self::selectBestColorCandidate($colorCandidates, $originalText);
    }

    /**
     * Smart size extraction focusing on measurements and position
     */
    private static function extractSizeSmart(string $text): array
    {
        $originalText = $text;
        $text = strtolower(trim($text));

        $sizeCandidates = [];

        // Strategy 1: Dimensional measurements (highest priority)
        $sizeCandidates = array_merge($sizeCandidates, self::findDimensionalSizes($text));

        // Strategy 2: Named sizes in context
        $sizeCandidates = array_merge($sizeCandidates, self::findNamedSizes($text));

        // Strategy 3: Position-based size detection (sizes often at end)
        $sizeCandidates = array_merge($sizeCandidates, self::findPositionalSizes($originalText));

        // Return the best size information
        return self::selectBestSizeCandidate($sizeCandidates);
    }

    /**
     * Remove product base terms to avoid false color matches
     */
    private static function removeProductTerms(string $text): string
    {
        $cleaned = $text;
        foreach (self::$productTerms as $term) {
            // Use word boundaries to avoid removing parts of other words
            $cleaned = preg_replace('/\b'.preg_quote($term, '/').'\b/i', '', $cleaned);
        }

        return preg_replace('/\s+/', ' ', trim($cleaned));
    }

    /**
     * Find compound colors like "Burnt Orange", "Royal Blue"
     */
    private static function findCompoundColors(string $text): array
    {
        $candidates = [];

        // Look for modifier + color patterns
        foreach (self::$colorModifiers as $modifier) {
            // Pattern: modifier + word that could be a color
            if (preg_match('/\b'.preg_quote($modifier, '/').'\s+(\w+)\b/i', $text, $matches)) {
                $fullColor = $modifier.' '.$matches[1];
                $candidates[] = [
                    'value' => $fullColor,
                    'score' => 15, // High score for compound colors
                    'type' => 'compound',
                    'position' => strpos($text, strtolower($fullColor)),
                ];
            }
        }

        return $candidates;
    }

    /**
     * Find standalone color words using linguistic analysis
     */
    private static function findStandaloneColors(string $text): array
    {
        $candidates = [];
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        // Common color words that should always be considered
        $knownColors = ['white', 'black', 'red', 'blue', 'green', 'yellow', 'brown', 'silver', 'gold',
            'grey', 'gray', 'purple', 'pink', 'orange', 'beige', 'cream', 'ivory'];

        foreach ($words as $index => $word) {
            // Skip very short words or numbers
            if (strlen($word) < 3 || is_numeric($word)) {
                continue;
            }

            $wordLower = strtolower($word);

            // High priority for known color words
            if (in_array($wordLower, $knownColors)) {
                $candidates[] = [
                    'value' => $word,
                    'score' => 20, // High score for known colors
                    'type' => 'known_color',
                    'position' => $index,
                ];

                continue;
            }

            // Check if word could be a color based on linguistic patterns
            if (self::couldBeColor($word, $text)) {
                $score = 10;

                // Boost score for colors at the end (often the actual color name)
                if ($index >= count($words) - 2) { // Last or second-to-last word
                    $score += 5;
                }

                // Boost score for actual color words that appear early (like "Silver")
                if ($index <= 1 && ! in_array($wordLower, self::$materialTerms)) {
                    $score += 3; // Bonus for early color words that aren't materials
                }

                $candidates[] = [
                    'value' => $word,
                    'score' => $score,
                    'type' => 'standalone',
                    'position' => $index,
                ];
            }
        }

        return $candidates;
    }

    /**
     * Use position analysis to find colors (e.g., colors often appear after product type)
     */
    private static function findPositionalColors(string $text): array
    {
        $candidates = [];

        // For products like "Blackout Roller Blind Aubergine 60cm"
        // Color often appears between product type and size

        // Pattern: [product terms] [COLOR] [size/measurement]
        // More precise pattern to avoid capturing product terms
        if (preg_match('/(?:blackout|thermal)\s+(?:roller|venetian|roman)?\s*(?:blind|curtain|shade)\s+([a-zA-Z\s]+?)\s+\d+(?:cm|mm|inch|in)/i', $text, $matches)) {
            $potentialColor = trim($matches[1]);
            // Clean up any remaining product terms
            $potentialColor = preg_replace('/\b(?:blind|curtain|shade|roller|venetian|roman)\b/i', '', $potentialColor);
            $potentialColor = trim($potentialColor);

            if (! empty($potentialColor) && ! self::isProductTerm($potentialColor)) {
                $candidates[] = [
                    'value' => $potentialColor,
                    'score' => 12,
                    'type' => 'positional',
                    'position' => strpos($text, $potentialColor),
                ];
            }
        }

        return $candidates;
    }

    /**
     * Check if a word could linguistically be a color
     */
    private static function couldBeColor(string $word, string $context): bool
    {
        $wordLower = strtolower($word);

        // Skip if it's clearly a product term
        if (self::isProductTerm($word)) {
            return false;
        }

        // Skip if it's a size indicator
        if (in_array($wordLower, self::$sizeIndicators)) {
            return false;
        }

        // Skip dimension-related terms that could be mistaken for colors
        $dimensionTerms = ['drop', 'width', 'height', 'length', 'deep', 'wide', 'tall', 'long', 'short'];
        if (in_array($wordLower, $dimensionTerms)) {
            return false;
        }

        // Skip measurement abbreviations and technical terms
        $technicalTerms = ['mtm', 'mm', 'cm', 'inch', 'ft', 'made', 'measure', 'custom', 'standard', 'bespoke'];
        if (in_array($wordLower, $technicalTerms)) {
            return false;
        }

        // Completely exclude material terms from color extraction
        if (in_array($wordLower, self::$materialTerms)) {
            return false;
        }

        // Skip common non-color words
        $nonColors = ['with', 'without', 'style', 'design', 'finish', 'treatment', 'string', 'cord', 'chain'];
        if (in_array($wordLower, $nonColors)) {
            return false;
        }

        // Special handling: Skip "black" if it appears to be part of "blackout" or "black out"
        if ($wordLower === 'black') {
            if (preg_match('/black\s*out/i', $context)) {
                return false; // "Black" is part of "Black Out"
            }
        }

        // Skip words that appear to be part of dimension patterns (e.g., "x 150cm")
        if (preg_match('/\b'.preg_quote($word, '/').'\s*\d+(?:cm|mm|inch|in)/i', $context)) {
            return false; // Word is followed by a measurement
        }

        // Color words often have certain linguistic patterns
        // Colors are typically nouns or adjectives
        return strlen($word) >= 3 && ctype_alpha($word);
    }

    /**
     * Find dimensional sizes like "60cm", "4ft", "120x180cm", "120x210"
     */
    private static function findDimensionalSizes(string $text): array
    {
        $candidates = [];

        // Pattern 1: Width x Height with units (e.g., "120x180cm", "45cm x 150cm")
        if (preg_match('/(\d+(?:\.\d+)?)(?:\s*(?:cm|mm|in|"|\')?)\s*[x×]\s*(\d+(?:\.\d+)?)\s*(cm|mm|in|"|\')/i', $text, $matches)) {
            $width = $matches[1];
            $height = $matches[2];
            $unit = $matches[3];
            $candidates[] = [
                'value' => "{$width}x{$height}{$unit}",
                'width' => $width.$unit,
                'drop' => $height.$unit,
                'score' => 25, // Highest score for explicit width x height with units
                'type' => 'dimensional_with_units',
            ];
        }

        // Pattern 2: Width x Height without units (e.g., "120x210") - assume cm for blinds
        if (preg_match('/(\d+(?:\.\d+)?)\s*[x×]\s*(\d+(?:\.\d+)?)(?!\s*(?:cm|mm|in|"|\'|ft))/i', $text, $matches)) {
            $width = $matches[1];
            $height = $matches[2];
            // For blinds, assume cm if no unit specified
            $candidates[] = [
                'value' => "{$width}x{$height}",
                'width' => $width.'cm',
                'drop' => $height.'cm',
                'score' => 20, // High score for width x height without units
                'type' => 'dimensional_assumed_cm',
            ];
        }

        // Pattern 3: Single dimensions with units (e.g., "60cm")
        if (preg_match_all('/(\d+(?:\.\d+)?)\s*(cm|mm|m|inch|in|ft|foot|feet|"|\')\b/i', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $value = $match[1];
                $unit = $match[2];
                $fullMatch = $value.$unit;
                $candidates[] = [
                    'value' => $fullMatch,
                    'score' => 15, // Good score for single dimensions
                    'type' => 'single_dimensional',
                ];
            }
        }

        return $candidates;
    }

    /**
     * Find named sizes like "Small", "Large", "XL"
     */
    private static function findNamedSizes(string $text): array
    {
        $candidates = [];
        $namedSizes = ['xs', 'small', 'sm', 'medium', 'md', 'large', 'lg', 'xl', 'xxl', 'mini', 'jumbo'];

        foreach ($namedSizes as $size) {
            if (preg_match('/\b'.preg_quote($size, '/').'\b/i', $text)) {
                $candidates[] = [
                    'value' => strtoupper($size),
                    'score' => 8,
                    'type' => 'named',
                ];
            }
        }

        return $candidates;
    }

    /**
     * Find sizes based on position (often at the end)
     */
    private static function findPositionalSizes(string $text): array
    {
        $candidates = [];

        // Look at the end of the string for size indicators
        if (preg_match('/(\d+(?:\.\d+)?(?:cm|mm|inch|in|ft|\'|")?)$/i', $text, $matches)) {
            $candidates[] = [
                'value' => $matches[1],
                'score' => 15,
                'type' => 'positional',
            ];
        }

        return $candidates;
    }

    /**
     * Select the best color candidate based on scoring
     */
    private static function selectBestColorCandidate(array $candidates, string $originalText): ?string
    {
        if (empty($candidates)) {
            return null;
        }

        // Sort by score (highest first)
        usort($candidates, function ($a, $b) {
            return $b['score'] - $a['score'];
        });

        $bestCandidate = $candidates[0];

        // Clean up and format the color name
        $colorName = trim($bestCandidate['value']);

        return self::formatColorName($colorName);
    }

    /**
     * Select the best size candidate based on scoring
     */
    private static function selectBestSizeCandidate(array $candidates): array
    {
        if (empty($candidates)) {
            return [];
        }

        // Sort by score (highest first)
        usort($candidates, function ($a, $b) {
            return $b['score'] - $a['score'];
        });

        $best = $candidates[0];
        $result = [];

        // If we have explicit width and drop, use those fields
        if (isset($best['width']) && isset($best['drop'])) {
            $result['width'] = self::normalizeDimension($best['width']);
            $result['drop'] = self::normalizeDimension($best['drop']);
            // Don't populate the deprecated size field
        }
        // If we have a single measurement, try to determine if it's width or drop
        elseif (isset($best['value'])) {
            $normalizedValue = self::normalizeSize($best['value']);

            // For single measurements, try to infer if it's width or drop based on context
            // In blind products, larger numbers are often drop, smaller are width
            if (preg_match('/(\d+)/', $normalizedValue, $matches)) {
                $number = intval($matches[1]);

                // Common drop heights for blinds are typically 120cm+, widths are typically < 200cm
                // But this is heuristic - ideally we'd have better context
                if ($number >= 120) {
                    $result['drop'] = $normalizedValue;
                } else {
                    $result['width'] = $normalizedValue;
                }
            } else {
                // Fallback for non-numeric sizes (like "Large")
                $result['width'] = $normalizedValue;
            }
        }

        // Add individual width/drop if specified
        if (isset($best['width']) && ! isset($result['width'])) {
            $result['width'] = self::normalizeDimension($best['width']);
        }
        if (isset($best['drop']) && ! isset($result['drop'])) {
            $result['drop'] = self::normalizeDimension($best['drop']);
        }

        return $result;
    }

    /**
     * Check if a word is a product term that should be ignored
     */
    private static function isProductTerm(string $word): bool
    {
        return in_array(strtolower($word), self::$productTerms);
    }

    /**
     * Format color name for consistency
     */
    private static function formatColorName(string $colorName): string
    {
        // Convert to title case
        return ucwords(strtolower(trim($colorName)));
    }

    /**
     * Analyze text segments for better extraction
     */
    private static function analyzeTextSegments(string $text): array
    {
        return explode(' ', trim($text));
    }

    /**
     * Get confidence score for extraction
     */
    public static function getExtractionConfidence(string $productName): array
    {
        $attributes = self::extractAttributes($productName);

        return [
            'color_confidence' => $attributes['color'] ? 0.9 : 0.0,
            'size_confidence' => $attributes['size'] ? 0.95 : 0.0,
            'overall_confidence' => ($attributes['color'] || $attributes['size']) ? 0.9 : 0.0,
        ];
    }

    /**
     * Test extraction with examples from your CSV data
     */
    public static function testExtraction(): array
    {
        $testCases = [
            'Blackout Roller Blind Aubergine 60cm',
            'Blackout Roller Blind Black 90cm',
            'Blackout Roller Blind Burnt Orange 120cm',
            'Blackout Roller Blind Cappuccino 150cm',
            'Thermal Curtain Red Large',
            'Window Shade Green 120x180cm',
            'Venetian Blind White 4ft',
            'Roman Blind Dark Navy 90cm',
            'Roller Shade Light Grey Medium',
        ];

        $results = [];
        foreach ($testCases as $testCase) {
            $results[$testCase] = self::extractAttributes($testCase);
        }

        return $results;
    }

    /**
     * Debug extraction process for a specific product name
     */
    public static function debugExtraction(string $productName): array
    {
        $originalText = $productName;
        $text = strtolower(trim($productName));

        $debug = [
            'original' => $originalText,
            'cleaned' => self::removeProductTerms($text),
            'color_candidates' => [],
            'size_candidates' => [],
            'final_result' => self::extractAttributes($productName),
        ];

        // Get color candidates
        $cleanedText = self::removeProductTerms($text);
        $debug['color_candidates'] = array_merge(
            self::findCompoundColors($cleanedText),
            self::findStandaloneColors($cleanedText),
            self::findPositionalColors($originalText)
        );

        // Get size candidates
        $debug['size_candidates'] = array_merge(
            self::findDimensionalSizes($text),
            self::findNamedSizes($text),
            self::findPositionalSizes($originalText)
        );

        return $debug;
    }

    /**
     * Normalize dimension strings by removing leading zeros
     */
    private static function normalizeDimension(string $dimension): string
    {
        // Extract number and unit (e.g., "045cm" -> "45cm")
        if (preg_match('/^0*(\d+(?:\.\d+)?)\s*(.*?)$/', $dimension, $matches)) {
            $number = $matches[1];
            $unit = $matches[2];

            // Handle edge case where all digits are zeros
            if ($number === '' || $number === '0') {
                $number = '0';
            }

            return $number.$unit;
        }

        return $dimension; // Return as-is if no match
    }

    /**
     * Normalize size strings (handles both single dimensions and width x height)
     */
    private static function normalizeSize(string $size): string
    {
        // Handle width x height format (e.g., "045x140" -> "45x140")
        if (preg_match('/^0*(\d+(?:\.\d+)?)\s*[x×]\s*0*(\d+(?:\.\d+)?)(.*)$/', $size, $matches)) {
            $width = $matches[1] ?: '0';
            $height = $matches[2] ?: '0';
            $unit = $matches[3];

            return $width.'x'.$height.$unit;
        }

        // Handle single dimensions
        return self::normalizeDimension($size);
    }
}
