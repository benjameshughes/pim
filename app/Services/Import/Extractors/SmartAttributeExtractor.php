<?php

namespace App\Services\Import\Extractors;

class SmartAttributeExtractor
{
    private array $colorDictionary = [
        // Basic colors (high confidence)
        'red' => 10,
        'blue' => 10,
        'green' => 10,
        'yellow' => 10,
        'orange' => 10,
        'purple' => 10,
        'pink' => 10,
        'brown' => 10,
        'black' => 10,
        'white' => 10,
        'grey' => 10,
        'gray' => 10,
        
        // Extended colors (medium-high confidence)
        'navy' => 9,
        'royal' => 8,
        'burgundy' => 9,
        'maroon' => 9,
        'crimson' => 9,
        'scarlet' => 9,
        'turquoise' => 9,
        'teal' => 9,
        'aqua' => 9,
        'lime' => 8,
        'olive' => 8,
        'gold' => 9,
        'silver' => 9,
        'bronze' => 9,
        'copper' => 9,
        'beige' => 9,
        'cream' => 9,
        'ivory' => 9,
        'tan' => 8,
        
        // Special compound colors (keep simple for now)
        'off-white' => 8,
        'off white' => 8,
        
        // Weak color indicators (low confidence)
        'natural' => 4,
        'neutral' => 4,
        'dark' => 3,
        'light' => 3,
        'bright' => 3,
        'deep' => 3,
        'pale' => 3,
    ];

    private array $sizeDictionary = [
        // Standard sizes (high confidence)
        'small' => 10,
        'medium' => 10,
        'large' => 10,
        'extra large' => 10,
        'extra small' => 10,
        
        // Abbreviated sizes (high confidence)
        'xs' => 10,
        's' => 9,  // Lower than others as it might be part of other words
        'm' => 9,
        'l' => 9,
        'xl' => 10,
        'xxl' => 10,
        'xxxl' => 10,
        
        // Descriptive sizes (medium confidence)
        'tiny' => 7,
        'mini' => 7,
        'compact' => 6,
        'big' => 6,
        'huge' => 7,
        'giant' => 7,
        'oversized' => 8,
        
        // Special sizes (medium confidence)
        'one size' => 9,
        'one-size' => 9,
        'freesize' => 8,
        'free size' => 8,
        'universal' => 6,
        
        // Numeric sizes (variable confidence)
        'size 8' => 8,
        'size 10' => 8,
        'size 12' => 8,
        'size 14' => 8,
        'size 16' => 8,
        'size 18' => 8,
    ];

    private array $excludePatterns = [
        // Patterns that should not be extracted as colors
        'blackout', 'bluetooth', 'greenhouse', 'redwood', 'whiteboard',
        'blueprint', 'greenlight', 'redline', 'blackbox', 'whitespace',
    ];

    public function extractColors(string $text): array
    {
        return $this->extractAttributes($text, $this->colorDictionary, 'colors');
    }

    public function extractSizes(string $text): array
    {
        return $this->extractAttributes($text, $this->sizeDictionary, 'sizes');
    }

    public function extractAll(string $text): array
    {
        $mtmExtractor = new MadeToMeasureExtractor();
        $dimensionExtractor = new SmartDimensionExtractor();
        
        return [
            'colors' => $this->extractColors($text),
            'sizes' => $this->extractSizes($text),
            'dimensions' => $dimensionExtractor->extract($text),
            'made_to_measure' => $mtmExtractor->extract($text),
        ];
    }

    private function extractAttributes(string $text, array $dictionary, string $attributeType): array
    {
        $text = strtolower($text);
        $foundAttributes = [];
        $matchedPatterns = [];
        $totalScore = 0;
        $maxPossibleScore = 0;

        // Check for exclusion patterns first
        foreach ($this->excludePatterns as $excludePattern) {
            if (strpos($text, $excludePattern) !== false) {
                // If we find an exclusion pattern, remove matching colors from consideration
                foreach ($dictionary as $attribute => $weight) {
                    if (strpos($excludePattern, $attribute) !== false) {
                        unset($dictionary[$attribute]);
                    }
                }
            }
        }

        // Find all matches with their positions to preserve text order
        $matches = [];
        foreach ($dictionary as $attribute => $weight) {
            $maxPossibleScore += $weight;
            
            if ($this->matchesWordBoundary($text, $attribute)) {
                $position = strpos($text, $attribute);
                $matches[] = [
                    'attribute' => $attribute,
                    'weight' => $weight,
                    'position' => $position,
                ];
                $totalScore += $weight;
            }
        }

        // Sort by position in text to preserve order, but prioritize longer matches
        usort($matches, function($a, $b) {
            // First sort by position
            $positionComparison = $a['position'] <=> $b['position'];
            if ($positionComparison !== 0) {
                return $positionComparison;
            }
            // If same position, prioritize longer strings (compound colors)
            return strlen($b['attribute']) <=> strlen($a['attribute']);
        });

        // Remove overlapping matches (compound colors override individual ones)
        $finalMatches = [];
        $usedPositions = [];
        
        foreach ($matches as $match) {
            $start = $match['position'];
            $end = $start + strlen($match['attribute']);
            $overlaps = false;
            
            // Check if this match overlaps with any already accepted match
            foreach ($usedPositions as $usedRange) {
                if (($start >= $usedRange[0] && $start < $usedRange[1]) ||
                    ($end > $usedRange[0] && $end <= $usedRange[1]) ||
                    ($start <= $usedRange[0] && $end >= $usedRange[1])) {
                    $overlaps = true;
                    break;
                }
            }
            
            if (!$overlaps) {
                $finalMatches[] = $match;
                $usedPositions[] = [$start, $end];
            }
        }

        // Extract final sorted attributes and patterns, recalculate score
        $totalScore = 0;
        foreach ($finalMatches as $match) {
            $foundAttributes[] = $match['attribute'];
            $matchedPatterns[] = [
                'attribute' => $match['attribute'],
                'weight' => $match['weight'],
                'method' => 'word_boundary',
            ];
            $totalScore += $match['weight'];
        }

        // Handle numeric sizes specially (size 16, etc.)
        if ($attributeType === 'sizes') {
            $numericSizes = $this->extractNumericSizes($text);
            foreach ($numericSizes as $size) {
                if (!in_array($size, $foundAttributes)) {
                    $foundAttributes[] = $size;
                    $matchedPatterns[] = [
                        'attribute' => $size,
                        'weight' => 8,
                        'method' => 'numeric_pattern',
                    ];
                    $totalScore += 8;
                }
            }
        }

        // Calculate confidence based on found attributes and their weights
        $confidence = $maxPossibleScore > 0 ? min(1.0, $totalScore / $maxPossibleScore) : 0;
        
        // Boost confidence for multiple high-quality matches
        if (count($foundAttributes) > 1) {
            $confidence = min(1.0, $confidence * 1.2);
        }

        return [
            $attributeType => $foundAttributes,
            'confidence' => round($confidence, 3),
            'extraction_method' => 'dictionary_matching',
            'matched_patterns' => $matchedPatterns,
            'word_boundaries_used' => true,
            'total_score' => $totalScore,
        ];
    }

    private function matchesWordBoundary(string $text, string $attribute): bool
    {
        // Use word boundaries to prevent false matches
        // e.g., prevent "black" from matching in "blackout"
        return preg_match('/\b' . preg_quote($attribute, '/') . '\b/i', $text) === 1;
    }

    private function extractNumericSizes(string $text): array
    {
        $sizes = [];
        
        // Pattern for "size 16", "size 14", etc.
        if (preg_match_all('/size\s+(\d+)/i', $text, $matches)) {
            foreach ($matches[1] as $size) {
                $sizes[] = $size;
            }
        }
        
        // Pattern for standalone numbers that might be sizes (be conservative)
        if (preg_match_all('/\b((?:0?[6-9])|(?:[12]\d)|(?:3[0-6]))\b/', $text, $matches)) {
            foreach ($matches[1] as $possibleSize) {
                // Only include if it looks like a clothing/curtain size
                $sizeNum = intval($possibleSize);
                if ($sizeNum >= 6 && $sizeNum <= 36) {
                    // Check context - should be near size-related words
                    $context = $this->getWordContext($text, $possibleSize);
                    if ($this->isLikelySizeContext($context)) {
                        $sizes[] = $possibleSize;
                    }
                }
            }
        }
        
        return array_unique($sizes);
    }

    private function getWordContext(string $text, string $target): string
    {
        $position = strpos($text, $target);
        if ($position === false) return '';
        
        $start = max(0, $position - 20);
        $length = min(40, strlen($text) - $start);
        
        return substr($text, $start, $length);
    }

    private function isLikelySizeContext(string $context): bool
    {
        $sizeContextWords = [
            'size', 'curtain', 'blind', 'ring', 'width', 'drop', 'length',
            'available', 'fits', 'suitable', 'diameter'
        ];
        
        foreach ($sizeContextWords as $contextWord) {
            if (strpos($context, $contextWord) !== false) {
                return true;
            }
        }
        
        return false;
    }
}