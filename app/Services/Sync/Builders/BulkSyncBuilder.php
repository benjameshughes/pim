<?php

namespace App\Services\Sync\Builders;

use Illuminate\Support\Collection;

/**
 * 📋 BULK SYNC BUILDER
 *
 * For bulk operations across multiple channels:
 * Sync::bulk()->products($products)->channels(['shopify', 'ebay'])->push()
 */
class BulkSyncBuilder
{
    protected Collection $products;

    protected array $channels = [];

    protected array $accounts = [];

    protected bool $dryRun = false;

    public function __construct()
    {
        $this->products = collect();
    }

    public function products(Collection|array $products): self
    {
        $this->products = collect($products);

        return $this;
    }

    public function channels(array $channels): self
    {
        $this->channels = $channels;

        return $this;
    }

    public function accounts(array $accounts): self
    {
        $this->accounts = $accounts;

        return $this;
    }

    public function dryRun(bool $enabled = true): self
    {
        $this->dryRun = $enabled;

        return $this;
    }

    public function push(): array
    {
        throw new \RuntimeException('Bulk sync not yet implemented');
    }

    public function pull(): array
    {
        throw new \RuntimeException('Bulk sync not yet implemented');
    }
}
