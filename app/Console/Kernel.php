<?php

namespace App\Console;

use App\Console\Commands\SyncAccountsHealthCheck;
use App\Console\Commands\MakeAdminUser;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * Explicitly register commands to keep everything in-app.
     */
    protected $commands = [
        SyncAccountsHealthCheck::class,
        MakeAdminUser::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Daily sync account connection health checks
        $schedule->command('sync-accounts:health-check')
            ->dailyAt('03:00')
            ->onOneServer()
            ->withoutOverlapping()
            ->runInBackground();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        // Commands are auto-discovered in app/Console/Commands and also explicitly registered above.
    }
}
