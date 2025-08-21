<?php

namespace App\Jobs;

use App\Services\Mirakl\Operators\BqOperatorClient;
use App\Services\Mirakl\Operators\DebenhamsOperatorClient;
use App\Services\Mirakl\Operators\FreemansOperatorClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * 💰 PROCESS MIRAKL OFFERS AFTER IMPORT
 *
 * Processes offers via JSON API after catalog import completes
 * This is the second phase of the dual API workflow
 */
class ProcessMiraklOffersAfterImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $marketplace;

    protected Collection $products;

    protected int $catalogImportId;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    public function __construct(string $marketplace, Collection $products, int $catalogImportId)
    {
        $this->marketplace = $marketplace;
        $this->products = $products;
        $this->catalogImportId = $catalogImportId;
    }

    /**
     * 🎯 EXECUTE JOB
     */
    public function handle(): void
    {
        Log::info('💰 Processing offers after catalog import', [
            'marketplace' => $this->marketplace,
            'catalog_import_id' => $this->catalogImportId,
            'products_count' => $this->products->count(),
        ]);

        $client = $this->getMarketplaceClient();

        if (! $client) {
            $this->fail(new \Exception("Unknown marketplace client: {$this->marketplace}"));

            return;
        }

        try {
            // Use the existing pushOffers method from marketplace clients
            $result = $client->pushOffers($this->products->all());

            if ($result['success']) {
                Log::info('✅ Offers processed successfully after catalog import', [
                    'marketplace' => $this->marketplace,
                    'catalog_import_id' => $this->catalogImportId,
                    'offers_import_id' => $result['import_id'] ?? null,
                    'offers_count' => $result['offers_count'] ?? 0,
                ]);

                $this->updateMarketplaceLinksAfterOffersSync($result);
            } else {
                throw new \Exception('Offers processing failed: '.($result['error'] ?? 'Unknown error'));
            }

        } catch (\Exception $e) {
            Log::error('❌ Failed to process offers after catalog import', [
                'marketplace' => $this->marketplace,
                'catalog_import_id' => $this->catalogImportId,
                'error' => $e->getMessage(),
            ]);

            $this->markOffersAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * 🏭 GET MARKETPLACE CLIENT
     */
    protected function getMarketplaceClient()
    {
        return match ($this->marketplace) {
            'freemans' => new FreemansOperatorClient,
            'debenhams' => new DebenhamsOperatorClient,
            'bq' => new BqOperatorClient,
            default => null,
        };
    }

    /**
     * 🔗 UPDATE MARKETPLACE LINKS AFTER OFFERS SYNC
     */
    protected function updateMarketplaceLinksAfterOffersSync(array $result): void
    {
        $offersImportId = $result['import_id'] ?? null;
        $success = $result['success'] ?? false;

        foreach ($this->products as $product) {
            $updateData = [
                'marketplace_data->offers_synced' => $success,
                'marketplace_data->offers_import_id' => $offersImportId,
                'marketplace_data->offers_synced_at' => now()->toISOString(),
                'marketplace_data->dual_sync_complete' => true,
                'marketplace_data->dual_sync_completed_at' => now()->toISOString(),
            ];

            // If both catalog and offers are successful, mark as linked
            if ($success) {
                $updateData['link_status'] = 'linked';
                $updateData['linked_at'] = now();
                $updateData['linked_by'] = 'dual_api_sync';
            }

            $product->marketplaceLinks()
                ->where('marketplace_type', $this->marketplace)
                ->update($updateData);

            // Also update variants
            foreach ($product->variants as $variant) {
                $variant->marketplaceLinks()
                    ->where('marketplace_type', $this->marketplace)
                    ->update($updateData);
            }
        }

        Log::info('🔗 MarketplaceLinks updated after offers sync', [
            'marketplace' => $this->marketplace,
            'products_count' => $this->products->count(),
            'success' => $success,
        ]);
    }

    /**
     * ❌ MARK OFFERS AS FAILED
     */
    protected function markOffersAsFailed(string $errorMessage): void
    {
        foreach ($this->products as $product) {
            $product->marketplaceLinks()
                ->where('marketplace_type', $this->marketplace)
                ->update([
                    'link_status' => 'failed',
                    'marketplace_data->offers_sync_failed' => true,
                    'marketplace_data->offers_failure_reason' => $errorMessage,
                    'marketplace_data->offers_failed_at' => now()->toISOString(),
                ]);

            // Also update variants
            foreach ($product->variants as $variant) {
                $variant->marketplaceLinks()
                    ->where('marketplace_type', $this->marketplace)
                    ->update([
                        'link_status' => 'failed',
                        'marketplace_data->offers_sync_failed' => true,
                        'marketplace_data->offers_failure_reason' => $errorMessage,
                        'marketplace_data->offers_failed_at' => now()->toISOString(),
                    ]);
            }
        }

        Log::error('❌ MarketplaceLinks marked as failed for offers sync', [
            'marketplace' => $this->marketplace,
            'error' => $errorMessage,
            'products_count' => $this->products->count(),
        ]);
    }

    /**
     * 🔄 JOB FAILED
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('❌ ProcessMiraklOffersAfterImport job failed', [
            'marketplace' => $this->marketplace,
            'catalog_import_id' => $this->catalogImportId,
            'error' => $exception->getMessage(),
        ]);

        $this->markOffersAsFailed('job_failed: '.$exception->getMessage());
    }
}
