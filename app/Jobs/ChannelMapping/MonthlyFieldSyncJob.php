<?php

namespace App\Jobs\ChannelMapping;

use App\Services\ChannelMapping\ChannelFieldDiscoveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 📅 MONTHLY FIELD SYNC JOB
 *
 * Background job that runs monthly to keep field requirements synchronized
 * with all marketplace APIs. Designed for queue processing.
 */
class MonthlyFieldSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 minutes

    public int $tries = 3;

    public int $maxExceptions = 1;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('channel-mapping');
    }

    /**
     * Execute the job.
     */
    public function handle(ChannelFieldDiscoveryService $discoveryService): void
    {
        Log::info('📅 Starting monthly channel field sync job');

        try {
            $startTime = microtime(true);

            // Get system status before
            $statsBefore = $discoveryService->getDiscoveryStatistics();

            // Check if sync is needed (should run monthly)
            if (! $this->shouldRunSync($statsBefore)) {
                Log::info('✅ Monthly sync not needed - field data is up to date');

                return;
            }

            // Run discovery for all channels
            $results = $discoveryService->discoverAllChannels();

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            // Get statistics after
            $statsAfter = $discoveryService->getDiscoveryStatistics();

            // Log comprehensive results
            Log::info('✅ Monthly channel field sync completed successfully', [
                'execution_time_ms' => $executionTime,
                'accounts_processed' => $results['processed_accounts'] ?? 0,
                'successful' => $results['summary']['successful'] ?? 0,
                'failed' => $results['summary']['failed'] ?? 0,
                'fields_before' => $statsBefore['field_definitions']['total_fields'] ?? 0,
                'fields_after' => $statsAfter['field_definitions']['total_fields'] ?? 0,
                'value_lists_before' => $statsBefore['value_lists']['total_lists'] ?? 0,
                'value_lists_after' => $statsAfter['value_lists']['total_lists'] ?? 0,
                'health_before' => $statsBefore['discovery_health']['overall_health']['score'] ?? 0,
                'health_after' => $statsAfter['discovery_health']['overall_health']['score'] ?? 0,
                'sync_timestamp' => now()->toISOString(),
            ]);

            // Dispatch notification if there were significant changes
            $this->checkForSignificantChanges($statsBefore, $statsAfter, $results);

        } catch (\Exception $e) {
            Log::error('❌ Monthly channel field sync job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'job_id' => $this->job->getJobId(),
            ]);

            // Re-throw to trigger job retry mechanism
            throw $e;
        }
    }

    /**
     * 🔍 Check if sync should run
     */
    protected function shouldRunSync(array $stats): bool
    {
        $lastSync = $stats['last_sync'] ?? null;

        if (! $lastSync) {
            Log::info('📅 No previous sync found - running initial discovery');

            return true;
        }

        $daysSinceLastSync = now()->diffInDays($lastSync);

        if ($daysSinceLastSync >= 30) {
            Log::info("📅 Last sync was {$daysSinceLastSync} days ago - running monthly sync");

            return true;
        }

        Log::info("📅 Last sync was {$daysSinceLastSync} days ago - sync not needed (monthly interval)");

        return false;
    }

    /**
     * 🔔 Check for significant changes and notify if needed
     */
    protected function checkForSignificantChanges(array $before, array $after, array $results): void
    {
        $fieldsBefore = $before['field_definitions']['total_fields'] ?? 0;
        $fieldsAfter = $after['field_definitions']['total_fields'] ?? 0;
        $fieldsChanged = abs($fieldsAfter - $fieldsBefore);

        $valueListsBefore = $before['value_lists']['total_lists'] ?? 0;
        $valueListsAfter = $after['value_lists']['total_lists'] ?? 0;
        $valueListsChanged = abs($valueListsAfter - $valueListsBefore);

        $failedAccounts = $results['summary']['failed'] ?? 0;

        // Define what constitutes "significant" changes
        $significantThreshold = [
            'fields_changed' => 50, // More than 50 field changes
            'value_lists_changed' => 10, // More than 10 value list changes
            'failed_accounts' => 2, // More than 2 failed accounts
        ];

        $notifications = [];

        if ($fieldsChanged >= $significantThreshold['fields_changed']) {
            $notifications[] = "Field definitions changed by {$fieldsChanged} items";
        }

        if ($valueListsChanged >= $significantThreshold['value_lists_changed']) {
            $notifications[] = "Value lists changed by {$valueListsChanged} items";
        }

        if ($failedAccounts >= $significantThreshold['failed_accounts']) {
            $notifications[] = "{$failedAccounts} sync accounts failed during discovery";
        }

        if (! empty($notifications)) {
            Log::warning('🔔 Significant changes detected in monthly channel sync', [
                'notifications' => $notifications,
                'fields_change' => $fieldsAfter - $fieldsBefore,
                'value_lists_change' => $valueListsAfter - $valueListsBefore,
                'failed_accounts' => $failedAccounts,
                'sync_timestamp' => now()->toISOString(),
            ]);

            // Here you could dispatch additional notification jobs:
            // - Email to administrators
            // - Slack notification
            // - Dashboard alert
            // - etc.
        }
    }

    /**
     * The job failed to process.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('❌ Monthly channel field sync job failed permanently', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'attempts' => $this->attempts(),
            'job_id' => $this->job->getJobId(),
        ]);

        // Here you could dispatch failure notification jobs
    }
}
