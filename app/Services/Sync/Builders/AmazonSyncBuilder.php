<?php

namespace App\Services\Sync\Builders;

/**
 * 📦 AMAZON SYNC BUILDER
 *
 * Placeholder for Amazon integration:
 * Sync::amazon()->account('uk')->product($product)->push()
 */
class AmazonSyncBuilder extends BaseSyncBuilder
{
    protected function getChannelName(): string
    {
        return 'amazon';
    }

    public function push(): array
    {
        throw new \RuntimeException('Amazon sync not yet implemented');
    }

    public function pull(): array
    {
        throw new \RuntimeException('Amazon sync not yet implemented');
    }
}
