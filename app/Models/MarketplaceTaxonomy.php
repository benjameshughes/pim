<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 🏷️ MARKETPLACE TAXONOMY MODEL
 *
 * Caches taxonomy data (categories, attributes, values) from all marketplaces.
 * Updated monthly via scheduled job to keep product attribute forms fast.
 *
 * Supports hierarchical structures and rich metadata for any marketplace.
 */
class MarketplaceTaxonomy extends Model
{
    use HasFactory;

    protected $table = 'marketplace_taxonomies';

    protected $fillable = [
        'sync_account_id',
        'taxonomy_type',
        'external_id',
        'external_parent_id',
        'name',
        'key',
        'description',
        'level',
        'is_leaf',
        'is_required',
        'data_type',
        'validation_rules',
        'metadata',
        'properties',
        'last_synced_at',
        'is_active',
        'sync_version',
    ];

    protected function casts(): array
    {
        return [
            'validation_rules' => 'array',
            'metadata' => 'array',
            'properties' => 'array',
            'last_synced_at' => 'datetime',
            'is_leaf' => 'boolean',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'level' => 'integer',
        ];
    }

    // ==================== RELATIONSHIPS ====================

    /**
     * 🔗 Marketplace sync account this taxonomy belongs to
     */
    public function syncAccount(): BelongsTo
    {
        return $this->belongsTo(SyncAccount::class);
    }

    /**
     * 🏷️ Product attributes assigned using this taxonomy
     */
    public function productAttributes(): HasMany
    {
        return $this->hasMany(MarketplaceProductAttribute::class);
    }

    /**
     * 👨‍👩‍👧‍👦 Parent taxonomy item (for hierarchical structures)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(MarketplaceTaxonomy::class, 'external_parent_id', 'external_id')
            ->where('sync_account_id', $this->sync_account_id);
    }

    /**
     * 👶 Child taxonomy items
     */
    public function children(): HasMany
    {
        return $this->hasMany(MarketplaceTaxonomy::class, 'external_parent_id', 'external_id')
            ->where('sync_account_id', $this->sync_account_id);
    }

    // ==================== SCOPES ====================

    /**
     * 📁 Categories only
     */
    public function scopeCategories($query)
    {
        return $query->where('taxonomy_type', 'category');
    }

    /**
     * 🏷️ Attributes only
     */
    public function scopeAttributes($query)
    {
        return $query->where('taxonomy_type', 'attribute');
    }

    /**
     * 🎯 Values only
     */
    public function scopeValues($query)
    {
        return $query->where('taxonomy_type', 'value');
    }

    /**
     * ✅ Active items only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * 🔍 For specific marketplace
     */
    public function scopeForMarketplace($query, SyncAccount $syncAccount)
    {
        return $query->where('sync_account_id', $syncAccount->id);
    }

    /**
     * 📊 Root level items
     */
    public function scopeRootLevel($query)
    {
        return $query->where('level', 1)->whereNull('external_parent_id');
    }

    /**
     * 🍃 Leaf nodes (no children)
     */
    public function scopeLeafNodes($query)
    {
        return $query->where('is_leaf', true);
    }

    // ==================== HELPER METHODS ====================

    /**
     * 🎯 Get choices/values for attribute taxonomies
     */
    public function getChoices(): array
    {
        if ($this->taxonomy_type !== 'attribute') {
            return [];
        }

        return $this->validation_rules['choices'] ?? [];
    }

    /**
     * 📊 Check if this taxonomy has choices/values
     */
    public function hasChoices(): bool
    {
        return ! empty($this->getChoices());
    }

    /**
     * 🔍 Get full hierarchy path
     */
    public function getHierarchyPath(): string
    {
        if (! $this->parent) {
            return $this->name;
        }

        return $this->parent->getHierarchyPath().' > '.$this->name;
    }

    /**
     * 📈 Get popularity score (if available in properties)
     */
    public function getPopularityScore(): ?int
    {
        return $this->properties['popularity_score'] ?? null;
    }

    /**
     * 🏷️ Get use cases (if available in properties)
     */
    public function getUseCases(): array
    {
        return $this->properties['use_cases'] ?? [];
    }

    /**
     * ⚡ Is this taxonomy recently synced? (within last 30 days)
     */
    public function isRecentlySynced(): bool
    {
        return $this->last_synced_at && $this->last_synced_at->gt(now()->subDays(30));
    }

    // ==================== STATIC METHODS ====================

    /**
     * 🔍 Find categories for a marketplace
     */
    public static function getCategoriesForMarketplace(SyncAccount $syncAccount): \Illuminate\Database\Eloquent\Collection
    {
        return static::categories()
            ->forMarketplace($syncAccount)
            ->active()
            ->orderBy('level')
            ->orderBy('name')
            ->get();
    }

    /**
     * 🏷️ Find attributes for a marketplace
     */
    public static function getAttributesForMarketplace(SyncAccount $syncAccount): \Illuminate\Database\Eloquent\Collection
    {
        return static::attributes()
            ->forMarketplace($syncAccount)
            ->active()
            ->orderBy('is_required', 'desc')
            ->orderBy('name')
            ->get();
    }

    /**
     * 🎯 Find values for a specific attribute
     */
    public static function getValuesForAttribute(SyncAccount $syncAccount, string $attributeKey): \Illuminate\Database\Eloquent\Collection
    {
        return static::values()
            ->forMarketplace($syncAccount)
            ->where('key', $attributeKey)
            ->active()
            ->orderBy('name')
            ->get();
    }
}
