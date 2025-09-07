<?php

namespace App\Services\Marketplace\Adapters;

/**
 * 🛍️ DEBENHAMS ADAPTER
 *
 * Extends BaseMiraklAdapter with Debenhams-specific defaults.
 */
class DebenhamsAdapter extends BaseMiraklAdapter
{
    protected function getMarketplaceName(): string
    {
        return 'debenhams';
    }

    protected function getOperatorCode(): string
    {
        return 'debenhams';
    }

    protected function getDefaultLogisticClass(): string
    {
        return (string) $this->fromAccount('logistic_class', 'STD');
    }

    protected function getDefaultLeadtime(): int
    {
        return (int) $this->fromAccount('leadtime_to_ship', 3);
    }

    protected function getProductIdType(): string
    {
        // Debenhams often expects EANs; allow override via account config
        return (string) $this->fromAccount('product_id_type', 'EAN');
    }
}

