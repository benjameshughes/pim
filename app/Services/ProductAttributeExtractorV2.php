<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Multi-Pass Product Attribute Extractor
 * 
 * Uses a sophisticated 6-pass algorithm to extract attributes from complex product names:
 * Pass 1: Text normalization and cleaning
 * Pass 2: Tokenization and structural analysis
 * Pass 3: Dimension extraction with robust pattern matching
 * Pass 4: Color extraction with context awareness
 * Pass 5: Parent name generation
 * Pass 6: Validation and scoring
 */
class ProductAttributeExtractorV2
{
    private static array $dimensionPatterns = [];
    private static array $colorDictionary = [];
    private static array $materialTerms = [];
    private static array $stopWords = [];
    private static bool $initialized = false;

    /**
     * Extract attributes using multi-pass algorithm
     */
    public static function extractAttributes(string $productName): array
    {
        self::initialize();
        
        $context = [
            'original' => $productName,
            'confidence' => 0.0,
            'debug' => []
        ];

        // Pass 1: Text Normalization & Cleaning
        $context = self::pass1_normalize($context);
        
        // Pass 2: Tokenization & Structure Analysis
        $context = self::pass2_tokenize($context);
        
        // Pass 3: Dimension Extraction
        $context = self::pass3_extractDimensions($context);
        
        // Pass 4: Color Extraction
        $context = self::pass4_extractColors($context);
        
        // Pass 5: Parent Name Generation
        $context = self::pass5_generateParentName($context);
        
        // Pass 6: Validation & Scoring
        $context = self::pass6_validate($context);

        return [
            'color' => $context['extracted']['color'] ?? null,
            'width' => $context['extracted']['width'] ?? null,
            'drop' => $context['extracted']['drop'] ?? null,
            'parent_name' => $context['extracted']['parent_name'] ?? null,
            'confidence' => $context['confidence'],
            'debug' => $context['debug'] ?? []
        ];
    }

    /**
     * Initialize static data structures
     */
    private static function initialize(): void
    {
        if (self::$initialized) return;

        // Dimension patterns - ordered by specificity (most specific first)
        self::$dimensionPatterns = [
            // Pattern 1: Width × Height with units (45cm × 150cm, 45cm x 150cm)
            [
                'pattern' => "/(\d+(?:\.\d+)?)\s*cm\s*[×xX]\s*(\d+(?:\.\d+)?)\s*cm/iu",
                'type' => 'width_x_height_with_units',
                'score' => 100
            ],
            
            // Pattern 2: Width × Height without units (45 × 150, 45x150)  
            [
                'pattern' => "/(\d+(?:\.\d+)?)\s*[×xX]\s*(\d+(?:\.\d+)?)/iu",
                'type' => 'width_x_height_no_units',
                'score' => 90
            ],
            
            // Pattern 3: Dimension words followed by measurements (Width 45, Drop 150)
            [
                'pattern' => "/\b(width|drop|height|depth)\s*:?\s*(\d+(?:\.\d+)?)\s*(cm|mm|in|ft|\"|')?/iu",
                'type' => 'labeled_dimension',
                'score' => 95
            ],
            
            // Pattern 4: Single dimension with explicit unit (150cm, 45mm) - lower priority
            [
                'pattern' => "/(\d+(?:\.\d+)?)\s*(cm|mm|in|ft|\"|')\b/iu",
                'type' => 'single_dimension_with_unit',
                'score' => 70
            ]
        ];

        // Comprehensive color dictionary with scoring
        self::$colorDictionary = [
            // Primary colors (high confidence)
            'white' => 100, 'black' => 100, 'red' => 100, 'blue' => 100, 'green' => 100,
            'yellow' => 100, 'brown' => 100, 'grey' => 100, 'gray' => 100, 'silver' => 100,
            'gold' => 100, 'purple' => 100, 'pink' => 100, 'orange' => 100,
            
            // Secondary colors (medium confidence)
            'beige' => 80, 'cream' => 80, 'ivory' => 80, 'navy' => 80, 'teal' => 80,
            'maroon' => 80, 'olive' => 80, 'lime' => 80, 'cyan' => 80, 'magenta' => 80,
            
            // Compound colors (medium confidence)
            'dark blue' => 85, 'light blue' => 85, 'dark green' => 85, 'light green' => 85,
            'dark grey' => 85, 'light grey' => 85, 'dark gray' => 85, 'light gray' => 85,
            'burnt orange' => 85, 'royal blue' => 85, 'forest green' => 85,
            
            // Specific product colors (lower confidence - verify context)
            'aubergine' => 70, 'cappuccino' => 70, 'burgundy' => 70, 'charcoal' => 70,
            'bronze' => 70, 'copper' => 70, 'pearl' => 70, 'champagne' => 70
        ];

        // Material terms that should NOT be extracted as colors
        self::$materialTerms = [
            'aluminium', 'aluminum', 'wood', 'wooden', 'metal', 'plastic', 'vinyl', 'pvc',
            'steel', 'bamboo', 'fabric', 'cotton', 'polyester', 'linen', 'silk', 'leather',
            'glass', 'acrylic', 'faux', 'grain', 'natural', 'composite', 'synthetic',
            'artificial', 'imitation', 'stone', 'marble', 'granite', 'ceramic', 'porcelain'
        ];

        // Stop words for parent name generation
        self::$stopWords = [
            'with', 'without', 'and', 'or', 'the', 'a', 'an', 'in', 'on', 'at', 'to',
            'for', 'of', 'by', 'from', 'up', 'about', 'into', 'over', 'after',
            'mtm', 'made', 'measure', 'custom', 'standard', 'size', 'drop', 'width'
        ];

        self::$initialized = true;
    }

    /**
     * Pass 1: Text Normalization & Cleaning
     */
    private static function pass1_normalize(array $context): array
    {
        $text = $context['original'];
        $context['debug']['pass1_input'] = $text;
        
        // Unicode normalization
        $text = \Normalizer::normalize($text, \Normalizer::FORM_C);
        
        // Standardize multiplication symbols and spaces (only replace x/X when used as dimension separators)
        // Look for x/X surrounded by numbers/spaces to avoid replacing within words like "Faux"
        $text = preg_replace('/(\d+)\s*[xX]\s*(\d+)/', '$1 × $2', $text);
        
        // Standardize quotes and measurements
        $text = str_replace(['"', "'", '"', '"'], ['"', "'", "'", "'"], $text);
        
        // Clean up multiple spaces
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Trim and store
        $context['normalized'] = trim($text);
        $context['debug']['pass1_output'] = $context['normalized'];
        
        Log::debug('Pass 1 Complete', [
            'input' => $context['original'],
            'output' => $context['normalized']
        ]);
        
        return $context;
    }

    /**
     * Pass 2: Tokenization & Structure Analysis
     */
    private static function pass2_tokenize(array $context): array
    {
        $text = $context['normalized'];
        
        // Tokenize by spaces while preserving special patterns
        $tokens = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        // Build context map
        $tokenContext = [];
        foreach ($tokens as $index => $token) {
            $tokenContext[$index] = [
                'token' => $token,
                'is_numeric' => is_numeric($token),
                'has_dimension' => preg_match('/\d+\s*(cm|mm|in|ft|"|\')/i', $token),
                'has_multiplication' => strpos($token, '×') !== false,
                'is_potential_color' => self::isPotentialColor($token),
                'position' => $index,
                'total_tokens' => count($tokens)
            ];
        }
        
        $context['tokens'] = $tokens;
        $context['token_context'] = $tokenContext;
        $context['debug']['pass2_tokens'] = count($tokens);
        
        Log::debug('Pass 2 Complete', [
            'tokens' => count($tokens),
            'has_dimensions' => array_sum(array_column($tokenContext, 'has_dimension')),
            'potential_colors' => array_sum(array_column($tokenContext, 'is_potential_color'))
        ]);
        
        return $context;
    }

    /**
     * Pass 3: Dimension Extraction with Robust Pattern Matching
     */
    private static function pass3_extractDimensions(array $context): array
    {
        $text = $context['normalized'];
        $candidates = [];
        
        // Apply each dimension pattern
        foreach (self::$dimensionPatterns as $patternInfo) {
            if (preg_match($patternInfo['pattern'], $text, $matches)) {
                $extracted = self::extractDimensionFromMatches($matches, $patternInfo['type']);
                if (!empty($extracted)) {
                    $candidates[] = [
                        'type' => $patternInfo['type'],
                        'score' => $patternInfo['score'],
                        'extracted' => $extracted,
                        'match' => $matches[0],
                        'full_match' => $matches
                    ];
                    
                    // Debug logging
                    Log::debug('Dimension pattern matched', [
                        'pattern_type' => $patternInfo['type'],
                        'match' => $matches[0],
                        'extracted' => $extracted,
                        'score' => $patternInfo['score']
                    ]);
                }
            }
        }
        
        // Select best candidate (highest score)
        $bestCandidate = null;
        $highestScore = 0;
        
        foreach ($candidates as $candidate) {
            if ($candidate['score'] > $highestScore) {
                $highestScore = $candidate['score'];
                $bestCandidate = $candidate;
            }
        }
        
        if ($bestCandidate) {
            $context['extracted'] = array_merge(
                $context['extracted'] ?? [],
                $bestCandidate['extracted']
            );
            $context['confidence'] += 0.3; // Boost confidence for successful dimension extraction
        }
        
        $context['debug']['pass3_candidates'] = count($candidates);
        $context['debug']['pass3_best_score'] = $highestScore;
        
        Log::debug('Pass 3 Complete', [
            'candidates' => count($candidates),
            'best_score' => $highestScore,
            'extracted' => $bestCandidate['extracted'] ?? null
        ]);
        
        return $context;
    }

    /**
     * Extract dimensions from regex matches based on pattern type
     */
    private static function extractDimensionFromMatches(array $matches, string $type): array
    {
        switch ($type) {
            case 'width_x_height_with_units':
                return [
                    'width' => self::normalizeNumber($matches[1]) . 'cm',
                    'drop' => self::normalizeNumber($matches[2]) . 'cm'
                ];
                
            case 'width_x_height_no_units':
                return [
                    'width' => self::normalizeNumber($matches[1]) . 'cm',
                    'drop' => self::normalizeNumber($matches[2]) . 'cm'
                ];
                
            case 'single_dimension_with_unit':
                $value = self::normalizeNumber($matches[1]) . $matches[2];
                $number = floatval($matches[1]);
                
                // Heuristic: larger numbers (120+) are typically drop, smaller are width
                if ($number >= 120) {
                    return ['drop' => $value];
                } else {
                    return ['width' => $value];
                }
                
            case 'labeled_dimension':
                $dimType = strtolower($matches[1]);
                $value = self::normalizeNumber($matches[2]) . ($matches[3] ?? 'cm');
                
                if (in_array($dimType, ['width'])) {
                    return ['width' => $value];
                } elseif (in_array($dimType, ['drop', 'height', 'depth'])) {
                    return ['drop' => $value];
                }
                return [];
                
            default:
                return [];
        }
    }

    /**
     * Pass 4: Color Extraction with Context Awareness
     */
    private static function pass4_extractColors(array $context): array
    {
        $tokens = $context['tokens'];
        $tokenContext = $context['token_context'];
        $candidates = [];
        
        // Multi-word color detection (e.g., "Dark Blue", "Dark Grey")
        for ($i = 0; $i < count($tokens) - 1; $i++) {
            $twoWordColor = strtolower($tokens[$i] . ' ' . $tokens[$i + 1]);
            if (isset(self::$colorDictionary[$twoWordColor])) {
                $candidates[] = [
                    'color' => ucwords($twoWordColor),
                    'score' => self::$colorDictionary[$twoWordColor] + 25, // Higher bonus for compound colors
                    'position' => $i,
                    'tokens_used' => 2,
                    'type' => 'compound'
                ];
            }
        }
        
        // Single word color detection
        foreach ($tokens as $index => $token) {
            $tokenLower = strtolower($token);
            
            // Skip if it's a material term
            if (in_array($tokenLower, self::$materialTerms)) {
                continue;
            }
            
            // Skip if this token is part of a compound color we already found
            $isPartOfCompound = false;
            foreach ($candidates as $candidate) {
                if ($candidate['type'] === 'compound') {
                    $compoundWords = explode(' ', strtolower($candidate['color']));
                    if (in_array($tokenLower, $compoundWords)) {
                        $isPartOfCompound = true;
                        break;
                    }
                }
            }
            if ($isPartOfCompound) {
                continue;
            }
            
            // Check if it's in our color dictionary
            if (isset(self::$colorDictionary[$tokenLower])) {
                $score = self::$colorDictionary[$tokenLower];
                
                // Position-based scoring adjustments
                $totalTokens = count($tokens);
                if ($index <= 1) {
                    $score += 5; // Bonus for early position
                }
                if ($index >= $totalTokens - 2) {
                    $score += 8; // Bonus for late position (often actual color)
                }
                
                // Context adjustments
                if ($tokenContext[$index]['has_dimension']) {
                    $score -= 20; // Heavy penalty if token contains dimensions
                }
                
                $candidates[] = [
                    'color' => ucwords($token),
                    'score' => $score,
                    'position' => $index,
                    'tokens_used' => 1,
                    'type' => 'single'
                ];
            }
        }
        
        // Select best color candidate
        $bestColor = null;
        $highestScore = 0;
        
        foreach ($candidates as $candidate) {
            if ($candidate['score'] > $highestScore) {
                $highestScore = $candidate['score'];
                $bestColor = $candidate;
            }
        }
        
        if ($bestColor && $highestScore >= 50) { // Minimum confidence threshold
            $context['extracted'] = array_merge(
                $context['extracted'] ?? [],
                ['color' => $bestColor['color']]
            );
            $context['confidence'] += 0.25; // Boost confidence for successful color extraction
        }
        
        $context['debug']['pass4_candidates'] = count($candidates);
        $context['debug']['pass4_best_score'] = $highestScore;
        
        Log::debug('Pass 4 Complete', [
            'candidates' => count($candidates),
            'best_score' => $highestScore,
            'extracted_color' => $bestColor['color'] ?? null
        ]);
        
        return $context;
    }

    /**
     * Pass 5: Parent Name Generation
     */
    private static function pass5_generateParentName(array $context): array
    {
        $original = $context['normalized'];
        $extracted = $context['extracted'] ?? [];
        
        $parentName = $original;
        
        // Remove extracted color
        if (!empty($extracted['color'])) {
            $colorPattern = '/\b' . preg_quote($extracted['color'], '/') . '\b/i';
            $parentName = preg_replace($colorPattern, '', $parentName);
        }
        
        // Remove extracted dimensions
        if (!empty($extracted['width']) || !empty($extracted['drop'])) {
            // Remove dimension patterns
            foreach (self::$dimensionPatterns as $patternInfo) {
                if (preg_match($patternInfo['pattern'], $parentName)) {
                    $parentName = preg_replace($patternInfo['pattern'], '', $parentName);
                    break; // Only remove first match to avoid over-cleaning
                }
            }
        }
        
        // Clean up the result
        $parentName = self::cleanParentName($parentName);
        
        // Validate result quality
        if (strlen($parentName) < 3 || strlen($parentName) / strlen($original) < 0.3) {
            // Result too short or removed too much - use conservative approach
            $words = explode(' ', $original);
            if (count($words) > 2) {
                // Remove last 1-2 words (often contain color/size)
                array_pop($words);
                if (count($words) > 3 && 
                    (isset(self::$colorDictionary[strtolower($words[count($words) - 1])]) ||
                     preg_match('/\d+/', $words[count($words) - 1]))) {
                    array_pop($words);
                }
                $parentName = implode(' ', $words);
            } else {
                $parentName = $original; // Keep original if too few words
            }
        }
        
        $context['extracted']['parent_name'] = trim($parentName);
        $context['confidence'] += 0.2; // Boost confidence for parent name generation
        
        $context['debug']['pass5_parent_name'] = $context['extracted']['parent_name'];
        
        Log::debug('Pass 5 Complete', [
            'original_length' => strlen($original),
            'parent_length' => strlen($parentName),
            'parent_name' => $parentName
        ]);
        
        return $context;
    }

    /**
     * Pass 6: Validation & Scoring
     */
    private static function pass6_validate(array $context): array
    {
        $extracted = $context['extracted'] ?? [];
        
        // Cross-validation checks
        $validationScore = 0;
        
        // Check if we extracted meaningful data
        if (!empty($extracted['color'])) {
            $validationScore += 0.3;
        }
        if (!empty($extracted['width']) || !empty($extracted['drop'])) {
            $validationScore += 0.4;
        }
        if (!empty($extracted['parent_name']) && strlen($extracted['parent_name']) >= 3) {
            $validationScore += 0.3;
        }
        
        // Final confidence calculation
        $context['confidence'] = min(1.0, $context['confidence'] * $validationScore);
        
        $context['debug']['pass6_validation_score'] = $validationScore;
        $context['debug']['pass6_final_confidence'] = $context['confidence'];
        
        Log::debug('Pass 6 Complete', [
            'validation_score' => $validationScore,
            'final_confidence' => $context['confidence'],
            'extracted_attributes' => array_keys($extracted)
        ]);
        
        return $context;
    }

    /**
     * Utility Methods
     */
    
    private static function isPotentialColor(string $token): bool
    {
        $tokenLower = strtolower($token);
        return isset(self::$colorDictionary[$tokenLower]) && 
               !in_array($tokenLower, self::$materialTerms);
    }

    private static function normalizeNumber(string $number): string
    {
        // Remove leading zeros but preserve decimal parts
        return preg_replace('/^0+(?=\d)/', '', $number);
    }

    private static function cleanParentName(string $name): string
    {
        // Remove multiplication symbols but only the × symbol, not x/X from words
        $name = str_replace(['×'], '', $name);
        
        // Remove standalone measurement units
        $name = preg_replace('/\b(?:cm|mm|in|ft|"|\')\b/i', '', $name);
        
        // Remove isolated numbers (dimensions without context)
        $name = preg_replace('/\b\d+(?:\.\d+)?\b/', '', $name);
        
        // Remove common filler words
        foreach (self::$stopWords as $stopWord) {
            $name = preg_replace('/\b' . preg_quote($stopWord, '/') . '\b/i', '', $name);
        }
        
        // Clean up extra spaces and punctuation
        $name = preg_replace('/\s+/', ' ', $name);
        $name = preg_replace('/[^\w\s-]/', '', $name);
        
        return trim($name);
    }

    /**
     * Debug method for testing
     */
    public static function debugExtraction(string $productName): array
    {
        $result = self::extractAttributes($productName);
        return [
            'input' => $productName,
            'extracted' => [
                'color' => $result['color'],
                'width' => $result['width'],
                'drop' => $result['drop'],
                'parent_name' => $result['parent_name']
            ],
            'confidence' => $result['confidence'],
            'debug_trace' => $result['debug']
        ];
    }
}