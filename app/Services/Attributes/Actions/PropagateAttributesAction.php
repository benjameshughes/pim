<?php

namespace App\Services\Attributes\Actions;

use App\Models\Product;

class PropagateAttributesAction
{
    /**
     * Propagate inheritable attributes from product to all variants.
     * @param array<int,string>|null $onlyKeys
     */
    public function execute(Product $product, string $strategy = 'fallback', ?array $onlyKeys = null): void
    {
        $inherit = app(InheritAttributesAction::class);
        foreach ($product->variants as $variant) {
            $inherit->execute($product, $variant, $strategy, $onlyKeys);
        }
    }
}

