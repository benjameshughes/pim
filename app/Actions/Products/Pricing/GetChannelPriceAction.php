<?php

namespace App\Actions\Products\Pricing;

use App\Actions\Base\BaseAction;
use App\Models\ProductVariant;
use App\Models\SalesChannel;
use Illuminate\Support\Facades\Log;

/**
 * 🔍 GET CHANNEL PRICE ACTION
 * 
 * Retrieves channel-specific pricing for a product variant using the attribute system.
 * Falls back to default variant price if no channel-specific override is set.
 * 
 * Usage: $action->handle($variant, 'shopify') 
 * Returns: ['success' => true, 'data' => ['price' => 89.99, 'source' => 'channel_override']]
 */
class GetChannelPriceAction extends BaseAction
{
    /**
     * Execute the get channel price action
     * 
     * @param ProductVariant $variant - The variant to get pricing for
     * @param string|null $channelCode - The sales channel code (null for default price)
     */
    protected function performAction(...$params): array
    {
        $variant = $params[0] ?? null;
        $channelCode = $params[1] ?? null;
        $options = $params[2] ?? [];

        // Validation
        if (!$variant instanceof ProductVariant) {
            return $this->failure('Invalid variant provided - must be ProductVariant instance');
        }

        // If no channel specified, return default price
        if (!$channelCode) {
            return $this->success('Default price retrieved', [
                'price' => $variant->price,
                'source' => 'default',
                'channel_code' => null,
                'channel_name' => 'Default',
                'variant_id' => $variant->id,
                'variant_sku' => $variant->sku,
            ]);
        }

        // Validate channel exists and is active
        $channel = SalesChannel::where('code', $channelCode)->active()->first();
        if (!$channel) {
            return $this->failure("Invalid or inactive sales channel: {$channelCode}");
        }

        $attributeKey = $channelCode . '_price';

        Log::info('🔍 Getting channel price', [
            'variant_id' => $variant->id,
            'variant_sku' => $variant->sku,
            'channel_code' => $channelCode,
            'attribute_key' => $attributeKey,
        ]);

        $startTime = microtime(true);

        try {
            // Try to get channel-specific price from attributes
            $channelPrice = $variant->getSmartAttributeValue($attributeKey);
            $defaultPrice = $variant->price;

            // Determine which price to use and why
            if ($channelPrice !== null) {
                $effectivePrice = (float) $channelPrice;
                $source = 'channel_override';
                $message = "Retrieved {$channel->name} override price for variant {$variant->sku}: £{$effectivePrice}";
            } else {
                $effectivePrice = $defaultPrice;
                $source = 'default_fallback';
                $message = "No {$channel->name} override found for variant {$variant->sku}, using default price: £{$effectivePrice}";
            }

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            // Get all channel prices for context (useful for UI)
            $allChannelPrices = $this->getAllChannelPrices($variant);

            Log::info('✅ Channel price retrieved successfully', [
                'variant_id' => $variant->id,
                'channel_code' => $channelCode,
                'effective_price' => $effectivePrice,
                'source' => $source,
                'duration_ms' => $duration,
            ]);

            return $this->success($message, [
                'price' => $effectivePrice,
                'source' => $source,
                'channel_code' => $channelCode,
                'channel_name' => $channel->name,
                'variant_id' => $variant->id,
                'variant_sku' => $variant->sku,
                'default_price' => $defaultPrice,
                'channel_override' => $channelPrice,
                'has_override' => $channelPrice !== null,
                'all_channel_prices' => $allChannelPrices,
                'duration_ms' => $duration,
            ]);

        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('❌ Failed to get channel price', [
                'variant_id' => $variant->id,
                'channel_code' => $channelCode,
                'error' => $e->getMessage(),
                'duration_ms' => $duration,
            ]);

            return $this->failure("Failed to get {$channel->name} price: " . $e->getMessage(), [
                'error_type' => get_class($e),
                'duration_ms' => $duration,
            ]);
        }
    }

    /**
     * Get all channel prices for this variant (useful for UI display)
     */
    protected function getAllChannelPrices(ProductVariant $variant): array
    {
        $channelPrices = [];
        
        $activeChannels = SalesChannel::active()->get();
        
        foreach ($activeChannels as $channel) {
            $attributeKey = $channel->code . '_price';
            $channelPrice = $variant->getSmartAttributeValue($attributeKey);
            
            $channelPrices[$channel->code] = [
                'name' => $channel->name,
                'price' => $channelPrice,
                'effective_price' => $channelPrice ?? $variant->price,
                'has_override' => $channelPrice !== null,
                'attribute_key' => $attributeKey,
            ];
        }

        return $channelPrices;
    }

    /**
     * Static helper method for easy usage
     */
    public static function run(ProductVariant $variant, ?string $channelCode = null): array
    {
        $action = new static();
        return $action->handle($variant, $channelCode);
    }

    /**
     * Get just the price value (no metadata)
     */
    public static function getPrice(ProductVariant $variant, ?string $channelCode = null): float
    {
        $result = static::run($variant, $channelCode);
        return $result['success'] ? $result['data']['price'] : $variant->price;
    }

    /**
     * Check if variant has channel-specific pricing override
     */
    public static function hasChannelOverride(ProductVariant $variant, string $channelCode): bool
    {
        $result = static::run($variant, $channelCode);
        return $result['success'] && $result['data']['has_override'];
    }

    /**
     * Get all channel prices for a variant (useful for bulk operations)
     */
    public static function getAllChannelPricesFor(ProductVariant $variant): array
    {
        $action = new static();
        return $action->getAllChannelPrices($variant);
    }
}