<?php

namespace App\Services\Pricing\Actions;

/**
 * RecalculateProfitAction
 *
 * Refresh any derived profitability fields after pricing changes.
 */
class RecalculateProfitAction
{
    /**
     * Recompute for the given variant IDs in the target sales channel.
     */
    public function execute(array $variantIds, ?int $salesChannelId): void
    {
        // Scaffolding: implement recalculation using Pricing model later.
    }
}

