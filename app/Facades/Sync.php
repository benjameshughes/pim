<?php

namespace App\Facades;

use App\Services\Sync\SyncManager;
use Illuminate\Support\Facades\Facade;

/**
 * 🎭 SYNC FACADE
 *
 * Beautiful fluent API entry point for all sync operations:
 *
 * Sync::shopify()->product($product)->push();
 * Sync::ebay()->account('uk')->product($product)->pull();
 * Sync::log()->product($product)->failures()->today();
 * Sync::status()->product($product)->shopify()->needsSync();
 *
 * @method static \App\Services\Sync\Builders\ShopifySyncBuilder shopify()
 * @method static \App\Services\Sync\Builders\EbaySyncBuilder ebay()
 * @method static \App\Services\Sync\Builders\AmazonSyncBuilder amazon()
 * @method static \App\Services\Sync\Builders\MiraklSyncBuilder mirakl()
 * @method static \App\Services\Sync\Builders\SyncLogBuilder log()
 * @method static \App\Services\Sync\Builders\SyncStatusBuilder status()
 * @method static \App\Services\Sync\Builders\BulkSyncBuilder bulk()
 * @method static \App\Services\Sync\Builders\SyncAnalyticsBuilder analytics()
 */
class Sync extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return SyncManager::class;
    }
}
