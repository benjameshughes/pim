<?php

namespace App\ValueObjects;

use Illuminate\Support\Collection;

/**
 * ✅✨ VALIDATION SUMMARY VALUE OBJECT ✨✅
 *
 * Structured validation results for import mapping and data validation,
 * replacing scattered validation arrays with cohesive result handling
 */
readonly class ValidationSummary
{
    public function __construct(
        public bool $isValid,
        public Collection $errors = new Collection,
        public Collection $warnings = new Collection,
        public Collection $recommendations = new Collection,
        public array $statistics = [],
        public array $metadata = []
    ) {}

    /**
     * ✅ Create successful validation summary
     */
    public static function valid(array $statistics = [], array $metadata = []): self
    {
        return new self(
            isValid: true,
            statistics: $statistics,
            metadata: $metadata
        );
    }

    /**
     * ❌ Create failed validation summary
     */
    public static function invalid(
        Collection $errors,
        Collection $warnings = new Collection,
        Collection $recommendations = new Collection,
        array $statistics = [],
        array $metadata = []
    ): self {
        return new self(
            isValid: false,
            errors: $errors,
            warnings: $warnings,
            recommendations: $recommendations,
            statistics: $statistics,
            metadata: $metadata
        );
    }

    /**
     * ⚠️ Create validation with warnings
     */
    public static function withWarnings(
        Collection $warnings,
        Collection $recommendations = new Collection,
        array $statistics = [],
        array $metadata = []
    ): self {
        return new self(
            isValid: true,
            warnings: $warnings,
            recommendations: $recommendations,
            statistics: $statistics,
            metadata: $metadata
        );
    }

    /**
     * 📊 Get validation score (0-100)
     */
    public function getValidationScore(): float
    {
        if (! $this->isValid) {
            return 0.0;
        }

        $maxDeductions = 50; // Maximum points that can be deducted for warnings
        $warningDeduction = min($this->warnings->count() * 5, $maxDeductions);

        return max(0.0, 100.0 - $warningDeduction);
    }

    /**
     * 🎯 Get validation level description
     */
    public function getValidationLevel(): string
    {
        if (! $this->isValid) {
            return 'invalid';
        }

        $score = $this->getValidationScore();

        return match (true) {
            $score >= 90 => 'excellent',
            $score >= 75 => 'good',
            $score >= 60 => 'fair',
            $score >= 40 => 'poor',
            default => 'concerning'
        };
    }

    /**
     * 🎨 Get display color for UI components
     */
    public function getDisplayColor(): string
    {
        if (! $this->isValid) {
            return 'red';
        }

        return match ($this->getValidationLevel()) {
            'excellent' => 'green',
            'good' => 'blue',
            'fair' => 'yellow',
            'poor' => 'orange',
            default => 'red'
        };
    }

    /**
     * 🔍 Check if has critical issues
     */
    public function hasCriticalIssues(): bool
    {
        return ! $this->isValid || $this->errors->isNotEmpty();
    }

    /**
     * ⚠️ Check if has warnings
     */
    public function hasWarnings(): bool
    {
        return $this->warnings->isNotEmpty();
    }

    /**
     * 💡 Check if has recommendations
     */
    public function hasRecommendations(): bool
    {
        return $this->recommendations->isNotEmpty();
    }

    /**
     * 📋 Get comprehensive summary
     */
    public function getSummary(): array
    {
        return [
            'is_valid' => $this->isValid,
            'validation_score' => $this->getValidationScore(),
            'validation_level' => $this->getValidationLevel(),
            'total_errors' => $this->errors->count(),
            'total_warnings' => $this->warnings->count(),
            'total_recommendations' => $this->recommendations->count(),
            'has_critical_issues' => $this->hasCriticalIssues(),
            'statistics' => $this->statistics,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * 📋 Get grouped issues by type
     */
    public function getGroupedIssues(): array
    {
        return [
            'critical' => $this->errors->groupBy('type')->toArray(),
            'warnings' => $this->warnings->groupBy('type')->toArray(),
            'recommendations' => $this->recommendations->groupBy('type')->toArray(),
        ];
    }

    /**
     * 🎯 Get top priority issues (most important to fix first)
     */
    public function getPriorityIssues(int $limit = 5): Collection
    {
        return collect()
            ->concat($this->errors->map(fn ($error) => array_merge($error, ['priority' => 'critical'])))
            ->concat($this->warnings->map(fn ($warning) => array_merge($warning, ['priority' => 'warning'])))
            ->concat($this->recommendations->map(fn ($rec) => array_merge($rec, ['priority' => 'recommendation'])))
            ->sortBy([
                ['priority', 'asc'], // critical first
                ['type', 'asc'],      // then by type
            ])
            ->take($limit)
            ->values();
    }

    /**
     * 📊 Get issue statistics by category
     */
    public function getIssueStatistics(): array
    {
        $allIssues = collect()
            ->concat($this->errors)
            ->concat($this->warnings)
            ->concat($this->recommendations);

        return [
            'total_issues' => $allIssues->count(),
            'critical_issues' => $this->errors->count(),
            'warning_issues' => $this->warnings->count(),
            'recommendation_issues' => $this->recommendations->count(),
            'issues_by_type' => $allIssues->groupBy('type')->map->count()->toArray(),
            'most_common_issue' => $allIssues->groupBy('type')->sortByDesc->count()->keys()->first(),
        ];
    }

    /**
     * 🎨 Get display data for UI components
     */
    public function getDisplayData(): array
    {
        return [
            'summary' => $this->getSummary(),
            'color' => $this->getDisplayColor(),
            'icon' => $this->getStatusIcon(),
            'title' => $this->getStatusTitle(),
            'description' => $this->getStatusDescription(),
            'priority_issues' => $this->getPriorityIssues(),
            'issue_statistics' => $this->getIssueStatistics(),
            'actions' => $this->getRecommendedActions(),
        ];
    }

    /**
     * 🎯 Get status icon for UI
     */
    public function getStatusIcon(): string
    {
        if (! $this->isValid) {
            return 'x-circle';
        }

        return match ($this->getValidationLevel()) {
            'excellent' => 'check-circle',
            'good' => 'check-circle',
            'fair' => 'exclamation-triangle',
            'poor' => 'exclamation-triangle',
            default => 'x-circle'
        };
    }

    /**
     * 📋 Get status title
     */
    public function getStatusTitle(): string
    {
        if (! $this->isValid) {
            return 'Validation Failed';
        }

        return match ($this->getValidationLevel()) {
            'excellent' => 'Excellent Validation',
            'good' => 'Good Validation',
            'fair' => 'Fair Validation',
            'poor' => 'Poor Validation',
            default => 'Validation Issues'
        };
    }

    /**
     * 📝 Get status description
     */
    public function getStatusDescription(): string
    {
        if (! $this->isValid) {
            return 'There are validation errors that must be fixed before proceeding.';
        }

        if ($this->warnings->isNotEmpty()) {
            return "Validation passed with {$this->warnings->count()} warning(s). Review recommended improvements.";
        }

        return 'Validation passed successfully. Ready to proceed.';
    }

    /**
     * 🎯 Get recommended actions
     */
    public function getRecommendedActions(): array
    {
        $actions = [];

        if (! $this->isValid) {
            $actions[] = [
                'type' => 'fix_errors',
                'title' => 'Fix Validation Errors',
                'description' => 'Resolve all validation errors before proceeding',
                'priority' => 'high',
            ];
        }

        if ($this->warnings->isNotEmpty()) {
            $actions[] = [
                'type' => 'review_warnings',
                'title' => 'Review Warnings',
                'description' => 'Address warnings to improve data quality',
                'priority' => 'medium',
            ];
        }

        if ($this->recommendations->isNotEmpty()) {
            $actions[] = [
                'type' => 'apply_recommendations',
                'title' => 'Apply Recommendations',
                'description' => 'Consider implementing suggested improvements',
                'priority' => 'low',
            ];
        }

        return $actions;
    }

    /**
     * 📊 Convert to array for API responses
     */
    public function toArray(): array
    {
        return [
            'is_valid' => $this->isValid,
            'validation_score' => $this->getValidationScore(),
            'validation_level' => $this->getValidationLevel(),
            'errors' => $this->errors->toArray(),
            'warnings' => $this->warnings->toArray(),
            'recommendations' => $this->recommendations->toArray(),
            'statistics' => $this->statistics,
            'metadata' => $this->metadata,
            'summary' => $this->getSummary(),
        ];
    }

    /**
     * 🔄 Merge with another validation summary
     */
    public function merge(ValidationSummary $other): self
    {
        return new self(
            isValid: $this->isValid && $other->isValid,
            errors: $this->errors->concat($other->errors),
            warnings: $this->warnings->concat($other->warnings),
            recommendations: $this->recommendations->concat($other->recommendations),
            statistics: array_merge($this->statistics, $other->statistics),
            metadata: array_merge($this->metadata, $other->metadata)
        );
    }

    /**
     * 🎯 Create from mapping validation results
     */
    public static function fromMappingValidation(array $mappingData): self
    {
        $errors = collect();
        $warnings = collect();
        $recommendations = collect();

        // Check for conflicts
        if (! empty($mappingData['conflicts'])) {
            foreach ($mappingData['conflicts'] as $conflict) {
                $errors->push([
                    'type' => 'mapping_conflict',
                    'message' => "Field '{$conflict['field']}' is mapped multiple times",
                    'details' => $conflict,
                ]);
            }
        }

        // Check for missing required fields
        if (! empty($mappingData['missing_required'])) {
            foreach ($mappingData['missing_required'] as $field) {
                $errors->push([
                    'type' => 'missing_required',
                    'message' => "Required field '{$field}' is not mapped",
                    'field' => $field,
                ]);
            }
        }

        // Check for low mapping coverage
        $statistics = $mappingData['statistics'] ?? [];
        if (($statistics['mapping_percentage'] ?? 0) < 50) {
            $warnings->push([
                'type' => 'low_coverage',
                'message' => 'Low field mapping coverage - consider mapping more fields',
                'percentage' => $statistics['mapping_percentage'] ?? 0,
            ]);
        }

        // Add recommendations based on unmapped fields
        if (($statistics['unmapped_headers'] ?? 0) > 0) {
            $recommendations->push([
                'type' => 'unmapped_fields',
                'message' => 'Consider mapping or skipping unmapped fields',
                'count' => $statistics['unmapped_headers'],
            ]);
        }

        return new self(
            isValid: $errors->isEmpty(),
            errors: $errors,
            warnings: $warnings,
            recommendations: $recommendations,
            statistics: $statistics
        );
    }
}
