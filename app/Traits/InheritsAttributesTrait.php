<?php

namespace App\Traits;

use App\Models\AttributeDefinition;
use App\Models\VariantAttribute;

/**
 * 🧬 INHERITS ATTRIBUTES TRAIT
 *
 * Provides inheritance functionality for ProductVariant models.
 * Handles inheriting attributes from parent products with smart fallback logic.
 */
trait InheritsAttributesTrait
{
    /**
     * 🧬 GET EFFECTIVE ATTRIBUTE VALUE
     *
     * Get attribute value with full inheritance logic:
     * 1. Check variant's explicit value
     * 2. Check variant's inherited value
     * 3. Fallback to product's value
     * 4. Fallback to attribute's default value
     */
    public function getEffectiveAttributeValue(string $key)
    {
        // 1. Check for explicit variant attribute (not inherited)
        $variantAttribute = $this->attributes()
            ->forAttribute($key)
            ->where('is_inherited', false)
            ->first();

        if ($variantAttribute) {
            return $variantAttribute->getTypedValue();
        }

        // 2. Check for inherited variant attribute
        $inheritedAttribute = $this->attributes()
            ->forAttribute($key)
            ->where('is_inherited', true)
            ->first();

        if ($inheritedAttribute) {
            return $inheritedAttribute->getTypedValue();
        }

        // 3. Check if we should inherit from product
        $attributeDefinition = AttributeDefinition::findByKey($key);
        if (! $attributeDefinition || ! $attributeDefinition->supportsInheritance()) {
            return null;
        }

        // 4. Get value from product
        if ($this->product) {
            $productValue = $this->product->getTypedAttributeValue($key);

            // If strategy is 'always' or 'fallback', use product value
            $strategy = $attributeDefinition->getInheritanceStrategy();
            if (in_array($strategy, ['always', 'fallback']) && $productValue !== null) {
                return $productValue;
            }
        }

        // 5. Final fallback to default value
        return $attributeDefinition->default_value;
    }

    /**
     * 🧬 INHERIT SPECIFIC ATTRIBUTE
     *
     * Inherit a specific attribute from parent product
     */
    public function inheritAttribute(string $key, array $options = []): bool
    {
        if (! $this->product) {
            return false;
        }

        $attributeDefinition = AttributeDefinition::findByKey($key);
        if (! $attributeDefinition || ! $attributeDefinition->supportsInheritance()) {
            return false;
        }

        $productAttribute = $this->product->attributes()
            ->where('attribute_definition_id', $attributeDefinition->id)
            ->first();

        if (! $productAttribute) {
            return false;
        }

        // Get or create variant attribute
        $variantAttribute = $this->attributes()
            ->where('attribute_definition_id', $attributeDefinition->id)
            ->first();

        if (! $variantAttribute) {
            $variantAttribute = new VariantAttribute([
                'variant_id' => $this->id,
                'attribute_definition_id' => $attributeDefinition->id,
            ]);
        }

        // Inherit the value
        $success = $variantAttribute->inheritFromProduct($productAttribute);
        if ($success) {
            $variantAttribute->save();
        }

        return $success;
    }

    /**
     * 🧬 INHERIT ALL ATTRIBUTES
     *
     * Inherit all inheritable attributes from parent product
     */
    public function inheritAllAttributes(array $options = []): array
    {
        $results = [
            'inherited' => [],
            'skipped' => [],
            'errors' => [],
            'total_processed' => 0,
        ];

        if (! $this->product) {
            return $results;
        }

        $inheritableDefinitions = AttributeDefinition::getInheritableAttributes();
        $force = $options['force'] ?? false; // Force re-inherit even if already inherited

        foreach ($inheritableDefinitions as $definition) {
            $results['total_processed']++;

            try {
                // Check if product has this attribute
                $productAttribute = $this->product->attributes()
                    ->where('attribute_definition_id', $definition->id)
                    ->first();

                if (! $productAttribute) {
                    $results['skipped'][] = [
                        'key' => $definition->key,
                        'reason' => 'Product does not have this attribute',
                    ];

                    continue;
                }

                // Check if variant already has this attribute
                $variantAttribute = $this->attributes()
                    ->where('attribute_definition_id', $definition->id)
                    ->first();

                if ($variantAttribute) {
                    // Skip if already inherited and not forcing
                    if ($variantAttribute->is_inherited && ! $force) {
                        $results['skipped'][] = [
                            'key' => $definition->key,
                            'reason' => 'Already inherited',
                        ];

                        continue;
                    }

                    // Skip if explicitly set (override) and not forcing
                    if (! $variantAttribute->is_inherited && ! $force) {
                        $results['skipped'][] = [
                            'key' => $definition->key,
                            'reason' => 'Explicitly set (override)',
                        ];

                        continue;
                    }
                }

                // Inherit the attribute
                if ($this->inheritAttribute($definition->key, $options)) {
                    $results['inherited'][] = $definition->key;
                } else {
                    $results['errors'][$definition->key] = 'Failed to inherit attribute';
                }

            } catch (\Exception $e) {
                $results['errors'][$definition->key] = $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * 🔄 REFRESH INHERITANCE
     *
     * Refresh inherited attributes when parent values change
     */
    public function refreshInheritance(?array $attributeKeys = null): array
    {
        $results = [
            'refreshed' => [],
            'unchanged' => [],
            'errors' => [],
        ];

        if (! $this->product) {
            return $results;
        }

        // Get attributes to refresh
        $query = $this->attributes()->inherited();
        if ($attributeKeys) {
            $query->whereHas('attributeDefinition', function ($q) use ($attributeKeys) {
                $q->whereIn('key', $attributeKeys);
            });
        }

        $inheritedAttributes = $query->with('attributeDefinition')->get();

        foreach ($inheritedAttributes as $variantAttribute) {
            try {
                $key = $variantAttribute->getAttributeKey();

                // Get current product attribute value
                $productAttribute = $this->product->attributes()
                    ->where('attribute_definition_id', $variantAttribute->attribute_definition_id)
                    ->first();

                if (! $productAttribute) {
                    // Product no longer has this attribute, remove inheritance
                    $variantAttribute->delete();
                    $results['refreshed'][] = [
                        'key' => $key,
                        'action' => 'removed (parent no longer has attribute)',
                    ];

                    continue;
                }

                // Check if values differ
                $currentValue = $variantAttribute->value;
                $newValue = $productAttribute->value;

                if ($currentValue !== $newValue) {
                    // Update inherited value
                    if ($variantAttribute->inheritFromProduct($productAttribute)) {
                        $variantAttribute->save();
                        $results['refreshed'][] = [
                            'key' => $key,
                            'action' => 'updated',
                            'old_value' => $currentValue,
                            'new_value' => $newValue,
                        ];
                    } else {
                        $results['errors'][$key] = 'Failed to refresh inherited value';
                    }
                } else {
                    $results['unchanged'][] = $key;
                }

            } catch (\Exception $e) {
                $results['errors'][$variantAttribute->getAttributeKey()] = $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * 🎯 OVERRIDE INHERITED ATTRIBUTE
     *
     * Override an inherited attribute with a specific value
     */
    public function overrideAttribute(string $key, $value, array $options = []): bool
    {
        $attributeDefinition = AttributeDefinition::findByKey($key);
        if (! $attributeDefinition) {
            return false;
        }

        $variantAttribute = $this->attributes()
            ->where('attribute_definition_id', $attributeDefinition->id)
            ->first();

        if (! $variantAttribute) {
            // Create new override attribute
            $variantAttribute = new VariantAttribute([
                'variant_id' => $this->id,
                'attribute_definition_id' => $attributeDefinition->id,
            ]);
        }

        $success = $variantAttribute->overrideInheritedValue($value, $options);
        if ($success) {
            $variantAttribute->save();
        }

        return $success;
    }

    /**
     * 🧹 CLEAR ATTRIBUTE OVERRIDE
     *
     * Remove override and revert to inherited value
     */
    public function clearAttributeOverride(string $key): bool
    {
        $variantAttribute = $this->attributes()
            ->forAttribute($key)
            ->where('is_override', true)
            ->first();

        if (! $variantAttribute) {
            return false; // No override to clear
        }

        // Check if we can inherit from product
        if ($this->product && $variantAttribute->canInheritFromProduct()) {
            $productAttribute = $this->product->attributes()
                ->where('attribute_definition_id', $variantAttribute->attribute_definition_id)
                ->first();

            if ($productAttribute) {
                // Revert to inherited value
                $success = $variantAttribute->inheritFromProduct($productAttribute);
                if ($success) {
                    $variantAttribute->save();

                    return true;
                }
            }
        }

        // If can't inherit, remove the attribute entirely
        $variantAttribute->delete();

        return true;
    }

    /**
     * 📊 GET INHERITANCE SUMMARY
     *
     * Get a summary of inheritance status for all attributes
     */
    public function getInheritanceSummary(): array
    {
        $summary = [
            'total_attributes' => 0,
            'inherited' => 0,
            'overridden' => 0,
            'explicit' => 0,
            'inheritable_available' => 0,
            'details' => [],
        ];

        if (! $this->product) {
            return $summary;
        }

        // Get all variant attributes
        $variantAttributes = $this->attributes()->with('attributeDefinition')->get();
        $summary['total_attributes'] = $variantAttributes->count();

        foreach ($variantAttributes as $attribute) {
            $key = $attribute->getAttributeKey();

            if ($attribute->is_inherited) {
                $summary['inherited']++;
                $status = 'inherited';
            } elseif ($attribute->is_override) {
                $summary['overridden']++;
                $status = 'overridden';
            } else {
                $summary['explicit']++;
                $status = 'explicit';
            }

            $summary['details'][$key] = [
                'status' => $status,
                'value' => $attribute->getTypedValue(),
                'display_value' => $attribute->display_value,
                'can_inherit' => $attribute->canInheritFromProduct(),
                'inheritance_strategy' => $attribute->attributeDefinition->getInheritanceStrategy(),
            ];
        }

        // Check for inheritable attributes that variant doesn't have
        $inheritableDefinitions = AttributeDefinition::getInheritableAttributes();
        $variantAttributeKeys = $variantAttributes->pluck('attributeDefinition.key')->toArray();

        foreach ($inheritableDefinitions as $definition) {
            if (! in_array($definition->key, $variantAttributeKeys)) {
                // Check if product has this attribute
                $productHasAttribute = $this->product->attributes()
                    ->where('attribute_definition_id', $definition->id)
                    ->exists();

                if ($productHasAttribute) {
                    $summary['inheritable_available']++;
                    $summary['details'][$definition->key] = [
                        'status' => 'available_for_inheritance',
                        'value' => null,
                        'display_value' => null,
                        'can_inherit' => true,
                        'inheritance_strategy' => $definition->getInheritanceStrategy(),
                    ];
                }
            }
        }

        return $summary;
    }

    /**
     * 🔍 GET ATTRIBUTE WITH INHERITANCE PATH
     *
     * Get attribute value and show the inheritance path
     */
    public function getAttributeWithInheritancePath(string $key): array
    {
        $path = [
            'key' => $key,
            'final_value' => null,
            'resolution_path' => [],
        ];

        // Check variant explicit
        $variantAttribute = $this->attributes()
            ->forAttribute($key)
            ->where('is_inherited', false)
            ->first();

        if ($variantAttribute) {
            $path['final_value'] = $variantAttribute->getTypedValue();
            $path['resolution_path'][] = [
                'level' => 'variant_explicit',
                'value' => $variantAttribute->getTypedValue(),
                'source' => $variantAttribute->source,
                'assigned_by' => $variantAttribute->assigned_by,
            ];

            return $path;
        }

        // Check variant inherited
        $inheritedAttribute = $this->attributes()
            ->forAttribute($key)
            ->where('is_inherited', true)
            ->first();

        if ($inheritedAttribute) {
            $path['final_value'] = $inheritedAttribute->getTypedValue();
            $path['resolution_path'][] = [
                'level' => 'variant_inherited',
                'value' => $inheritedAttribute->getTypedValue(),
                'inherited_from' => 'product',
                'inherited_at' => $inheritedAttribute->inherited_at,
            ];
        }

        // Check product attribute
        if ($this->product) {
            $productAttribute = $this->product->attributes()
                ->forAttribute($key)
                ->first();

            if ($productAttribute) {
                if (! $inheritedAttribute) {
                    $path['final_value'] = $productAttribute->getTypedValue();
                }

                $path['resolution_path'][] = [
                    'level' => 'product',
                    'value' => $productAttribute->getTypedValue(),
                    'source' => $productAttribute->source,
                    'assigned_by' => $productAttribute->assigned_by,
                ];
            }
        }

        // Check default value
        $attributeDefinition = AttributeDefinition::findByKey($key);
        if ($attributeDefinition && $attributeDefinition->default_value !== null) {
            if ($path['final_value'] === null) {
                $path['final_value'] = $attributeDefinition->castValue($attributeDefinition->default_value);
            }

            $path['resolution_path'][] = [
                'level' => 'default',
                'value' => $attributeDefinition->castValue($attributeDefinition->default_value),
                'source' => 'attribute_definition',
            ];
        }

        return $path;
    }

    /**
     * 🧬 BULK INHERIT ATTRIBUTES
     *
     * Efficiently inherit multiple attributes
     */
    public function bulkInheritAttributes(array $attributeKeys, array $options = []): array
    {
        $results = [
            'success' => [],
            'errors' => [],
            'skipped' => [],
        ];

        foreach ($attributeKeys as $key) {
            try {
                if ($this->inheritAttribute($key, $options)) {
                    $results['success'][] = $key;
                } else {
                    $results['skipped'][] = $key;
                }
            } catch (\Exception $e) {
                $results['errors'][$key] = $e->getMessage();
            }
        }

        return $results;
    }
}
