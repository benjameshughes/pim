<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * 🏷️ PRODUCT ATTRIBUTE MODEL
 *
 * Stores attribute values for products. Each instance represents
 * one attribute value for one product, with full audit trail,
 * validation status, and marketplace sync tracking.
 */
class ProductAttribute extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
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
        'sync_status' => 'array',
        'last_synced_at' => 'datetime',
        'sync_metadata' => 'array',
        'value_changed_at' => 'datetime',
    ];

    /**
     * 🔗 RELATIONSHIPS
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attributeDefinition(): BelongsTo
    {
        return $this->belongsTo(AttributeDefinition::class);
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
     * Set value with automatic validation and type casting
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

        // Reset sync status since value changed
        if ($this->value !== $this->previous_value) {
            $this->resetSyncStatus();
        }

        return true;
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

        return match ($this->attributeDefinition->data_type) {
            'boolean' => $value ? 'Yes' : 'No',
            'number' => is_numeric($value) ? number_format((float) $value, 2) : (string) $value,
            'json' => is_array($value) ? json_encode($value, JSON_PRETTY_PRINT) : (string) $value,
            'date' => $value instanceof Carbon ? $value->format('M j, Y') : (string) $value,
            default => (string) $value,
        };
    }

    /**
     * Re-validate the current value
     */
    public function revalidate(): bool
    {
        $validationResult = $this->attributeDefinition->validateValue($this->value);

        $this->is_valid = $validationResult['valid'];
        $this->validation_errors = $validationResult['valid'] ? null : $validationResult['errors'];
        $this->last_validated_at = now();

        return $this->is_valid;
    }

    /**
     * 🏪 MARKETPLACE SYNC
     */

    /**
     * Mark as synced to a marketplace
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

    /**
     * Mark as sync failed for a marketplace
     */
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

    /**
     * Check if needs sync to a marketplace
     */
    public function needsSyncTo(string $marketplace): bool
    {
        // Check if attribute should sync to this marketplace
        if (! $this->attributeDefinition->shouldSyncToMarketplace($marketplace)) {
            return false;
        }

        // Check sync status
        $status = $this->sync_status[$marketplace] ?? null;

        if ($status !== 'synced') {
            return true;
        }

        // Check if value changed after last sync
        return $this->value_changed_at && $this->last_synced_at &&
               $this->value_changed_at > $this->last_synced_at;
    }

    /**
     * Get transformed value for marketplace
     */
    public function getValueForMarketplace(string $marketplace)
    {
        $value = $this->getTypedValue();

        return $this->attributeDefinition->transformForMarketplace($value, $marketplace);
    }

    /**
     * Reset sync status (when value changes)
     */
    protected function resetSyncStatus(): void
    {
        $this->sync_status = [];
        $this->last_synced_at = null;
    }

    /**
     * 📊 HELPER METHODS
     */

    /**
     * Get the attribute key for easy access
     */
    public function getAttributeKey(): string
    {
        return $this->attributeDefinition->key;
    }

    /**
     * Get the attribute name for display
     */
    public function getAttributeName(): string
    {
        return $this->attributeDefinition->name;
    }

    /**
     * Check if this attribute is inheritable
     */
    public function isInheritable(): bool
    {
        return $this->attributeDefinition->supportsInheritance();
    }

    /**
     * Get inheritance strategy
     */
    public function getInheritanceStrategy(): string
    {
        return $this->attributeDefinition->getInheritanceStrategy();
    }

    /**
     * Get change history summary
     */
    public function getChangeHistory(): array
    {
        return [
            'current_value' => $this->getTypedValue(),
            'previous_value' => $this->previous_value,
            'changed_at' => $this->value_changed_at,
            'assigned_by' => $this->assigned_by,
            'source' => $this->source,
            'version' => $this->version,
        ];
    }

    /**
     * Get sync status summary
     */
    public function getSyncStatusSummary(): array
    {
        $summary = [];

        foreach (['shopify', 'ebay', 'mirakl'] as $marketplace) {
            if ($this->attributeDefinition->shouldSyncToMarketplace($marketplace)) {
                $summary[$marketplace] = [
                    'should_sync' => true,
                    'status' => $this->sync_status[$marketplace] ?? 'pending',
                    'needs_sync' => $this->needsSyncTo($marketplace),
                    'last_synced' => $this->last_synced_at,
                ];
            } else {
                $summary[$marketplace] = [
                    'should_sync' => false,
                    'status' => 'disabled',
                    'needs_sync' => false,
                ];
            }
        }

        return $summary;
    }

    /**
     * 🏗️ STATIC HELPERS
     */

    /**
     * Create or update attribute value
     */
    public static function createOrUpdate(Product $product, string $attributeKey, $value, array $options = []): self
    {
        $attributeDefinition = AttributeDefinition::findByKey($attributeKey);

        if (! $attributeDefinition) {
            throw new \InvalidArgumentException("Attribute definition '{$attributeKey}' not found");
        }

        $attribute = static::firstOrNew([
            'product_id' => $product->id,
            'attribute_definition_id' => $attributeDefinition->id,
        ]);

        $attribute->setValue($value, $options);
        $attribute->save();

        return $attribute;
    }

    /**
     * Get attribute value for a product
     */
    public static function getValueFor(Product $product, string $attributeKey)
    {
        $attribute = static::forAttribute($attributeKey)
            ->where('product_id', $product->id)
            ->first();

        return $attribute?->getTypedValue();
    }
}
