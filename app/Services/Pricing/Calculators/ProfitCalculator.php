<?php

namespace App\Services\Pricing\Calculators;

/**
 * ProfitCalculator
 *
 * Provides profitability calculations (profit amount, margin %, ROI %)
 * given a selling price, cost price, and optional fee context.
 */
class ProfitCalculator
{
    /**
     * Calculate profitability metrics.
     * fees: ['platform' => %, 'payment' => %, 'fixed' => amount, 'vat_rate' => %]
     */
    public function calculate(float $price, float $cost, array $fees = []): array
    {
        $platform = ($fees['platform'] ?? 0) / 100 * $price;
        $payment = ($fees['payment'] ?? 0) / 100 * $price;
        $fixed = (float) ($fees['fixed'] ?? 0);
        $vat = ($fees['vat_rate'] ?? 0) > 0 && !($fees['vat_inclusive'] ?? false)
            ? ($fees['vat_rate'] / 100) * $price
            : 0.0;

        $totalCosts = $cost + $platform + $payment + $fixed + $vat;
        $profit = $price - $totalCosts;
        $margin = $price > 0 ? ($profit / $price) * 100 : 0.0;
        $roi = $totalCosts > 0 ? ($profit / $totalCosts) * 100 : 0.0;

        return [
            'price' => $price,
            'cost' => $cost,
            'platform_fee' => $platform,
            'payment_fee' => $payment,
            'fixed_fee' => $fixed,
            'vat' => $vat,
            'total_costs' => $totalCosts,
            'profit' => $profit,
            'margin_percent' => $margin,
            'roi_percent' => $roi,
        ];
    }

    /**
     * Example placeholder used by scaffolding while integrating reads.
     */
    public function example(): array
    {
        return $this->calculate(19.99, 8.50, ['platform' => 10, 'payment' => 2.9, 'fixed' => 0.3]);
    }
}

