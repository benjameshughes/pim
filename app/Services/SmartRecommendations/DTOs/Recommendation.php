<?php

namespace App\Services\SmartRecommendations\DTOs;

use App\Services\SmartRecommendations\Contracts\RecommendationActionInterface;

class Recommendation
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $priority, // critical, high, medium, low
        public readonly string $title,
        public readonly string $description,
        public readonly int $affectedCount,
        public readonly int $impactScore, // 1-100
        public readonly int $effortScore, // 1-100 (lower = easier)
        public readonly array $metadata,
        public readonly RecommendationActionInterface $action,
    ) {}

    /**
     * Get priority level as integer for sorting
     */
    public function getPriorityLevel(): int
    {
        return match ($this->priority) {
            'critical' => 4,
            'high' => 3,
            'medium' => 2,
            'low' => 1,
            default => 0,
        };
    }

    /**
     * Calculate overall score (impact vs effort)
     */
    public function getScore(): float
    {
        // Higher impact, lower effort = better score
        return ($this->impactScore / max($this->effortScore, 1)) * 10;
    }

    /**
     * Check if this is a quick win (high impact, low effort)
     */
    public function isQuickWin(): bool
    {
        return $this->impactScore >= 70 && $this->effortScore <= 30;
    }

    /**
     * Check if this is a critical issue
     */
    public function isCritical(): bool
    {
        return $this->priority === 'critical';
    }

    /**
     * Get the executable action
     */
    public function getAction(): RecommendationActionInterface
    {
        return $this->action;
    }

    /**
     * Get estimated time to complete
     */
    public function getEstimatedTime(): string
    {
        return match (true) {
            $this->effortScore <= 20 => 'Instant',
            $this->effortScore <= 40 => '< 5 minutes',
            $this->effortScore <= 70 => '< 30 minutes',
            default => '> 1 hour',
        };
    }

    /**
     * Convert to array for API/JSON responses
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'priority' => $this->priority,
            'title' => $this->title,
            'description' => $this->description,
            'affected_count' => $this->affectedCount,
            'impact_score' => $this->impactScore,
            'effort_score' => $this->effortScore,
            'score' => $this->getScore(),
            'estimated_time' => $this->getEstimatedTime(),
            'is_quick_win' => $this->isQuickWin(),
            'is_critical' => $this->isCritical(),
            'metadata' => $this->metadata,
        ];
    }
}
