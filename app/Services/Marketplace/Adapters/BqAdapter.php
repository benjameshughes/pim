<?php

namespace App\Services\Marketplace\Adapters;

/**
 * 🛠️ B&Q ADAPTER
 *
 * Extends BaseMiraklAdapter with B&Q-specific defaults.
 */
class BqAdapter extends BaseMiraklAdapter
{
    protected function getMarketplaceName(): string
    {
        return 'bq';
    }

    protected function getOperatorCode(): string
    {
        return 'bq';
    }

    protected function getDefaultLogisticClass(): string
    {
        return (string) $this->fromAccount('logistic_class', 'STD');
    }

    protected function getDefaultLeadtime(): int
    {
        return (int) $this->fromAccount('leadtime_to_ship', 5);
    }

    protected function getProductIdType(): string
    {
        // B&Q usually requires EANs too
        return (string) $this->fromAccount('product_id_type', 'EAN');
    }
}

