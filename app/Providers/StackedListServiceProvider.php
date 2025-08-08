<?php

namespace App\Providers;

use App\StackedList\StackedListManager;
use Illuminate\Support\ServiceProvider;

class StackedListServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('stacked-list', function ($app) {
            return new StackedListManager($app);
        });

        // Register default configuration
        $this->mergeConfigFrom(
            __DIR__.'/../../config/stacked-list.php',
            'stacked-list'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Publish configuration
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/stacked-list.php' => config_path('stacked-list.php'),
            ], 'stacked-list-config');
        }

        // Register Blade components
        $this->loadViewsFrom(__DIR__.'/../../resources/views/components', 'stacked-list');

        // Register default column limit and cache settings
        $this->configureDefaults();
    }

    /**
     * Configure system defaults.
     */
    private function configureDefaults(): void
    {
        // Set global defaults from config
        config([
            'stacked-list.defaults.column_limit' => config('stacked-list.column_limit', 6),
            'stacked-list.defaults.cache_ttl' => config('stacked-list.cache_ttl', 3600),
            'stacked-list.defaults.hidden_columns' => config('stacked-list.hidden_columns', [
                'id', 'password', 'remember_token', 'email_verified_at',
                'created_at', 'updated_at', 'deleted_at',
            ]),
        ]);
    }
}
