<?php

namespace App\Providers;

use App\Contracts\DraftStorageInterface;
use App\Services\Draft\CacheDraftStorage;
use App\Services\Draft\DraftManager;
use App\Services\ProductWizard\WizardDraftService;
use Illuminate\Support\ServiceProvider;

class DraftServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind the storage interface to cache implementation
        $this->app->bind(DraftStorageInterface::class, function ($app) {
            return new CacheDraftStorage(
                keyPrefix: config('drafts.key_prefix', 'draft'),
                defaultTtl: config('drafts.default_ttl', 86400)
            );
        });

        // Bind general draft manager
        $this->app->bind(DraftManager::class, function ($app) {
            return new DraftManager($app->make(DraftStorageInterface::class));
        });

        // Bind specialized wizard draft service
        $this->app->bind(WizardDraftService::class, function ($app) {
            return new WizardDraftService(
                $app->make(DraftStorageInterface::class)
            );
        });
    }

    public function boot(): void
    {
        // Publish config if needed
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/drafts.php' => config_path('drafts.php'),
            ], 'config');
        }
    }
}