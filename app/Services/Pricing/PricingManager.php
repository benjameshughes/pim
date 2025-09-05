<?php

namespace App\Services\Pricing;

use App\Services\Pricing\Fluent\PricingBuilder;

/**
 * PricingManager
 *
 * Factory for creating fluent pricing builders for different scopes
 * (variant(s) or product). Keeps construction in one place and allows
 * future adapter/registry extensions.
 */
class PricingManager
{
    /**
     * Start a pricing builder scoped to a single variant ID.
     */
    public function variant(int $variantId): PricingBuilder
    {
        return PricingBuilder::forVariants([$variantId]);
    }

    /**
     * Start a pricing builder scoped to multiple variant IDs.
     */
    public function variants(array $variantIds): PricingBuilder
    {
        return PricingBuilder::forVariants($variantIds);
    }

    /**
     * Start a pricing builder scoped to all variants of a product.
     */
    public function product(int $productId): PricingBuilder
    {
        return PricingBuilder::forProduct($productId);
    }
}

