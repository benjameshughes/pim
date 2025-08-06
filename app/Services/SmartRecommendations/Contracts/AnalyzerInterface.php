<?php

namespace App\Services\SmartRecommendations\Contracts;

use Illuminate\Support\Collection;

interface AnalyzerInterface
{
    /**
     * Analyze the given variant IDs and return recommendations
     * 
     * @param array $variantIds Empty array means analyze all variants
     * @return Collection<Recommendation>
     */
    public function analyze(array $variantIds = []): Collection;

    /**
     * Get the analyzer type identifier
     */
    public function getType(): string;

    /**
     * Get human-readable analyzer name
     */
    public function getName(): string;
}