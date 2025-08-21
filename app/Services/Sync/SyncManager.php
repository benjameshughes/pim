<?php

namespace App\Services\Sync;

use App\Services\Sync\Builders\AmazonSyncBuilder;
use App\Services\Sync\Builders\BulkSyncBuilder;
use App\Services\Sync\Builders\EbaySyncBuilder;
use App\Services\Sync\Builders\MiraklSyncBuilder;
use App\Services\Sync\Builders\ShopifySyncBuilder;
use App\Services\Sync\Builders\SyncAnalyticsBuilder;
use App\Services\Sync\Builders\SyncLogBuilder;
use App\Services\Sync\Builders\SyncStatusBuilder;

/**
 * 🎯 SYNC MANAGER
 *
 * Central orchestrator for the fluent sync API.
 * Routes facade calls to appropriate builders.
 *
 * This class makes the magic happen:
 * Sync::shopify() -> ShopifySyncBuilder
 * Sync::ebay() -> EbaySyncBuilder
 * Sync::log() -> SyncLogBuilder
 */
class SyncManager
{
    /**
     * 🛍️ Shopify sync builder
     *
     * Usage: Sync::shopify()->product($product)->push()
     */
    public function shopify(): ShopifySyncBuilder
    {
        return new ShopifySyncBuilder;
    }

    /**
     * 🏪 eBay sync builder
     *
     * Usage: Sync::ebay()->account('uk')->product($product)->push()
     */
    public function ebay(): EbaySyncBuilder
    {
        return new EbaySyncBuilder;
    }

    /**
     * 📦 Amazon sync builder
     *
     * Usage: Sync::amazon()->account('uk')->product($product)->push()
     */
    public function amazon(): AmazonSyncBuilder
    {
        return new AmazonSyncBuilder;
    }

    /**
     * 🌐 Mirakl sync builder
     *
     * Usage: Sync::mirakl()->product($product)->push()
     */
    public function mirakl(): MiraklSyncBuilder
    {
        return new MiraklSyncBuilder;
    }

    /**
     * 📝 Sync log builder
     *
     * Usage: Sync::log()->product($product)->failures()->today()
     */
    public function log(): SyncLogBuilder
    {
        return new SyncLogBuilder;
    }

    /**
     * 📊 Sync status builder
     *
     * Usage: Sync::status()->product($product)->needsSync()
     */
    public function status(): SyncStatusBuilder
    {
        return new SyncStatusBuilder;
    }

    /**
     * 📋 Bulk operations builder
     *
     * Usage: Sync::bulk()->products($products)->channels(['shopify', 'ebay'])->push()
     */
    public function bulk(): BulkSyncBuilder
    {
        return new BulkSyncBuilder;
    }

    /**
     * 📈 Analytics builder
     *
     * Usage: Sync::analytics()->channel('shopify')->lastHours(24)->stats()
     */
    public function analytics(): SyncAnalyticsBuilder
    {
        return new SyncAnalyticsBuilder;
    }
}
