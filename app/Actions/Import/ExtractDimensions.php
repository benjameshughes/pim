<?php

namespace App\Actions\Import;

/**
 * 📐 EXTRACT DIMENSIONS ACTION
 *
 * Handles dimension extraction from product titles
 * Supports multiple patterns and formats
 */
class ExtractDimensions
{
    /**
     * Extract width and drop dimensions from product title
     *
     * Supports ALL patterns:
     * - "45cm Width x 150cm Drop" (with keywords)
     * - "45cm x 150cm" (standard format) 
     * - "45 x 150" (no units)
     * - "60cm 210cm drop" (with drop keyword)
     * - "60cm 210cm" (space separated)
     * - "60cm" (width only)
     *
     * @param  string  $title  Product title
     * @return array ['width' => int|null, 'drop' => int|null]
     */
    public function execute(string $title): array
    {
        $width = null;
        $drop = null;

        // Pattern 1: "45cm Width x 150cm Drop" (with Width/Drop keywords)
        if (preg_match('/(\d+)(?:cm)?\s*Width\s*x\s*(\d+)(?:cm)?\s*Drop/i', $title, $matches)) {
            $width = (int) $matches[1];
            $drop = (int) $matches[2];
        }
        // Pattern 2: "45cm x 150cm" or "45 x 150" (standard x format with/without units)
        elseif (preg_match('/(\d+)(?:cm)?\s*x\s*(\d+)(?:cm)?/i', $title, $matches)) {
            $width = (int) $matches[1];
            $drop = (int) $matches[2];
        }
        // Pattern 3: "60cm 210cm drop" or "60cm 210cm" (space separated with optional drop keyword)
        elseif (preg_match('/(\d+)(?:cm)?\s+(\d+)(?:cm)?(?:\s+drop)?/i', $title, $matches)) {
            $width = (int) $matches[1];
            $drop = (int) $matches[2];
        }
        // Pattern 4: Single dimension - just width (fallback)
        elseif (preg_match('/(\d+)(?:cm)?/i', $title, $matches)) {
            $width = (int) $matches[1];
            // Drop remains null when not found in title
        }

        return [
            'width' => $width,
            'drop' => $drop,
        ];
    }

    /**
     * Check if title contains dimension information
     */
    public function hasDimensions(string $title): bool
    {
        return (bool) preg_match('/\d+cm/', $title);
    }

    /**
     * Get dimension pattern used in the title (for debugging)
     */
    public function getPatternUsed(string $title): string
    {
        if (preg_match('/(\d+)(?:cm)?\s*Width\s*x\s*(\d+)(?:cm)?\s*Drop/i', $title)) {
            return 'width-keyword x drop-keyword (45cm Width x 150cm Drop)';
        } elseif (preg_match('/(\d+)(?:cm)?\s*x\s*(\d+)(?:cm)?/i', $title)) {
            return 'width x drop (60cm x 160cm or 45 x 150)';
        } elseif (preg_match('/(\d+)(?:cm)?\s+(\d+)(?:cm)?\s+drop/i', $title)) {
            return 'width drop-keyword (60cm 210cm drop)';
        } elseif (preg_match('/(\d+)(?:cm)?\s+(\d+)(?:cm)?/i', $title)) {
            return 'width space drop (60cm 210cm)';
        } elseif (preg_match('/(\d+)(?:cm)?/i', $title)) {
            return 'width only (60cm)';
        }

        return 'no dimensions found';
    }
}
