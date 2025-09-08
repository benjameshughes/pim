<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * 🖼️ IMAGE ATTRIBUTE MODEL
 *
 * Stores attribute values for images. Mirrors ProductAttribute structure
 * (without variant inheritance concerns) to keep behavior consistent across entities.
 */
class ImageAttribute extends Model
{
    use HasFactory;

    protected $fillable = [
        'image_id',
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
    public function image(): BelongsTo
    {
        return $this->belongsTo(Image::class);
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
    public function getTypedValue()
    {
        if ($this->value === null) {
            return null;
        }

        if ($this->attributeDefinition) {
            return $this->attributeDefinition->castValue($this->value);
        }

        // Freeform: try basic decoding for JSON; otherwise raw string
        $val = $this->value;
        if (is_string($val) && $this->looksLikeJson($val)) {
            $decoded = json_decode($val, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : $val;
        }
        return $val;
    }

    public function setValue($value, array $options = []): bool
    {
        // Track previous value
        $this->previous_value = $this->value;

        if ($this->attributeDefinition) {
            // Validate with definition
            $validationResult = $this->attributeDefinition->validateValue($value);
            if (! $validationResult['valid']) {
                $this->is_valid = false;
                $this->validation_errors = $validationResult['errors'];
                $this->last_validated_at = now();
                return false;
            }
            $storeValue = $this->convertValueForStorage($validationResult['value']);
            $this->display_value = $this->formatDisplayValue($validationResult['value']);
        } else {
            // Freeform: store as JSON if array/object, else string
            $storeValue = is_array($value) || is_object($value) ? json_encode($value) : (string) $value;
            $this->display_value = is_array($value) || is_object($value)
                ? json_encode($value, JSON_PRETTY_PRINT)
                : (string) $value;
        }

        $this->value = $storeValue;
        $this->is_valid = true;
        $this->validation_errors = null;
        $this->last_validated_at = now();

        // Change tracking
        if ($this->value !== $this->previous_value) {
            $this->value_changed_at = now();
            $this->version++;
        }

        // Assignment metadata
        $this->source = $options['source'] ?? 'manual';
        $this->assigned_at = now();
        $this->assigned_by = $options['assigned_by'] ?? Auth::user()?->name ?? 'system';
        $this->assignment_metadata = $options['metadata'] ?? null;

        // Reset sync status if changed
        if ($this->value !== $this->previous_value) {
            $this->resetSyncStatus();
        }

        return true;
    }

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

    public function revalidate(): bool
    {
        if ($this->attributeDefinition) {
            $validationResult = $this->attributeDefinition->validateValue($this->value);
            $this->is_valid = $validationResult['valid'];
            $this->validation_errors = $validationResult['valid'] ? null : $validationResult['errors'];
            $this->last_validated_at = now();
            return $this->is_valid;
        }
        // Freeform: always valid
        $this->is_valid = true;
        $this->validation_errors = null;
        $this->last_validated_at = now();
        return true;
    }

    /**
     * 🏪 MARKETPLACE SYNC
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
        if ($this->attributeDefinition && ! $this->attributeDefinition->shouldSyncToMarketplace($marketplace)) {
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
        return $this->attributeDefinition
            ? $this->attributeDefinition->transformForMarketplace($value, $marketplace)
            : $value;
    }

    protected function resetSyncStatus(): void
    {
        $this->sync_status = [];
        $this->last_synced_at = null;
    }

    /**
     * 📊 HELPERS
     */
    public function getAttributeKey(): string
    {
        return $this->attributeDefinition->key;
    }

    public function getAttributeName(): string
    {
        return $this->attributeDefinition->name;
    }

    /**
     * 🏗️ STATIC HELPERS
     */
    public static function createOrUpdate(Image $image, string $attributeKey, $value, array $options = []): self
    {
        $attributeDefinition = AttributeDefinition::findByKey($attributeKey);

        // Auto-create minimal definition if missing (freeform)
        if (! $attributeDefinition) {
            $attributeDefinition = AttributeDefinition::create([
                'key' => $attributeKey,
                'name' => ucwords(str_replace(['_', '-'], ' ', $attributeKey)),
                'data_type' => is_numeric($value) ? 'number' : (is_bool($value) ? 'boolean' : (is_array($value) ? 'json' : 'string')),
                'is_inheritable' => false,
                'inheritance_strategy' => 'never',
                'is_system_attribute' => false,
                'is_active' => true,
                'group' => 'image',
                'input_type' => 'text',
            ]);
        }

        $attribute = static::firstOrNew([
            'image_id' => $image->id,
            'attribute_definition_id' => $attributeDefinition->id,
        ]);

        $attribute->setValue($value, $options);
        $attribute->save();

        return $attribute;
    }

    public static function getValueFor(Image $image, string $attributeKey)
    {
        $attribute = static::forAttribute($attributeKey)
            ->where('image_id', $image->id)
            ->first();

        return $attribute?->getTypedValue();
    }

    protected function looksLikeJson(string $value): bool
    {
        $value = trim($value);
        return (str_starts_with($value, '{') && str_ends_with($value, '}')) ||
               (str_starts_with($value, '[') && str_ends_with($value, ']'));
    }
}
