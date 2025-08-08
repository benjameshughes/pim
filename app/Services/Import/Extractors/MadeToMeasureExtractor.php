<?php

namespace App\Services\Import\Extractors;

class MadeToMeasureExtractor
{
    private array $mtmKeywords = [
        // Primary MTM indicators (high confidence)
        'made to measure' => 10,
        'made-to-measure' => 10,
        'mtm' => 8,
        'bespoke' => 9,
        'custom size' => 8,
        'custom made' => 8,
        'made to order' => 7,
        
        // Secondary indicators (medium confidence)
        'custom' => 6,
        'tailored' => 6,
        'personalized' => 5,
        'customized' => 5,
        'custom width' => 7,
        'custom length' => 7,
        'custom height' => 7,
        'custom drop' => 7,
        'any size' => 6,
        'your size' => 6,
        'cut to size' => 7,
        'specified size' => 6,
        
        // Weak indicators (low confidence)
        'available in' => 2,
        'choose your' => 3,
        'select your' => 3,
    ];

    private array $negativeTriggers = [
        'ready made',
        'standard size',
        'standard',
        'pre-made',
        'premade',
        'off the shelf',
        'ready to hang',
    ];

    public function extract(string $text): array
    {
        $text = strtolower($text);
        $indicators = [];
        $matchedPatterns = [];
        $totalScore = 0;
        $maxPossibleScore = 0;

        // Check for negative triggers first
        foreach ($this->negativeTriggers as $trigger) {
            if ($this->containsWordBoundary($text, $trigger)) {
                $totalScore -= 5;
            }
        }

        // Check for MTM keywords
        foreach ($this->mtmKeywords as $keyword => $weight) {
            $maxPossibleScore += $weight;
            
            if ($this->containsWordBoundary($text, $keyword)) {
                $indicators[] = $keyword;
                $matchedPatterns[] = [
                    'pattern' => $keyword,
                    'weight' => $weight,
                    'method' => 'word_boundary'
                ];
                $totalScore += $weight;
            }
        }

        // Calculate confidence (0-1 scale)
        $confidence = $maxPossibleScore > 0 ? max(0, min(1, $totalScore / 15)) : 0;
        
        // Determine if MTM based on score threshold
        $isMadeToMeasure = $totalScore >= 6; // Minimum threshold for MTM classification

        return [
            'is_made_to_measure' => $isMadeToMeasure,
            'confidence' => round($confidence, 3),
            'indicators' => $indicators,
            'extraction_method' => 'keyword_matching',
            'matched_patterns' => $matchedPatterns,
            'total_score' => $totalScore,
            'threshold_used' => 6,
        ];
    }

    private function containsWordBoundary(string $text, string $pattern): bool
    {
        // Use word boundaries to prevent false matches like "black" in "blackout"
        return preg_match('/\b' . preg_quote($pattern, '/') . '\b/i', $text) === 1;
    }
}