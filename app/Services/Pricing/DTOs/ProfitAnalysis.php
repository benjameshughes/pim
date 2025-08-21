<?php

namespace App\Services\Pricing\DTOs;

use Illuminate\Support\Number;

/**
 * 📊🎭 PROFIT ANALYSIS DTO - THE FINANCIAL INSIGHTS QUEEN! 🎭📊
 *
 * This DTO is SERVING major analytical excellence!
 * Profit insights, margin analysis, ROI calculations - pure FINANCIAL SASS! ✨
 */
readonly class ProfitAnalysis
{
    public function __construct(
        public float $revenue,
        public float $totalCosts,
        public float $profit,
        public float $profitMargin,
        public float $roi,
        public array $costBreakdown,
        public string $currency,
        public array $metadata = []
    ) {}

    /**
     * 🎯 IS PROFITABLE - Simple profitability check
     */
    public function getIsProfitableAttribute(): bool
    {
        return $this->profit > 0;
    }

    /**
     * 📈 PROFITABILITY LEVEL - Categorize profitability
     */
    public function getProfitabilityLevelAttribute(): string
    {
        return match (true) {
            $this->profitMargin >= 50 => 'excellent',
            $this->profitMargin >= 30 => 'very_good',
            $this->profitMargin >= 20 => 'good',
            $this->profitMargin >= 10 => 'fair',
            $this->profitMargin > 0 => 'low',
            default => 'loss_making',
        };
    }

    /**
     * 🎨 PROFITABILITY COLOR - UI color coding
     */
    public function getProfitabilityColorAttribute(): string
    {
        return match ($this->profitabilityLevel) {
            'excellent' => 'emerald',
            'very_good' => 'green',
            'good' => 'lime',
            'fair' => 'yellow',
            'low' => 'orange',
            'loss_making' => 'red',
            default => 'gray',
        };
    }

    /**
     * 💎 BREAK EVEN POINT - Units needed to break even
     */
    public function getBreakEvenUnitsAttribute(): int
    {
        if ($this->profit <= 0) {
            return 0;
        }

        $contributionMargin = $this->revenue - $this->costBreakdown['base_cost'];

        return $contributionMargin > 0 ? (int) ceil($this->totalCosts / $contributionMargin) : 0;
    }

    /**
     * 🚀 TARGET PRICE FOR MARGIN - Price needed to achieve target margin
     */
    public function targetPriceForMargin(float $targetMargin): float
    {
        if ($targetMargin >= 100) {
            return 0; // Invalid target
        }

        return $this->totalCosts / (1 - ($targetMargin / 100));
    }

    /**
     * 📊 COST EFFICIENCY - How efficiently costs are managed
     */
    public function getCostEfficiencyAttribute(): array
    {
        $totalCosts = $this->totalCosts;
        $percentages = [];

        foreach ($this->costBreakdown as $costType => $amount) {
            $percentages[$costType] = $totalCosts > 0 ? ($amount / $totalCosts) * 100 : 0;
        }

        return [
            'percentages' => $percentages,
            'largest_cost' => array_keys($percentages, max($percentages))[0] ?? 'none',
            'optimization_opportunity' => $this->identifyOptimizationOpportunity($percentages),
        ];
    }

    /**
     * 📈 PERFORMANCE METRICS - Key performance indicators
     */
    public function getPerformanceMetricsAttribute(): array
    {
        return [
            'gross_profit_margin' => $this->profitMargin,
            'return_on_investment' => $this->roi,
            'cost_to_revenue_ratio' => $this->revenue > 0 ? ($this->totalCosts / $this->revenue) * 100 : 0,
            'markup_percentage' => $this->totalCosts > 0 ? (($this->revenue - $this->totalCosts) / $this->totalCosts) * 100 : 0,
            'contribution_margin' => $this->revenue - ($this->costBreakdown['base_cost'] ?? 0),
            'efficiency_score' => $this->calculateEfficiencyScore(),
        ];
    }

    /**
     * 🎨 FORMATTED VALUES - Beautiful display formatting
     */
    public function getFormattedAttribute(): array
    {
        return [
            'revenue' => Number::currency($this->revenue, in: $this->currency),
            'total_costs' => Number::currency($this->totalCosts, in: $this->currency),
            'profit' => Number::currency($this->profit, in: $this->currency),
            'profit_margin' => number_format($this->profitMargin, 1).'%',
            'roi' => number_format($this->roi, 1).'%',
            'cost_breakdown' => array_map(
                fn ($amount) => Number::currency($amount, in: $this->currency),
                $this->costBreakdown
            ),
        ];
    }

    /**
     * ⚠️ RISK ASSESSMENT - Financial risk indicators
     */
    public function getRiskAssessmentAttribute(): array
    {
        $risks = [];
        $riskLevel = 'low';

        if ($this->profitMargin < 5) {
            $risks[] = 'Very low profit margin - vulnerable to cost increases';
            $riskLevel = 'high';
        } elseif ($this->profitMargin < 15) {
            $risks[] = 'Low profit margin - limited flexibility for market changes';
            $riskLevel = 'medium';
        }

        if ($this->roi < 10) {
            $risks[] = 'Low return on investment - consider better opportunities';
            $riskLevel = $riskLevel === 'high' ? 'high' : 'medium';
        }

        $costStructure = $this->costEfficiency;
        if ($costStructure['percentages']['base_cost'] > 80) {
            $risks[] = 'High cost base - vulnerable to supplier price changes';
            $riskLevel = $riskLevel === 'high' ? 'high' : 'medium';
        }

        return [
            'level' => $riskLevel,
            'risks' => $risks,
            'score' => $this->calculateRiskScore(),
        ];
    }

    /**
     * 🎯 IMPROVEMENT SUGGESTIONS - AI-powered recommendations
     */
    public function getImprovementSuggestionsAttribute(): array
    {
        $suggestions = [];

        if ($this->profitMargin < 20) {
            $suggestions[] = [
                'type' => 'price_increase',
                'title' => 'Consider Price Increase',
                'description' => 'Increase price to improve profit margin',
                'potential_impact' => 'Could improve margin to 25%',
                'suggested_action' => 'Increase price by '.Number::currency(
                    $this->targetPriceForMargin(25) - $this->revenue,
                    in: $this->currency
                ),
            ];
        }

        $costEfficiency = $this->costEfficiency;
        if ($costEfficiency['percentages']['platform_fee'] > 15) {
            $suggestions[] = [
                'type' => 'platform_optimization',
                'title' => 'High Platform Fees',
                'description' => 'Platform fees are consuming significant margin',
                'potential_impact' => 'Could save '.Number::currency(
                    $this->costBreakdown['platform_fee'] * 0.3,
                    in: $this->currency
                ),
                'suggested_action' => 'Consider negotiating better rates or alternative platforms',
            ];
        }

        if ($this->roi < 15) {
            $suggestions[] = [
                'type' => 'cost_reduction',
                'title' => 'Cost Optimization',
                'description' => 'ROI is below optimal levels',
                'potential_impact' => 'Target 20% cost reduction',
                'suggested_action' => 'Review and optimize cost structure',
            ];
        }

        return $suggestions;
    }

    /**
     * 📊 TO ARRAY - Convert to array for JSON/API responses
     */
    public function toArray(): array
    {
        return [
            'revenue' => $this->revenue,
            'total_costs' => $this->totalCosts,
            'profit' => $this->profit,
            'profit_margin' => $this->profitMargin,
            'roi' => $this->roi,
            'currency' => $this->currency,
            'is_profitable' => $this->isProfitable,
            'profitability_level' => $this->profitabilityLevel,
            'profitability_color' => $this->profitabilityColor,
            'break_even_units' => $this->breakEvenUnits,
            'cost_breakdown' => $this->costBreakdown,
            'cost_efficiency' => $this->costEfficiency,
            'performance_metrics' => $this->performanceMetrics,
            'risk_assessment' => $this->riskAssessment,
            'improvement_suggestions' => $this->improvementSuggestions,
            'formatted' => $this->formatted,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * 🔍 IDENTIFY OPTIMIZATION OPPORTUNITY
     */
    protected function identifyOptimizationOpportunity(array $costPercentages): string
    {
        $highestCost = max($costPercentages);

        if ($highestCost > 50) {
            $costType = array_keys($costPercentages, $highestCost)[0];

            return "Focus on reducing {$costType} which represents ".number_format($highestCost, 1).'% of total costs';
        }

        return 'Cost structure is well balanced';
    }

    /**
     * ⚡ CALCULATE EFFICIENCY SCORE
     */
    protected function calculateEfficiencyScore(): float
    {
        $score = 0;

        // Profit margin component (0-40 points)
        $score += min(40, $this->profitMargin * 2);

        // ROI component (0-30 points)
        $score += min(30, $this->roi * 1.5);

        // Cost efficiency component (0-30 points)
        $costStructure = $this->costEfficiency;
        if ($costStructure['percentages']['base_cost'] < 70) {
            $score += 30;
        } elseif ($costStructure['percentages']['base_cost'] < 80) {
            $score += 20;
        } else {
            $score += 10;
        }

        return min(100, $score);
    }

    /**
     * ⚠️ CALCULATE RISK SCORE
     */
    protected function calculateRiskScore(): float
    {
        $risk = 0;

        if ($this->profitMargin < 5) {
            $risk += 40;
        } elseif ($this->profitMargin < 15) {
            $risk += 20;
        }

        if ($this->roi < 10) {
            $risk += 30;
        } elseif ($this->roi < 20) {
            $risk += 15;
        }

        $costStructure = $this->costEfficiency;
        if ($costStructure['percentages']['base_cost'] > 80) {
            $risk += 30;
        } elseif ($costStructure['percentages']['base_cost'] > 70) {
            $risk += 15;
        }

        return min(100, $risk);
    }

    /**
     * 🎭 MAGIC GETTERS - Access calculated properties
     */
    public function __get(string $property): mixed
    {
        return match ($property) {
            'isProfitable' => $this->getIsProfitableAttribute(),
            'profitabilityLevel' => $this->getProfitabilityLevelAttribute(),
            'profitabilityColor' => $this->getProfitabilityColorAttribute(),
            'breakEvenUnits' => $this->getBreakEvenUnitsAttribute(),
            'costEfficiency' => $this->getCostEfficiencyAttribute(),
            'performanceMetrics' => $this->getPerformanceMetricsAttribute(),
            'formatted' => $this->getFormattedAttribute(),
            'riskAssessment' => $this->getRiskAssessmentAttribute(),
            'improvementSuggestions' => $this->getImprovementSuggestionsAttribute(),
            default => null,
        };
    }
}
