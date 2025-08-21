<?php

namespace App\Providers;

use App\Facades\Sync;
use App\Services\Sync\SyncManager;
use Illuminate\Support\ServiceProvider;

/**
 * 🔄 SYNC SERVICE PROVIDER
 *
 * Registers the beautiful Sync facade and related services
 */
class SyncServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register(): void
    {
        // Register the SyncManager as a singleton
        $this->app->singleton(SyncManager::class, function () {
            return new SyncManager;
        });

        // Register the facade alias
        $this->app->alias(SyncManager::class, 'sync');
    }

    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        // Boot logic if needed
    }

    /**
     * Get the services provided by the provider
     */
    public function provides(): array
    {
        return [
            SyncManager::class,
            'sync',
        ];
    }
}
