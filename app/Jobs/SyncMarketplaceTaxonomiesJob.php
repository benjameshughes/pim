<?php

namespace App\Jobs;

use App\Services\Marketplace\TaxonomySyncService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 🔄 SYNC MARKETPLACE TAXONOMIES JOB
 *
 * Monthly job that fetches and caches taxonomy data from all marketplace integrations.
 * Updates categories, attributes, and values to keep product attribute forms fast.
 *
 * Uses TaxonomySyncService for clean separation of concerns and testability.
 */
class SyncMarketplaceTaxonomiesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // 1 hour timeout for large syncs

    public int $tries = 3;

    public int $maxExceptions = 5;

    protected TaxonomySyncService $taxonomySyncService;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('default');
        $this->taxonomySyncService = new TaxonomySyncService;
    }

    /**
     * 🚀 Execute the job - sync all marketplace taxonomies
     */
    public function handle(): void
    {
        $startTime = now();

        Log::info('🔄 Starting monthly marketplace taxonomy sync job', [
            'job_id' => $this->job->uuid ?? 'unknown',
            'started_at' => $startTime,
        ]);

        try {
            $result = $this->taxonomySyncService->syncAllMarketplaces();
            $this->logSyncCompletion($result, $startTime);
        } catch (Exception $e) {
            $this->handleSyncFailure($e, $startTime);
            throw $e;
        }
    }

    /**
     * ✅ Log successful sync completion
     */
    protected function logSyncCompletion(array $result, $startTime): void
    {
        Log::info('✅ Monthly marketplace taxonomy sync job completed successfully', [
            'job_id' => $this->job->uuid ?? 'unknown',
            'duration_minutes' => $result['duration_minutes'],
            'accounts_processed' => $result['accounts_processed'],
            'total_accounts' => $result['total_accounts'],
            'stats' => $result['stats'],
        ]);
    }

    /**
     * ❌ Handle sync failure
     */
    protected function handleSyncFailure(Exception $e, $startTime): void
    {
        $duration = $startTime->diffInMinutes(now());

        Log::error('❌ Monthly marketplace taxonomy sync job failed', [
            'job_id' => $this->job->uuid ?? 'unknown',
            'duration_minutes' => $duration,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }

    /**
     * 🔄 Handle job failure
     */
    public function failed(Exception $exception): void
    {
        Log::error('💥 SyncMarketplaceTaxonomiesJob failed permanently', [
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }
}
