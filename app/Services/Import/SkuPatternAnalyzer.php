<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\Log;

class SkuPatternAnalyzer
{
    private ?array $patterns = null;

    private function getPatterns(): array
    {
        if ($this->patterns === null) {
            $this->patterns = [
                'numeric_parent_variant' => [
                    'regex' => '/^(\d{3})-(\d{3})$/',
                    'description' => 'Three-digit parent and variant (001-001)',
                    'confidence_base' => 0.95,
                    'extract_parent' => function($matches) { return $matches[1]; },
                    'extract_variant' => function($matches) { return $matches[2]; },
                ],
                'structured_attributes' => [
                    'regex' => '/^([A-Z]{2,4})-(\d{2,4})-([A-Z0-9]{2,4})-([A-Z]{2,4})$/',
                    'description' => 'Category-Size-Color-Material (BLI-120-WHT-PVC)',
                    'confidence_base' => 0.90,
                    'extract_parent' => function($matches) { return $matches[1] . '-' . $matches[2]; },
                    'extract_variant' => function($matches) { return $matches[3] . '-' . $matches[4]; },
                ],
                'hierarchical' => [
                    'regex' => '/^([A-Z]{2,4})-([A-Z]{2,4})-([A-Z0-9]{2,6})$/',
                    'description' => 'Category-Subcategory-Variant (BLI-ROL-001)',
                    'confidence_base' => 0.85,
                    'extract_parent' => function($matches) { return $matches[1] . '-' . $matches[2]; },
                    'extract_variant' => function($matches) { return $matches[3]; },
                ],
                'size_color_pattern' => [
                    'regex' => '/^([A-Z]+)-?(\d+)x?(\d+)?-?([A-Z]+)$/',
                    'description' => 'Product-Dimensions-Color (BLI120x160WHT)',
                    'confidence_base' => 0.80,
                    'extract_parent' => function($matches) { return $matches[1]; },
                    'extract_variant' => function($matches) { return ($matches[2] ?? '') . 'x' . ($matches[3] ?? '') . '-' . ($matches[4] ?? ''); },
                ],
                'simple_incremental' => [
                    'regex' => '/^([A-Z]+)(\d+)$/',
                    'description' => 'Simple product code with number (PROD001)',
                    'confidence_base' => 0.60,
                    'extract_parent' => function($matches) { return $matches[1]; },
                    'extract_variant' => function($matches) { return $matches[2]; },
                ],
            ];
        }
        return $this->patterns;
    }

    public function analyzePatterns(array $skus): array
    {
        if (empty($skus)) {
            return $this->createNoPatternResult();
        }

        Log::info('Analyzing SKU patterns', [
            'total_skus' => count($skus),
            'sample_skus' => array_slice($skus, 0, 5),
        ]);

        $patternScores = [];
        $detectedPatterns = [];
        
        // Analyze each SKU against all patterns
        foreach ($skus as $sku) {
            $sku = strtoupper(trim($sku));
            
            foreach ($this->getPatterns() as $patternName => $patternConfig) {
                if (preg_match($patternConfig['regex'], $sku, $matches)) {
                    $patternScores[$patternName] = ($patternScores[$patternName] ?? 0) + 1;
                    
                    if (!isset($detectedPatterns[$patternName])) {
                        $detectedPatterns[$patternName] = [];
                    }
                    
                    $detectedPatterns[$patternName][] = [
                        'sku' => $sku,
                        'parent' => $patternConfig['extract_parent']($matches),
                        'variant' => $patternConfig['extract_variant']($matches),
                        'matches' => $matches,
                    ];
                }
            }
        }

        if (empty($patternScores)) {
            return $this->createNoPatternResult();
        }

        // Find dominant pattern
        $totalSkus = count($skus);
        $dominantPattern = array_keys($patternScores, max($patternScores))[0];
        $dominantCount = $patternScores[$dominantPattern];
        $baseConfidence = $dominantCount / $totalSkus;
        
        // Apply pattern-specific confidence adjustments
        $patternConfig = $this->getPatterns()[$dominantPattern];
        $adjustedConfidence = min(1.0, $baseConfidence * $patternConfig['confidence_base']);

        // Analyze parent-variant relationships
        $relationships = $this->analyzeParentVariantRelationships(
            $detectedPatterns[$dominantPattern] ?? []
        );

        $result = [
            'dominant_pattern' => $dominantPattern,
            'pattern_description' => $patternConfig['description'],
            'confidence' => round($adjustedConfidence, 3),
            'coverage' => [
                'matched_skus' => $dominantCount,
                'total_skus' => $totalSkus,
                'percentage' => round(($dominantCount / $totalSkus) * 100, 1),
            ],
            'all_patterns' => $this->formatPatternScores($patternScores, $totalSkus),
            'parent_variant_analysis' => $relationships,
            'recommendations' => $this->generateRecommendations($dominantPattern, $adjustedConfidence, $relationships),
        ];

        Log::info('SKU pattern analysis completed', [
            'dominant_pattern' => $dominantPattern,
            'confidence' => $adjustedConfidence,
            'coverage_percentage' => $result['coverage']['percentage'],
        ]);

        return $result;
    }

    private function createNoPatternResult(): array
    {
        return [
            'dominant_pattern' => 'none',
            'pattern_description' => 'No consistent pattern detected',
            'confidence' => 0,
            'coverage' => [
                'matched_skus' => 0,
                'total_skus' => 0,
                'percentage' => 0,
            ],
            'all_patterns' => [],
            'parent_variant_analysis' => [
                'total_groups' => 0,
                'average_variants_per_group' => 0,
                'single_variant_groups' => 0,
            ],
            'recommendations' => [
                [
                    'type' => 'warning',
                    'message' => 'No consistent SKU pattern detected. Consider using name-based grouping instead.',
                    'action' => 'Switch to name-based parent-child relationships',
                ],
            ],
        ];
    }

    private function formatPatternScores(array $scores, int $total): array
    {
        $formatted = [];
        
        foreach ($scores as $pattern => $count) {
            $formatted[] = [
                'pattern' => $pattern,
                'description' => $this->getPatterns()[$pattern]['description'],
                'matched_skus' => $count,
                'percentage' => round(($count / $total) * 100, 1),
            ];
        }

        // Sort by match count descending
        usort($formatted, fn($a, $b) => $b['matched_skus'] <=> $a['matched_skus']);

        return $formatted;
    }

    private function analyzeParentVariantRelationships(array $detectedPatterns): array
    {
        if (empty($detectedPatterns)) {
            return [
                'total_groups' => 0,
                'average_variants_per_group' => 0,
                'single_variant_groups' => 0,
                'sample_groups' => [],
            ];
        }

        // Group by parent
        $parentGroups = [];
        foreach ($detectedPatterns as $pattern) {
            $parentKey = $pattern['parent'];
            if (!isset($parentGroups[$parentKey])) {
                $parentGroups[$parentKey] = [];
            }
            $parentGroups[$parentKey][] = $pattern;
        }

        $totalGroups = count($parentGroups);
        $singleVariantGroups = 0;
        $totalVariants = 0;
        $sampleGroups = [];

        foreach ($parentGroups as $parentKey => $variants) {
            $variantCount = count($variants);
            $totalVariants += $variantCount;
            
            if ($variantCount === 1) {
                $singleVariantGroups++;
            }

            // Collect sample groups for analysis
            if (count($sampleGroups) < 5) {
                $sampleGroups[] = [
                    'parent' => $parentKey,
                    'variant_count' => $variantCount,
                    'variants' => array_column($variants, 'variant'),
                    'sample_skus' => array_slice(array_column($variants, 'sku'), 0, 3),
                ];
            }
        }

        return [
            'total_groups' => $totalGroups,
            'average_variants_per_group' => $totalGroups > 0 ? round($totalVariants / $totalGroups, 1) : 0,
            'single_variant_groups' => $singleVariantGroups,
            'multi_variant_groups' => $totalGroups - $singleVariantGroups,
            'sample_groups' => $sampleGroups,
        ];
    }

    private function generateRecommendations(string $dominantPattern, float $confidence, array $relationships): array
    {
        $recommendations = [];

        // Confidence-based recommendations
        if ($confidence < 0.5) {
            $recommendations[] = [
                'type' => 'error',
                'message' => 'Low pattern confidence. SKU-based grouping may not work reliably.',
                'action' => 'Consider using name-based grouping or standardizing your SKU format.',
            ];
        } elseif ($confidence < 0.7) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'Moderate pattern confidence. Some SKUs may not group correctly.',
                'action' => 'Review ungrouped products after import and consider SKU standardization.',
            ];
        } else {
            $recommendations[] = [
                'type' => 'success',
                'message' => 'High pattern confidence. SKU-based grouping should work well.',
                'action' => 'Proceed with SKU-based parent-child relationships.',
            ];
        }

        // Relationship-based recommendations
        $singleVariantPercentage = $relationships['total_groups'] > 0 
            ? ($relationships['single_variant_groups'] / $relationships['total_groups']) * 100 
            : 0;

        if ($singleVariantPercentage > 50) {
            $recommendations[] = [
                'type' => 'info',
                'message' => 'Many products have only one variant. Consider if parent-child grouping is necessary.',
                'action' => 'Review if these should be standalone products instead of variants.',
            ];
        }

        if ($relationships['average_variants_per_group'] > 10) {
            $recommendations[] = [
                'type' => 'info',
                'message' => 'Some product groups have many variants. This might indicate over-grouping.',
                'action' => 'Review large groups to ensure they represent genuine product families.',
            ];
        }

        // Pattern-specific recommendations
        switch ($dominantPattern) {
            case 'numeric_parent_variant':
                $recommendations[] = [
                    'type' => 'success',
                    'message' => 'Excellent SKU structure detected. This is the most reliable pattern for grouping.',
                    'action' => 'Enable SKU-based grouping with high confidence.',
                ];
                break;
                
            case 'structured_attributes':
                $recommendations[] = [
                    'type' => 'info',
                    'message' => 'Attribute-based SKU structure detected. Good for filtering and organization.',
                    'action' => 'Consider extracting attributes from SKUs for enhanced functionality.',
                ];
                break;
                
            case 'simple_incremental':
                $recommendations[] = [
                    'type' => 'warning',
                    'message' => 'Simple incremental pattern may lead to unexpected groupings.',
                    'action' => 'Monitor grouping results and consider name-based grouping as backup.',
                ];
                break;
        }

        return $recommendations;
    }

    public function extractParentSku(string $sku, ?string $pattern = null): ?string
    {
        if (!$pattern) {
            // Try to detect pattern first
            $analysis = $this->analyzePatterns([$sku]);
            $pattern = $analysis['dominant_pattern'];
        }

        if ($pattern === 'none' || !isset($this->getPatterns()[$pattern])) {
            return null;
        }

        $patternConfig = $this->getPatterns()[$pattern];
        
        if (preg_match($patternConfig['regex'], strtoupper(trim($sku)), $matches)) {
            return $patternConfig['extract_parent']($matches);
        }

        return null;
    }

    public function generateSkuRecommendations(array $skus): array
    {
        $recommendations = [];

        // Length consistency check
        $lengths = array_map('strlen', $skus);
        $avgLength = array_sum($lengths) / count($lengths);
        $lengthVariance = array_sum(array_map(fn($l) => ($l - $avgLength) ** 2, $lengths)) / count($lengths);

        if ($lengthVariance > 4) {
            $recommendations[] = [
                'type' => 'suggestion',
                'category' => 'standardization',
                'message' => 'SKU lengths vary significantly. Consistent length improves organization.',
                'current_state' => "Length range: " . min($lengths) . "-" . max($lengths) . " characters",
                'recommended_action' => 'Standardize SKU length (e.g., always 7 characters: ABC-001)',
            ];
        }

        // Character consistency check
        $hasNumbers = array_filter($skus, fn($sku) => preg_match('/\d/', $sku));
        $hasLetters = array_filter($skus, fn($sku) => preg_match('/[A-Za-z]/', $sku));
        $hasSpecialChars = array_filter($skus, fn($sku) => preg_match('/[^A-Za-z0-9]/', $sku));

        if (count($hasNumbers) / count($skus) < 0.8) {
            $recommendations[] = [
                'type' => 'suggestion',
                'category' => 'format',
                'message' => 'Most SKUs lack numbers. Sequential numbering improves organization.',
                'recommended_action' => 'Include sequential numbers in SKUs (e.g., PROD001, PROD002)',
            ];
        }

        return $recommendations;
    }
}