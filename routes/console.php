<?php

use App\Jobs\ChannelMapping\MonthlyFieldSyncJob;
use App\Jobs\SyncMarketplaceTaxonomiesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 📅 MONTHLY CHANNEL FIELD SYNC SCHEDULING
Schedule::job(new MonthlyFieldSyncJob)
    ->monthly()
    ->name('monthly-channel-field-sync')
    ->description('Monthly sync of marketplace field requirements')
    ->withoutOverlapping()
    ->onSuccess(function () {
        logger('✅ Monthly channel field sync completed successfully');
    })
    ->onFailure(function () {
        logger('❌ Monthly channel field sync failed');
    });

// 🏷️ MONTHLY MARKETPLACE TAXONOMY SYNC SCHEDULING
Schedule::job(new SyncMarketplaceTaxonomiesJob)
    ->monthly()
    ->name('monthly-marketplace-taxonomy-sync')
    ->description('Monthly sync of marketplace categories, attributes, and values')
    ->withoutOverlapping()
    ->onSuccess(function () {
        logger('✅ Monthly marketplace taxonomy sync completed successfully');
    })
    ->onFailure(function () {
        logger('❌ Monthly marketplace taxonomy sync failed');
    });

// Alternative: Run the console command monthly (disabled in favor of job-based approach)
// Schedule::command('channel-mapping:sync-monthly')
//     ->monthly()
//     ->name('monthly-channel-field-sync-command')
//     ->description('Monthly field sync via console command')
//     ->withoutOverlapping()
//     ->skip(true); // Always skip - use job-based sync instead
