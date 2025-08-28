<?php

namespace App\Providers;

use App\Models\Team;
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
        
        $this->registerTeamGates();
    }

    private function registerTeamGates(): void
    {
        Gate::define('manage-team', function (User $user, Team $team) {
            return $user->canManageTeam($team);
        });

        Gate::define('manage-system', function (User $user) {
            return $user->canManageAnyTeam();
        });

        Gate::define('manage-products', function (User $user, Team $team) {
            return $user->canManageProducts($team);
        });

        Gate::define('manage-barcodes', function (User $user, Team $team) {
            return $user->canManageProducts($team);
        });

        Gate::define('manage-integrations', function (User $user, Team $team) {
            return $user->canManageProducts($team);
        });

        Gate::define('view-team', function (User $user, Team $team) {
            return $user->canViewTeam($team);
        });

        Gate::define('view-products', function (User $user, Team $team) {
            return $user->canViewTeam($team);
        });

        Gate::define('view-barcodes', function (User $user, Team $team) {
            return $user->canViewTeam($team);
        });

        Gate::define('view-analytics', function (User $user, Team $team) {
            return $user->canViewTeam($team);
        });
    }
}
