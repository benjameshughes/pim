<?php

namespace App\Services;

use App\Models\ProductVariant;

class SkuGeneratorService
{
    /**
     * Generate sequential variant SKUs for a parent SKU
     * Format: 000-001, 000-002, 000-003, etc.
     */
    public function generateVariantSkus(string $parentSku, int $count): array
    {
        $variants = [];
        
        for ($i = 1; $i <= $count; $i++) {
            $variantNumber = str_pad($i, 3, '0', STR_PAD_LEFT);
            $variants[] = "{$parentSku}-{$variantNumber}";
        }

        return $variants;
    }

    /**
     * Get the next available variant SKU for a parent
     */
    public function getNextVariantSku(string $parentSku): string
    {
        // Find the highest variant number for this parent
        $lastVariant = ProductVariant::where('sku', 'LIKE', "{$parentSku}-%")
            ->orderByRaw('CAST(SUBSTRING(sku, 5) AS UNSIGNED) DESC')
            ->first();

        $nextNumber = 1;
        if ($lastVariant) {
            // Extract number from SKU like "123-456"
            $parts = explode('-', $lastVariant->sku);
            if (count($parts) === 2 && is_numeric($parts[1])) {
                $nextNumber = (int)$parts[1] + 1;
            }
        }

        $variantNumber = str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
        return "{$parentSku}-{$variantNumber}";
    }

    /**
     * Suggest alternative parent SKUs if one is taken
     */
    public function suggestAlternativeParentSkus(string $takenSku, int $count = 3): array
    {
        $suggestions = [];
        $baseNumber = (int)$takenSku;
        
        // Try incrementing
        for ($i = 1; $i <= 10 && count($suggestions) < $count; $i++) {
            $candidate = str_pad($baseNumber + $i, 3, '0', STR_PAD_LEFT);
            if (!$this->isParentSkuTaken($candidate)) {
                $suggestions[] = $candidate;
            }
        }
        
        // Try decrementing if we need more
        for ($i = 1; $i <= 10 && count($suggestions) < $count; $i++) {
            $newNumber = $baseNumber - $i;
            if ($newNumber >= 1) {
                $candidate = str_pad($newNumber, 3, '0', STR_PAD_LEFT);
                if (!$this->isParentSkuTaken($candidate)) {
                    $suggestions[] = $candidate;
                }
            }
        }

        return array_slice($suggestions, 0, $count);
    }

    private function isParentSkuTaken(string $sku): bool
    {
        return \App\Models\Product::where('parent_sku', $sku)->exists();
    }
}