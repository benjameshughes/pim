<?php

namespace App\Services\Marketplace;

use Illuminate\Support\ServiceProvider;

/**
 * Simple service provider for marketplace integrations
 */
class MarketplaceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // TEMPORARILY DISABLED FOR PRODUCTION DEBUGGING
        // $this->app->singleton(ShopifyAPI::class, function ($app) {
        //     return new ShopifyAPI;
        // });

        // $this->app->singleton('marketplace.sync', function ($app) {
        //     return new MarketplaceSync($app->make(ShopifyAPI::class));
        // });
    }

    public function boot(): void
    {
        // Boot logic if needed
    }
}
