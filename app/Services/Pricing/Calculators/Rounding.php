<?php

namespace App\Services\Pricing\Calculators;

/**
 * Rounding strategies for prices before saving.
 */
class Rounding
{
    /**
     * Apply a rounding strategy to a pending updates map.
     * Pending map shape: [variantId => ['price' => 12.34, ...], ...]
     */
    public function apply(string $strategy, array &$pending, ?string $currency = null): void
    {
        foreach ($pending as $variantId => &$fields) {
            if (isset($fields['price'])) {
                $fields['price'] = $this->roundPrice((float) $fields['price'], $strategy, $currency);
            }
            if (isset($fields['discount_price']) && $fields['discount_price'] !== null) {
                $fields['discount_price'] = $this->roundPrice((float) $fields['discount_price'], $strategy, $currency);
            }
        }
    }

    /**
     * Core rounding function by strategy (naive implementations for now).
     */
    public function roundPrice(float $value, string $strategy, ?string $currency = null): float
    {
        return match ($strategy) {
            'nearest_0_99' => floor($value) + 0.99,
            'nearest_0_95' => floor($value) + 0.95,
            'nearest_0_49' => floor($value) + 0.49,
            default => round($value, 2),
        };
    }
}

