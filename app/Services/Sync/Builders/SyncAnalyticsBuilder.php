<?php

namespace App\Services\Sync\Builders;

/**
 * 📈 SYNC ANALYTICS BUILDER
 *
 * For analytics and reporting:
 * Sync::analytics()->channel('shopify')->lastHours(24)->stats()
 */
class SyncAnalyticsBuilder
{
    protected ?string $channel = null;

    protected int $hours = 24;

    public function channel(string $channel): self
    {
        $this->channel = $channel;

        return $this;
    }

    public function lastHours(int $hours): self
    {
        $this->hours = $hours;

        return $this;
    }

    public function stats(): array
    {
        throw new \RuntimeException('Sync analytics not yet implemented');
    }

    public function report(): array
    {
        throw new \RuntimeException('Sync analytics not yet implemented');
    }
}
