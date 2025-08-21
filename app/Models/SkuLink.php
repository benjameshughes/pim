<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 🔗 SKU LINK MODEL
 *
 * Represents the linking between internal products and marketplace listings
 * based on SKU matching. Integrates with the existing sync system.
 */
class SkuLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sync_account_id',
        'internal_sku',
        'external_sku',
        'external_product_id',
        'link_status',
        'marketplace_data',
        'linked_at',
        'linked_by',
    ];

    protected $casts = [
        'marketplace_data' => 'array',
        'linked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 📦 PRODUCT RELATIONSHIP
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * 🏢 SYNC ACCOUNT RELATIONSHIP
     */
    public function syncAccount(): BelongsTo
    {
        return $this->belongsTo(SyncAccount::class);
    }

    /**
     * 🎯 SCOPES
     */
    public function scopeLinked($query)
    {
        return $query->where('link_status', 'linked');
    }

    public function scopePending($query)
    {
        return $query->where('link_status', 'pending');
    }

    public function scopeFailed($query)
    {
        return $query->where('link_status', 'failed');
    }

    public function scopeByMarketplace($query, string $marketplace)
    {
        return $query->whereHas('syncAccount', fn ($q) => $q->where('channel', $marketplace));
    }

    /**
     * ✅ STATUS CHECKS
     */
    public function isLinked(): bool
    {
        return $this->link_status === 'linked';
    }

    public function isPending(): bool
    {
        return $this->link_status === 'pending';
    }

    public function hasFailed(): bool
    {
        return $this->link_status === 'failed';
    }

    /**
     * 🔗 LINK ACTIONS
     */
    public function markAsLinked(?string $externalProductId = null, ?string $linkedBy = null): void
    {
        $this->update([
            'link_status' => 'linked',
            'linked_at' => now(),
            'linked_by' => $linkedBy ?? auth()->user()?->name,
            'external_product_id' => $externalProductId ?: $this->external_product_id,
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update([
            'link_status' => 'failed',
        ]);
    }

    public function unlink(): void
    {
        $this->update([
            'link_status' => 'unlinked',
            'linked_at' => null,
        ]);
    }

    /**
     * 🎨 STATUS BADGE CLASS
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->link_status) {
            'linked' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
            'pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
            'failed' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300'
        };
    }

    /**
     * 🔍 MARKETPLACE DISPLAY NAME
     */
    public function getMarketplaceDisplayNameAttribute(): string
    {
        return $this->syncAccount->display_name ?? ucfirst($this->syncAccount->channel ?? 'Unknown');
    }

    /**
     * 🌐 EXTERNAL URL
     */
    public function getExternalUrlAttribute(): ?string
    {
        if (! $this->external_product_id || ! $this->syncAccount) {
            return null;
        }

        return match ($this->syncAccount->channel) {
            'shopify' => $this->buildShopifyUrl(),
            'ebay' => $this->buildEbayUrl(),
            'amazon' => $this->buildAmazonUrl(),
            default => null
        };
    }

    private function buildShopifyUrl(): ?string
    {
        $storeUrl = $this->syncAccount->credentials['store_url'] ?? null;

        return $storeUrl ? "https://{$storeUrl}/admin/products/{$this->external_product_id}" : null;
    }

    private function buildEbayUrl(): string
    {
        return "https://www.ebay.co.uk/itm/{$this->external_product_id}";
    }

    private function buildAmazonUrl(): string
    {
        return "https://www.amazon.co.uk/dp/{$this->external_product_id}";
    }

    /**
     * 📊 FIND OR CREATE FOR PRODUCT
     */
    public static function findOrCreateFor(Product $product, SyncAccount $syncAccount): self
    {
        return static::firstOrCreate([
            'product_id' => $product->id,
            'sync_account_id' => $syncAccount->id,
        ], [
            'internal_sku' => $product->parent_sku ?? 'NO-SKU',
            'external_sku' => $product->parent_sku ?? 'NO-SKU', // Default to same SKU
            'link_status' => 'pending',
        ]);
    }
}
