<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * 🔗 MARKETPLACE LINK MODEL
 *
 * Enhanced polymorphic model for linking both products and variants to marketplace listings.
 * Supports hierarchical relationships where variant links can reference their parent product links.
 *
 * @property string $linkable_type (App\Models\Product or App\Models\ProductVariant)
 * @property int $linkable_id
 * @property int $sync_account_id
 * @property int|null $parent_link_id
 * @property string $internal_sku
 * @property string $external_sku
 * @property string|null $external_product_id
 * @property string|null $external_variant_id
 * @property string $link_status
 * @property string $link_level (product|variant)
 * @property array|null $marketplace_data
 * @property \Carbon\Carbon|null $linked_at
 * @property string|null $linked_by
 */
class MarketplaceLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'linkable_type',
        'linkable_id',
        'sync_account_id',
        'parent_link_id',
        'internal_sku',
        'external_sku',
        'external_product_id',
        'external_variant_id',
        'link_status',
        'link_level',
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
     * 🎯 POLYMORPHIC RELATIONSHIP
     *
     * Links to either Product or ProductVariant
     */
    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * 🏢 SYNC ACCOUNT RELATIONSHIP
     */
    public function syncAccount(): BelongsTo
    {
        return $this->belongsTo(SyncAccount::class);
    }

    /**
     * 👨‍👩‍👧‍👦 PARENT LINK RELATIONSHIP
     *
     * For variant links to reference their parent product link
     */
    public function parentLink(): BelongsTo
    {
        return $this->belongsTo(MarketplaceLink::class, 'parent_link_id');
    }

    /**
     * 👶 CHILD LINKS RELATIONSHIP
     *
     * For product links to access their variant children
     */
    public function childLinks(): HasMany
    {
        return $this->hasMany(MarketplaceLink::class, 'parent_link_id');
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

    public function scopeUnlinked($query)
    {
        return $query->where('link_status', 'unlinked');
    }

    public function scopeProducts($query)
    {
        return $query->where('linkable_type', Product::class);
    }

    public function scopeVariants($query)
    {
        return $query->where('linkable_type', ProductVariant::class);
    }

    public function scopeByMarketplace($query, string $marketplace)
    {
        return $query->whereHas('syncAccount', fn ($q) => $q->where('channel', $marketplace));
    }

    public function scopeByLinkLevel($query, string $level)
    {
        return $query->where('link_level', $level);
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

    public function isUnlinked(): bool
    {
        return $this->link_status === 'unlinked';
    }

    public function isProductLevel(): bool
    {
        return $this->link_level === 'product' && $this->linkable_type === Product::class;
    }

    public function isVariantLevel(): bool
    {
        return $this->link_level === 'variant' && $this->linkable_type === ProductVariant::class;
    }

    public function hasParent(): bool
    {
        return ! is_null($this->parent_link_id);
    }

    public function hasChildren(): bool
    {
        return $this->childLinks()->exists();
    }

    /**
     * 🔗 LINK ACTIONS
     */
    public function markAsLinked(?string $externalProductId = null, ?string $externalVariantId = null, ?string $linkedBy = null): void
    {
        $this->update([
            'link_status' => 'linked',
            'linked_at' => now(),
            'linked_by' => $linkedBy ?? auth()->user()?->name,
            'external_product_id' => $externalProductId ?: $this->external_product_id,
            'external_variant_id' => $externalVariantId ?: $this->external_variant_id,
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

    public function markAsPending(): void
    {
        $this->update([
            'link_status' => 'pending',
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
            'unlinked' => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300'
        };
    }

    /**
     * 🎨 LINK LEVEL BADGE CLASS
     */
    public function getLevelBadgeClassAttribute(): string
    {
        return match ($this->link_level) {
            'product' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
            'variant' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
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
     * 📝 LINKABLE DISPLAY NAME
     *
     * Get human-readable name of linked item
     */
    public function getLinkableDisplayNameAttribute(): string
    {
        if ($this->isProductLevel()) {
            return $this->linkable->name;
        }

        if ($this->isVariantLevel()) {
            return $this->linkable->display_title ?? "{$this->linkable->product->name} - {$this->linkable->color}";
        }

        return 'Unknown';
    }

    /**
     * 🌐 EXTERNAL URL
     */
    public function getExternalUrlAttribute(): ?string
    {
        $productId = $this->external_product_id;
        $variantId = $this->external_variant_id;

        if (! $productId || ! $this->syncAccount) {
            return null;
        }

        return match ($this->syncAccount->channel) {
            'shopify' => $this->buildShopifyUrl($productId, $variantId),
            'ebay' => $this->buildEbayUrl($productId),
            'amazon' => $this->buildAmazonUrl($productId),
            default => null
        };
    }

    private function buildShopifyUrl(string $productId, ?string $variantId = null): ?string
    {
        $storeUrl = $this->syncAccount->credentials['store_url'] ?? null;
        if (! $storeUrl) {
            return null;
        }

        $url = "https://{$storeUrl}/admin/products/{$productId}";
        if ($variantId && $this->isVariantLevel()) {
            $url .= "/variants/{$variantId}";
        }

        return $url;
    }

    private function buildEbayUrl(string $productId): string
    {
        return "https://www.ebay.co.uk/itm/{$productId}";
    }

    private function buildAmazonUrl(string $productId): string
    {
        return "https://www.amazon.co.uk/dp/{$productId}";
    }

    /**
     * 📊 FIND OR CREATE FOR LINKABLE
     */
    public static function findOrCreateFor(Model $linkable, SyncAccount $syncAccount, ?MarketplaceLink $parentLink = null): self
    {
        $linkLevel = $linkable instanceof Product ? 'product' : 'variant';
        $internalSku = $linkable instanceof Product ? $linkable->parent_sku : $linkable->sku;

        return static::firstOrCreate([
            'linkable_type' => get_class($linkable),
            'linkable_id' => $linkable->id,
            'sync_account_id' => $syncAccount->id,
        ], [
            'parent_link_id' => $parentLink?->id,
            'internal_sku' => $internalSku ?? 'NO-SKU',
            'external_sku' => $internalSku ?? 'NO-SKU', // Default to same SKU
            'link_status' => 'pending',
            'link_level' => $linkLevel,
        ]);
    }

    /**
     * 🏗️ CREATE HIERARCHICAL LINK
     *
     * Create both product and variant links in proper hierarchy
     */
    public static function createHierarchicalLink(
        Product $product,
        ProductVariant $variant,
        SyncAccount $syncAccount,
        string $externalProductId,
        string $externalVariantId,
        string $externalSku
    ): array {
        // Create or find product link
        $productLink = static::findOrCreateFor($product, $syncAccount);
        $productLink->update([
            'external_product_id' => $externalProductId,
            'link_status' => 'linked',
            'linked_at' => now(),
        ]);

        // Create variant link with parent reference
        $variantLink = static::findOrCreateFor($variant, $syncAccount, $productLink);
        $variantLink->update([
            'external_product_id' => $externalProductId,
            'external_variant_id' => $externalVariantId,
            'external_sku' => $externalSku,
            'link_status' => 'linked',
            'linked_at' => now(),
        ]);

        return [
            'product_link' => $productLink,
            'variant_link' => $variantLink,
        ];
    }

    /**
     * 🗂️ GET HIERARCHY
     *
     * Get complete hierarchy for this link
     */
    public function getHierarchy(): array
    {
        if ($this->isProductLevel()) {
            return [
                'product_link' => $this,
                'variant_links' => $this->childLinks()->with('linkable')->get(),
            ];
        }

        if ($this->isVariantLevel() && $this->hasParent()) {
            return [
                'product_link' => $this->parentLink,
                'variant_links' => $this->parentLink->childLinks()->with('linkable')->get(),
                'current_variant' => $this,
            ];
        }

        return [
            'variant_link' => $this,
        ];
    }
}
