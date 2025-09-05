<?php

namespace App\Services\Pricing\Calculators;

/**
 * Solve a selling price that achieves a target margin given cost and fees.
 */
class TargetMarginSolver
{
    /**
     * Solve price for margin: margin% = (price - totalCosts) / price
     * totalCosts = cost + platform%*price + payment%*price + fixed + vat%
     * This is a simplified linear solve for common fee models.
     */
    public function solve(float $cost, float $targetMargin, array $fees = []): float
    {
        $platformRate = ($fees['platform'] ?? 0) / 100; // of price
        $paymentRate = ($fees['payment'] ?? 0) / 100;   // of price
        $fixed = (float) ($fees['fixed'] ?? 0);
        $vatRate = ($fees['vat_rate'] ?? 0) / 100;      // of price if not inclusive
        $vatInclusive = (bool) ($fees['vat_inclusive'] ?? false);

        // Effective cost component that scales with price
        $scaling = $platformRate + $paymentRate + ($vatInclusive ? 0 : $vatRate);

        // margin = (p - (cost + scaling*p + fixed)) / p
        // => margin = 1 - (cost + fixed)/p - scaling
        // => (cost + fixed)/p = 1 - scaling - margin
        $den = (1 - $scaling - ($targetMargin / 100));
        if ($den <= 0.0001) {
            // Avoid division by zero; fallback to naive markup
            return round(($cost + $fixed) * 1.5, 2);
        }

        $price = ($cost + $fixed) / $den;
        return round($price, 2);
    }
}

