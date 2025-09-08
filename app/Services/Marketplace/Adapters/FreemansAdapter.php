<?php

namespace App\Services\Marketplace\Adapters;

/**
 * 📄 FREEMANS MARKETPLACE ADAPTER
 *
 * Thin wrapper over BaseMiraklAdapter providing operator-specific hooks.
 */
class FreemansAdapter extends BaseMiraklAdapter
{
    protected function getMarketplaceName(): string
    {
        return 'freemans';
    }

    protected function getOperatorCode(): string
    {
        return 'freemans';
    }

    protected function getDefaultLogisticClass(): string
    {
        return (string) $this->fromAccount('logistic_class', 'DL');
    }

    protected function getDefaultLeadtime(): int
    {
        return (int) $this->fromAccount('leadtime_to_ship', 2);
    }
}
