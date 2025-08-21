<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\AttributeDefinition;
use App\Models\ProductAttribute;
use App\Models\VariantAttribute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * 🧬 ATTRIBUTE INHERITANCE SERVICE
 * 
 * Centralized service for handling attribute inheritance logic between
 * products and their variants. Provides efficient bulk operations and
 * maintains inheritance consistency across the system.
 */
class AttributeInheritanceService
{
    /**
     * 🧬 INHERIT ATTRIBUTES FOR VARIANT
     *
     * Inherit all applicable attributes from product to a specific variant
     */
    public function inheritAttributesForVariant(ProductVariant $variant, array $options = []): array
    {
        $results = [
            'variant_id' => $variant->id,
            'variant_sku' => $variant->sku,
            'inherited' => [],
            'skipped' => [],
            'errors' => [],
            'total_processed' => 0,
        ];

        if (!$variant->product) {
            $results['errors']['product'] = 'Variant has no associated product';
            return $results;
        }

        $force = $options['force'] ?? false;
        $attributeKeys = $options['attributes'] ?? null; // Specific attributes or all

        // Get inheritable attribute definitions
        $inheritableDefinitions = AttributeDefinition::getInheritableAttributes();
        
        if ($attributeKeys) {
            $inheritableDefinitions = $inheritableDefinitions->whereIn('key', $attributeKeys);
        }

        foreach ($inheritableDefinitions as $definition) {
            $results['total_processed']++;
            
            try {
                $result = $this->inheritSingleAttribute($variant, $definition, $options);
                
                if ($result['success']) {
                    $results['inherited'][] = [
                        'key' => $definition->key,
                        'action' => $result['action'],
                        'value' => $result['value'],
                    ];
                } else {
                    $results['skipped'][] = [
                        'key' => $definition->key,
                        'reason' => $result['reason'],
                    ];
                }

            } catch (\Exception $e) {
                $results['errors'][$definition->key] = $e->getMessage();
                Log::warning('Failed to inherit attribute', [
                    'variant_id' => $variant->id,
                    'attribute_key' => $definition->key,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Inheritance completed for variant', [
            'variant_id' => $variant->id,
            'inherited_count' => count($results['inherited']),
            'skipped_count' => count($results['skipped']),
            'error_count' => count($results['errors']),
        ]);

        return $results;
    }

    /**
     * 🧬 INHERIT ATTRIBUTES FOR ALL VARIANTS
     *
     * Inherit attributes for all variants of a product
     */
    public function inheritAttributesForProduct(Product $product, array $options = []): array
    {
        $results = [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'variants_processed' => 0,
            'variants_succeeded' => 0,
            'variants_with_errors' => 0,
            'total_inherited' => 0,
            'variant_results' => [],
            'summary' => [],
        ];

        $variants = $product->variants;
        if ($variants->isEmpty()) {
            $results['summary'][] = 'Product has no variants';
            return $results;
        }

        foreach ($variants as $variant) {
            $results['variants_processed']++;
            
            $variantResult = $this->inheritAttributesForVariant($variant, $options);
            $results['variant_results'][$variant->id] = $variantResult;
            
            if (empty($variantResult['errors'])) {
                $results['variants_succeeded']++;
            } else {
                $results['variants_with_errors']++;
            }
            
            $results['total_inherited'] += count($variantResult['inherited']);
        }

        $results['summary'] = [
            "Processed {$results['variants_processed']} variants",
            "Succeeded: {$results['variants_succeeded']}, Errors: {$results['variants_with_errors']}",
            "Total attributes inherited: {$results['total_inherited']}",
        ];

        Log::info('Bulk inheritance completed for product', [
            'product_id' => $product->id,
            'variants_processed' => $results['variants_processed'],
            'total_inherited' => $results['total_inherited'],
        ]);

        return $results;
    }

    /**
     * 🔄 REFRESH INHERITANCE FOR VARIANT
     *
     * Refresh inherited attributes when parent product values change
     */
    public function refreshInheritanceForVariant(ProductVariant $variant, array $options = []): array
    {
        $results = [
            'variant_id' => $variant->id,
            'refreshed' => [],
            'unchanged' => [],
            'removed' => [],
            'errors' => [],
        ];

        if (!$variant->product) {
            $results['errors']['product'] = 'Variant has no associated product';
            return $results;
        }

        $attributeKeys = $options['attributes'] ?? null;

        // Get inherited variant attributes
        $query = $variant->attributes()->inherited()->with('attributeDefinition');
        if ($attributeKeys) {
            $query->whereHas('attributeDefinition', function ($q) use ($attributeKeys) {
                $q->whereIn('key', $attributeKeys);
            });
        }

        $inheritedAttributes = $query->get();

        foreach ($inheritedAttributes as $variantAttribute) {
            $key = $variantAttribute->getAttributeKey();
            
            try {
                // Get corresponding product attribute
                $productAttribute = $variant->product->attributes()
                    ->where('attribute_definition_id', $variantAttribute->attribute_definition_id)
                    ->first();

                if (!$productAttribute) {
                    // Product no longer has this attribute
                    $variantAttribute->delete();
                    $results['removed'][] = [
                        'key' => $key,
                        'reason' => 'Product no longer has this attribute',
                    ];
                    continue;
                }

                // Compare values
                $currentValue = $variantAttribute->value;
                $newValue = $productAttribute->value;

                if ($currentValue !== $newValue) {
                    // Refresh inherited value
                    if ($variantAttribute->inheritFromProduct($productAttribute)) {
                        $variantAttribute->save();
                        $results['refreshed'][] = [
                            'key' => $key,
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
                $results['errors'][$key] = $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * 🧹 CLEANUP ORPHANED INHERITANCE
     *
     * Remove inheritance records where parent attribute no longer exists
     */
    public function cleanupOrphanedInheritance(Product $product = null): array
    {
        $results = [
            'total_checked' => 0,
            'orphans_found' => 0,
            'orphans_removed' => 0,
            'errors' => [],
        ];

        // Build query
        $query = VariantAttribute::inherited()->with(['variant.product', 'attributeDefinition']);
        
        if ($product) {
            $query->whereHas('variant', function ($q) use ($product) {
                $q->where('product_id', $product->id);
            });
        }

        $inheritedAttributes = $query->get();
        $results['total_checked'] = $inheritedAttributes->count();

        foreach ($inheritedAttributes as $variantAttribute) {
            try {
                $variant = $variantAttribute->variant;
                if (!$variant || !$variant->product) {
                    continue;
                }

                // Check if parent product still has this attribute
                $productAttribute = $variant->product->attributes()
                    ->where('attribute_definition_id', $variantAttribute->attribute_definition_id)
                    ->first();

                if (!$productAttribute) {
                    // Orphaned inheritance - remove it
                    $variantAttribute->delete();
                    $results['orphans_found']++;
                    $results['orphans_removed']++;
                    
                    Log::info('Removed orphaned inheritance', [
                        'variant_id' => $variant->id,
                        'attribute_key' => $variantAttribute->getAttributeKey(),
                    ]);
                }

            } catch (\Exception $e) {
                $results['errors'][] = [
                    'variant_attribute_id' => $variantAttribute->id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * 📊 GET INHERITANCE STATISTICS
     *
     * Get statistics about inheritance across the system
     */
    public function getInheritanceStatistics(Product $product = null): array
    {
        $stats = [
            'products' => 0,
            'variants' => 0,
            'total_variant_attributes' => 0,
            'inherited_attributes' => 0,
            'override_attributes' => 0,
            'explicit_attributes' => 0,
            'inheritance_percentage' => 0,
            'by_attribute' => [],
            'by_product' => [],
        ];

        if ($product) {
            // Stats for specific product
            $variants = $product->variants;
            $stats['products'] = 1;
            $stats['variants'] = $variants->count();

            $variantAttributes = VariantAttribute::whereIn('variant_id', $variants->pluck('id'))->get();
        } else {
            // Global stats
            $stats['products'] = Product::count();
            $stats['variants'] = ProductVariant::count();
            $variantAttributes = VariantAttribute::all();
        }

        $stats['total_variant_attributes'] = $variantAttributes->count();
        $stats['inherited_attributes'] = $variantAttributes->where('is_inherited', true)->count();
        $stats['override_attributes'] = $variantAttributes->where('is_override', true)->count();
        $stats['explicit_attributes'] = $variantAttributes->where('is_inherited', false)->where('is_override', false)->count();

        if ($stats['total_variant_attributes'] > 0) {
            $stats['inheritance_percentage'] = round(
                ($stats['inherited_attributes'] / $stats['total_variant_attributes']) * 100,
                1
            );
        }

        // Stats by attribute type
        $attributeCounts = $variantAttributes
            ->load('attributeDefinition')
            ->groupBy('attributeDefinition.key')
            ->map(function ($group) {
                return [
                    'total' => $group->count(),
                    'inherited' => $group->where('is_inherited', true)->count(),
                    'overrides' => $group->where('is_override', true)->count(),
                    'explicit' => $group->where('is_inherited', false)->where('is_override', false)->count(),
                ];
            });

        $stats['by_attribute'] = $attributeCounts->toArray();

        return $stats;
    }

    /**
     * 🎯 INHERIT SINGLE ATTRIBUTE
     *
     * Core logic for inheriting a single attribute
     */
    protected function inheritSingleAttribute(ProductVariant $variant, AttributeDefinition $definition, array $options = []): array
    {
        $force = $options['force'] ?? false;

        // Check if product has this attribute
        $productAttribute = $variant->product->attributes()
            ->where('attribute_definition_id', $definition->id)
            ->first();

        if (!$productAttribute) {
            return [
                'success' => false,
                'reason' => 'Product does not have this attribute',
            ];
        }

        // Check existing variant attribute
        $variantAttribute = $variant->attributes()
            ->where('attribute_definition_id', $definition->id)
            ->first();

        if ($variantAttribute) {
            // Skip if already inherited and not forcing
            if ($variantAttribute->is_inherited && !$force) {
                return [
                    'success' => false,
                    'reason' => 'Already inherited',
                ];
            }

            // Skip if explicitly set (not inherited) and not forcing
            if (!$variantAttribute->is_inherited && !$force) {
                return [
                    'success' => false,
                    'reason' => 'Explicitly set (would override)',
                ];
            }
        } else {
            // Create new variant attribute
            $variantAttribute = new VariantAttribute([
                'variant_id' => $variant->id,
                'attribute_definition_id' => $definition->id,
            ]);
        }

        // Perform inheritance
        if ($variantAttribute->inheritFromProduct($productAttribute)) {
            $variantAttribute->save();
            
            return [
                'success' => true,
                'action' => $variantAttribute->wasRecentlyCreated ? 'created' : 'updated',
                'value' => $variantAttribute->getTypedValue(),
            ];
        }

        return [
            'success' => false,
            'reason' => 'Failed to inherit value (validation failed)',
        ];
    }

    /**
     * 🔍 FIND INHERITANCE CONFLICTS
     *
     * Find potential conflicts in inheritance setup
     */
    public function findInheritanceConflicts(Product $product = null): array
    {
        $conflicts = [
            'missing_definitions' => [],
            'invalid_strategies' => [],
            'orphaned_inheritance' => [],
            'validation_failures' => [],
        ];

        // Find variant attributes referencing non-existent attribute definitions
        $query = VariantAttribute::whereDoesntHave('attributeDefinition');
        if ($product) {
            $query->whereHas('variant', function ($q) use ($product) {
                $q->where('product_id', $product->id);
            });
        }

        $conflicts['missing_definitions'] = $query->get()->map(function ($attr) {
            return [
                'variant_id' => $attr->variant_id,
                'attribute_definition_id' => $attr->attribute_definition_id,
            ];
        })->toArray();

        // Find inherited attributes with invalid validation
        $query = VariantAttribute::inherited()->invalid();
        if ($product) {
            $query->whereHas('variant', function ($q) use ($product) {
                $q->where('product_id', $product->id);
            });
        }

        $conflicts['validation_failures'] = $query->with('attributeDefinition')->get()->map(function ($attr) {
            return [
                'variant_id' => $attr->variant_id,
                'attribute_key' => $attr->getAttributeKey(),
                'errors' => $attr->validation_errors,
            ];
        })->toArray();

        return $conflicts;
    }

    /**
     * 🔧 REPAIR INHERITANCE CONFLICTS
     *
     * Attempt to repair found inheritance conflicts
     */
    public function repairInheritanceConflicts(array $conflicts, array $options = []): array
    {
        $results = [
            'repaired' => [],
            'failed' => [],
        ];

        // Remove orphaned inheritance
        foreach ($conflicts['missing_definitions'] as $conflict) {
            try {
                VariantAttribute::where('variant_id', $conflict['variant_id'])
                    ->where('attribute_definition_id', $conflict['attribute_definition_id'])
                    ->delete();
                    
                $results['repaired'][] = [
                    'type' => 'missing_definition',
                    'variant_id' => $conflict['variant_id'],
                    'action' => 'removed_orphaned_attribute',
                ];
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'type' => 'missing_definition',
                    'conflict' => $conflict,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Fix validation failures by re-inheriting
        foreach ($conflicts['validation_failures'] as $conflict) {
            try {
                $variant = ProductVariant::find($conflict['variant_id']);
                if ($variant) {
                    $this->inheritAttributesForVariant($variant, [
                        'attributes' => [$conflict['attribute_key']],
                        'force' => true,
                    ]);
                    
                    $results['repaired'][] = [
                        'type' => 'validation_failure',
                        'variant_id' => $conflict['variant_id'],
                        'attribute_key' => $conflict['attribute_key'],
                        'action' => 're_inherited',
                    ];
                }
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'type' => 'validation_failure',
                    'conflict' => $conflict,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}