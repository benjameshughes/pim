<?php

namespace App\Actions\Marketplace;

use App\Actions\Base\BaseAction;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\EbayConnectService;
use App\Services\Marketplace\MiraklConnectService;
use App\Services\MarketplaceAttributeMappingService;
use App\Services\Shopify\API\ShopifyApiClient;
use Illuminate\Database\Eloquent\Model;

/**
 * 🔄 SYNC ATTRIBUTES TO MARKETPLACE ACTION
 *
 * Synchronizes product/variant attributes to specific marketplaces
 * using the MarketplaceAttributeMappingService for proper field mapping.
 */
class SyncAttributesToMarketplaceAction extends BaseAction
{
    public function __construct(
        protected MarketplaceAttributeMappingService $mappingService,
        protected ?ShopifyApiClient $shopifyClient = null,
        protected ?EbayConnectService $ebayService = null,
        protected ?MiraklConnectService $miraklService = null
    ) {}

    /**
     * 🎯 EXECUTE SYNC
     *
     * @param  Model  $model  Product or ProductVariant
     * @param  string  $marketplace  Target marketplace
     * @param  array  $options  Sync options
     */
    public function execute(Model $model, string $marketplace, array $options = []): array
    {
        $this->validateInputs($model, $marketplace, $options);

        try {
            // Map attributes to marketplace format
            $mappedData = $this->mapToMarketplace($model, $marketplace);

            if (! $mappedData['is_valid']) {
                return $this->fail('Attribute mapping validation failed', [
                    'validation_errors' => $mappedData['validation_errors'],
                    'warnings' => $mappedData['warnings'],
                ]);
            }

            // Perform the actual sync
            $syncResult = $this->performSync($model, $marketplace, $mappedData, $options);

            if (! $syncResult['success']) {
                return $this->fail($syncResult['error'], $syncResult['details'] ?? []);
            }

            // Update sync status on attributes
            $this->updateAttributeSyncStatus($model, $marketplace, $syncResult);

            return $this->success('Attributes synced successfully to '.$marketplace, [
                'marketplace' => $marketplace,
                'model_type' => get_class($model),
                'model_id' => $model->id,
                'synced_attributes' => $syncResult['synced_attributes'] ?? [],
                'sync_metadata' => $syncResult['metadata'] ?? [],
                'external_id' => $syncResult['external_id'] ?? null,
            ]);

        } catch (\Exception $e) {
            return $this->fail('Sync failed: '.$e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    /**
     * 🔄 BATCH SYNC
     *
     * Sync multiple models to marketplace efficiently
     */
    public function batchSync(array $models, string $marketplace, array $options = []): array
    {
        $results = [
            'successful' => [],
            'failed' => [],
            'total_processed' => 0,
            'total_succeeded' => 0,
            'total_failed' => 0,
        ];

        $batchSize = $options['batch_size'] ?? 10;
        $chunks = array_chunk($models, $batchSize);

        foreach ($chunks as $chunk) {
            foreach ($chunk as $model) {
                $results['total_processed']++;

                $result = $this->execute($model, $marketplace, $options);

                if ($result['success']) {
                    $results['successful'][] = [
                        'model' => get_class($model),
                        'id' => $model->id,
                        'result' => $result,
                    ];
                    $results['total_succeeded']++;
                } else {
                    $results['failed'][] = [
                        'model' => get_class($model),
                        'id' => $model->id,
                        'error' => $result['message'],
                        'details' => $result['details'],
                    ];
                    $results['total_failed']++;
                }
            }

            // Optional delay between batches
            if (isset($options['delay_between_batches'])) {
                sleep($options['delay_between_batches']);
            }
        }

        return [
            'success' => $results['total_failed'] === 0,
            'message' => "Processed {$results['total_processed']} items: {$results['total_succeeded']} succeeded, {$results['total_failed']} failed",
            'details' => $results,
        ];
    }

    /**
     * ✅ VALIDATE INPUTS
     */
    protected function validateInputs(Model $model, string $marketplace, array $options): void
    {
        if (! in_array(get_class($model), [Product::class, ProductVariant::class])) {
            throw new \InvalidArgumentException('Model must be Product or ProductVariant');
        }

        $supportedMarketplaces = ['shopify', 'ebay', 'mirakl'];
        if (! in_array($marketplace, $supportedMarketplaces)) {
            throw new \InvalidArgumentException("Unsupported marketplace: {$marketplace}");
        }

        // Ensure marketplace service is available
        $this->ensureMarketplaceServiceAvailable($marketplace);
    }

    /**
     * 🗺️ MAP TO MARKETPLACE
     */
    protected function mapToMarketplace(Model $model, string $marketplace): array
    {
        if ($model instanceof Product) {
            return $this->mappingService->mapProductToMarketplace($model, $marketplace);
        } else {
            return $this->mappingService->mapVariantToMarketplace($model, $marketplace);
        }
    }

    /**
     * 🔄 PERFORM SYNC
     *
     * Execute the actual marketplace sync
     */
    protected function performSync(Model $model, string $marketplace, array $mappedData, array $options): array
    {
        switch ($marketplace) {
            case 'shopify':
                return $this->syncToShopify($model, $mappedData, $options);
            case 'ebay':
                return $this->syncToEbay($model, $mappedData, $options);
            case 'mirakl':
                return $this->syncToMirakl($model, $mappedData, $options);
            default:
                throw new \InvalidArgumentException("Unsupported marketplace: {$marketplace}");
        }
    }

    /**
     * 🛍️ SYNC TO SHOPIFY
     */
    protected function syncToShopify(Model $model, array $mappedData, array $options): array
    {
        if (! $this->shopifyClient) {
            throw new \RuntimeException('Shopify client not available');
        }

        try {
            if ($model instanceof Product) {
                return $this->syncProductToShopify($model, $mappedData, $options);
            } else {
                return $this->syncVariantToShopify($model, $mappedData, $options);
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Shopify sync failed: '.$e->getMessage(),
                'details' => ['exception' => get_class($e)],
            ];
        }
    }

    /**
     * 🛍️ SYNC PRODUCT TO SHOPIFY
     */
    protected function syncProductToShopify(Product $product, array $mappedData, array $options): array
    {
        $coreFields = $mappedData['core_fields'];
        $metafields = $mappedData['custom_attributes']['metafields'] ?? [];

        // Check if product already exists in Shopify
        $existingLinks = $product->marketplaceLinks()
            ->where('marketplace', 'shopify')
            ->where('link_level', 'product')
            ->get();

        if ($existingLinks->isNotEmpty()) {
            // Update existing product
            $shopifyProductId = $existingLinks->first()->external_id;

            $updateData = [
                'input' => array_merge($coreFields, [
                    'id' => $shopifyProductId,
                    'metafields' => $metafields,
                ]),
            ];

            $result = $this->shopifyClient->updateProduct($updateData);

            if ($result['success']) {
                return [
                    'success' => true,
                    'external_id' => $shopifyProductId,
                    'operation' => 'update',
                    'synced_attributes' => array_keys($metafields),
                    'metadata' => $result['data'] ?? [],
                ];
            }
        } else {
            // Create new product
            $createData = [
                'input' => array_merge($coreFields, [
                    'metafields' => $metafields,
                ]),
            ];

            $result = $this->shopifyClient->createProduct($createData);

            if ($result['success']) {
                $shopifyProductId = $result['data']['product']['id'] ?? null;

                if ($shopifyProductId) {
                    // Create marketplace link
                    $product->marketplaceLinks()->create([
                        'marketplace' => 'shopify',
                        'link_level' => 'product',
                        'external_id' => $shopifyProductId,
                        'sync_status' => 'synced',
                        'last_synced_at' => now(),
                    ]);
                }

                return [
                    'success' => true,
                    'external_id' => $shopifyProductId,
                    'operation' => 'create',
                    'synced_attributes' => array_keys($metafields),
                    'metadata' => $result['data'] ?? [],
                ];
            }
        }

        return [
            'success' => false,
            'error' => 'Failed to sync to Shopify',
            'details' => $result ?? [],
        ];
    }

    /**
     * 🛍️ SYNC VARIANT TO SHOPIFY
     */
    protected function syncVariantToShopify(ProductVariant $variant, array $mappedData, array $options): array
    {
        $coreFields = $mappedData['core_fields'];
        $metafields = $mappedData['custom_attributes']['metafields'] ?? [];

        // Find existing variant link
        $existingLink = $variant->variantMarketplaceLinks()
            ->where('marketplace', 'shopify')
            ->first();

        if ($existingLink) {
            // Update existing variant
            $shopifyVariantId = $existingLink->external_id;

            $updateData = [
                'input' => array_merge($coreFields, [
                    'id' => $shopifyVariantId,
                    'metafields' => $metafields,
                ]),
            ];

            $result = $this->shopifyClient->updateVariant($updateData);

            if ($result['success']) {
                return [
                    'success' => true,
                    'external_id' => $shopifyVariantId,
                    'operation' => 'update',
                    'synced_attributes' => array_keys($metafields),
                    'metadata' => $result['data'] ?? [],
                ];
            }
        }

        return [
            'success' => false,
            'error' => 'Variant sync to Shopify not supported without existing link',
        ];
    }

    /**
     * 🏪 SYNC TO EBAY
     */
    protected function syncToEbay(Model $model, array $mappedData, array $options): array
    {
        if (! $this->ebayService) {
            throw new \RuntimeException('eBay service not available');
        }

        // eBay sync would be implemented here
        // This is a placeholder for the actual eBay API integration
        return [
            'success' => false,
            'error' => 'eBay sync not yet implemented',
        ];
    }

    /**
     * 🏬 SYNC TO MIRAKL
     */
    protected function syncToMirakl(Model $model, array $mappedData, array $options): array
    {
        if (! $this->miraklService) {
            throw new \RuntimeException('Mirakl service not available');
        }

        // Mirakl sync would be implemented here
        // This is a placeholder for the actual Mirakl API integration
        return [
            'success' => false,
            'error' => 'Mirakl sync not yet implemented',
        ];
    }

    /**
     * 📝 UPDATE ATTRIBUTE SYNC STATUS
     *
     * Mark attributes as synced in the PIM system
     */
    protected function updateAttributeSyncStatus(Model $model, string $marketplace, array $syncResult): void
    {
        $syncedAttributes = $syncResult['synced_attributes'] ?? [];
        $externalId = $syncResult['external_id'] ?? null;
        $metadata = $syncResult['metadata'] ?? [];

        foreach ($syncedAttributes as $attributeKey) {
            $attributes = $model->attributes()->forAttribute($attributeKey)->get();

            foreach ($attributes as $attribute) {
                $attribute->markAsSynced($marketplace, [
                    'external_id' => $externalId,
                    'operation' => $syncResult['operation'] ?? 'sync',
                    'sync_timestamp' => now()->toISOString(),
                    'metadata' => $metadata,
                ]);
                $attribute->save();
            }
        }
    }

    /**
     * 🛠️ ENSURE MARKETPLACE SERVICE AVAILABLE
     */
    protected function ensureMarketplaceServiceAvailable(string $marketplace): void
    {
        switch ($marketplace) {
            case 'shopify':
                if (! $this->shopifyClient) {
                    $this->shopifyClient = app(ShopifyApiClient::class);
                }
                break;
            case 'ebay':
                if (! $this->ebayService) {
                    $this->ebayService = app(EbayConnectService::class);
                }
                break;
            case 'mirakl':
                if (! $this->miraklService) {
                    // Mirakl service would be injected here
                    // $this->miraklService = app(MiraklConnectService::class);
                }
                break;
        }
    }

    /**
     * 🔍 GET SYNC STATUS
     *
     * Get current sync status for a model across all marketplaces
     */
    public function getSyncStatus(Model $model): array
    {
        $status = [
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'marketplaces' => [],
            'last_sync' => null,
            'pending_syncs' => [],
        ];

        $marketplaces = ['shopify', 'ebay', 'mirakl'];

        foreach ($marketplaces as $marketplace) {
            $marketplaceStatus = [
                'marketplace' => $marketplace,
                'linked' => false,
                'external_id' => null,
                'last_synced' => null,
                'attributes_needing_sync' => [],
            ];

            // Check for marketplace links
            $links = $model->marketplaceLinks()
                ->where('marketplace', $marketplace)
                ->get();

            if ($links->isNotEmpty()) {
                $link = $links->first();
                $marketplaceStatus['linked'] = true;
                $marketplaceStatus['external_id'] = $link->external_id;
                $marketplaceStatus['last_synced'] = $link->last_synced_at;
            }

            // Check for attributes needing sync
            $attributes = $model->attributes()
                ->needingSync($marketplace)
                ->get();

            $marketplaceStatus['attributes_needing_sync'] = $attributes->map(function ($attr) {
                return [
                    'key' => $attr->getAttributeKey(),
                    'value' => $attr->getTypedValue(),
                    'last_changed' => $attr->value_changed_at,
                ];
            })->toArray();

            $status['marketplaces'][$marketplace] = $marketplaceStatus;

            // Track pending syncs
            if (! empty($marketplaceStatus['attributes_needing_sync'])) {
                $status['pending_syncs'][] = $marketplace;
            }
        }

        // Find most recent sync across all marketplaces
        $allSyncDates = collect($status['marketplaces'])
            ->pluck('last_synced')
            ->filter()
            ->sort()
            ->reverse();

        $status['last_sync'] = $allSyncDates->first();

        return $status;
    }

    /**
     * 📊 GET SYNC STATISTICS
     *
     * Get sync statistics for reporting
     */
    public function getSyncStatistics(string $marketplace, array $options = []): array
    {
        $dateFrom = $options['date_from'] ?? now()->subDays(7);
        $dateTo = $options['date_to'] ?? now();

        // This would query sync logs/status to provide statistics
        // Placeholder implementation
        return [
            'marketplace' => $marketplace,
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'total_syncs' => 0,
            'successful_syncs' => 0,
            'failed_syncs' => 0,
            'pending_syncs' => 0,
            'most_synced_attributes' => [],
            'common_errors' => [],
        ];
    }
}
