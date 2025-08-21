<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 🎯 CHANNEL FIELD DEFINITION MODEL
 *
 * Stores field requirements discovered from marketplace APIs:
 * - Mirakl operators: /api/products/attributes
 * - Shopify: product/variant/metafields
 * - eBay: item specifics and aspects
 * - Amazon: browse node attributes
 */
class ChannelFieldDefinition extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel_type',
        'channel_subtype',
        'category',
        'field_code',
        'field_label',
        'field_type',
        'is_required',
        'description',
        'field_metadata',
        'validation_rules',
        'allowed_values',
        'value_list_code',
        'discovered_at',
        'last_verified_at',
        'api_version',
        'is_active',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'field_metadata' => 'array',
        'validation_rules' => 'array',
        'allowed_values' => 'array',
        'discovered_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 🔗 FIELD MAPPINGS
     *
     * Get all user-defined mappings for this field
     */
    public function fieldMappings(): HasMany
    {
        return $this->hasMany(ChannelFieldMapping::class, 'channel_field_code', 'field_code');
    }

    /**
     * 🎯 SCOPE: Active fields only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * 🎯 SCOPE: Required fields only
     */
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    /**
     * 🎯 SCOPE: Filter by channel
     */
    public function scopeForChannel($query, string $channelType, ?string $channelSubtype = null)
    {
        $query->where('channel_type', $channelType);

        if ($channelSubtype) {
            $query->where('channel_subtype', $channelSubtype);
        }

        return $query;
    }

    /**
     * 🎯 SCOPE: Filter by category
     */
    public function scopeForCategory($query, ?string $category = null)
    {
        if ($category) {
            $query->where('category', $category);
        } else {
            $query->whereNull('category');
        }

        return $query;
    }

    /**
     * 🎯 SCOPE: Filter by field type
     */
    public function scopeByType($query, string $fieldType)
    {
        return $query->where('field_type', $fieldType);
    }

    /**
     * 📋 GET FIELD REQUIREMENTS
     *
     * Get organized field requirements for a channel
     */
    public static function getFieldRequirements(
        string $channelType,
        ?string $channelSubtype = null,
        ?string $category = null
    ): array {
        $fields = static::active()
            ->forChannel($channelType, $channelSubtype)
            ->forCategory($category)
            ->orderBy('is_required', 'desc')
            ->orderBy('field_label')
            ->get();

        return [
            'required' => $fields->where('is_required', true)->values(),
            'optional' => $fields->where('is_required', false)->values(),
            'total_count' => $fields->count(),
            'required_count' => $fields->where('is_required', true)->count(),
            'optional_count' => $fields->where('is_required', false)->count(),
            'field_types' => $fields->groupBy('field_type')->map->count(),
        ];
    }

    /**
     * 📋 GET LIST FIELDS
     *
     * Get fields that require value validation
     */
    public static function getListFields(
        string $channelType,
        ?string $channelSubtype = null,
        ?string $category = null
    ): \Illuminate\Database\Eloquent\Collection {
        return static::active()
            ->forChannel($channelType, $channelSubtype)
            ->forCategory($category)
            ->whereIn('field_type', ['LIST', 'LIST_MULTIPLE_VALUES'])
            ->with('valueLists')
            ->get();
    }

    /**
     * 🔗 VALUE LISTS RELATIONSHIP
     *
     * Get value lists for this field
     */
    public function valueLists()
    {
        return ChannelValueList::where('channel_type', $this->channel_type)
            ->where('channel_subtype', $this->channel_subtype)
            ->where('list_code', $this->value_list_code);
    }

    /**
     * ✅ HAS VALUE LIST
     *
     * Check if field has associated value list
     */
    public function hasValueList(): bool
    {
        return ! empty($this->value_list_code) || ! empty($this->allowed_values);
    }

    /**
     * 📋 GET ALLOWED VALUES
     *
     * Get valid values for this field
     */
    public function getAllowedValues(): array
    {
        // Direct values from field definition
        if (! empty($this->allowed_values)) {
            return $this->allowed_values;
        }

        // Values from separate value list
        if ($this->value_list_code) {
            $valueList = $this->valueLists()->first();

            return $valueList?->allowed_values ?? [];
        }

        return [];
    }

    /**
     * ✅ VALIDATE VALUE
     *
     * Check if a value is valid for this field
     */
    public function validateValue($value): bool
    {
        if (! $this->hasValueList()) {
            return true; // No validation needed
        }

        $allowedValues = $this->getAllowedValues();

        if (empty($allowedValues)) {
            return true; // No constraints
        }

        // For LIST_MULTIPLE_VALUES, check each value
        if ($this->field_type === 'LIST_MULTIPLE_VALUES') {
            $values = is_array($value) ? $value : explode(',', $value);
            foreach ($values as $val) {
                if (! in_array(trim($val), $allowedValues)) {
                    return false;
                }
            }

            return true;
        }

        // Single value validation
        return in_array($value, $allowedValues);
    }

    /**
     * 📊 GET FIELD STATISTICS
     *
     * Get statistics for field definitions
     */
    public static function getStatistics(): array
    {
        $all = static::all();

        return [
            'total_fields' => $all->count(),
            'active_fields' => $all->where('is_active', true)->count(),
            'required_fields' => $all->where('is_required', true)->count(),
            'by_channel' => $all->groupBy('channel_type')->map->count(),
            'by_type' => $all->groupBy('field_type')->map->count(),
            'with_value_lists' => $all->whereNotNull('value_list_code')->count(),
            'last_discovery' => $all->max('discovered_at'),
            'needs_verification' => $all->where('last_verified_at', '<', now()->subWeek())->count(),
        ];
    }

    /**
     * 🏷️ GET DISPLAY NAME
     *
     * Human-readable field identifier
     */
    public function getDisplayNameAttribute(): string
    {
        $channel = $this->channel_subtype ? "{$this->channel_type}:{$this->channel_subtype}" : $this->channel_type;
        $category = $this->category ? " [{$this->category}]" : '';

        return "{$channel}{$category} - {$this->field_label}";
    }

    /**
     * 🎨 GET FIELD TYPE COLOR
     *
     * UI color for field type badges
     */
    public function getFieldTypeColorAttribute(): string
    {
        return match ($this->field_type) {
            'TEXT' => 'blue',
            'LONG_TEXT' => 'indigo',
            'LIST' => 'green',
            'LIST_MULTIPLE_VALUES' => 'emerald',
            'MEDIA' => 'purple',
            'MEASUREMENT' => 'orange',
            'INTEGER' => 'gray',
            'DECIMAL' => 'gray',
            'BOOLEAN' => 'red',
            'DATE' => 'yellow',
            default => 'gray',
        };
    }

    /**
     * 🔍 SEARCH FIELDS
     *
     * Search field definitions by label or code
     */
    public static function search(string $query): \Illuminate\Database\Eloquent\Collection
    {
        return static::active()
            ->where(function ($q) use ($query) {
                $q->where('field_label', 'like', "%{$query}%")
                    ->orWhere('field_code', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            })
            ->orderBy('is_required', 'desc')
            ->orderBy('field_label')
            ->get();
    }
}
