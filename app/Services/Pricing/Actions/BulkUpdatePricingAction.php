<?php

namespace App\Services\Pricing\Actions;

/**
 * BulkUpdatePricingAction
 *
 * Applies pricing updates to many variants in one go. The map allows
 * variant‑level field differences (e.g., different prices).
 */
class BulkUpdatePricingAction
{
    /**
     * @param array<int> $variantIds Scope variant IDs
     * @param array<int, array<string,mixed>> $map Per‑variant fields map
     */
    public function execute(array $variantIds, ?int $salesChannelId, array $map): void
    {
        if (! $salesChannelId) {
            return;
        }
        $single = app(UpdateVariantPriceAction::class);
        foreach ($variantIds as $vid) {
            $fields = $map[$vid] ?? [];
            if (!empty($fields)) {
                $single->execute($vid, $salesChannelId, $fields);
            }
        }
    }
}
