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
        //
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
    }

    private function registerRoleGates(): void
    {
        // System management - Admin only
        Gate::define('manage-system', function (User $user) {
            return $user->isAdmin();
        });

        Gate::define('manage-users', function (User $user) {
            return $user->isAdmin();
        });

        // manage-teams gate removed - teams feature deprecated

        // Product management - Manager and Admin
        Gate::define('manage-products', function (User $user) {
            return $user->isManager();
        });

        Gate::define('manage-barcodes', function (User $user) {
            return $user->isManager();
        });

        Gate::define('manage-integrations', function (User $user) {
            return $user->isManager();
        });

        Gate::define('manage-pricing', function (User $user) {
            return $user->isManager();
        });

        // View permissions - All authenticated users with roles
        Gate::define('view-products', function (User $user) {
            return $user->hasRole();
        });

        Gate::define('view-barcodes', function (User $user) {
            return $user->hasRole();
        });

        Gate::define('view-analytics', function (User $user) {
            return $user->hasRole();
        });

        Gate::define('view-integrations', function (User $user) {
            return $user->hasRole();
        });
    }
}
