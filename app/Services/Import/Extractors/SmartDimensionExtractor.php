<?php

namespace App\Services\Import\Extractors;

class SmartDimensionExtractor
{
    private array $config;
    
    private array $dimensionPatterns = [
        // Standard dimension patterns
        '/(?:width|w):\s*(\d+(?:\.\d+)?)\s*(cm|mm|m|ft|in|")/i' => 'width',
        '/(?:height|h):\s*(\d+(?:\.\d+)?)\s*(cm|mm|m|ft|in|")/i' => 'height',
        '/(?:drop|d):\s*(\d+(?:\.\d+)?)\s*(cm|mm|m|ft|in|")/i' => 'drop',
        '/(?:length|l):\s*(\d+(?:\.\d+)?)\s*(cm|mm|m|ft|in|")/i' => 'length',
        '/(?:depth|thickness):\s*(\d+(?:\.\d+)?)\s*(cm|mm|m|ft|in|")/i' => 'depth',
        '/(?:diameter|dia):\s*(\d+(?:\.\d+)?)\s*(cm|mm|m|ft|in|")/i' => 'diameter',
        
        // Width specific patterns
        '/(\d+(?:\.\d+)?)\s*(cm|mm|m|ft|in|")\s+wide/i' => 'width',
        '/(\d+(?:\.\d+)?)\s*(cm|mm|m|ft|in|")\s+width/i' => 'width',
        
        // Drop specific patterns  
        '/(\d+(?:\.\d+)?)\s*(cm|mm|m|ft|in|")\s+drop/i' => 'drop',
        
        // Standard x format (150cm x 200cm)
        '/(\d+(?:\.\d+)?)\s*(?:cm|mm|m|ft|in|")\s*[x×]\s*(\d+(?:\.\d+)?)\s*(cm|mm|m|ft|in|")/i' => 'width_height',
        
        // Simple x format (150 x 200 cm)
        '/(\d+(?:\.\d+)?)\s*[x×]\s*(\d+(?:\.\d+)?)\s*(cm|mm|m|ft|in|")/i' => 'width_height',
        
        // Feet format (5ft x 6ft)
        '/(\d+(?:\.\d+)?)\s*ft\s*[x×]\s*(\d+(?:\.\d+)?)\s*ft/i' => 'width_height_ft',
    ];

    private array $unitConversions = [
        'cm' => 1,
        'mm' => 0.1,
        'm' => 100,
        'ft' => 30.48,
        'in' => 2.54,
        '"' => 2.54,
    ];

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'digits_only' => false,
            'normalize_units' => true,
            'target_unit' => 'cm',
        ], $config);
    }

    public function extract(string $text): array
    {
        $dimensions = [];
        $confidence = 0;
        $matchedPatterns = [];
        $unitSystem = null;

        foreach ($this->dimensionPatterns as $pattern => $type) {
            if (preg_match($pattern, $text, $matches)) {
                $result = $this->processDimensionMatch($matches, $type);
                
                if ($result) {
                    $dimensions = array_merge($dimensions, $result['dimensions']);
                    $matchedPatterns[] = [
                        'pattern' => $pattern,
                        'type' => $type,
                        'matches' => $matches,
                    ];
                    $confidence += $result['confidence_boost'];
                    
                    if (!$unitSystem) {
                        $unitSystem = $result['unit_system'];
                    }
                }
            }
        }

        // If digits only mode, remove units
        if ($this->config['digits_only'] && !empty($dimensions)) {
            unset($dimensions['unit']);
            unset($dimensions['unit_system']);
        }

        // Calculate final confidence
        $finalConfidence = min(1.0, $confidence);
        
        return [
            'found_dimensions' => !empty($dimensions),
            'dimensions' => $dimensions,
            'confidence' => round($finalConfidence, 3),
            'unit_system' => $this->config['digits_only'] ? null : $unitSystem,
            'extraction_method' => 'pattern_matching',
            'matched_patterns' => $matchedPatterns,
        ];
    }

    private function processDimensionMatch(array $matches, string $type): ?array
    {
        $dimensions = [];
        $confidenceBoost = 0.3;
        $unitSystem = null;

        switch ($type) {
            case 'width_height':
                $width = $this->parseNumber($matches[1]);
                $height = $this->parseNumber($matches[2]);
                $unit = strtolower($matches[3] ?? 'cm');
                
                $dimensions['width'] = $width;
                $dimensions['height'] = $height;
                $confidenceBoost = 0.5;
                break;
                
            case 'width_height_ft':
                $width = $this->parseNumber($matches[1]);
                $height = $this->parseNumber($matches[2]);
                $unit = 'ft';
                
                $dimensions['width'] = $width;
                $dimensions['height'] = $height;
                $confidenceBoost = 0.4;
                break;
                
            default:
                $value = $this->parseNumber($matches[1]);
                $unit = strtolower($matches[2] ?? 'cm');
                
                $dimensions[$type] = $value;
                $confidenceBoost = 0.3;
                break;
        }

        // Determine unit system
        if (in_array($unit, ['ft', 'in', '"'])) {
            $unitSystem = 'imperial';
        } elseif (in_array($unit, ['cm', 'mm', 'm'])) {
            $unitSystem = 'metric';
        }

        // Add unit information if not in digits-only mode
        if (!$this->config['digits_only']) {
            $dimensions['unit'] = $unit;
            if ($unitSystem) {
                $dimensions['unit_system'] = $unitSystem;
            }
        }

        return [
            'dimensions' => $dimensions,
            'confidence_boost' => $confidenceBoost,
            'unit_system' => $unitSystem,
        ];
    }

    private function parseNumber(string $value): int|float
    {
        $number = (float) $value;
        return $number == (int) $number ? (int) $number : $number;
    }
}