<?php

namespace App\Services\Pricing\Actions;

use App\Jobs\UpdateShopifyPricingJob;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesChannel;
use App\Models\SyncAccount;

/**
 * SyncPricingToMarketplaceAction
 *
 * Dispatches marketplace-specific pricing update flows for the given
 * variant scope and sales channel code.
 */
class SyncPricingToMarketplaceAction
{
    /**
     * Push pricing to external marketplace for the selected channel.
     */
    public function execute(array $variantIds, ?string $salesChannelCode): array
    {
        if (! $salesChannelCode) {
            return ['success' => false, 'message' => 'No sales channel specified'];
        }

        $prefix = strtolower(strtok($salesChannelCode, '_'));

        // Group variants by product to dispatch per-product jobs
        $variants = ProductVariant::whereIn('id', $variantIds)->get(['id','product_id']);
        $byProduct = $variants->groupBy('product_id');

        $dispatched = 0;

        if ($prefix === 'shopify') {
            $channel = SalesChannel::where('code', $salesChannelCode)->first();
            $syncAccountId = $channel?->config['sync_account_id'] ?? null;
            $syncAccount = $syncAccountId ? SyncAccount::find($syncAccountId) : null;

            if (! $syncAccount) {
                return ['success' => false, 'message' => 'Shopify SyncAccount not found for channel '.$salesChannelCode];
            }

            foreach ($byProduct as $productId => $group) {
                $product = Product::find($productId);
                if ($product) {
                    dispatch(new UpdateShopifyPricingJob($product, $syncAccount, []));
                    $dispatched++;
                }
            }
        } else {
            // Other channels to implement later
            return ['success' => false, 'message' => 'Push not implemented for '.$prefix];
        }

        return [
            'success' => true,
            'channel' => $salesChannelCode,
            'pushed' => $dispatched,
        ];
    }
}
