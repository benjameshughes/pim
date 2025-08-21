<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopifySyncStatus extends Model
{
    use HasFactory;

    protected $table = 'shopify_sync_status';

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'shopify_product_id',
        'shopify_variant_id',
        'sync_status',
        'last_synced_at',
        'error_message',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 🏠 PRODUCT RELATIONSHIP
     *
     * Each sync record belongs to a product
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * 📦 VARIANT RELATIONSHIP
     *
     * Each sync record can belong to a specific variant
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * ✅ IS SYNCED
     *
     * Check if the item is successfully synced
     */
    public function isSynced()
    {
        return $this->sync_status === 'synced';
    }

    /**
     * ⏳ IS PENDING
     *
     * Check if sync is pending
     */
    public function isPending()
    {
        return $this->sync_status === 'pending';
    }

    /**
     * ❌ HAS FAILED
     *
     * Check if sync has failed
     */
    public function hasFailed()
    {
        return $this->sync_status === 'failed';
    }

    /**
     * 🛍️ SHOPIFY URL
     *
     * Get the Shopify admin URL for this item
     */
    public function getShopifyUrlAttribute()
    {
        if ($this->shopify_product_id) {
            $baseUrl = config('shopify.admin_url', 'https://admin.shopify.com');

            return "{$baseUrl}/products/{$this->shopify_product_id}";
        }

        return null;
    }

    /**
     * 📊 SYNC AGE
     *
     * Get human-readable sync age
     */
    public function getSyncAgeAttribute()
    {
        if (! $this->last_synced_at) {
            return 'Never synced';
        }

        return $this->last_synced_at->diffForHumans();
    }

    /**
     * 🎨 STATUS BADGE CLASS
     *
     * Get CSS class for status badge
     */
    public function getStatusBadgeClassAttribute()
    {
        return match ($this->sync_status) {
            'synced' => 'bg-green-100 text-green-800',
            'pending' => 'bg-yellow-100 text-yellow-800',
            'failed' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    /**
     * 🔍 SCOPE: Synced records
     */
    public function scopeSynced($query)
    {
        return $query->where('sync_status', 'synced');
    }

    /**
     * 🔍 SCOPE: Pending records
     */
    public function scopePending($query)
    {
        return $query->where('sync_status', 'pending');
    }

    /**
     * 🔍 SCOPE: Failed records
     */
    public function scopeFailed($query)
    {
        return $query->where('sync_status', 'failed');
    }

    /**
     * 🔍 SCOPE: Recent syncs
     */
    public function scopeRecentSyncs($query, $hours = 24)
    {
        return $query->where('last_synced_at', '>=', now()->subHours($hours));
    }

    /**
     * 🎯 MARK AS SYNCED
     *
     * Mark this record as successfully synced
     */
    public function markAsSynced(?string $shopifyProductId = null, ?string $shopifyVariantId = null)
    {
        $this->update([
            'sync_status' => 'synced',
            'last_synced_at' => now(),
            'error_message' => null,
            'shopify_product_id' => $shopifyProductId ?: $this->shopify_product_id,
            'shopify_variant_id' => $shopifyVariantId ?: $this->shopify_variant_id,
        ]);
    }

    /**
     * ❌ MARK AS FAILED
     *
     * Mark this record as failed with error message
     */
    public function markAsFailed(string $errorMessage)
    {
        $this->update([
            'sync_status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * ⏳ MARK AS PENDING
     *
     * Mark this record as pending sync
     */
    public function markAsPending()
    {
        $this->update([
            'sync_status' => 'pending',
            'error_message' => null,
        ]);
    }
}
