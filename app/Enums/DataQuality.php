<?php

namespace App\Enums;

/**
 * 📊✨ DATA QUALITY ASSESSMENT ✨📊
 *
 * Defines data quality levels for CSV analysis, providing consistent
 * quality scoring and recommendations across the import system
 */
enum DataQuality: string
{
    case EXCELLENT = 'excellent';
    case GOOD = 'good';
    case FAIR = 'fair';
    case POOR = 'poor';
    case UNUSABLE = 'unusable';

    /**
     * 🎨 Get quality display information
     */
    public function getDisplayData(): array
    {
        return match ($this) {
            self::EXCELLENT => [
                'label' => 'Excellent',
                'color' => 'emerald',
                'icon' => 'shield-check',
                'description' => 'High quality data ready for import',
                'score_range' => '90-100%',
            ],
            self::GOOD => [
                'label' => 'Good',
                'color' => 'green',
                'icon' => 'check-circle',
                'description' => 'Good quality data with minor issues',
                'score_range' => '75-89%',
            ],
            self::FAIR => [
                'label' => 'Fair',
                'color' => 'yellow',
                'icon' => 'exclamation-triangle',
                'description' => 'Acceptable quality but may need review',
                'score_range' => '50-74%',
            ],
            self::POOR => [
                'label' => 'Poor',
                'color' => 'orange',
                'icon' => 'exclamation-circle',
                'description' => 'Poor quality data requiring attention',
                'score_range' => '25-49%',
            ],
            self::UNUSABLE => [
                'label' => 'Unusable',
                'color' => 'red',
                'icon' => 'x-circle',
                'description' => 'Data quality too low for reliable import',
                'score_range' => '0-24%',
            ]
        };
    }

    /**
     * 🎯 Determine quality from completeness and confidence scores
     */
    public static function fromScores(float $completeness, float $confidence, float $uniqueness = 1.0): self
    {
        // Weighted quality score
        $qualityScore = (
            $completeness * 0.5 +    // 50% weight on completeness
            $confidence * 0.3 +      // 30% weight on confidence
            $uniqueness * 0.2        // 20% weight on uniqueness
        ) * 100;

        return match (true) {
            $qualityScore >= 90 => self::EXCELLENT,
            $qualityScore >= 75 => self::GOOD,
            $qualityScore >= 50 => self::FAIR,
            $qualityScore >= 25 => self::POOR,
            default => self::UNUSABLE
        };
    }

    /**
     * 🔢 Get minimum quality score for this level
     */
    public function getMinScore(): float
    {
        return match ($this) {
            self::EXCELLENT => 90.0,
            self::GOOD => 75.0,
            self::FAIR => 50.0,
            self::POOR => 25.0,
            self::UNUSABLE => 0.0
        };
    }

    /**
     * 🔢 Get maximum quality score for this level
     */
    public function getMaxScore(): float
    {
        return match ($this) {
            self::EXCELLENT => 100.0,
            self::GOOD => 89.9,
            self::FAIR => 74.9,
            self::POOR => 49.9,
            self::UNUSABLE => 24.9
        };
    }

    /**
     * ✅ Check if quality level is acceptable for import
     */
    public function isAcceptable(): bool
    {
        return in_array($this, [self::EXCELLENT, self::GOOD, self::FAIR]);
    }

    /**
     * ⚠️ Check if quality level requires warning
     */
    public function requiresWarning(): bool
    {
        return in_array($this, [self::POOR, self::UNUSABLE]);
    }

    /**
     * 🚫 Check if quality level should block import
     */
    public function shouldBlockImport(): bool
    {
        return $this === self::UNUSABLE;
    }

    /**
     * 💡 Get quality improvement recommendations
     */
    public function getRecommendations(): array
    {
        return match ($this) {
            self::EXCELLENT => [
                'Data quality is excellent - ready for import',
                'Continue with confidence',
            ],
            self::GOOD => [
                'Good data quality with minor issues',
                'Review any warnings but safe to proceed',
                'Consider addressing minor quality issues for optimal results',
            ],
            self::FAIR => [
                'Acceptable data quality but could be improved',
                'Review data completeness and accuracy',
                'Consider data cleanup before import for better results',
            ],
            self::POOR => [
                'Poor data quality detected - proceed with caution',
                'Significant data cleanup recommended before import',
                'Review missing values and data consistency',
                'Consider manual data validation',
            ],
            self::UNUSABLE => [
                'Data quality too low for reliable import',
                'Data cleanup required before proceeding',
                'Check for corrupt or incomplete data',
                'Consider alternative data sources',
            ]
        };
    }

    /**
     * 🎯 Get specific quality issues based on level
     */
    public function getTypicalIssues(): array
    {
        return match ($this) {
            self::EXCELLENT => [],
            self::GOOD => [
                'Minor formatting inconsistencies',
                'Occasional missing optional fields',
            ],
            self::FAIR => [
                'Moderate data completeness issues',
                'Some formatting inconsistencies',
                'Missing non-critical information',
            ],
            self::POOR => [
                'Significant missing data',
                'Inconsistent formatting',
                'Duplicate or invalid entries',
                'Poor data structure',
            ],
            self::UNUSABLE => [
                'Majority of data missing or corrupt',
                'Severe formatting problems',
                'Unreadable or malformed content',
                'Critical fields completely empty',
            ]
        };
    }

    /**
     * 🎨 Get CSS badge classes for UI display
     */
    public function getBadgeClasses(): string
    {
        $displayData = $this->getDisplayData();
        $color = $displayData['color'];

        return "inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-{$color}-100 text-{$color}-800";
    }

    /**
     * 📊 Get quality metrics thresholds
     */
    public function getThresholds(): array
    {
        return match ($this) {
            self::EXCELLENT => [
                'min_completeness' => 95.0,
                'min_confidence' => 85.0,
                'min_uniqueness' => 90.0,
                'max_error_rate' => 2.0,
            ],
            self::GOOD => [
                'min_completeness' => 85.0,
                'min_confidence' => 70.0,
                'min_uniqueness' => 75.0,
                'max_error_rate' => 5.0,
            ],
            self::FAIR => [
                'min_completeness' => 70.0,
                'min_confidence' => 50.0,
                'min_uniqueness' => 60.0,
                'max_error_rate' => 10.0,
            ],
            self::POOR => [
                'min_completeness' => 50.0,
                'min_confidence' => 30.0,
                'min_uniqueness' => 40.0,
                'max_error_rate' => 20.0,
            ],
            self::UNUSABLE => [
                'min_completeness' => 0.0,
                'min_confidence' => 0.0,
                'min_uniqueness' => 0.0,
                'max_error_rate' => 100.0,
            ]
        };
    }

    /**
     * ⏰ Get recommended action timeline
     */
    public function getActionTimeline(): string
    {
        return match ($this) {
            self::EXCELLENT => 'Import immediately',
            self::GOOD => 'Import with minor review',
            self::FAIR => 'Review within 1 hour before import',
            self::POOR => 'Data cleanup required (1-4 hours)',
            self::UNUSABLE => 'Major data work needed (4+ hours)'
        };
    }

    /**
     * 🎯 Get import success probability
     */
    public function getSuccessProbability(): float
    {
        return match ($this) {
            self::EXCELLENT => 0.98,
            self::GOOD => 0.90,
            self::FAIR => 0.75,
            self::POOR => 0.50,
            self::UNUSABLE => 0.20
        };
    }

    /**
     * 📈 Get expected import efficiency
     */
    public function getImportEfficiency(): array
    {
        return match ($this) {
            self::EXCELLENT => [
                'success_rate' => '95-99%',
                'processing_speed' => 'Fast',
                'manual_intervention' => 'Minimal',
                'expected_issues' => 'Very few',
            ],
            self::GOOD => [
                'success_rate' => '85-94%',
                'processing_speed' => 'Good',
                'manual_intervention' => 'Low',
                'expected_issues' => 'Minor',
            ],
            self::FAIR => [
                'success_rate' => '70-84%',
                'processing_speed' => 'Moderate',
                'manual_intervention' => 'Moderate',
                'expected_issues' => 'Some',
            ],
            self::POOR => [
                'success_rate' => '50-69%',
                'processing_speed' => 'Slow',
                'manual_intervention' => 'High',
                'expected_issues' => 'Many',
            ],
            self::UNUSABLE => [
                'success_rate' => '0-49%',
                'processing_speed' => 'Very slow',
                'manual_intervention' => 'Extensive',
                'expected_issues' => 'Critical',
            ]
        };
    }
}
