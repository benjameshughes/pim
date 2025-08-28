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
     * Supports patterns:
     * - "60cm x 160cm" (standard format)
     * - "60cm 210cm drop" (with drop keyword)
     * - "60cm 210cm" (space separated)
     * - "60cm" (width only)
     *
     * @param string $title Product title
     * @return array ['width' => int|null, 'drop' => int|null]
     */
    public function execute(string $title): array
    {
        $width = null;
        $drop = null;

        // Pattern 1: "60cm x 160cm" (most common format)
        if (preg_match('/(\d+)cm\s*x\s*(\d+)cm/', $title, $matches)) {
            $width = (int) $matches[1];
            $drop = (int) $matches[2];
        }
        // Pattern 2: "60cm 210cm drop" or "60cm 210cm" (space separated with optional drop keyword)
        elseif (preg_match('/(\d+)cm\s+(\d+)cm(?:\s+drop)?/', $title, $matches)) {
            $width = (int) $matches[1];
            $drop = (int) $matches[2];
        }
        // Pattern 3: Single dimension - just width (fallback)
        elseif (preg_match('/(\d+)cm/', $title, $matches)) {
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
        if (preg_match('/(\d+)cm\s*x\s*(\d+)cm/', $title)) {
            return 'width x drop (60cm x 160cm)';
        } elseif (preg_match('/(\d+)cm\s+(\d+)cm\s+drop/', $title)) {
            return 'width drop-keyword (60cm 210cm drop)';
        } elseif (preg_match('/(\d+)cm\s+(\d+)cm/', $title)) {
            return 'width space drop (60cm 210cm)';
        } elseif (preg_match('/(\d+)cm/', $title)) {
            return 'width only (60cm)';
        }

        return 'no dimensions found';
    }
}