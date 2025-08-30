<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register MarketplaceManager as singleton
        $this->app->singleton(\App\Services\Marketplace\MarketplaceManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // TEMPORARILY DISABLED ALL BOOTSTRAP CODE FOR PRODUCTION DEBUGGING
        // $this->configureRateLimiting();
        // $this->registerEventListeners();
        // $this->configureUrlGeneration();
        
        $this->registerRoleGates();
        $this->registerObservers();
    }

    private function registerRoleGates(): void
    {
        // MINIMAL GATES - Only define gates for permissions that don't exist in database
        // Most permissions will be handled directly by Spatie Permission system
        
        // Define only custom gates that combine multiple permissions or add business logic
        Gate::define('access-management-area', function (User $user) {
            return $user->hasRole('admin');
        });
        
        // Super admin gate for emergency access
        Gate::define('super-admin', function (User $user) {
            return $user->hasRole('admin') && $user->email === 'admin@example.com';
        });
    }

    private function registerObservers(): void
    {
        // Register SyncAccount observer for auto-creating SalesChannels
        \App\Models\SyncAccount::observe(\App\Observers\SyncAccountObserver::class);
    }
}
