<?php

namespace App\Services\Pricing\Integrations;

/**
 * ShopifyPricingAdapter
 *
 * Encapsulates details of pushing pricing changes to Shopify
 * (e.g., dispatch UpdateShopifyPricingJob or call adapter update).
 */
class ShopifyPricingAdapter
{
    /**
     * Push pricing for the given variant IDs.
     */
    public function push(array $variantIds, ?string $salesChannelCode): array
    {
        // Scaffolding: implement job dispatch / API calls later.
        return [
            'success' => true,
            'variant_count' => count($variantIds),
            'channel' => $salesChannelCode,
        ];
    }
}

