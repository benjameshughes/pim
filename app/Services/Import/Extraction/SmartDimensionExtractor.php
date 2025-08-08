<?php

namespace App\Services\Import\Extraction;

use Illuminate\Support\Facades\Log;

class SmartDimensionExtractor implements AttributeExtractor
{
    private array $patterns = [
        // Direct dimension patterns - highest confidence
        'width_explicit' => [
            'regex' => '/(?:width|w)[\s:]*([0-9]+(?:\.[0-9]+)?)(?:cm|mm|inch|"|\s|$)/i',
            'dimension' => 'width',
            'confidence' => 0.95,
        ],
        'drop_explicit' => [
            'regex' => '/(?:drop|height|h|length|l)[\s:]*([0-9]+(?:\.[0-9]+)?)(?:cm|mm|inch|"|\s|$)/i',
            'dimension' => 'drop',
            'confidence' => 0.95,
        ],
        
        // Dimension pairs - very high confidence
        'width_x_drop' => [
            'regex' => '/([0-9]+(?:\.[0-9]+)?)\s*[x×X]\s*([0-9]+(?:\.[0-9]+)?)(?:cm|mm|inch|"|\s|$)/i',
            'dimensions' => ['width', 'drop'],
            'confidence' => 0.98,
        ],
        'width_by_drop' => [
            'regex' => '/([0-9]+(?:\.[0-9]+)?)\s*(?:by|BY)\s*([0-9]+(?:\.[0-9]+)?)(?:cm|mm|inch|"|\s|$)/i',
            'dimensions' => ['width', 'drop'],
            'confidence' => 0.96,
        ],
        
        // SKU embedded dimensions - high confidence for structured SKUs
        'sku_structured_3digit' => [
            'regex' => '/[A-Z]+-([0-9]{3})-([0-9]{3})-[A-Z]+/i',
            'dimensions' => ['width', 'drop'],
            'confidence' => 0.90,
            'context_required' => 'sku',
        ],
        'sku_structured_mixed' => [
            'regex' => '/[A-Z]+([0-9]{2,4})x([0-9]{2,4})[A-Z]*/i',
            'dimensions' => ['width', 'drop'],
            'confidence' => 0.85,
            'context_required' => 'sku',
        ],
        
        // Product name embedded - medium confidence
        'product_name_dimensions' => [
            'regex' => '/\b([0-9]{2,4})\s*(?:x|X|×)\s*([0-9]{2,4})\b/i',
            'dimensions' => ['width', 'drop'],
            'confidence' => 0.80,
            'context_preferred' => 'product_name',
        ],
        
        // Single dimension in context - lower confidence
        'contextual_single' => [
            'regex' => '/\b([0-9]{2,4})\b/i',
            'dimension' => 'width', // Default to width for single dimension
            'confidence' => 0.60,
            'requires_context_boost' => true,
        ],
    ];

    private array $contextBoosts = [
        'variant_sku' => 1.2,
        'product_name' => 1.1,
        'title' => 1.1,
        'description' => 1.0,
        'variant_size' => 1.3, // Size field is most likely to contain dimensions
    ];

    private array $unitConversions = [
        'cm' => 10,      // cm to mm
        'inch' => 25.4,  // inches to mm  
        '"' => 25.4,     // inches to mm
        'mm' => 1,       // mm to mm (base unit)
    ];

    private array $reasonableRanges = [
        'width' => ['min' => 10, 'max' => 5000],   // 1cm to 5m in mm
        'drop' => ['min' => 10, 'max' => 8000],    // 1cm to 8m in mm
    ];

    public function extract(string $text, array $context = []): array
    {
        $text = trim($text);
        if (empty($text)) {
            return [];
        }

        $dimensions = [];
        $bestMatches = [];

        foreach ($this->patterns as $patternName => $config) {
            // Skip patterns that require specific context if context doesn't match
            if (isset($config['context_required'])) {
                $contextField = $context['field'] ?? 'unknown';
                if (!str_contains($contextField, $config['context_required'])) {
                    continue;
                }
            }

            if (preg_match($config['regex'], $text, $matches)) {
                $confidence = $this->calculateConfidence($config, $context, $matches);
                
                if (isset($config['dimensions']) && count($config['dimensions']) === 2) {
                    // Two-dimension pattern
                    $width = $this->normalizeDimension((float) $matches[1]);
                    $drop = $this->normalizeDimension((float) $matches[2]);
                    
                    if ($this->isReasonableDimension('width', $width) && 
                        $this->isReasonableDimension('drop', $drop)) {
                        
                        $bestMatches[] = [
                            'pattern' => $patternName,
                            'confidence' => $confidence,
                            'dimensions' => [
                                'width' => (int) $width,
                                'drop' => (int) $drop,
                            ],
                            'raw_match' => $matches[0],
                            'extracted_values' => [$matches[1], $matches[2]],
                        ];
                    }
                } else {
                    // Single dimension pattern
                    $dimension = $config['dimension'];
                    $value = $this->normalizeDimension((float) $matches[1]);
                    
                    if ($this->isReasonableDimension($dimension, $value)) {
                        $bestMatches[] = [
                            'pattern' => $patternName,
                            'confidence' => $confidence,
                            'dimensions' => [
                                $dimension => (int) $value,
                            ],
                            'raw_match' => $matches[0],
                            'extracted_values' => [$matches[1]],
                        ];
                    }
                }
            }
        }

        if (empty($bestMatches)) {
            return [];
        }

        // Sort by confidence and pick the best match
        usort($bestMatches, fn($a, $b) => $b['confidence'] <=> $a['confidence']);
        $bestMatch = $bestMatches[0];

        // Only return if confidence is above threshold
        if ($bestMatch['confidence'] < 0.6) {
            return [];
        }

        $result = [
            'dimensions' => $bestMatch['dimensions'],
            'confidence' => round($bestMatch['confidence'], 3),
            'extraction_method' => $bestMatch['pattern'],
            'raw_match' => $bestMatch['raw_match'],
            'context_field' => $context['field'] ?? 'unknown',
        ];

        // Add individual dimension access
        if (isset($bestMatch['dimensions']['width'])) {
            $result['width'] = $bestMatch['dimensions']['width'];
        }
        if (isset($bestMatch['dimensions']['drop'])) {
            $result['drop'] = $bestMatch['dimensions']['drop'];
        }

        Log::info('Dimensions extracted', [
            'text_sample' => substr($text, 0, 50),
            'extracted' => $result['dimensions'],
            'confidence' => $result['confidence'],
            'method' => $result['extraction_method'],
            'context' => $context['field'] ?? 'unknown',
        ]);

        return $result;
    }

    private function calculateConfidence(array $config, array $context, array $matches): float
    {
        $baseConfidence = $config['confidence'];
        
        // Apply context boost
        $contextField = $context['field'] ?? 'unknown';
        $contextMultiplier = $this->contextBoosts[$contextField] ?? 1.0;
        
        // Apply context preference boost
        if (isset($config['context_preferred']) && 
            str_contains($contextField, $config['context_preferred'])) {
            $contextMultiplier *= 1.2;
        }

        // Apply context requirement boost for patterns that need context
        if (isset($config['requires_context_boost'])) {
            $contextMultiplier *= $this->analyzeContextRelevance($matches, $context);
        }

        return min(1.0, $baseConfidence * $contextMultiplier);
    }

    private function analyzeContextRelevance(array $matches, array $context): float
    {
        $contextField = $context['field'] ?? 'unknown';
        
        // Higher boost for fields more likely to contain dimensions
        $fieldRelevance = match (true) {
            str_contains($contextField, 'size') => 1.4,
            str_contains($contextField, 'dimension') => 1.3,
            str_contains($contextField, 'sku') => 1.2,
            str_contains($contextField, 'name') || str_contains($contextField, 'title') => 1.1,
            default => 1.0,
        };

        // Check if the matched number looks like a realistic dimension
        $value = (float) $matches[1];
        $realismBoost = match (true) {
            $value >= 50 && $value <= 300 => 1.2,    // Very common window sizes
            $value >= 20 && $value <= 500 => 1.1,    // Reasonable sizes
            $value >= 10 && $value <= 1000 => 1.0,   // Possible sizes
            default => 0.8,                          // Unusual sizes
        };

        return min(1.5, $fieldRelevance * $realismBoost);
    }

    private function normalizeDimension(float $value): float
    {
        // Convert to millimeters (our base unit)
        // For now, assume input is already in reasonable units
        // More sophisticated unit detection could be added here
        
        if ($value < 10) {
            // Likely meters, convert to mm
            return $value * 1000;
        } elseif ($value > 5000) {
            // Likely micrometers or very large mm, normalize
            while ($value > 5000) {
                $value /= 10;
            }
        }
        
        return $value;
    }

    private function isReasonableDimension(string $dimension, float $value): bool
    {
        $range = $this->reasonableRanges[$dimension] ?? ['min' => 1, 'max' => 10000];
        return $value >= $range['min'] && $value <= $range['max'];
    }

    public function extractMultipleFields(array $data): array
    {
        $allResults = [];
        $bestResult = null;
        $bestScore = 0;

        // Define field priority for dimension extraction
        $fieldPriority = [
            'variant_size' => 15,
            'variant_sku' => 12,
            'product_name' => 10,
            'title' => 10,
            'description' => 8,
            'product_features_1' => 6,
            'product_features_2' => 6,
            'width' => 20,  // Explicit dimension fields
            'drop' => 20,
            'height' => 18,
            'length' => 18,
        ];

        foreach ($data as $fieldName => $fieldValue) {
            if (empty($fieldValue) || !is_string($fieldValue)) {
                continue;
            }

            $priority = $fieldPriority[$fieldName] ?? 1;
            
            $result = $this->extract($fieldValue, [
                'field' => $fieldName,
                'priority' => $priority,
            ]);

            if (!empty($result) && isset($result['dimensions'])) {
                $priorityAdjustedScore = $result['confidence'] * ($priority / 10);
                
                $allResults[] = array_merge($result, [
                    'source_field' => $fieldName,
                    'field_priority' => $priority,
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
            $bestResult['all_extractions'] = $allResults;
            $bestResult['extraction_count'] = count($allResults);
            
            Log::info('Multi-field dimension extraction completed', [
                'extractions_found' => count($allResults),
                'best_source_field' => $bestResult['source_field'],
                'best_confidence' => $bestScore,
                'dimensions' => $bestResult['dimensions'],
            ]);
        }

        return $bestResult ?? [];
    }

    public function suggestSizeString(array $dimensionResult): string
    {
        if (empty($dimensionResult['dimensions'])) {
            return '';
        }

        $dimensions = $dimensionResult['dimensions'];
        
        if (isset($dimensions['width']) && isset($dimensions['drop'])) {
            return $dimensions['width'] . ' x ' . $dimensions['drop'];
        }
        
        if (isset($dimensions['width'])) {
            return 'W' . $dimensions['width'];
        }
        
        if (isset($dimensions['drop'])) {
            return 'H' . $dimensions['drop'];
        }

        return '';
    }

    public function getExtractionSummary(): array
    {
        return [
            'supported_patterns' => count($this->patterns),
            'dimension_types' => ['width', 'drop'],
            'base_unit' => 'millimeters',
            'confidence_threshold' => 0.6,
            'reasonable_ranges' => $this->reasonableRanges,
        ];
    }
}