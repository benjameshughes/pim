<?php

namespace App\Services\SmartRecommendations\Actions;

use App\Services\SmartRecommendations\Contracts\RecommendationActionInterface;
use Illuminate\Support\Facades\Log;

abstract class BaseRecommendationAction implements RecommendationActionInterface
{
    /**
     * Execute the action with error handling and logging
     */
    public function execute(array $variantIds): bool
    {
        try {
            Log::info("Executing recommendation action: {$this->getType()}", [
                'action' => $this->getType(),
                'variant_count' => count($variantIds),
                'variants' => $variantIds,
            ]);

            $result = $this->performAction($variantIds);

            if ($result) {
                Log::info("Recommendation action completed successfully: {$this->getType()}");
            } else {
                Log::warning("Recommendation action failed: {$this->getType()}");
            }

            return $result;

        } catch (\Exception $e) {
            Log::error("Recommendation action error: {$this->getType()}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Check if action can be executed (default implementation)
     */
    public function canExecute(array $variantIds): bool
    {
        return ! empty($variantIds);
    }

    /**
     * The actual action implementation (to be overridden)
     */
    abstract protected function performAction(array $variantIds): bool;
}
