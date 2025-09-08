<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

/**
 * 🏗️ ATTRIBUTE DEFINITION MODEL
 *
 * Defines the schema and rules for attributes that can be applied
 * to products and variants. This is the foundation of our flexible
 * attribute system that supports inheritance and marketplace sync.
 */
class AttributeDefinition extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'description',
        'data_type',
        'validation_rules',
        'enum_values',
        'default_value',
        'is_inheritable',
        'inheritance_strategy',
        'is_required_for_products',
        'is_required_for_variants',
        'is_applicable_for_images',
        'is_unique_per_product',
        'is_system_attribute',
        'marketplace_mappings',
        'sync_to_shopify',
        'sync_to_ebay',
        'sync_to_mirakl',
        'input_type',
        'ui_options',
        'sort_order',
        'group',
        'icon',
        'is_active',
        'deprecated_at',
        'replaced_by',
    ];

    protected $casts = [
        'validation_rules' => 'array',
        'enum_values' => 'array',
        'marketplace_mappings' => 'array',
        'ui_options' => 'array',
        'is_inheritable' => 'boolean',
        'is_required_for_products' => 'boolean',
        'is_required_for_variants' => 'boolean',
        'is_applicable_for_images' => 'boolean',
        'is_unique_per_product' => 'boolean',
        'is_system_attribute' => 'boolean',
        'sync_to_shopify' => 'boolean',
        'sync_to_ebay' => 'boolean',
        'sync_to_mirakl' => 'boolean',
        'is_active' => 'boolean',
        'deprecated_at' => 'datetime',
    ];

    /**
     * 🔍 SCOPES
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNull('deprecated_at');
    }

    public function scopeInheritable(Builder $query): Builder
    {
        return $query->where('is_inheritable', true);
    }

    public function scopeForGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group);
    }

    public function scopeSystemAttributes(Builder $query): Builder
    {
        return $query->where('is_system_attribute', true);
    }

    public function scopeCustomAttributes(Builder $query): Builder
    {
        return $query->where('is_system_attribute', false);
    }

    /**
     * Only attributes marked applicable to images
     */
    public function scopeForImages(Builder $query): Builder
    {
        return $query->where('is_applicable_for_images', true);
    }

    public function scopeOrderedForDisplay(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Filter attributes that should sync to a given marketplace channel
     */
    public function scopeForMarketplace(Builder $query, string $marketplace): Builder
    {
        $marketplace = strtolower($marketplace);

        return match ($marketplace) {
            'shopify' => $query->where('sync_to_shopify', true),
            'ebay' => $query->where('sync_to_ebay', true),
            'mirakl' => $query->where('sync_to_mirakl', true),
            default => $query->whereRaw('1 = 0'), // no-op for unsupported channels
        };
    }

    /**
     * 🎯 ATTRIBUTE VALUE VALIDATION
     */

    /**
     * Validate a value against this attribute definition
     */
    public function validateValue($value): array
    {
        $rules = $this->buildValidationRules();

        if (empty($rules)) {
            return ['valid' => true, 'value' => $this->castValue($value)];
        }

        $validator = Validator::make(
            ['value' => $value],
            ['value' => $rules],
            $this->getValidationMessages()
        );

        if ($validator->fails()) {
            return [
                'valid' => false,
                'errors' => $validator->errors()->get('value'),
                'value' => $value,
            ];
        }

        return [
            'valid' => true,
            'value' => $this->castValue($validator->validated()['value']),
        ];
    }

    /**
     * Build Laravel validation rules for this attribute
     */
    protected function buildValidationRules(): array
    {
        $rules = [];

        // Required rules
        if ($this->is_required_for_products || $this->is_required_for_variants) {
            // Note: We'll handle required differently for products vs variants in the service layer
            $rules[] = 'nullable'; // Allow null, but services will enforce required
        } else {
            $rules[] = 'nullable';
        }

        // Data type rules
        switch ($this->data_type) {
            case 'string':
                $rules[] = 'string';
                if (! empty($this->validation_rules['max_length'])) {
                    $rules[] = 'max:'.$this->validation_rules['max_length'];
                }
                break;

            case 'number':
                $rules[] = 'numeric';
                if (isset($this->validation_rules['min'])) {
                    $rules[] = 'min:'.$this->validation_rules['min'];
                }
                if (isset($this->validation_rules['max'])) {
                    $rules[] = 'max:'.$this->validation_rules['max'];
                }
                break;

            case 'boolean':
                $rules[] = 'boolean';
                break;

            case 'enum':
                if (! empty($this->enum_values)) {
                    $rules[] = 'in:'.implode(',', $this->enum_values);
                }
                break;

            case 'json':
                $rules[] = 'json';
                break;

            case 'date':
                $rules[] = 'date';
                break;

            case 'url':
                $rules[] = 'url';
                break;
        }

        // Custom validation rules from the definition
        if (! empty($this->validation_rules['custom'])) {
            $rules = array_merge($rules, $this->validation_rules['custom']);
        }

        return $rules;
    }

    /**
     * Get validation error messages
     */
    protected function getValidationMessages(): array
    {
        return [
            'value.required' => "The {$this->name} field is required.",
            'value.string' => "The {$this->name} must be a text value.",
            'value.numeric' => "The {$this->name} must be a number.",
            'value.boolean' => "The {$this->name} must be true or false.",
            'value.in' => "The {$this->name} must be one of: ".implode(', ', $this->enum_values ?? []),
            'value.json' => "The {$this->name} must be valid JSON.",
            'value.date' => "The {$this->name} must be a valid date.",
            'value.url' => "The {$this->name} must be a valid URL.",
        ];
    }

    /**
     * Cast value to the correct PHP type
     */
    public function castValue($value)
    {
        if ($value === null) {
            return null;
        }

        return match ($this->data_type) {
            'number' => is_numeric($value) ? (float) $value : $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => is_string($value) ? json_decode($value, true) : $value,
            'date' => $value instanceof \DateTime ? $value : new \DateTime($value),
            default => (string) $value,
        };
    }

    /**
     * 🏪 MARKETPLACE INTEGRATION
     */

    /**
     * Get marketplace mapping for a specific marketplace
     */
    public function getMarketplaceMapping(string $marketplace): ?array
    {
        return $this->marketplace_mappings[$marketplace] ?? null;
    }

    /**
     * Check if this attribute should sync to a marketplace
     */
    public function shouldSyncToMarketplace(string $marketplace): bool
    {
        return match ($marketplace) {
            'shopify' => $this->sync_to_shopify,
            'ebay' => $this->sync_to_ebay,
            'mirakl' => $this->sync_to_mirakl,
            default => false,
        };
    }

    /**
     * Transform value for marketplace sync
     */
    public function transformForMarketplace($value, string $marketplace): mixed
    {
        $mapping = $this->getMarketplaceMapping($marketplace);

        if (! $mapping) {
            return $value;
        }

        // Apply transformations defined in marketplace_mappings
        if (isset($mapping['transform'])) {
            $transform = $mapping['transform'];

            switch ($transform['type']) {
                case 'map_values':
                    return $transform['mappings'][$value] ?? $value;

                case 'prefix':
                    return $transform['prefix'].$value;

                case 'suffix':
                    return $value.$transform['suffix'];

                case 'format':
                    return sprintf($transform['format'], $value);

                case 'custom':
                    // For complex transformations, we'll use a service
                    return $value;
            }
        }

        return $value;
    }

    /**
     * 🔄 INHERITANCE LOGIC
     */

    /**
     * Check if this attribute supports inheritance
     */
    public function supportsInheritance(): bool
    {
        return $this->is_inheritable && $this->inheritance_strategy !== 'never';
    }

    /**
     * Get the inheritance strategy
     */
    public function getInheritanceStrategy(): string
    {
        return $this->inheritance_strategy;
    }

    /**
     * Should this attribute always inherit (never be overridden)?
     */
    public function shouldAlwaysInherit(): bool
    {
        return $this->inheritance_strategy === 'always';
    }

    /**
     * Should this attribute use fallback inheritance?
     */
    public function shouldFallbackInherit(): bool
    {
        return $this->inheritance_strategy === 'fallback';
    }

    /**
     * 🎨 UI HELPERS
     */

    /**
     * Get the appropriate input type for forms
     */
    public function getInputType(): string
    {
        if ($this->input_type !== 'text') {
            return $this->input_type;
        }

        // Auto-determine input type from data type
        return match ($this->data_type) {
            'boolean' => 'checkbox',
            'enum' => 'select',
            'number' => 'number',
            'date' => 'date',
            'url' => 'url',
            'json' => 'textarea',
            default => 'text',
        };
    }

    /**
     * Get options for select inputs (enum types)
     */
    public function getSelectOptions(): array
    {
        if ($this->data_type !== 'enum' || empty($this->enum_values)) {
            return [];
        }

        return collect($this->enum_values)->mapWithKeys(function ($value) {
            return [$value => $value];
        })->toArray();
    }

    /**
     * Get UI configuration
     */
    public function getUIConfig(): array
    {
        return [
            'type' => $this->getInputType(),
            'label' => $this->name,
            'description' => $this->description,
            'required' => $this->is_required_for_products || $this->is_required_for_variants,
            'options' => $this->getSelectOptions(),
            'ui_options' => $this->ui_options ?? [],
            'default_value' => $this->default_value,
            'icon' => $this->icon,
        ];
    }

    /**
     * 📊 STATIC HELPERS
     */

    /**
     * Get all attributes grouped by category
     */
    public static function getGroupedAttributes(): Collection
    {
        return static::active()
            ->orderedForDisplay()
            ->get()
            ->groupBy('group');
    }

    /**
     * Get core system attributes (brand, material, etc.)
     */
    public static function getSystemAttributes(): Collection
    {
        return static::systemAttributes()
            ->active()
            ->orderedForDisplay()
            ->get();
    }

    /**
     * Get inheritable attributes
     */
    public static function getInheritableAttributes(): Collection
    {
        return static::inheritable()
            ->active()
            ->orderedForDisplay()
            ->get();
    }

    /**
     * Find attribute by key
     */
    public static function findByKey(string $key): ?static
    {
        return static::where('key', $key)->active()->first();
    }

    /**
     * Create a new system attribute
     */
    public static function createSystemAttribute(array $data): static
    {
        $data['is_system_attribute'] = true;
        $data['is_active'] = true;

        return static::create($data);
    }
}
