<?php

namespace App\Services\Pricing\Actions;

/**
 * CopyPricingBetweenChannelsAction
 *
 * Copies pricing from one sales channel code to another for a set of variants.
 */
class CopyPricingBetweenChannelsAction
{
    /**
     * Copy pricing from $sourceChannelCode to $targetChannelCode for $variantIds.
     */
    public function execute(array $variantIds, string $sourceChannelCode, ?string $targetChannelCode): void
    {
        // Scaffolding: implement select from source channel and upsert into target channel later.
    }
}

