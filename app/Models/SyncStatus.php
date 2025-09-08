<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 📊 UNIFIED SYNC STATUS MODEL
 *
 * Single source of truth for sync status across ALL integrations.
 * Replaces the old Shopify-specific sync status system.
 *
 * Supports:
 * - Multi-account sync (eBay UK/US, multiple Shopify stores)
 * - Color-separated products (Shopify)
 * - Variant-level sync tracking
 * - Rich metadata for each integration
 */
class SyncStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sync_account_id',
        'channel',
        'status',
        'external_id',
        'last_synced_at',
        'last_attempted_at',
        'last_error',
        'error_count',
        'product_checksum',
        'pricing_checksum',
        'inventory_checksum',
        'sync_metadata',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'last_attempted_at' => 'datetime',
        'sync_metadata' => 'array',
        'error_count' => 'integer',
        'updated_at' => 'datetime',
    ];

    /**
     * Backward-compatible attribute alias for code referencing `sync_status` while DB column is `status`.
     */
    public function getSyncStatusAttribute(): ?string
    {
        return $this->attributes['status'] ?? null;
    }

    public function setSyncStatusAttribute($value): void
    {
        $this->attributes['status'] = $value;
    }

    /**
     * 📦 PRODUCT RELATIONSHIP
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * 🎨 VARIANT RELATIONSHIP
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * 🏢 SYNC ACCOUNT RELATIONSHIP
     */
    public function syncAccount(): BelongsTo
    {
        return $this->belongsTo(SyncAccount::class);
    }

    /**
     * 📝 SYNC LOGS RELATIONSHIP
     */
    public function syncLogs(): HasMany
    {
        return $this->hasMany(SyncLog::class);
    }

    /**
     * ✅ STATUS CHECKS
     */
    public function isSynced(): bool
    {
        return $this->status === 'synced';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isOutOfSync(): bool
    {
        return $this->status === 'out_of_sync';
    }

    public function needsSync(): bool
    {
        return in_array($this->status, ['pending', 'out_of_sync', 'failed']);
    }

    /**
     * 🎯 SCOPES
     */
    public function scopeSynced($query)
    {
        return $query->where('status', 'synced');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeOutOfSync($query)
    {
        return $query->where('sync_status', 'out_of_sync');
    }

    public function scopeNeedsSync($query)
    {
        return $query->whereIn('sync_status', ['pending', 'out_of_sync', 'failed']);
    }

    public function scopeForChannel($query, string $channel)
    {
        return $query->whereHas('syncAccount', fn ($q) => $q->where('channel', $channel));
    }

    public function scopeForAccount($query, string $channel, string $accountName)
    {
        return $query->whereHas('syncAccount', function ($q) use ($channel, $accountName) {
            $q->where('channel', $channel)->where('name', $accountName);
        });
    }

    public function scopeColorSeparated($query)
    {
        return $query->where('sync_type', 'color_separated');
    }

    public function scopeByColor($query, string $color)
    {
        return $query->where('color', $color);
    }

    /**
     * 🔍 FIND OR CREATE SYNC STATUS
     *
     * Used by sync services to get/create sync status records
     */
    public static function findOrCreateFor(
        Product $product,
        SyncAccount $account,
        ?ProductVariant $variant = null,
        ?string $color = null
    ): self {
        return static::firstOrCreate([
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'sync_account_id' => $account->id,
            'color' => $color,
        ], [
            'sync_status' => 'pending',
            'sync_type' => $color ? 'color_separated' : 'standard',
        ]);
    }

    /**
     * 🎯 MARK AS SYNCED
     */
    public function markAsSynced(
        ?string $externalProductId = null,
        ?string $externalVariantId = null,
        ?string $externalHandle = null,
        ?array $metadata = null
    ): void {
        $this->update([
            'status' => 'synced',
            'last_synced_at' => now(),
            'last_error' => null,
            // Keep external IDs if your schema supports them; otherwise, skip
            // 'external_product_id' => $externalProductId ?: $this->external_product_id,
            // 'external_variant_id' => $externalVariantId ?: $this->external_variant_id,
            // 'external_handle' => $externalHandle ?: $this->external_handle,
            // 'metadata' => $metadata ? array_merge($this->metadata ?? [], $metadata) : $this->metadata,
        ]);
    }

    /**
     * ❌ MARK AS FAILED
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'last_error' => $errorMessage,
        ]);
    }

    /**
     * ⏳ MARK AS PENDING
     */
    public function markAsPending(): void
    {
        $this->update([
            'status' => 'pending',
            'last_error' => null,
        ]);
    }

    /**
     * 🔄 MARK AS OUT OF SYNC
     */
    public function markAsOutOfSync(?string $reason = null): void
    {
        $this->update([
            'status' => 'out_of_sync',
            'last_error' => $reason,
        ]);
    }

    /**
     * 🛍️ GET EXTERNAL URL
     *
     * Build URL to view this item in the external system
     */
    public function getExternalUrlAttribute(): ?string
    {
        if (! $this->external_product_id) {
            return null;
        }

        return match ($this->syncAccount->channel) {
            'shopify' => $this->buildShopifyUrl(),
            'ebay' => $this->buildEbayUrl(),
            'amazon' => $this->buildAmazonUrl(),
            default => null
        };
    }

    private function buildShopifyUrl(): string
    {
        $storeUrl = $this->syncAccount->credentials['store_url'] ?? '';

        return "https://{$storeUrl}/admin/products/{$this->external_product_id}";
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
     * 🎨 STATUS BADGE CLASS
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'synced' => 'bg-green-100 text-green-800',
            'pending' => 'bg-yellow-100 text-yellow-800',
            'failed' => 'bg-red-100 text-red-800',
            'out_of_sync' => 'bg-orange-100 text-orange-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    /**
     * 📊 SYNC AGE
     */
    public function getSyncAgeAttribute(): string
    {
        if (! $this->last_synced_at) {
            return 'Never synced';
        }

        return $this->last_synced_at->diffForHumans();
    }

    /**
     * 🔍 GET LAST SYNC LOG
     */
    public function getLastSyncLog(): ?SyncLog
    {
        return $this->syncLogs()->latest()->first();
    }

    /**
     * 📈 GET SYNC SUCCESS RATE
     *
     * Percentage of successful syncs for this record
     */
    public function getSyncSuccessRate(): float
    {
        $logs = $this->syncLogs();
        $total = $logs->count();

        if ($total === 0) {
            return 0.0;
        }

        $successful = $logs->where('status', 'success')->count();

        return round(($successful / $total) * 100, 1);
    }
}
