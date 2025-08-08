<?php

namespace App\Services\SmartRecommendations\DTOs;

use Illuminate\Support\Collection;

class RecommendationCollection
{
    protected Collection $recommendations;

    public function __construct(Collection $recommendations)
    {
        $this->recommendations = $recommendations;
    }

    /**
     * Get all recommendations sorted by priority and score
     */
    public function all(): Collection
    {
        return $this->recommendations
            ->sortByDesc(fn (Recommendation $r) => $r->getPriorityLevel())
            ->sortByDesc(fn (Recommendation $r) => $r->getScore());
    }

    /**
     * Get critical recommendations only
     */
    public function getCritical(): Collection
    {
        return $this->recommendations->filter(fn (Recommendation $r) => $r->isCritical());
    }

    /**
     * Get quick wins only
     */
    public function getQuickWins(): Collection
    {
        return $this->recommendations->filter(fn (Recommendation $r) => $r->isQuickWin());
    }

    /**
     * Get recommendations by type
     */
    public function getByType(string $type): Collection
    {
        return $this->recommendations->filter(fn (Recommendation $r) => $r->type === $type);
    }

    /**
     * Find specific recommendation by ID
     */
    public function find(string $id): ?Recommendation
    {
        return $this->recommendations->first(fn (Recommendation $r) => $r->id === $id);
    }

    /**
     * Calculate overall data health score (0-100)
     */
    public function calculateHealthScore(): int
    {
        if ($this->recommendations->isEmpty()) {
            return 100;
        }

        $criticalCount = $this->getCritical()->count();
        $totalCount = $this->recommendations->count();

        // Critical issues heavily impact score
        $criticalPenalty = $criticalCount * 20;
        $generalPenalty = ($totalCount - $criticalCount) * 5;

        $score = 100 - min($criticalPenalty + $generalPenalty, 95);

        return max($score, 5); // Minimum 5% health score
    }

    /**
     * Get summary statistics
     */
    public function getSummary(): array
    {
        return [
            'total' => $this->recommendations->count(),
            'critical' => $this->getCritical()->count(),
            'quick_wins' => $this->getQuickWins()->count(),
            'health_score' => $this->calculateHealthScore(),
            'types' => $this->recommendations
                ->groupBy('type')
                ->map(fn (Collection $group) => $group->count())
                ->toArray(),
        ];
    }

    /**
     * Convert to array for API responses
     */
    public function toArray(): array
    {
        return [
            'summary' => $this->getSummary(),
            'recommendations' => $this->all()->map(fn (Recommendation $r) => $r->toArray())->values(),
        ];
    }

    /**
     * Check if collection is empty
     */
    public function isEmpty(): bool
    {
        return $this->recommendations->isEmpty();
    }

    /**
     * Get count of recommendations
     */
    public function count(): int
    {
        return $this->recommendations->count();
    }

    /**
     * Filter recommendations using a callback
     */
    public function filter(callable $callback): Collection
    {
        return $this->recommendations->filter($callback);
    }
}
