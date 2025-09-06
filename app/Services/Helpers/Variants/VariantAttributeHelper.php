<?php

namespace App\Services\Helpers\Variants;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Attributes\Actions\InheritAttributesAction;

/**
 * VariantAttributeHelper
 */
class VariantAttributeHelper
{
    /** Inherit from product using strategy (fallback|always). */
    public function inheritFromProduct(Product $product, ProductVariant $variant, string $strategy = 'fallback'): void
    {
        app(InheritAttributesAction::class)->execute($product, $variant, $strategy);
    }
}

