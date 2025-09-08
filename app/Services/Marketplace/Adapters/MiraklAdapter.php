<?php

namespace App\Services\Marketplace\Adapters;

use App\Models\Product;
use App\Services\Marketplace\API\MarketplaceClient;
use App\Services\Marketplace\ValueObjects\SyncResult;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

/**
 * 🏬 MIRAKL ADAPTER (Generic for Freemans, Debenhams, B&Q)
 *
 * Uses the Mirakl marketplace service implementation under the unified
 * MarketplaceClient to create/update products using SyncAccount credentials.
 *
 * - Supports operator-based Mirakl accounts via SyncAccount
 * - Derives operator-specific behavior from account credentials/settings
 * - Keeps transformation minimal; rely on existing product fields for MVP
 */
class MiraklAdapter extends AbstractAdapter
{
    /**
     * Get the marketplace name used for attribute keys and logs
     * If a SyncAccount is bound, prefer its channel (e.g., 'freemans', 'debenhams', 'bq', or 'mirakl')
     */
    protected function getMarketplaceName(): string
    {
        return $this->syncAccount?->channel ?: 'mirakl';
    }

    /**
     * Prepare a product for creation
     */
    public function create(int $productId): self
    {
        $this->mode = 'create';
        $this->currentProductId = $productId;

        return $this;
    }

    /**
     * Push the prepared operation to Mirakl
     */
    public function push(): SyncResult
    {
        try {
            // Ensure we have a SyncAccount and a product selected
            $account = $this->requireSyncAccount();
            $product = $this->loadProduct($this->currentProductId ?? 0);

            // Build the service for Mirakl using the existing MarketplaceClient
            $service = MarketplaceClient::for('mirakl')
                ->withAccount($account)
                ->build();

            // Determine operation
            if ($this->isUpdateMode()) {
                $remoteId = $this->determineRemoteProductId($product);
                $updateData = $this->buildUpdatePayload($product, $this->getFieldsToUpdate());

                $response = $service->updateProduct($remoteId, $updateData);

                if (! ($response['success'] ?? false)) {
                    return SyncResult::failure(
                        'Mirakl update failed',
                        [$response['error'] ?? 'Unknown error'],
                        ['marketplace' => $this->getMarketplaceName()]
                    );
                }

                return SyncResult::success('Mirakl product updated', [
                    'product_id' => $remoteId,
                    'updated_fields' => array_keys($this->getFieldsToUpdate()),
                ]);

            }

            // Default: create/recreate
            $payload = $this->buildCreatePayload($product);
            $response = $service->createProduct($payload);

            if (! ($response['success'] ?? false)) {
                return SyncResult::failure(
                    'Mirakl create failed',
                    [$response['error'] ?? 'Unknown error'],
                    ['marketplace' => $this->getMarketplaceName()]
                );
            }

            $remote = Arr::get($response, 'data.product', []);
            $remoteId = $remote['product_id'] ?? $payload['product_id'] ?? $product->parent_sku;

            return SyncResult::success('Mirakl product created', [
                'product_id' => $remoteId,
                'marketplace' => $this->getMarketplaceName(),
            ]);

        } catch (\Throwable $e) {
            Log::error('Mirakl push failed', [
                'marketplace' => $this->getMarketplaceName(),
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
     * Build create payload from Product model
     */
    protected function buildCreatePayload(Product $product): array
    {
        $brand = (string) ($product->getSmartAttributeValue('brand') ?? '');
        $categoryCode = $this->resolveDefaultCategoryCode();

        [$price, $quantity] = $this->resolvePriceAndQuantity($product);

        return [
            'sku' => $product->parent_sku,
            'product_id' => $product->parent_sku,
            'title' => $product->name,
            'description' => (string) ($product->description ?? ''),
            'brand' => $brand,
            'category_code' => $categoryCode,
            'price' => number_format((float) $price, 2, '.', ''),
            'currency' => $this->resolveCurrency(),
            'quantity' => $quantity,
            'state' => (int) $this->resolveDefaultState(),
        ];
    }

    /**
     * Build update payload from selected fields
     */
    protected function buildUpdatePayload(Product $product, array $fields): array
    {
        $payload = ['product_id' => $this->determineRemoteProductId($product)];

        if (isset($fields['title'])) {
            $payload['title'] = (string) $fields['title'];
        }

        if (isset($fields['images'])) {
            // Placeholder: Mirakl images require URLs; map if provided
            $payload['images'] = array_values(array_filter($fields['images']));
        }

        if (! empty($fields['pricing'])) {
            [$price] = $this->resolvePriceAndQuantity($product);
            $payload['price'] = number_format((float) $price, 2, '.', '');
            $payload['currency'] = $this->resolveCurrency();
        }

        return $payload;
    }

    /**
     * Determine remote product ID in Mirakl (typically our parent_sku)
     */
    protected function determineRemoteProductId(Product $product): string
    {
        return (string) ($product->parent_sku ?? $product->id);
    }

    /**
     * Resolve price and quantity heuristically from variants
     */
    protected function resolvePriceAndQuantity(Product $product): array
    {
        $variants = $product->variants;
        $price = $product->retail_price ?? null;
        $quantity = 0;

        if ($variants && $variants->count() > 0) {
            $prices = $variants->pluck('price')->filter()->all();
            if (empty($price) && ! empty($prices)) {
                $price = min($prices);
            }
            $quantity = (int) $variants->pluck('stock_level')->filter()->sum();
        }

        return [($price ?? 0.0), max(0, $quantity)];
    }

    /**
     * Resolve currency based on operator defaults or fallback to GBP
     */
    protected function resolveCurrency(): string
    {
        $channel = $this->getMarketplaceName();
        $map = config('services.mirakl_operators');

        return $map[$channel]['currency'] ?? 'GBP';
    }

    /**
     * Resolve default product state (active)
     */
    protected function resolveDefaultState(): int
    {
        $channel = $this->getMarketplaceName();
        $map = config('services.mirakl_operators');

        return (int) ($map[$channel]['default_state'] ?? 11);
    }

    /**
     * Resolve default category code from config mapping
     */
    protected function resolveDefaultCategoryCode(): string
    {
        $channel = $this->getMarketplaceName();
        $map = config('services.mirakl_operators');

        return (string) ($map[$channel]['category_code'] ?? '');
    }
}

