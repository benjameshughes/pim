<?php

namespace App\Services\Marketplace\Adapters;

use App\Models\Product;
use App\Services\Marketplace\API\MarketplaceClient;
use App\Services\Marketplace\ValueObjects\MarketplaceProduct;
use App\Services\Marketplace\ValueObjects\SyncResult;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * 🧱 BASE MIRAKL ADAPTER
 *
 * Shared behaviour for Mirakl-based operators (Freemans, Debenhams, B&Q).
 * Provides REST-based create/update and a link() operation via offers lookup.
 *
 * Subclasses can override small hooks to adjust operator-specific defaults
 * (category code, currency, logistic class, leadtime, state, product-id-type).
 */
abstract class BaseMiraklAdapter extends AbstractAdapter
{
    protected string $mode = 'create';
    protected bool $useCsv = false;

    /**
     * Set up create operation
     */
    public function create(int $productId): self
    {
        $this->mode = 'create';
        $this->currentProductId = $productId;

        // Optional: attach prepared data for inspection
        $product = $this->loadProduct($productId);
        $this->setMarketplaceProduct(new MarketplaceProduct(
            data: $this->buildCreatePayload($product),
            metadata: [
                'operator' => $this->getOperatorCode(),
                'transformation_type' => 'mirakl_payload',
                'original_product_id' => $product->id,
            ]
        ));

        return $this;
    }

    /**
     * Enable CSV import path (scaffolded with service stubs)
     */
    public function useCsv(bool $use = true): self
    {
        $this->useCsv = $use;
        return $this;
    }

    /**
     * Link operation - discover existing offers by SKU and store IDs
     */
    public function link(int $productId): self
    {
        $this->mode = 'link';
        $this->currentProductId = $productId;

        return $this;
    }

    /**
     * Execute current operation using Mirakl service
     */
    public function push(): SyncResult
    {
        try {
            $account = $this->requireSyncAccount();
            $product = $this->loadProduct($this->currentProductId ?? 0);

            /** @var \App\Services\Marketplace\API\Implementations\MiraklMarketplaceService $service */
            $service = MarketplaceClient::for('mirakl')
                ->withAccount($account)
                ->build();

            if ($this->mode === 'link') {
                return $this->executeLink($service, $product);
            }

            if ($this->isUpdateMode()) {
                $remoteId = $this->determineRemoteProductId($product);
                $payload = $this->buildUpdatePayload($product, $this->getFieldsToUpdate());
                $response = $service->updateProduct($remoteId, $payload);

                if (! ($response['success'] ?? false)) {
                    return SyncResult::failure('Mirakl update failed', [$response['error'] ?? 'Unknown error']);
                }

                return SyncResult::success('Mirakl product updated', [
                    'product_id' => $remoteId,
                    'updated_fields' => array_keys($this->getFieldsToUpdate()),
                ]);
            }

            // Create
            if ($this->useCsv) {
                // Scaffolded: generate CSVs and call stubbed import endpoints
                $catalog = $this->buildCatalogCsvData($product);
                $offers = $this->buildOffersCsvData($product);

                $dir = 'temp/mirakl_exports/'.$this->getOperatorCode();
                Storage::makeDirectory($dir);
                $timestamp = now()->format('Ymd_His');
                $catalogPath = $dir."/catalog_{$product->parent_sku}_{$timestamp}.csv";
                $offersPath = $dir."/offers_{$product->parent_sku}_{$timestamp}.csv";
                Storage::put($catalogPath, $catalog['csv']);
                Storage::put($offersPath, $offers['csv']);

                $importProducts = $service->importProductsCsv(Storage::path($catalogPath));
                $importOffers = $service->importOffersCsv(Storage::path($offersPath));

                return SyncResult::success('CSV imports queued (stubs)', [
                    'operator' => $this->getOperatorCode(),
                    'products_import' => $importProducts,
                    'offers_import' => $importOffers,
                    'files' => [
                        'catalog' => Storage::path($catalogPath),
                        'offers' => Storage::path($offersPath),
                    ],
                ]);
            }

            $payload = $this->buildCreatePayload($product);
            $response = $service->createProduct($payload);

            if (! ($response['success'] ?? false)) {
                return SyncResult::failure('Mirakl create failed', [$response['error'] ?? 'Unknown error']);
            }

            $remote = Arr::get($response, 'data.product', []);
            $remoteId = $remote['product_id'] ?? $payload['product_id'] ?? $product->parent_sku;

            return SyncResult::success('Mirakl product created', [
                'product_id' => $remoteId,
                'operator' => $this->getOperatorCode(),
            ]);

        } catch (\Throwable $e) {
            Log::error('Mirakl push failed', [
                'operator' => $this->getOperatorCode(),
                'product_id' => $this->currentProductId,
                'error' => $e->getMessage(),
            ]);

            return SyncResult::failure('Mirakl push failed: '.$e->getMessage());
        }
    }

    /**
     * Connection test via Mirakl service
     */
    public function testConnection(): SyncResult
    {
        try {
            $account = $this->requireSyncAccount();
            /** @var \App\Services\Marketplace\API\Implementations\MiraklMarketplaceService $service */
            $service = MarketplaceClient::for('mirakl')
                ->withAccount($account)
                ->build();

            $result = $service->testConnection();
            if (! ($result['success'] ?? false)) {
                return SyncResult::failure($result['error'] ?? 'Mirakl connection failed');
            }

            return SyncResult::success('Mirakl connection OK', $result);

        } catch (\Throwable $e) {
            return SyncResult::failure('Mirakl connection test failed: '.$e->getMessage());
        }
    }

    /**
     * 📋 Generate CSV previews (catalog + offers) without uploading
     * Useful for debugging or manual uploads while REST path is primary.
     */
    public function generateCsvPreview(int $productId): SyncResult
    {
        try {
            $product = $this->loadProduct($productId);

            $catalog = $this->buildCatalogCsvData($product);
            $offers = $this->buildOffersCsvData($product);

            // Save previews under storage for inspection
            $dir = 'temp/mirakl_exports/'.$this->getOperatorCode();
            Storage::makeDirectory($dir);

            $timestamp = now()->format('Ymd_His');
            $catalogPath = $dir."/catalog_{$product->parent_sku}_{$timestamp}.csv";
            $offersPath = $dir."/offers_{$product->parent_sku}_{$timestamp}.csv";

            Storage::put($catalogPath, $catalog['csv']);
            Storage::put($offersPath, $offers['csv']);

            return SyncResult::success('CSV preview generated', [
                'operator' => $this->getOperatorCode(),
                'catalog' => [
                    'headers' => $catalog['headers'],
                    'rows_count' => count($catalog['rows']),
                    'filename' => $catalogPath,
                    'absolute_path' => Storage::path($catalogPath),
                ],
                'offers' => [
                    'headers' => $offers['headers'],
                    'rows_count' => count($offers['rows']),
                    'filename' => $offersPath,
                    'absolute_path' => Storage::path($offersPath),
                ],
            ]);

        } catch (\Throwable $e) {
            return SyncResult::failure('Failed to generate CSV preview: '.$e->getMessage());
        }
    }

    /**
     * Execute link operation via offers search
     */
    protected function executeLink($service, Product $product): SyncResult
    {
        // Attempt to find existing offers by variant SKUs
        $variantSkus = $product->variants->pluck('sku')->filter()->values()->all();

        $found = collect();
        if (!empty($variantSkus)) {
            $found = $service->getInventoryLevels($variantSkus);
        }

        // Fallback: also try parent SKU
        if ($found->isEmpty() && !empty($product->parent_sku)) {
            $found = $service->getInventoryLevels([$product->parent_sku]);
        }

        // Build mapping sku => product_id
        $mapping = [];
        foreach ($found as $offer) {
            $sku = $offer['sku'] ?? $offer['product_id'] ?? null;
            $id = $offer['product_id'] ?? null;
            if ($sku && $id) {
                $mapping[$sku] = $id;
            }
        }

        // Persist to product attributes for quick reads
        $attrKey = $this->getOperatorCode().'_product_ids';
        if (!empty($mapping)) {
            $product->setAttributeValue($attrKey, json_encode($mapping));
            $product->setAttributeValue($this->getOperatorCode().'_status', 'synced');
        }

        $coverage = 0;
        if (!empty($variantSkus)) {
            $matched = count(array_intersect($variantSkus, array_keys($mapping)));
            $coverage = (int) round(($matched / count($variantSkus)) * 100);
        }

        return SyncResult::success(ucfirst($this->getOperatorCode()).' link completed', [
            'linked_offers' => $mapping,
            'coverage_percent' => $coverage,
            'variants' => $variantSkus,
        ]);
    }

    // ====== Payload builders and helpers ======

    protected function buildCreatePayload(Product $product): array
    {
        $brand = (string) ($product->getSmartAttributeValue('brand') ?? '');
        $categoryCode = (string) $this->getDefaultCategoryCode();
        [$price, $quantity] = $this->resolvePriceAndQuantity($product);

        return [
            'sku' => $product->parent_sku,
            'product_id' => $product->parent_sku,
            'title' => $product->name,
            'description' => (string) ($product->description ?? ''),
            'brand' => $brand,
            'category_code' => $categoryCode,
            'price' => number_format((float) $price, 2, '.', ''),
            'currency' => (string) $this->getDefaultCurrency(),
            'quantity' => $quantity,
            'state' => (int) $this->getDefaultState(),
        ];
    }

    protected function buildUpdatePayload(Product $product, array $fields): array
    {
        $payload = ['product_id' => $this->determineRemoteProductId($product)];

        if (isset($fields['title'])) {
            $payload['title'] = (string) $fields['title'];
        }
        if (! empty($fields['pricing'])) {
            [$price] = $this->resolvePriceAndQuantity($product);
            $payload['price'] = number_format((float) $price, 2, '.', '');
            $payload['currency'] = (string) $this->getDefaultCurrency();
        }
        if (isset($fields['images'])) {
            $payload['images'] = array_values(array_filter($fields['images']));
        }

        return $payload;
    }

    protected function determineRemoteProductId(Product $product): string
    {
        return (string) ($product->parent_sku ?? $product->id);
    }

    protected function resolvePriceAndQuantity(Product $product): array
    {
        $variants = $product->variants;
        $price = $product->retail_price ?? null;
        $quantity = 0;

        if ($variants->count() > 0) {
            $prices = $variants->pluck('price')->filter()->all();
            if (empty($price) && ! empty($prices)) {
                $price = min($prices);
            }
            $quantity = (int) $variants->pluck('stock_level')->filter()->sum();
        }

        return [($price ?? 0.0), max(0, $quantity)];
    }

    // ====== CSV scaffolding ======

    /**
     * Build catalog CSV data (headers, rows, csv string)
     */
    protected function buildCatalogCsvData(Product $product): array
    {
        $headers = [
            'product-id',
            'product-title',
            'product-description',
            'brand',
            'category-code',
            'product-references',
            'media-url-1',
            'media-url-2',
            'media-url-3',
        ];

        $row = [
            $product->parent_sku,
            $product->name,
            (string) ($product->description ?? ''),
            (string) ($product->getSmartAttributeValue('brand') ?? ''),
            $this->getDefaultCategoryCode(),
            $product->parent_sku,
            '', '', '', // media urls can be filled by mapping later
        ];

        $rows = [$row];
        $csv = $this->toCsv($headers, $rows);

        return compact('headers', 'rows', 'csv');
    }

    /**
     * Build offers CSV data (headers, rows, csv string)
     */
    protected function buildOffersCsvData(Product $product): array
    {
        $headers = [
            'shop-sku',
            'product-id',
            'product-id-type',
            'description',
            'price',
            'quantity',
            'leadtime-to-ship',
            'logistic-class',
            'state',
        ];

        $rows = [];
        $lead = $this->getDefaultLeadtime();
        $logi = $this->getDefaultLogisticClass();
        $state = $this->getDefaultState();
        $idType = $this->getProductIdType();

        foreach ($product->variants as $variant) {
            /** @var \App\Models\ProductVariant $variant */
            $rows[] = [
                $variant->sku,
                $product->parent_sku,
                $idType,
                $variant->title ?? $product->name,
                number_format((float) ($variant->price ?? 0), 2, '.', ''),
                (int) ($variant->stock_level ?? 0),
                $lead,
                $logi,
                (string) $state,
            ];
        }

        $csv = $this->toCsv($headers, $rows);
        return compact('headers', 'rows', 'csv');
    }

    /**
     * Basic CSV builder
     */
    protected function toCsv(array $headers, array $rows): string
    {
        $fp = fopen('php://temp', 'w+');
        fputcsv($fp, $headers);
        foreach ($rows as $r) {
            fputcsv($fp, $r);
        }
        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);
        return (string) $csv;
    }

    /**
     * Helper to read operator-specific value from SyncAccount first, then config
     */
    protected function fromAccount(string $key, mixed $default = null): mixed
    {
        $settings = $this->syncAccount ? ($this->syncAccount->settings ?? []) : [];
        $credentials = $this->syncAccount ? ($this->syncAccount->credentials ?? []) : [];

        if (array_key_exists($key, $settings)) {
            return $settings[$key];
        }
        if (array_key_exists($key, $credentials)) {
            return $credentials[$key];
        }
        if (isset($settings['auto_fetched_data'][$key])) {
            return $settings['auto_fetched_data'][$key];
        }

        return $default;
    }

    // ====== Operator hooks (override in subclasses as needed) ======

    protected function getOperatorCode(): string
    {
        // Prefer explicit channel; fall back to subtype or 'mirakl'
        return $this->syncAccount ? ($this->syncAccount->channel ?: ($this->syncAccount->marketplace_subtype ?? 'mirakl')) : 'mirakl';
    }

    protected function getDefaultCategoryCode(): string
    {
        $op = $this->getOperatorCode();
        return (string) $this->fromAccount('category_code', config("services.mirakl_operators.$op.category_code", ''));
    }

    protected function getDefaultCurrency(): string
    {
        $op = $this->getOperatorCode();
        return (string) $this->fromAccount('currency', config("services.mirakl_operators.$op.currency", 'GBP'));
    }

    protected function getDefaultState(): int
    {
        $op = $this->getOperatorCode();
        return (int) $this->fromAccount('default_state', config("services.mirakl_operators.$op.default_state", 11));
    }

    protected function getDefaultLogisticClass(): string
    {
        $op = $this->getOperatorCode();
        return (string) $this->fromAccount('logistic_class', config("services.mirakl_operators.$op.logistic_class", 'STD'));
    }

    protected function getDefaultLeadtime(): int
    {
        $op = $this->getOperatorCode();
        return (int) $this->fromAccount('leadtime_to_ship', config("services.mirakl_operators.$op.leadtime_to_ship", 3));
    }

    protected function getProductIdType(): string
    {
        // Common defaults; override where needed (e.g., 'EAN')
        return (string) $this->fromAccount('product_id_type', 'SHOP_SKU');
    }
}
