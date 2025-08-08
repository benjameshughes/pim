<?php

namespace App\Services\SmartRecommendations;

use App\Services\SmartRecommendations\Analyzers\ConsistencyAnalyzer;
use App\Services\SmartRecommendations\Analyzers\DataCompletenessAnalyzer;
use App\Services\SmartRecommendations\Analyzers\MarketplaceReadinessAnalyzer;
use App\Services\SmartRecommendations\DTOs\RecommendationCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SmartRecommendationsService
{
    public function __construct(
        protected DataCompletenessAnalyzer $completenessAnalyzer,
        protected MarketplaceReadinessAnalyzer $marketplaceAnalyzer,
        protected ConsistencyAnalyzer $consistencyAnalyzer,
    ) {}

    /**
     * Get all smart recommendations for the given variants
     */
    public function getRecommendations(array $variantIds = []): RecommendationCollection
    {
        $cacheKey = 'smart_recommendations_'.md5(serialize($variantIds));

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($variantIds) {
            $recommendations = collect();

            // Run all analyzers
            $recommendations = $recommendations->merge(
                $this->completenessAnalyzer->analyze($variantIds)
            );

            $recommendations = $recommendations->merge(
                $this->marketplaceAnalyzer->analyze($variantIds)
            );

            $recommendations = $recommendations->merge(
                $this->consistencyAnalyzer->analyze($variantIds)
            );

            return new RecommendationCollection($recommendations);
        });
    }

    /**
     * Get quick wins - high impact, low effort recommendations
     */
    public function getQuickWins(array $variantIds = []): Collection
    {
        $recommendations = $this->getRecommendations($variantIds);

        return $recommendations->getQuickWins();
    }

    /**
     * Get critical issues that need immediate attention
     */
    public function getCriticalIssues(array $variantIds = []): Collection
    {
        $recommendations = $this->getRecommendations($variantIds);

        return $recommendations->getCritical();
    }

    /**
     * Execute a specific recommendation action
     */
    public function executeRecommendation(string $recommendationId, array $variantIds): bool
    {
        $recommendations = $this->getRecommendations($variantIds);
        $recommendation = $recommendations->find($recommendationId);

        if (! $recommendation) {
            return false;
        }

        return $recommendation->getAction()->execute($variantIds);
    }

    /**
     * Get overall data health score (0-100)
     */
    public function getDataHealthScore(array $variantIds = []): int
    {
        $recommendations = $this->getRecommendations($variantIds);

        return $recommendations->calculateHealthScore();
    }

    /**
     * Clear recommendations cache
     */
    public function clearCache(): void
    {
        Cache::forget('smart_recommendations_*');
    }
}
