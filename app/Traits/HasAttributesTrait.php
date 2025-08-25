<?php

namespace App\Traits;

use Illuminate\Support\Collection;

/**
 * 🏷️ HAS ATTRIBUTES TRAIT
 *
 * Provides common attribute functionality for both Product and ProductVariant models.
 * Handles getting/setting attributes, validation, and basic attribute operations.
 */
trait HasAttributesTrait
{
    /**
     * 🎯 GET TYPED ATTRIBUTE VALUE
     *
     * Get an attribute value with proper type casting
     */
    public function getTypedAttributeValue(string $key)
    {
        $attribute = $this->attributes()->forAttribute($key)->first();

        return $attribute?->getTypedValue();
    }

    /**
     * 🎯 SET TYPED ATTRIBUTE VALUE
     *
     * Set an attribute value with validation and type casting
     */
    public function setTypedAttributeValue(string $key, $value, array $options = []): bool
    {
        $attributeModel = $this instanceof \App\Models\Product ?
            \App\Models\ProductAttribute::class :
            \App\Models\VariantAttribute::class;

        try {
            $relationKey = $this instanceof \App\Models\Product ? 'product_id' : 'variant_id';
            $attribute = $attributeModel::createOrUpdate($this, $key, $value, $options);

            return $attribute !== null;
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * 🏷️ GET ALL TYPED ATTRIBUTES
     *
     * Get all attributes as a key-value array with typed values
     */
    public function getTypedAttributesArray(): array
    {
        $attributes = [];

        foreach ($this->validAttributes as $attribute) {
            $attributes[$attribute->getAttributeKey()] = $attribute->getTypedValue();
        }

        return $attributes;
    }

    /**
     * 🏷️ GET ATTRIBUTES BY GROUP
     *
     * Get attributes grouped by their definition group
     */
    public function getAttributesByGroup(): Collection
    {
        return $this->validAttributes
            ->load('attributeDefinition')
            ->groupBy('attributeDefinition.group')
            ->map(function ($groupAttributes) {
                return $groupAttributes->mapWithKeys(function ($attribute) {
                    return [$attribute->getAttributeKey() => [
                        'value' => $attribute->getTypedValue(),
                        'display_value' => $attribute->display_value,
                        'definition' => $attribute->attributeDefinition,
                    ]];
                });
            });
    }

    /**
     * ✅ VALIDATE ALL ATTRIBUTES
     *
     * Validate all current attribute values
     */
    public function validateAllAttributes(): array
    {
        $results = [
            'valid' => true,
            'errors' => [],
            'validated_count' => 0,
        ];

        foreach ($this->attributes as $attribute) {
            $attribute->revalidate();
            $attribute->save();

            $results['validated_count']++;

            if (! $attribute->is_valid) {
                $results['valid'] = false;
                $results['errors'][$attribute->getAttributeKey()] = $attribute->validation_errors;
            }
        }

        return $results;
    }

    /**
     * 🔄 SYNC ATTRIBUTES FROM ARRAY
     *
     * Sync multiple attributes from an array
     */
    public function syncAttributes(array $attributes, array $options = []): array
    {
        $results = [
            'created' => [],
            'updated' => [],
            'errors' => [],
        ];

        foreach ($attributes as $key => $value) {
            try {
                $existingAttribute = $this->attributes()->forAttribute($key)->first();
                $wasUpdate = (bool) $existingAttribute;

                $success = $this->setTypedAttributeValue($key, $value, $options);

                if ($success) {
                    if ($wasUpdate) {
                        $results['updated'][] = $key;
                    } else {
                        $results['created'][] = $key;
                    }
                } else {
                    $results['errors'][$key] = 'Failed to set attribute value';
                }
            } catch (\Exception $e) {
                $results['errors'][$key] = $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * 🏪 GET MARKETPLACE SYNC STATUS
     *
     * Get sync status for all attributes across marketplaces
     */
    public function getAttributesSyncStatus(?string $marketplace = null): array
    {
        $statuses = [];

        foreach ($this->attributes as $attribute) {
            $key = $attribute->getAttributeKey();

            if ($marketplace) {
                $statuses[$key] = [
                    'needs_sync' => $attribute->needsSyncTo($marketplace),
                    'status' => $attribute->sync_status[$marketplace] ?? 'pending',
                    'last_synced' => $attribute->last_synced_at,
                ];
            } else {
                $statuses[$key] = $attribute->getSyncStatusSummary();
            }
        }

        return $statuses;
    }

    /**
     * 📊 GET ATTRIBUTES STATISTICS
     *
     * Get statistics about this entity's attributes
     */
    public function getAttributesStatistics(): array
    {
        $total = $this->attributes->count();
        $valid = $this->validAttributes->count();
        $invalid = $total - $valid;

        $bySource = $this->attributes->groupBy('source')->map->count();
        $byGroup = $this->attributes->load('attributeDefinition')
            ->groupBy('attributeDefinition.group')
            ->map->count();

        return [
            'total_attributes' => $total,
            'valid_attributes' => $valid,
            'invalid_attributes' => $invalid,
            'completion_percentage' => $total > 0 ? round(($valid / $total) * 100, 1) : 0,
            'by_source' => $bySource->toArray(),
            'by_group' => $byGroup->toArray(),
            'last_updated' => $this->attributes->max('updated_at'),
        ];
    }

    /**
     * 🔍 SEARCH ATTRIBUTES
     *
     * Search attributes by key or value
     */
    public function searchAttributes(string $query, array $options = []): Collection
    {
        $searchIn = $options['search_in'] ?? ['key', 'value', 'display_value'];

        return $this->attributes()->with('attributeDefinition')
            ->where(function ($queryBuilder) use ($query, $searchIn) {
                if (in_array('key', $searchIn)) {
                    $queryBuilder->orWhereHas('attributeDefinition', function ($q) use ($query) {
                        $q->where('key', 'like', "%{$query}%")
                            ->orWhere('name', 'like', "%{$query}%");
                    });
                }

                if (in_array('value', $searchIn)) {
                    $queryBuilder->orWhere('value', 'like', "%{$query}%");
                }

                if (in_array('display_value', $searchIn)) {
                    $queryBuilder->orWhere('display_value', 'like', "%{$query}%");
                }
            })
            ->get();
    }

    /**
     * 📝 GET ATTRIBUTE CHANGE HISTORY
     *
     * Get change history for all attributes
     */
    public function getAttributeChangeHistory(): array
    {
        return $this->attributes->map(function ($attribute) {
            return [
                'key' => $attribute->getAttributeKey(),
                'name' => $attribute->getAttributeName(),
                'history' => $attribute->getChangeHistory(),
            ];
        })->toArray();
    }

    /**
     * 🧹 CLEAN UP INVALID ATTRIBUTES
     *
     * Remove or fix invalid attribute values
     */
    public function cleanUpInvalidAttributes(array $options = []): array
    {
        $action = $options['action'] ?? 'fix'; // 'fix', 'remove', 'report'
        $results = [
            'processed' => 0,
            'fixed' => 0,
            'removed' => 0,
            'unfixable' => 0,
            'errors' => [],
        ];

        $invalidAttributes = $this->attributes()->invalid()->get();

        foreach ($invalidAttributes as $attribute) {
            $results['processed']++;

            try {
                switch ($action) {
                    case 'fix':
                        // Try to revalidate first
                        if ($attribute->revalidate()) {
                            $attribute->save();
                            $results['fixed']++;
                        } else {
                            // Try to set to default value
                            $defaultValue = $attribute->attributeDefinition->default_value;
                            if ($defaultValue !== null) {
                                if ($attribute->setValue($defaultValue)) {
                                    $attribute->save();
                                    $results['fixed']++;
                                } else {
                                    $results['unfixable']++;
                                }
                            } else {
                                $results['unfixable']++;
                            }
                        }
                        break;

                    case 'remove':
                        $attribute->delete();
                        $results['removed']++;
                        break;

                    case 'report':
                        // Just report, don't fix
                        $results['errors'][$attribute->getAttributeKey()] = $attribute->validation_errors;
                        break;
                }
            } catch (\Exception $e) {
                $results['errors'][$attribute->getAttributeKey()] = $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * 🎯 BULK UPDATE ATTRIBUTES
     *
     * Update multiple attributes efficiently
     */
    public function bulkUpdateAttributes(array $updates, array $options = []): array
    {
        $results = [
            'success' => [],
            'errors' => [],
            'total' => count($updates),
        ];

        foreach ($updates as $key => $value) {
            try {
                if ($this->setTypedAttributeValue($key, $value, $options)) {
                    $results['success'][] = $key;
                } else {
                    $results['errors'][$key] = 'Failed to update attribute';
                }
            } catch (\Exception $e) {
                $results['errors'][$key] = $e->getMessage();
            }
        }

        return $results;
    }
}
