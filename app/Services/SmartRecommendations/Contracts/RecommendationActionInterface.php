<?php

namespace App\Services\SmartRecommendations\Contracts;

interface RecommendationActionInterface
{
    /**
     * Execute the recommendation action
     */
    public function execute(array $variantIds): bool;

    /**
     * Get preview of what this action will do
     */
    public function getPreview(array $variantIds): array;

    /**
     * Check if this action can be executed safely
     */
    public function canExecute(array $variantIds): bool;

    /**
     * Get the action type identifier
     */
    public function getType(): string;

    /**
     * Get human-readable action name
     */
    public function getName(): string;
}
