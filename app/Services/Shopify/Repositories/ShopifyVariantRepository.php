<?php

namespace App\Services\Shopify\Repositories;

use App\Models\ProductVariant;
use App\Services\Shopify\API\Client\ShopifyClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Repository for Shopify variant operations
 *
 * Handles variant-specific operations between the app and Shopify.
 * Complements the ShopifyProductRepository for variant management.
 */
class ShopifyVariantRepository
{
    protected ShopifyClient $client;

    public function __construct(?ShopifyClient $client = null)
    {
        $this->client = $client ?? ShopifyClient::fromEnv();
    }

    /**
     * Create a variant in Shopify
     */
    public function createVariant(int $shopifyProductId, ProductVariant $variant): array
    {
        try {
            Log::info('Creating variant in Shopify', [
                'shopify_product_id' => $shopifyProductId,
                'variant_sku' => $variant->sku,
            ]);

            $variantData = $this->buildVariantData($variant);
            $result = $this->client->rest()->createVariant($shopifyProductId, $variantData);

            if ($result['success']) {
                // Update local variant with Shopify ID
                $variant->update([
                    'shopify_variant_id' => $result['variant_id'],
                    'shopify_synced_at' => now(),
                ]);

                Log::info('Variant created successfully', [
                    'variant_id' => $variant->id,
                    'shopify_variant_id' => $result['variant_id'],
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Failed to create variant in Shopify', [
                'variant_id' => $variant->id,
                'shopify_product_id' => $shopifyProductId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update a variant in Shopify
     */
    public function updateVariant(ProductVariant $variant): array
    {
        if (! $variant->shopify_variant_id) {
            return [
                'success' => false,
                'error' => 'Variant has no Shopify ID for update operation',
            ];
        }

        try {
            Log::info('Updating variant in Shopify', [
                'variant_id' => $variant->id,
                'shopify_variant_id' => $variant->shopify_variant_id,
            ]);

            $variantData = $this->buildVariantData($variant);
            $result = $this->client->rest()->updateVariant(
                (int) $variant->shopify_variant_id,
                $variantData
            );

            if ($result['success']) {
                $variant->update(['shopify_synced_at' => now()]);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Failed to update variant in Shopify', [
                'variant_id' => $variant->id,
                'shopify_variant_id' => $variant->shopify_variant_id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get variants for a Shopify product
     */
    public function getVariants(int $shopifyProductId): array
    {
        try {
            Log::info('Getting variants from Shopify', [
                'shopify_product_id' => $shopifyProductId,
            ]);

            return $this->client->rest()->getVariants($shopifyProductId);

        } catch (\Exception $e) {
            Log::error('Failed to get variants from Shopify', [
                'shopify_product_id' => $shopifyProductId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete a variant from Shopify
     */
    public function deleteVariant(ProductVariant $variant): bool
    {
        if (! $variant->shopify_variant_id) {
            return false;
        }

        try {
            Log::info('Deleting variant from Shopify', [
                'variant_id' => $variant->id,
                'shopify_variant_id' => $variant->shopify_variant_id,
            ]);

            // Note: Shopify doesn't have a direct variant delete API
            // Variants are usually deleted by updating the product
            // For now, we'll clear the Shopify ID locally
            $variant->update([
                'shopify_variant_id' => null,
                'shopify_synced_at' => null,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to delete variant from Shopify', [
                'variant_id' => $variant->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Sync multiple variants to Shopify
     */
    public function syncVariants(int $shopifyProductId, Collection $variants): Collection
    {
        $results = collect();

        Log::info('Starting bulk variant sync', [
            'shopify_product_id' => $shopifyProductId,
            'variant_count' => $variants->count(),
        ]);

        foreach ($variants as $variant) {
            if ($variant->shopify_variant_id) {
                $result = $this->updateVariant($variant);
            } else {
                $result = $this->createVariant($shopifyProductId, $variant);
            }

            $results->push([
                'variant_id' => $variant->id,
                'sku' => $variant->sku,
                'success' => $result['success'],
                'shopify_variant_id' => $result['variant_id'] ?? null,
                'error' => $result['error'] ?? null,
            ]);

            // Small delay to avoid rate limiting
            usleep(100000); // 100ms
        }

        Log::info('Bulk variant sync completed', [
            'total' => $results->count(),
            'successful' => $results->where('success', true)->count(),
            'failed' => $results->where('success', false)->count(),
        ]);

        return $results;
    }

    /**
     * Update inventory levels for variants
     */
    public function updateInventoryLevels(Collection $variants): Collection
    {
        $results = collect();

        foreach ($variants as $variant) {
            if (! $variant->shopify_variant_id) {
                $results->push([
                    'variant_id' => $variant->id,
                    'success' => false,
                    'error' => 'No Shopify variant ID',
                ]);

                continue;
            }

            try {
                // Update inventory using the variant update API
                $result = $this->updateVariant($variant);

                $results->push([
                    'variant_id' => $variant->id,
                    'success' => $result['success'],
                    'stock_level' => $variant->stock_level,
                    'error' => $result['error'] ?? null,
                ]);

            } catch (\Exception $e) {
                $results->push([
                    'variant_id' => $variant->id,
                    'success' => false,
                    'error' => $e->getMessage(),
                ]);
            }

            usleep(100000); // 100ms delay
        }

        return $results;
    }

    /**
     * Build variant data for Shopify API
     */
    protected function buildVariantData(ProductVariant $variant): array
    {
        $data = [
            'sku' => $variant->sku,
            'price' => (string) ($variant->price ?? '0.00'),
            'inventory_quantity' => $variant->stock_level ?? 0,
            'weight' => $variant->weight ?? 0,
            'weight_unit' => 'kg',
        ];

        // Add barcode if available
        if ($variant->barcode) {
            $data['barcode'] = $variant->barcode->barcode_number;
        }

        // Add options
        if ($variant->color) {
            $data['option1'] = $variant->color;
        }

        if ($variant->size) {
            $data['option2'] = $variant->size;
        }

        // Add dimensions if available
        if ($variant->width && $variant->drop) {
            $data['option3'] = "W:{$variant->width} x D:{$variant->drop}";
        }

        // Add inventory management
        $data['inventory_management'] = 'shopify';
        $data['inventory_policy'] = 'deny'; // Don't allow negative inventory
        $data['fulfillment_service'] = 'manual';
        $data['requires_shipping'] = true;
        $data['taxable'] = true;

        return $data;
    }

    /**
     * Find variant by Shopify ID
     */
    public function findByShopifyId(string $shopifyVariantId): ?ProductVariant
    {
        return ProductVariant::where('shopify_variant_id', $shopifyVariantId)->first();
    }

    /**
     * Check if variant exists in Shopify
     */
    public function exists(string $shopifyVariantId): bool
    {
        try {
            // We can check this by trying to get the parent product's variants
            // and looking for our specific variant ID
            // This is a bit indirect but works with the Shopify API

            $variant = $this->findByShopifyId($shopifyVariantId);
            if (! $variant || ! $variant->product || ! $variant->product->shopify_product_id) {
                return false;
            }

            $result = $this->getVariants((int) $variant->product->shopify_product_id);

            if (! $result['success']) {
                return false;
            }

            foreach ($result['data'] as $shopifyVariant) {
                if ((string) $shopifyVariant['id'] === $shopifyVariantId) {
                    return true;
                }
            }

            return false;

        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the underlying client
     */
    public function getClient(): ShopifyClient
    {
        return $this->client;
    }
}
