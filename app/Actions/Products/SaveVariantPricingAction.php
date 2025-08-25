<?php

namespace App\Actions\Products;

use App\Exceptions\ProductWizard\ProductSaveException;
use App\Models\Pricing;
use App\Models\ProductVariant;
use App\Models\SalesChannel;
use Illuminate\Support\Collection;

/**
 * 💰 SAVE VARIANT PRICING ACTION - New Architecture
 *
 * Handles channel-specific pricing using the new Pricing model
 * - Creates pricing records with sales_channel_id
 * - Maps variant indices to actual variant IDs
 * - Supports multiple sales channels per variant
 */
class SaveVariantPricingAction
{
    /**
     * Execute pricing save for wizard-generated variants
     *
     * @param  Collection  $variants  - Created ProductVariant models
     * @param  array  $pricingData  - Wizard pricing data (indexed by variant array position)
     * @param  int|null  $salesChannelId  - Channel to price for (defaults to first available)
     */
    public function execute(Collection $variants, array $pricingData, ?int $salesChannelId = null): array
    {
        try {
            // Get default sales channel if none provided
            $salesChannel = $salesChannelId
                ? SalesChannel::findOrFail($salesChannelId)
                : $this->getDefaultSalesChannel();

            if (! $salesChannel) {
                throw new \Exception('No sales channel available for pricing. Please create at least one sales channel.');
            }

            $updatedRecords = 0;
            $errors = [];

            // Loop through variants (not array indices!)
            foreach ($variants as $index => $variant) {
                // Check if we have pricing data for this variant position
                if (! isset($pricingData[$index])) {
                    continue;
                }

                $pricing = $pricingData[$index];

                try {
                    // Create/update pricing record with proper relationships
                    Pricing::updateOrCreate(
                        [
                            'product_variant_id' => $variant->id,
                            'sales_channel_id' => $salesChannel->id,
                        ],
                        [
                            'cost_price' => $pricing['cost_price'] ?? 0,
                            'price' => $pricing['retail_price'] ?? 0, // Map retail_price to price
                            'discount_price' => null,
                            'margin_percentage' => $this->calculateMargin(
                                $pricing['retail_price'] ?? 0,
                                $pricing['cost_price'] ?? 0
                            ),
                            'currency' => $salesChannel->default_currency ?? 'GBP',
                        ]
                    );

                    $updatedRecords++;

                } catch (\Exception $e) {
                    $errors[] = "Variant {$variant->sku}: {$e->getMessage()}";
                }
            }

            if (! empty($errors)) {
                throw new \Exception('Some pricing updates failed: '.implode(', ', $errors));
            }

            return [
                'success' => true,
                'updated_records' => $updatedRecords,
                'sales_channel' => $salesChannel->name,
                'message' => "Pricing updated for {$updatedRecords} variants on {$salesChannel->name}",
            ];

        } catch (\Exception $e) {
            throw ProductSaveException::pricingUpdateFailed($e);
        }
    }

    /**
     * Calculate profit margin percentage
     */
    private function calculateMargin(float $retailPrice, float $costPrice): float
    {
        if ($retailPrice <= 0) {
            return 0;
        }

        return (($retailPrice - $costPrice) / $retailPrice) * 100;
    }

    /**
     * Get default sales channel for pricing
     */
    private function getDefaultSalesChannel(): ?SalesChannel
    {
        // Try to get default channel, or create one if none exists
        $channel = SalesChannel::getDefault();

        if (! $channel) {
            // Create a default 'Direct Sales' channel if none exists
            $channel = SalesChannel::create([
                'name' => 'direct',
                'code' => 'DIRECT',
                'display_name' => 'Direct Sales',
                'slug' => 'direct-sales',
                'description' => 'Direct sales channel (auto-created)',
                'default_currency' => 'GBP',
                'status' => 'active',
                'is_active' => true,
                'priority' => 1,
                'default_markup_percentage' => 0,
                'platform_fee_percentage' => 0,
                'payment_fee_percentage' => 0,
            ]);
        }

        return $channel;
    }

    /**
     * Bulk update pricing for multiple variants across channels
     */
    public function bulkUpdate(array $variantIds, array $pricingData, array $salesChannelIds = []): array
    {
        try {
            if (empty($salesChannelIds)) {
                $salesChannelIds = [SalesChannel::getDefault()->id];
            }

            $updatedRecords = 0;

            foreach ($variantIds as $variantId) {
                foreach ($salesChannelIds as $channelId) {
                    Pricing::updateOrCreate(
                        [
                            'product_variant_id' => $variantId,
                            'sales_channel_id' => $channelId,
                        ],
                        [
                            'cost_price' => $pricingData['cost_price'] ?? 0,
                            'price' => $pricingData['retail_price'] ?? 0,
                            'margin_percentage' => $this->calculateMargin(
                                $pricingData['retail_price'] ?? 0,
                                $pricingData['cost_price'] ?? 0
                            ),
                            'currency' => 'GBP',
                        ]
                    );

                    $updatedRecords++;
                }
            }

            return [
                'success' => true,
                'updated_variants' => count($variantIds),
                'updated_records' => $updatedRecords,
                'message' => "Bulk pricing update completed for {$updatedRecords} records",
            ];

        } catch (\Exception $e) {
            throw ProductSaveException::bulkPricingUpdateFailed($e);
        }
    }
}
