<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopifyProductSync extends Model
{
    protected $fillable = [
        'product_id',
        'color',
        'shopify_product_id',
        'shopify_handle',
        'sync_status',
        'last_sync_data',
        'last_synced_at',
    ];

    protected $casts = [
        'last_sync_data' => 'array',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Get the Laravel product
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Check if a product/color combination is already synced
     */
    public static function isAlreadySynced(int $productId, string $color): bool
    {
        return static::where('product_id', $productId)
            ->where('color', $color)
            ->where('sync_status', 'synced')
            ->exists();
    }

    /**
     * Get sync record for a product/color combination
     */
    public static function getSyncRecord(int $productId, string $color): ?self
    {
        return static::where('product_id', $productId)
            ->where('color', $color)
            ->first();
    }

    /**
     * Create or update sync record
     */
    public static function updateSyncRecord(
        int $productId,
        string $color,
        string $shopifyProductId,
        array $syncData,
        string $status = 'synced',
        ?string $handle = null
    ): self {
        return static::updateOrCreate(
            [
                'product_id' => $productId,
                'color' => $color,
            ],
            [
                'shopify_product_id' => $shopifyProductId,
                'shopify_handle' => $handle,
                'sync_status' => $status,
                'last_sync_data' => $syncData,
                'last_synced_at' => now(),
            ]
        );
    }

    /**
     * Check if sync data has changed (needs update)
     */
    public function hasDataChanged(array $newData): bool
    {
        if (empty($this->last_sync_data)) {
            return true;
        }

        // Compare key fields that would require an update
        $compareFields = ['title', 'body_html', 'variants_count', 'price_range'];

        foreach ($compareFields as $field) {
            if (($this->last_sync_data[$field] ?? null) !== ($newData[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all synced products for a Laravel product
     */
    public static function getSyncedProductsFor(int $productId): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('product_id', $productId)
            ->where('sync_status', 'synced')
            ->get();
    }
}
