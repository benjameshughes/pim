<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 🔗 MARKETPLACE PRODUCT ATTRIBUTE MODEL
 *
 * Stores assignments of marketplace-specific attributes to products.
 * Keeps marketplace taxonomy data separate from core product functionality.
 *
 * Supports validation, tracking, and bulk operations for marketplace attributes.
 */
class MarketplaceProductAttribute extends Model
{
    use HasFactory;

    protected $table = 'marketplace_product_attributes';

    protected $fillable = [
        'product_id',
        'sync_account_id',
        'marketplace_taxonomy_id',
        'attribute_key',
        'attribute_name',
        'attribute_value',
        'display_value',
        'data_type',
        'is_required',
        'value_metadata',
        'sync_metadata',
        'assigned_at',
        'assigned_by',
        'last_validated_at',
        'is_valid',
    ];

    protected function casts(): array
    {
        return [
            'value_metadata' => 'array',
            'sync_metadata' => 'array',
            'assigned_at' => 'datetime',
            'last_validated_at' => 'datetime',
            'is_required' => 'boolean',
            'is_valid' => 'boolean',
        ];
    }

    // ==================== RELATIONSHIPS ====================

    /**
     * 📦 Product this attribute is assigned to
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * 🔗 Marketplace sync account
     */
    public function syncAccount(): BelongsTo
    {
        return $this->belongsTo(SyncAccount::class);
    }

    /**
     * 🏷️ Marketplace taxonomy definition
     */
    public function marketplaceTaxonomy(): BelongsTo
    {
        return $this->belongsTo(MarketplaceTaxonomy::class);
    }

    // ==================== SCOPES ====================

    /**
     * 📦 For specific product
     */
    public function scopeForProduct($query, Product $product)
    {
        return $query->where('product_id', $product->id);
    }

    /**
     * 🔗 For specific marketplace
     */
    public function scopeForMarketplace($query, SyncAccount $syncAccount)
    {
        return $query->where('sync_account_id', $syncAccount->id);
    }

    /**
     * ✅ Valid attributes only
     */
    public function scopeValid($query)
    {
        return $query->where('is_valid', true);
    }

    /**
     * ⚠️ Invalid attributes only
     */
    public function scopeInvalid($query)
    {
        return $query->where('is_valid', false);
    }

    /**
     * ❗ Required attributes only
     */
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    /**
     * 🔍 By attribute key
     */
    public function scopeByAttribute($query, string $attributeKey)
    {
        return $query->where('attribute_key', $attributeKey);
    }

    /**
     * 📊 Recently assigned (within last 7 days)
     */
    public function scopeRecentlyAssigned($query)
    {
        return $query->where('assigned_at', '>=', now()->subDays(7));
    }

    // ==================== HELPER METHODS ====================

    /**
     * 🎯 Get parsed attribute value based on data type
     */
    public function getParsedValue()
    {
        return match ($this->data_type) {
            'boolean' => (bool) $this->attribute_value,
            'number', 'integer' => (int) $this->attribute_value,
            'decimal', 'float' => (float) $this->attribute_value,
            'list', 'array' => json_decode($this->attribute_value, true),
            'dimension' => $this->parseDimensionValue(),
            default => $this->attribute_value,
        };
    }

    /**
     * 📏 Parse dimension value (width x height)
     */
    private function parseDimensionValue(): ?array
    {
        if ($this->data_type !== 'dimension') {
            return null;
        }

        $value = json_decode($this->attribute_value, true);

        return [
            'width' => $value['width'] ?? null,
            'height' => $value['height'] ?? null,
            'unit' => $value['unit'] ?? 'cm',
            'display' => $this->display_value,
        ];
    }

    /**
     * ✅ Validate this attribute against taxonomy rules
     */
    public function validateAttribute(): bool
    {
        $taxonomy = $this->marketplaceTaxonomy;

        if (! $taxonomy) {
            return false;
        }

        $rules = $taxonomy->validation_rules ?? [];
        $value = $this->getParsedValue();

        // Check required field
        if ($taxonomy->is_required && empty($value)) {
            $this->update(['is_valid' => false]);

            return false;
        }

        // Check choices validation
        if (! empty($rules['choices']) && ! in_array($value, $rules['choices'])) {
            $this->update(['is_valid' => false]);

            return false;
        }

        // Check data type validation
        if (! $this->validateDataType($value, $taxonomy->data_type)) {
            $this->update(['is_valid' => false]);

            return false;
        }

        // All validations passed
        $this->update([
            'is_valid' => true,
            'last_validated_at' => now(),
        ]);

        return true;
    }

    /**
     * 🔍 Validate data type
     */
    private function validateDataType($value, string $expectedType): bool
    {
        return match ($expectedType) {
            'text', 'string' => is_string($value),
            'number', 'integer' => is_numeric($value),
            'boolean' => is_bool($value),
            'list', 'array' => is_array($value),
            'dimension' => is_array($value) && isset($value['width'], $value['height']),
            default => true, // Allow unknown types
        };
    }

    /**
     * 📊 Get display-friendly value
     */
    public function getDisplayValue(): string
    {
        if ($this->display_value) {
            return $this->display_value;
        }

        $value = $this->getParsedValue();

        return match ($this->data_type) {
            'boolean' => $value ? 'Yes' : 'No',
            'list', 'array' => implode(', ', $value),
            'dimension' => $value['display'] ?? "{$value['width']}x{$value['height']} {$value['unit']}",
            default => (string) $value,
        };
    }

    /**
     * ⚡ Is this attribute recently validated? (within last 7 days)
     */
    public function isRecentlyValidated(): bool
    {
        return $this->last_validated_at && $this->last_validated_at->gt(now()->subDays(7));
    }

    /**
     * 📈 Get completeness score for this attribute (0-100)
     */
    public function getCompletenessScore(): int
    {
        $score = 0;

        // Has value
        if (! empty($this->attribute_value)) {
            $score += 40;
        }

        // Has display value
        if (! empty($this->display_value)) {
            $score += 20;
        }

        // Is valid
        if ($this->is_valid) {
            $score += 30;
        }

        // Recently validated
        if ($this->isRecentlyValidated()) {
            $score += 10;
        }

        return $score;
    }

    // ==================== STATIC METHODS ====================

    /**
     * 🔍 Get all attributes for a product in a marketplace
     */
    public static function getForProductInMarketplace(Product $product, SyncAccount $syncAccount): \Illuminate\Database\Eloquent\Collection
    {
        return static::forProduct($product)
            ->forMarketplace($syncAccount)
            ->with(['marketplaceTaxonomy'])
            ->orderBy('is_required', 'desc')
            ->orderBy('attribute_name')
            ->get();
    }

    /**
     * ⚠️ Get missing required attributes for a product in a marketplace
     */
    public static function getMissingRequiredAttributes(Product $product, SyncAccount $syncAccount): \Illuminate\Database\Eloquent\Collection
    {
        $assignedKeys = static::forProduct($product)
            ->forMarketplace($syncAccount)
            ->pluck('attribute_key');

        return MarketplaceTaxonomy::attributes()
            ->forMarketplace($syncAccount)
            ->where('is_required', true)
            ->whereNotIn('key', $assignedKeys)
            ->get();
    }

    /**
     * 📊 Get completion percentage for a product in a marketplace
     */
    public static function getCompletionPercentage(Product $product, SyncAccount $syncAccount): int
    {
        $totalRequired = MarketplaceTaxonomy::attributes()
            ->forMarketplace($syncAccount)
            ->where('is_required', true)
            ->count();

        if ($totalRequired === 0) {
            return 100;
        }

        $completedRequired = static::forProduct($product)
            ->forMarketplace($syncAccount)
            ->required()
            ->valid()
            ->count();

        return round(($completedRequired / $totalRequired) * 100);
    }
}
