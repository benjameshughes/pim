<?php

namespace App\Services\Import\Analyzers;

class SkuPatternAnalyzer
{
    private array $sizePatterns = ['s', 'm', 'l', 'xl', 'xxl', 'small', 'medium', 'large', 'extra large'];
    private array $colorPatterns = ['red', 'blue', 'green', 'black', 'white', 'yellow', 'purple', 'orange', 'pink', 'brown'];

    public function analyze(array $skus): array
    {
        if (empty($skus)) {
            return $this->getEmptyResult();
        }

        $analysisResults = [
            'hierarchical' => $this->analyzeHierarchicalPattern($skus),
            'sequential' => $this->analyzeSequentialPattern($skus),
            'attribute_based' => $this->analyzeAttributeBasedPattern($skus),
            'numeric_hierarchical' => $this->analyzeNumericHierarchicalPattern($skus),
        ];

        // Find the best pattern
        $bestPattern = $this->determineBestPattern($analysisResults);
        
        if (!$bestPattern) {
            return $this->getNoPatternResult();
        }

        return $this->buildFinalResult($bestPattern, $analysisResults[$bestPattern['type']], $skus);
    }

    private function analyzeHierarchicalPattern(array $skus): array
    {
        $groups = [];
        $confidence = 0;
        $separators = ['-', '_', '.'];
        
        foreach ($separators as $separator) {
            $currentGroups = [];
            $validPattern = true;
            
            foreach ($skus as $sku) {
                $parts = explode($separator, $sku);
                if (count($parts) >= 2) {
                    $groupKey = $parts[0];
                    $currentGroups[$groupKey][] = $sku;
                } else {
                    $validPattern = false;
                    break;
                }
            }
            
            if ($validPattern && count($currentGroups) > 1) {
                $groups = $currentGroups;
                $confidence = $this->calculatePatternConfidence($groups, count($skus));
                break;
            }
        }

        return [
            'has_pattern' => !empty($groups),
            'confidence' => $confidence,
            'suggested_groups' => $groups,
            'pattern_complexity' => $this->determineComplexity($groups),
        ];
    }

    private function analyzeSequentialPattern(array $skus): array
    {
        // Look for common prefix with sequential numbers
        $commonPrefix = $this->findCommonPrefix($skus);
        
        if (strlen($commonPrefix) < 2) {
            return ['has_pattern' => false, 'confidence' => 0];
        }

        $suffixes = [];
        $isNumericSequence = true;
        
        foreach ($skus as $sku) {
            if (strpos($sku, $commonPrefix) === 0) {
                $suffix = substr($sku, strlen($commonPrefix));
                $suffixes[] = $suffix;
                
                if (!is_numeric($suffix)) {
                    $isNumericSequence = false;
                }
            }
        }

        $confidence = 0;
        if ($isNumericSequence && count($suffixes) > 1) {
            sort($suffixes, SORT_NUMERIC);
            $isSequential = $this->isSequentialArray($suffixes);
            $confidence = $isSequential ? 0.8 : 0.4;
        }

        return [
            'has_pattern' => $confidence > 0.3,
            'confidence' => $confidence,
            'base_pattern' => $commonPrefix,
            'sequence_type' => $isNumericSequence ? 'numeric' : 'text',
            'suffixes' => $suffixes,
        ];
    }

    private function analyzeAttributeBasedPattern(array $skus): array
    {
        $attributesDetected = [];
        $confidence = 0;
        $groups = [];

        // Check for size patterns
        foreach ($this->sizePatterns as $size) {
            $matchingSkus = array_filter($skus, fn($sku) => 
                preg_match('/\b' . preg_quote($size, '/') . '\b/i', $sku)
            );
            
            if (count($matchingSkus) >= 2) {
                $attributesDetected[] = 'size';
                $confidence += 0.3;
                break;
            }
        }

        // Check for color patterns
        foreach ($this->colorPatterns as $color) {
            $matchingSkus = array_filter($skus, fn($sku) => 
                preg_match('/\b' . preg_quote($color, '/') . '\b/i', $sku)
            );
            
            if (count($matchingSkus) >= 2) {
                $attributesDetected[] = 'color';
                $confidence += 0.3;
                break;
            }
        }

        // Group by base pattern (removing attributes)
        if (!empty($attributesDetected)) {
            $groups = $this->groupByBasePattern($skus);
        }

        return [
            'has_pattern' => !empty($attributesDetected),
            'confidence' => min(1.0, $confidence),
            'attributes_detected' => array_unique($attributesDetected),
            'suggested_groups' => $groups,
        ];
    }

    private function analyzeNumericHierarchicalPattern(array $skus): array
    {
        $groups = [];
        $confidence = 0;
        
        // Look for digit-digit pattern (001-002, 123-456, etc.)
        foreach ($skus as $sku) {
            if (preg_match('/^(\d{3})-(\d{3})$/', $sku, $matches)) {
                $parentSku = $matches[1];
                $groups[$parentSku][] = $sku;
            }
        }

        if (!empty($groups)) {
            $confidence = $this->calculatePatternConfidence($groups, count($skus));
        }

        return [
            'has_pattern' => !empty($groups),
            'confidence' => $confidence,
            'suggested_groups' => $groups,
            'parent_extraction_method' => 'first_digits',
        ];
    }

    private function determineBestPattern(array $analysisResults): ?array
    {
        $maxConfidence = 0;
        $bestType = null;

        foreach ($analysisResults as $type => $result) {
            if ($result['has_pattern'] && $result['confidence'] > $maxConfidence) {
                $maxConfidence = $result['confidence'];
                $bestType = $type;
            }
        }

        return $bestType ? ['type' => $bestType, 'confidence' => $maxConfidence] : null;
    }

    private function buildFinalResult(array $bestPattern, array $patternData, array $skus): array
    {
        $result = [
            'has_pattern' => true,
            'pattern_type' => $bestPattern['type'],
            'confidence' => $bestPattern['confidence'],
            'suggested_groups' => $patternData['suggested_groups'] ?? [],
            'pattern_complexity' => $this->determineComplexity($patternData['suggested_groups'] ?? []),
        ];

        // Add type-specific data
        switch ($bestPattern['type']) {
            case 'sequential':
                $result['base_pattern'] = $patternData['base_pattern'];
                $result['sequence_type'] = $patternData['sequence_type'];
                break;
                
            case 'attribute_based':
                $result['attributes_detected'] = $patternData['attributes_detected'];
                break;
                
            case 'numeric_hierarchical':
                $result['parent_extraction_method'] = $patternData['parent_extraction_method'];
                break;
        }

        // Add suggested parent name
        $result['suggested_parent_name'] = $this->suggestParentName($skus, $bestPattern['type']);
        $result['name_extraction_method'] = 'common_prefix_words';

        // Check for multiple patterns
        $multiplePatterns = array_sum(array_column($patternData, 'has_pattern')) > 1;
        if ($multiplePatterns) {
            $result['multiple_patterns'] = true;
            $result['pattern_complexity'] = 'mixed';
        }

        return $result;
    }

    private function suggestParentName(array $skus, string $patternType): string
    {
        $commonPrefix = $this->findCommonPrefix($skus);
        
        // Convert common prefix to readable name
        $words = preg_split('/[-_.]/', $commonPrefix);
        $cleanWords = array_filter(array_map('trim', $words));
        
        if (!empty($cleanWords)) {
            return ucwords(strtolower(implode(' ', $cleanWords)));
        }

        // Fallback: extract words from first SKU
        $firstSku = $skus[0] ?? '';
        $words = preg_split('/[-_.0-9]/', $firstSku);
        $cleanWords = array_filter(array_map('trim', $words), fn($word) => strlen($word) > 2);
        
        return !empty($cleanWords) ? ucwords(strtolower(implode(' ', array_slice($cleanWords, 0, 2)))) : 'Product';
    }

    private function findCommonPrefix(array $strings): string
    {
        if (empty($strings)) return '';
        
        $prefix = $strings[0];
        foreach ($strings as $string) {
            while (strpos($string, $prefix) !== 0) {
                $prefix = substr($prefix, 0, -1);
                if ($prefix === '') break;
            }
        }
        
        return $prefix;
    }

    private function calculatePatternConfidence(array $groups, int $totalSkus): float
    {
        if (empty($groups) || $totalSkus === 0) return 0;
        
        $groupedSkus = array_sum(array_map('count', $groups));
        $coverageRatio = $groupedSkus / $totalSkus;
        $groupVariety = count($groups) / $totalSkus;
        
        return min(1.0, $coverageRatio * 0.7 + $groupVariety * 0.3);
    }

    private function determineComplexity(array $groups): string
    {
        if (empty($groups)) return 'none';
        if (count($groups) === 1) return 'simple';
        if (count($groups) <= 3) return 'moderate';
        return 'complex';
    }

    private function isSequentialArray(array $numbers): bool
    {
        for ($i = 1; $i < count($numbers); $i++) {
            if ($numbers[$i] - $numbers[$i-1] !== 1) {
                return false;
            }
        }
        return true;
    }

    private function groupByBasePattern(array $skus): array
    {
        $groups = [];
        
        foreach ($skus as $sku) {
            // Remove size and color attributes to find base pattern
            $baseSku = $sku;
            
            // Remove size patterns
            foreach ($this->sizePatterns as $size) {
                $baseSku = preg_replace('/[-_.]?' . preg_quote($size, '/') . '[-_.]?/i', '', $baseSku);
            }
            
            // Remove color patterns
            foreach ($this->colorPatterns as $color) {
                $baseSku = preg_replace('/[-_.]?' . preg_quote($color, '/') . '[-_.]?/i', '', $baseSku);
            }
            
            // Clean up the base SKU
            $baseSku = trim(preg_replace('/[-_.]+/', '-', $baseSku), '-_.');
            
            if (!empty($baseSku)) {
                $groups[$baseSku][] = $sku;
            }
        }
        
        return $groups;
    }

    private function getEmptyResult(): array
    {
        return [
            'has_pattern' => false,
            'confidence' => 0,
            'pattern_type' => null,
            'suggested_groups' => [],
            'pattern_complexity' => 'none',
        ];
    }

    private function getNoPatternResult(): array
    {
        return [
            'has_pattern' => false,
            'confidence' => 0,
            'pattern_type' => null,
            'suggested_groups' => [],
            'pattern_complexity' => 'none',
            'multiple_patterns' => false,
        ];
    }
}