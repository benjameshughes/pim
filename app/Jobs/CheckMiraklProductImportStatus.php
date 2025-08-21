<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\Mirakl\ProductCsvUploader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * 🔄 CHECK MIRAKL PRODUCT IMPORT STATUS
 *
 * Queue job that polls Mirakl import status and processes offers when complete
 * Implements smart polling with exponential backoff
 */
class CheckMiraklProductImportStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $importId;

    protected string $marketplace;

    protected Collection $products;

    protected ?string $lastRequestDate;

    protected int $attempt;

    protected string $csvFilePath;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 50; // Allow long polling for up to ~4 hours

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 3;

    public function __construct(
        int $importId,
        string $marketplace,
        Collection $products,
        string $csvFilePath,
        ?string $lastRequestDate = null,
        int $attempt = 1
    ) {
        $this->importId = $importId;
        $this->marketplace = $marketplace;
        $this->products = $products;
        $this->csvFilePath = $csvFilePath;
        $this->lastRequestDate = $lastRequestDate;
        $this->attempt = $attempt;
    }

    /**
     * 🎯 EXECUTE JOB
     */
    public function handle(): void
    {
        Log::info('🔄 Checking Mirakl import status', [
            'import_id' => $this->importId,
            'marketplace' => $this->marketplace,
            'attempt' => $this->attempt,
            'products_count' => $this->products->count(),
        ]);

        $uploader = ProductCsvUploader::forMarketplace($this->marketplace);
        $statusResult = $uploader->checkImportStatus($this->importId, $this->lastRequestDate);

        $status = $statusResult['status'] ?? 'unknown';

        switch ($status) {
            case 'COMPLETE':
                $this->handleImportComplete($statusResult);
                break;

            case 'FAILED':
            case 'CANCELLED':
                $this->handleImportFailed($statusResult, $uploader);
                break;

            case 'WAITING':
            case 'RUNNING':
            case 'SENT':
                $this->scheduleNextCheck($statusResult);
                break;

            case 'no_changes':
                $this->scheduleNextCheck($statusResult);
                break;

            default:
                Log::warning('⚠️ Unknown import status', [
                    'import_id' => $this->importId,
                    'status' => $status,
                    'result' => $statusResult,
                ]);
                $this->scheduleNextCheck($statusResult);
        }
    }

    /**
     * ✅ HANDLE IMPORT COMPLETE
     */
    protected function handleImportComplete(array $statusResult): void
    {
        Log::info('✅ Product import completed successfully', [
            'import_id' => $this->importId,
            'marketplace' => $this->marketplace,
            'products_count' => $this->products->count(),
        ]);

        // Now process offers for all products via JSON API
        ProcessMiraklOffersAfterImport::dispatch(
            $this->marketplace,
            $this->products,
            $this->importId
        );

        // Cleanup CSV file
        $this->cleanupCsvFile();

        // Mark any pending MarketplaceLinks as product-synced
        $this->updateMarketplaceLinksAfterCatalogSync();
    }

    /**
     * ❌ HANDLE IMPORT FAILED
     */
    protected function handleImportFailed(array $statusResult, ProductCsvUploader $uploader): void
    {
        Log::error('❌ Product import failed', [
            'import_id' => $this->importId,
            'marketplace' => $this->marketplace,
            'status' => $statusResult['status'],
            'has_error_report' => $statusResult['has_error_report'] ?? false,
        ]);

        // Download and log error report if available
        if ($statusResult['has_error_report'] ?? false) {
            $errorReport = $uploader->downloadErrorReport($this->importId);
            if ($errorReport['success']) {
                Log::error('📋 Import error report', [
                    'import_id' => $this->importId,
                    'error_report' => substr($errorReport['error_report'], 0, 1000).'...',
                ]);
            }
        }

        // Cleanup CSV file
        $this->cleanupCsvFile();

        // Mark any pending MarketplaceLinks as failed
        $this->markMarketplaceLinksAsFailed($statusResult['status']);

        // Could implement retry logic here if needed
        $this->fail(new \Exception("Import failed with status: {$statusResult['status']}"));
    }

    /**
     * ⏰ SCHEDULE NEXT CHECK
     */
    protected function scheduleNextCheck(array $statusResult): void
    {
        // Calculate delay based on attempt number and import age
        $baseDelay = 300; // 5 minutes
        $maxDelay = 900;  // 15 minutes

        // Use exponential backoff for older attempts
        $delay = min($baseDelay * (1 + ($this->attempt * 0.1)), $maxDelay);

        // Update last request date for efficient polling
        $this->lastRequestDate = now()->toISOString();

        Log::info('⏰ Scheduling next import status check', [
            'import_id' => $this->importId,
            'delay_seconds' => $delay,
            'next_attempt' => $this->attempt + 1,
            'current_status' => $statusResult['status'] ?? 'unknown',
        ]);

        // Dispatch new job with updated parameters
        CheckMiraklProductImportStatus::dispatch(
            $this->importId,
            $this->marketplace,
            $this->products,
            $this->csvFilePath,
            $this->lastRequestDate,
            $this->attempt + 1
        )->delay(now()->addSeconds($delay));
    }

    /**
     * 🗑️ CLEANUP CSV FILE
     */
    protected function cleanupCsvFile(): void
    {
        try {
            if (\Illuminate\Support\Facades\Storage::exists($this->csvFilePath)) {
                \Illuminate\Support\Facades\Storage::delete($this->csvFilePath);
                Log::info('🗑️ CSV file cleaned up', [
                    'file_path' => $this->csvFilePath,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('⚠️ Failed to cleanup CSV file', [
                'file_path' => $this->csvFilePath,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 🔗 UPDATE MARKETPLACE LINKS AFTER CATALOG SYNC
     */
    protected function updateMarketplaceLinksAfterCatalogSync(): void
    {
        // Find MarketplaceLinks for these products and mark catalog as synced
        foreach ($this->products as $product) {
            $product->marketplaceLinks()
                ->where('marketplace_type', $this->marketplace)
                ->where('link_status', 'pending')
                ->update([
                    'marketplace_data->catalog_synced' => true,
                    'marketplace_data->catalog_import_id' => $this->importId,
                    'marketplace_data->catalog_synced_at' => now()->toISOString(),
                ]);
        }

        Log::info('🔗 MarketplaceLinks updated after catalog sync', [
            'import_id' => $this->importId,
            'products_count' => $this->products->count(),
        ]);
    }

    /**
     * ❌ MARK MARKETPLACE LINKS AS FAILED
     */
    protected function markMarketplaceLinksAsFailed(string $failureReason): void
    {
        foreach ($this->products as $product) {
            $product->marketplaceLinks()
                ->where('marketplace_type', $this->marketplace)
                ->where('link_status', 'pending')
                ->update([
                    'link_status' => 'failed',
                    'marketplace_data->catalog_sync_failed' => true,
                    'marketplace_data->catalog_failure_reason' => $failureReason,
                    'marketplace_data->catalog_failed_at' => now()->toISOString(),
                ]);
        }

        Log::error('❌ MarketplaceLinks marked as failed', [
            'import_id' => $this->importId,
            'failure_reason' => $failureReason,
            'products_count' => $this->products->count(),
        ]);
    }

    /**
     * 🔄 JOB FAILED
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('❌ CheckMiraklProductImportStatus job failed', [
            'import_id' => $this->importId,
            'marketplace' => $this->marketplace,
            'attempt' => $this->attempt,
            'error' => $exception->getMessage(),
        ]);

        // Cleanup CSV file on failure
        $this->cleanupCsvFile();

        // Mark MarketplaceLinks as failed
        $this->markMarketplaceLinksAsFailed('job_failed: '.$exception->getMessage());
    }
}
