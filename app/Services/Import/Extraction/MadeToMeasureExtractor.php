<?php

namespace App\Services\Import\Extraction;

use Illuminate\Support\Facades\Log;

class MadeToMeasureExtractor implements AttributeExtractor
{
    private array $patterns = [
        // High confidence patterns
        'MTM' => [
            'regex' => '/\bMTM\b/i',
            'confidence' => 0.95,
            'title_suffix' => 'MTM',
            'context_weight' => 1.0,
        ],
        'Made to Measure' => [
            'regex' => '/\bMade\s+to\s+Measure\b/i',
            'confidence' => 0.98,
            'title_suffix' => 'Made to Measure',
            'context_weight' => 1.0,
        ],
        'Made-to-Measure' => [
            'regex' => '/\bMade-to-Measure\b/i',
            'confidence' => 0.98,
            'title_suffix' => 'Made-to-Measure',
            'context_weight' => 1.0,
        ],
        
        // Medium confidence patterns
        'Bespoke' => [
            'regex' => '/\bBespoke\b/i',
            'confidence' => 0.90,
            'title_suffix' => 'Bespoke',
            'context_weight' => 0.9,
        ],
        'Custom Size' => [
            'regex' => '/\bCustom\s+Size\b/i',
            'confidence' => 0.85,
            'title_suffix' => 'Custom Size',
            'context_weight' => 0.8,
        ],
        'Made to Order' => [
            'regex' => '/\bMade\s+to\s+Order\b/i',
            'confidence' => 0.88,
            'title_suffix' => 'MTO',
            'context_weight' => 0.9,
        ],
        'Custom Made' => [
            'regex' => '/\bCustom\s+Made\b/i',
            'confidence' => 0.87,
            'title_suffix' => 'Custom Made',
            'context_weight' => 0.8,
        ],
        
        // Lower confidence patterns
        'Tailored' => [
            'regex' => '/\bTailored\b/i',
            'confidence' => 0.75,
            'title_suffix' => 'Tailored',
            'context_weight' => 0.7,
        ],
        'Custom' => [
            'regex' => '/\bCustom\b/i',
            'confidence' => 0.70,
            'title_suffix' => 'Custom',
            'context_weight' => 0.6,
        ],
        'Personalized' => [
            'regex' => '/\bPersonali[sz]ed\b/i',
            'confidence' => 0.65,
            'title_suffix' => 'Personalized',
            'context_weight' => 0.5,
        ],
    ];

    private array $contextBoosts = [
        'product_name' => 1.2,    // Higher weight for product name
        'title' => 1.2,          // Same for title fields
        'description' => 1.0,    // Normal weight for descriptions
        'features' => 0.9,       // Slightly lower for feature text
        'details' => 0.9,        // Same for details
    ];

    private array $negativePatterns = [
        '/\bNOT\s+Made\s+to\s+Measure\b/i',
        '/\bNo\s+MTM\b/i',
        '/\bStandard\s+Size\b/i',
        '/\bFixed\s+Size\b/i',
        '/\bReady\s+Made\b/i',
        '/\bOff\s+the\s+Shelf\b/i',
    ];

    public function extract(string $text, array $context = []): array
    {
        $text = trim($text);
        if (empty($text)) {
            return [];
        }

        // Check for negative patterns first
        foreach ($this->negativePatterns as $negativePattern) {
            if (preg_match($negativePattern, $text)) {
                Log::debug('MTM extraction blocked by negative pattern', [
                    'text' => substr($text, 0, 100),
                    'pattern' => $negativePattern,
                ]);
                
                return [
                    'made_to_measure' => false,
                    'confidence' => 0.95,
                    'reason' => 'explicitly_excluded',
                ];
            }
        }

        $bestMatch = null;
        $bestScore = 0;

        foreach ($this->patterns as $patternName => $config) {
            if (preg_match($config['regex'], $text, $matches)) {
                // Calculate context-adjusted confidence
                $contextField = $context['field'] ?? 'unknown';
                $contextMultiplier = $this->contextBoosts[$contextField] ?? 1.0;
                $patternWeight = $config['context_weight'];
                
                $adjustedConfidence = $config['confidence'] * $contextMultiplier * $patternWeight;
                
                // Boost score if found multiple times
                $matchCount = preg_match_all($config['regex'], $text);
                if ($matchCount > 1) {
                    $adjustedConfidence = min(1.0, $adjustedConfidence * 1.1);
                }

                // Boost if surrounded by relevant context
                $contextBoost = $this->analyzeContextRelevance($text, $matches[0]);
                $adjustedConfidence = min(1.0, $adjustedConfidence * $contextBoost);

                if ($adjustedConfidence > $bestScore) {
                    $bestScore = $adjustedConfidence;
                    $bestMatch = [
                        'pattern' => $patternName,
                        'matched_text' => $matches[0],
                        'title_suffix' => $config['title_suffix'],
                        'base_confidence' => $config['confidence'],
                        'context_multiplier' => $contextMultiplier,
                        'pattern_weight' => $patternWeight,
                        'context_boost' => $contextBoost,
                    ];
                }
            }
        }

        if ($bestMatch && $bestScore >= 0.7) {
            Log::info('MTM detected with high confidence', [
                'pattern' => $bestMatch['pattern'],
                'confidence' => $bestScore,
                'text_sample' => substr($text, 0, 100),
                'context_field' => $context['field'] ?? 'unknown',
            ]);

            return [
                'made_to_measure' => true,
                'confidence' => round($bestScore, 3),
                'title_suffix' => $bestMatch['title_suffix'],
                'detected_pattern' => $bestMatch['pattern'],
                'matched_text' => $bestMatch['matched_text'],
                'extraction_details' => [
                    'base_confidence' => $bestMatch['base_confidence'],
                    'context_multiplier' => $bestMatch['context_multiplier'],
                    'pattern_weight' => $bestMatch['pattern_weight'],
                    'context_boost' => $bestMatch['context_boost'],
                ],
            ];
        }

        return [];
    }

    private function analyzeContextRelevance(string $text, string $matchedText): float
    {
        // Find the position of the matched text
        $matchPos = stripos($text, $matchedText);
        if ($matchPos === false) {
            return 1.0; // Default if position can't be found
        }

        // Extract context around the match (50 chars before and after)
        $contextStart = max(0, $matchPos - 50);
        $contextEnd = min(strlen($text), $matchPos + strlen($matchedText) + 50);
        $contextText = substr($text, $contextStart, $contextEnd - $contextStart);

        $relevanceBoost = 1.0;

        // Positive context indicators
        $positiveIndicators = [
            '/\b(?:blind|shade|curtain|window|treatment)\b/i' => 1.1,
            '/\b(?:width|drop|size|dimension)\b/i' => 1.15,
            '/\b(?:exact|precise|specific|perfect)\s+(?:fit|size)\b/i' => 1.2,
            '/\b(?:any|your)\s+size\b/i' => 1.1,
            '/\b(?:measure|measurement)\b/i' => 1.1,
        ];

        foreach ($positiveIndicators as $pattern => $boost) {
            if (preg_match($pattern, $contextText)) {
                $relevanceBoost *= $boost;
            }
        }

        // Negative context indicators
        $negativeIndicators = [
            '/\b(?:standard|fixed|preset|ready)\s+(?:size|made)\b/i' => 0.8,
            '/\b(?:small|medium|large|xl|xxl)\b/i' => 0.9,
            '/\b(?:cannot|not|no)\s+(?:be\s+)?(?:made|custom|tailored)\b/i' => 0.5,
        ];

        foreach ($negativeIndicators as $pattern => $penalty) {
            if (preg_match($pattern, $contextText)) {
                $relevanceBoost *= $penalty;
            }
        }

        return min(1.3, max(0.5, $relevanceBoost)); // Cap between 0.5 and 1.3
    }

    public function extractMultipleFields(array $data): array
    {
        $allResults = [];
        $bestResult = null;
        $bestScore = 0;

        // Define field priority and context
        $fieldPriority = [
            'product_name' => ['field' => 'product_name', 'priority' => 10],
            'title' => ['field' => 'title', 'priority' => 10],
            'description' => ['field' => 'description', 'priority' => 8],
            'product_features_1' => ['field' => 'features', 'priority' => 6],
            'product_features_2' => ['field' => 'features', 'priority' => 6],
            'product_features_3' => ['field' => 'features', 'priority' => 6],
            'product_details_1' => ['field' => 'details', 'priority' => 5],
            'product_details_2' => ['field' => 'details', 'priority' => 5],
        ];

        foreach ($data as $fieldName => $fieldValue) {
            if (empty($fieldValue) || !is_string($fieldValue)) {
                continue;
            }

            $fieldConfig = $fieldPriority[$fieldName] ?? ['field' => 'unknown', 'priority' => 1];
            
            $result = $this->extract($fieldValue, [
                'field' => $fieldConfig['field'],
                'field_name' => $fieldName,
            ]);

            if (!empty($result) && isset($result['made_to_measure']) && $result['made_to_measure']) {
                $priorityAdjustedScore = $result['confidence'] * ($fieldConfig['priority'] / 10);
                
                $allResults[] = array_merge($result, [
                    'source_field' => $fieldName,
                    'field_priority' => $fieldConfig['priority'],
                    'priority_adjusted_score' => $priorityAdjustedScore,
                ]);

                if ($priorityAdjustedScore > $bestScore) {
                    $bestScore = $priorityAdjustedScore;
                    $bestResult = $result;
                    $bestResult['source_field'] = $fieldName;
                }
            }
        }

        if ($bestResult) {
            $bestResult['all_detections'] = $allResults;
            $bestResult['detection_count'] = count($allResults);
            
            Log::info('MTM multi-field extraction completed', [
                'detections_found' => count($allResults),
                'best_source_field' => $bestResult['source_field'],
                'best_confidence' => $bestScore,
            ]);
        }

        return $bestResult ?? [];
    }

    public function suggestTitleEnhancement(string $originalTitle, array $mtmResult): string
    {
        if (empty($mtmResult['made_to_measure']) || !$mtmResult['made_to_measure']) {
            return $originalTitle;
        }

        $titleSuffix = $mtmResult['title_suffix'] ?? 'MTM';
        
        // Check if title already contains MTM indicator
        foreach (array_keys($this->patterns) as $pattern) {
            if (stripos($originalTitle, $pattern) !== false) {
                return $originalTitle; // Already has MTM indicator
            }
        }

        // Add MTM suffix intelligently
        $title = trim($originalTitle);
        
        // If title ends with size info, insert before it
        if (preg_match('/(.+)\s+(\d+(?:x\d+)?(?:cm|mm|inch|")?)\s*$/i', $title, $matches)) {
            return trim($matches[1]) . ' ' . $titleSuffix . ' ' . $matches[2];
        }
        
        // Otherwise append
        return $title . ' ' . $titleSuffix;
    }

    public function getConfidenceThreshold(): float
    {
        return 0.7;
    }

    public function getPatternSummary(): array
    {
        return array_map(function ($config, $name) {
            return [
                'name' => $name,
                'base_confidence' => $config['confidence'],
                'title_suffix' => $config['title_suffix'],
            ];
        }, $this->patterns, array_keys($this->patterns));
    }
}