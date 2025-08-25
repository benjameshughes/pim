<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * 🎨 VARIANT ATTRIBUTE MODEL
 *
 * Stores attribute values for product variants. Each instance represents
 * one attribute value for one variant, with inheritance tracking from
 * the parent product, full audit trail, validation, and marketplace sync.
 */
class VariantAttribute extends Model
{
    use HasFactory;

    protected $fillable = [
        'variant_id',
        'attribute_definition_id',
        'value',
        'display_value',
        'value_metadata',
        'is_valid',
        'validation_errors',
        'last_validated_at',
        'source',
        'assigned_at',
        'assigned_by',
        'assignment_metadata',
        'is_inherited',
        'inherited_from_product_attribute_id',
        'inherited_at',
        'is_override',
        'sync_status',
        'last_synced_at',
        'sync_metadata',
        'previous_value',
        'value_changed_at',
        'version',
    ];

    protected $casts = [
        'value_metadata' => 'array',
        'is_valid' => 'boolean',
        'validation_errors' => 'array',
        'last_validated_at' => 'datetime',
        'assigned_at' => 'datetime',
        'assignment_metadata' => 'array',
        'is_inherited' => 'boolean',
        'inherited_at' => 'datetime',
        'is_override' => 'boolean',
        'sync_status' => 'array',
        'last_synced_at' => 'datetime',
        'sync_metadata' => 'array',
        'value_changed_at' => 'datetime',
    ];

    /**
     * 🔗 RELATIONSHIPS
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function attributeDefinition(): BelongsTo
    {
        return $this->belongsTo(AttributeDefinition::class);
    }

    public function inheritedFromProductAttribute(): BelongsTo
    {
        return $this->belongsTo(ProductAttribute::class, 'inherited_from_product_attribute_id');
    }

    /**
     * 🔍 SCOPES
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->where('is_valid', true);
    }

    public function scopeInvalid(Builder $query): Builder
    {
        return $query->where('is_valid', false);
    }

    public function scopeForAttribute(Builder $query, string $attributeKey): Builder
    {
        return $query->whereHas('attributeDefinition', function ($q) use ($attributeKey) {
            $q->where('key', $attributeKey);
        });
    }

    public function scopeFromSource(Builder $query, string $source): Builder
    {
        return $query->where('source', $source);
    }

    public function scopeInherited(Builder $query): Builder
    {
        return $query->where('is_inherited', true);
    }

    public function scopeOverrides(Builder $query): Builder
    {
        return $query->where('is_override', true);
    }

    public function scopeExplicitlySet(Builder $query): Builder
    {
        return $query->where('is_inherited', false);
    }

    public function scopeNeedingSync(Builder $query, string $marketplace): Builder
    {
        return $query->where(function ($q) use ($marketplace) {
            $q->whereNull('sync_status')
                ->orWhereJsonDoesntContain('sync_status', [$marketplace => 'synced'])
                ->orWhere('value_changed_at', '>', 'last_synced_at');
        });
    }

    /**
     * 🎯 VALUE HANDLING
     */

    /**
     * Get the typed value based on attribute definition
     */
    public function getTypedValue()
    {
        if ($this->value === null) {
            return null;
        }

        return $this->attributeDefinition->castValue($this->value);
    }

    /**
     * Set value with automatic validation, type casting, and inheritance tracking
     */
    public function setValue($value, array $options = []): bool
    {
        // Track previous value for change detection
        $this->previous_value = $this->value;

        // Validate the value
        $validationResult = $this->attributeDefinition->validateValue($value);

        if (! $validationResult['valid']) {
            $this->is_valid = false;
            $this->validation_errors = $validationResult['errors'];
            $this->last_validated_at = now();

            return false;
        }

        // Set the validated value
        $this->value = $this->convertValueForStorage($validationResult['value']);
        $this->display_value = $this->formatDisplayValue($validationResult['value']);
        $this->is_valid = true;
        $this->validation_errors = null;
        $this->last_validated_at = now();

        // Update change tracking
        if ($this->value !== $this->previous_value) {
            $this->value_changed_at = now();
            $this->version++;
        }

        // Update assignment metadata
        $this->source = $options['source'] ?? 'manual';
        $this->assigned_at = now();
        $this->assigned_by = $options['assigned_by'] ?? Auth::user()?->name ?? 'system';
        $this->assignment_metadata = $options['metadata'] ?? null;

        // Handle inheritance tracking
        $this->is_inherited = $options['is_inherited'] ?? false;
        $this->inherited_from_product_attribute_id = $options['inherited_from_product_attribute_id'] ?? null;
        $this->inherited_at = $options['inherited_at'] ?? null;
        $this->is_override = $options['is_override'] ?? false;

        // Reset sync status since value changed
        if ($this->value !== $this->previous_value) {
            $this->resetSyncStatus();
        }

        return true;
    }

    /**
     * Inherit value from parent product attribute
     */
    public function inheritFromProduct(ProductAttribute $productAttribute): bool
    {
        $result = $this->setValue($productAttribute->value, [
            'source' => 'inheritance',
            'is_inherited' => true,
            'inherited_from_product_attribute_id' => $productAttribute->id,
            'inherited_at' => now(),
            'assigned_by' => 'system',
            'metadata' => [
                'inheritance_type' => 'automatic',
                'parent_product_id' => $productAttribute->product_id,
            ],
        ]);

        return $result;
    }

    /**
     * Override inherited value with explicit value
     */
    public function overrideInheritedValue($value, array $options = []): bool
    {
        $options['is_override'] = true;
        $options['is_inherited'] = false;
        $options['source'] = $options['source'] ?? 'manual';

        return $this->setValue($value, $options);
    }

    /**
     * Convert value to storage format
     */
    protected function convertValueForStorage($value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($this->attributeDefinition->data_type) {
            'json' => is_string($value) ? $value : json_encode($value),
            'boolean' => $value ? '1' : '0',
            'date' => $value instanceof Carbon ? $value->toDateString() : (string) $value,
            default => (string) $value,
        };
    }

    /**
     * Format value for display
     */
    protected function formatDisplayValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $displayValue = match ($this->attributeDefinition->data_type) {
            'boolean' => $value ? 'Yes' : 'No',
            'number' => is_numeric($value) ? number_format((float) $value, 2) : (string) $value,
            'json' => is_array($value) ? json_encode($value, JSON_PRETTY_PRINT) : (string) $value,
            'date' => $value instanceof Carbon ? $value->format('M j, Y') : (string) $value,
            default => (string) $value,
        };

        // Add inheritance indicator
        if ($this->is_inherited) {
            $displayValue .= ' (inherited)';
        } elseif ($this->is_override) {
            $displayValue .= ' (override)';
        }

        return $displayValue;
    }

    /**
     * 🧬 INHERITANCE LOGIC
     */

    /**
     * Check if this attribute can be inherited from parent product
     */
    public function canInheritFromProduct(): bool
    {
        return $this->attributeDefinition->supportsInheritance();
    }

    /**
     * Get the parent product attribute that this could inherit from
     */
    public function getParentProductAttribute(): ?ProductAttribute
    {
        if (! $this->variant || ! $this->variant->product) {
            return null;
        }

        return ProductAttribute::where('product_id', $this->variant->product_id)
            ->where('attribute_definition_id', $this->attribute_definition_id)
            ->first();
    }

    /**
     * Check if this variant should inherit from product (no explicit value set)
     */
    public function shouldInheritFromProduct(): bool
    {
        if (! $this->canInheritFromProduct()) {
            return false;
        }

        // Check inheritance strategy
        $strategy = $this->attributeDefinition->getInheritanceStrategy();

        return match ($strategy) {
            'always' => true,
            'fallback' => $this->value === null && ! $this->is_override,
            'never' => false,
            default => false,
        };
    }

    /**
     * Refresh inheritance from parent product
     */
    public function refreshInheritance(): bool
    {
        if (! $this->shouldInheritFromProduct()) {
            return false;
        }

        $parentAttribute = $this->getParentProductAttribute();
        if (! $parentAttribute) {
            return false;
        }

        return $this->inheritFromProduct($parentAttribute);
    }

    /**
     * Clear inheritance and make explicit
     */
    public function clearInheritance($newValue = null): bool
    {
        $valueToSet = $newValue ?? $this->value;

        return $this->setValue($valueToSet, [
            'source' => 'manual',
            'is_inherited' => false,
            'inherited_from_product_attribute_id' => null,
            'inherited_at' => null,
            'is_override' => $this->is_inherited, // Mark as override if was previously inherited
        ]);
    }

    /**
     * 🏪 MARKETPLACE SYNC (same as ProductAttribute)
     */
    public function markAsSynced(string $marketplace, array $metadata = []): void
    {
        $syncStatus = $this->sync_status ?? [];
        $syncStatus[$marketplace] = 'synced';
        $this->sync_status = $syncStatus;

        if (! empty($metadata)) {
            $syncMetadata = $this->sync_metadata ?? [];
            $syncMetadata[$marketplace] = $metadata;
            $this->sync_metadata = $syncMetadata;
        }

        $this->last_synced_at = now();
    }

    public function markSyncFailed(string $marketplace, string $error, array $metadata = []): void
    {
        $syncStatus = $this->sync_status ?? [];
        $syncStatus[$marketplace] = 'failed';
        $this->sync_status = $syncStatus;

        $syncMetadata = $this->sync_metadata ?? [];
        $syncMetadata[$marketplace] = array_merge($metadata, [
            'error' => $error,
            'failed_at' => now()->toISOString(),
        ]);
        $this->sync_metadata = $syncMetadata;
    }

    public function needsSyncTo(string $marketplace): bool
    {
        if (! $this->attributeDefinition->shouldSyncToMarketplace($marketplace)) {
            return false;
        }

        $status = $this->sync_status[$marketplace] ?? null;

        if ($status !== 'synced') {
            return true;
        }

        return $this->value_changed_at && $this->last_synced_at &&
               $this->value_changed_at > $this->last_synced_at;
    }

    public function getValueForMarketplace(string $marketplace)
    {
        $value = $this->getTypedValue();

        return $this->attributeDefinition->transformForMarketplace($value, $marketplace);
    }

    protected function resetSyncStatus(): void
    {
        $this->sync_status = [];
        $this->last_synced_at = null;
    }

    /**
     * 📊 HELPER METHODS
     */
    public function getAttributeKey(): string
    {
        return $this->attributeDefinition->key;
    }

    public function getAttributeName(): string
    {
        return $this->attributeDefinition->name;
    }

    public function getInheritanceInfo(): array
    {
        return [
            'is_inherited' => $this->is_inherited,
            'is_override' => $this->is_override,
            'inherited_at' => $this->inherited_at,
            'inherited_from_product_attribute_id' => $this->inherited_from_product_attribute_id,
            'can_inherit' => $this->canInheritFromProduct(),
            'should_inherit' => $this->shouldInheritFromProduct(),
            'inheritance_strategy' => $this->attributeDefinition->getInheritanceStrategy(),
        ];
    }

    public function getChangeHistory(): array
    {
        return [
            'current_value' => $this->getTypedValue(),
            'previous_value' => $this->previous_value,
            'changed_at' => $this->value_changed_at,
            'assigned_by' => $this->assigned_by,
            'source' => $this->source,
            'version' => $this->version,
            'inheritance_info' => $this->getInheritanceInfo(),
        ];
    }

    /**
     * 🏗️ STATIC HELPERS
     */
    public static function createOrUpdate(ProductVariant $variant, string $attributeKey, $value, array $options = []): self
    {
        $attributeDefinition = AttributeDefinition::findByKey($attributeKey);

        if (! $attributeDefinition) {
            throw new \InvalidArgumentException("Attribute definition '{$attributeKey}' not found");
        }

        $attribute = static::firstOrNew([
            'variant_id' => $variant->id,
            'attribute_definition_id' => $attributeDefinition->id,
        ]);

        $attribute->setValue($value, $options);
        $attribute->save();

        return $attribute;
    }

    public static function getValueFor(ProductVariant $variant, string $attributeKey)
    {
        $attribute = static::forAttribute($attributeKey)
            ->where('variant_id', $variant->id)
            ->first();

        return $attribute?->getTypedValue();
    }

    public static function inheritFromProductFor(ProductVariant $variant, string $attributeKey): ?self
    {
        $attributeDefinition = AttributeDefinition::findByKey($attributeKey);
        if (! $attributeDefinition || ! $attributeDefinition->supportsInheritance()) {
            return null;
        }

        $productAttribute = ProductAttribute::getValueFor($variant->product, $attributeKey);
        if (! $productAttribute) {
            return null;
        }

        $variantAttribute = static::firstOrNew([
            'variant_id' => $variant->id,
            'attribute_definition_id' => $attributeDefinition->id,
        ]);

        if ($variantAttribute->inheritFromProduct($productAttribute)) {
            $variantAttribute->save();

            return $variantAttribute;
        }

        return null;
    }
}
