<?php

namespace App\Services\Attributes\Actions;

use App\Models\AttributeDefinition;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductVariant;

class InheritAttributesAction
{
    /**
     * Inherit inheritable attributes from product to a single variant.
     * Strategy:
     *  - 'fallback' (default): only set if variant has no explicit value
     *  - 'always': overwrite variant with product value
     * @param array<int,string>|null $onlyKeys
     */
    public function execute(Product $product, ProductVariant $variant, string $strategy = 'fallback', ?array $onlyKeys = null): void
    {
        $defs = AttributeDefinition::getInheritableAttributes();
        if ($onlyKeys) {
            $defs = $defs->whereIn('key', $onlyKeys);
        }

        foreach ($defs as $def) {
            /** @var AttributeDefinition $def */
            $key = $def->key;
            $productAttr = $product->attributes()->forAttribute($key)->first();
            if (! $productAttr instanceof ProductAttribute) {
                continue;
            }

            $variantAttr = $variant->attributes()->forAttribute($key)->first();

            $shouldInherit = match ($strategy) {
                'always' => true,
                default => $variantAttr === null || ($variantAttr->is_inherited ?? false),
            };

            if (! $shouldInherit) {
                continue;
            }

            // Use variant helper to inherit
            $variant->attributes()->updateOrCreate(
                [
                    'variant_id' => $variant->id,
                    'attribute_definition_id' => $def->id,
                ],
                []
            );

            // Use model API to set with inheritance flags
            $variant->setTypedAttributeValue($key, $productAttr->value, [
                'source' => 'inheritance',
                'is_inherited' => true,
                'inherited_from_product_attribute_id' => $productAttr->id,
                'inherited_at' => now(),
                'assigned_by' => 'system',
                'metadata' => [
                    'inheritance_type' => $strategy,
                    'parent_product_id' => $product->id,
                ],
            ]);
        }
    }
}

