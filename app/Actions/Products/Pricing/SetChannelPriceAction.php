<?php

namespace App\Actions\Products\Pricing;

use App\Actions\Base\BaseAction;
use App\Facades\Activity;
use App\Models\ProductVariant;
use App\Models\SalesChannel;
use App\Traits\WithActivityLogs;
use Illuminate\Support\Facades\Log;

/**
 * 💰 SET CHANNEL PRICE ACTION
 * 
 * Sets channel-specific pricing for a product variant using the attribute system.
 * Replaces the complex Pricing table approach with simple attribute-based pricing.
 * 
 * Usage: $action->handle($variant, 'shopify', 89.99)
 */
class SetChannelPriceAction extends BaseAction
{
    use WithActivityLogs;
    /**
     * Execute the set channel price action
     * 
     * @param ProductVariant $variant - The variant to set pricing for
     * @param string $channelCode - The sales channel code (e.g. 'shopify', 'ebay')
     * @param float|null $price - The price to set (null to remove override)
     */
    protected function performAction(...$params): array
    {
        $variant = $params[0] ?? null;
        $channelCode = $params[1] ?? null;
        $price = $params[2] ?? null;
        $options = $params[3] ?? [];

        // Validation
        if (!$variant instanceof ProductVariant) {
            return $this->failure('Invalid variant provided - must be ProductVariant instance');
        }

        if (!$channelCode || !is_string($channelCode)) {
            return $this->failure('Channel code is required and must be a string');
        }

        // Validate channel exists and is active
        $channel = SalesChannel::where('code', $channelCode)->active()->first();
        if (!$channel) {
            return $this->failure("Invalid or inactive sales channel: {$channelCode}");
        }

        // Validate price if provided
        if ($price !== null) {
            if (!is_numeric($price) || $price < 0) {
                return $this->failure('Price must be a positive number or null');
            }
            $price = round((float) $price, 2);
        }

        $attributeKey = $channelCode . '_price';

        Log::info('💰 Setting channel price', [
            'variant_id' => $variant->id,
            'variant_sku' => $variant->sku,
            'channel_code' => $channelCode,
            'attribute_key' => $attributeKey,
            'price' => $price,
            'action' => $price === null ? 'remove_override' : 'set_override',
        ]);

        $startTime = microtime(true);

        try {
            $previousPrice = $variant->getSmartAttributeValue($attributeKey);
            
            if ($price === null) {
                // Remove the channel price override - variant will use default price
                $this->removeChannelPriceOverride($variant, $attributeKey);
                $action = 'removed';
                $message = "Removed {$channel->name} price override for variant {$variant->sku}";
            } else {
                // Set the channel price override
                $this->setChannelPriceOverride($variant, $attributeKey, $price);
                $action = $previousPrice === null ? 'created' : 'updated';
                $message = ucfirst($action) . " {$channel->name} price for variant {$variant->sku}: £{$price}";
            }

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::info("✅ Channel price {$action} successfully", [
                'variant_id' => $variant->id,
                'channel_code' => $channelCode,
                'previous_price' => $previousPrice,
                'new_price' => $price,
                'duration_ms' => $duration,
            ]);

            // 📝 Log activity with gorgeous descriptive messages
            $userName = auth()->user()?->name ?? 'System';
            $productName = $variant->product->name ?? 'Unknown Product';
            
            if ($price === null) {
                $description = "{$productName} variant {$variant->sku} {$channel->name} price override removed by {$userName} (now uses default £{$variant->price})";
            } elseif ($previousPrice === null) {
                $description = "{$productName} variant {$variant->sku} {$channel->name} price set to £{$price} by {$userName} (was using default £{$variant->price})";
            } else {
                $priceChange = $price - $previousPrice;
                $changeDirection = $priceChange > 0 ? 'increased' : 'decreased';
                $changeAmount = abs($priceChange);
                $changePercent = $previousPrice > 0 ? round((abs($priceChange) / $previousPrice) * 100, 1) : 0;
                
                $description = "{$productName} variant {$variant->sku} {$channel->name} price {$changeDirection} from £{$previousPrice} to £{$price} by {$userName} (+£{$changeAmount}, {$changePercent}% change)";
            }

            Activity::log()
                ->by(auth()->id())
                ->customEvent('pricing.channel_price_updated', $variant)
                ->description($description)
                ->with([
                    'channel_code' => $channel->code,
                    'channel_name' => $channel->name,
                    'action' => $action,
                    'previous_price' => $previousPrice,
                    'new_price' => $price,
                    'default_price' => $variant->price,
                    'effective_price' => $price ?? $variant->price,
                    'price_change' => $price && $previousPrice ? ($price - $previousPrice) : null,
                    'price_change_percentage' => $price && $previousPrice && $previousPrice > 0 
                        ? round((($price - $previousPrice) / $previousPrice) * 100, 2) 
                        : null,
                    'variant_sku' => $variant->sku,
                    'product_name' => $productName,
                    'user_name' => $userName,
                ])
                ->save();

            return $this->success($message, [
                'action' => $action,
                'variant_id' => $variant->id,
                'variant_sku' => $variant->sku,
                'channel_code' => $channelCode,
                'channel_name' => $channel->name,
                'previous_price' => $previousPrice,
                'new_price' => $price,
                'default_price' => $variant->price,
                'effective_price' => $price ?? $variant->price, // What price will actually be used
                'duration_ms' => $duration,
            ]);

        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('❌ Failed to set channel price', [
                'variant_id' => $variant->id,
                'channel_code' => $channelCode,
                'price' => $price,
                'error' => $e->getMessage(),
                'duration_ms' => $duration,
            ]);

            return $this->failure("Failed to set {$channel->name} price: " . $e->getMessage(), [
                'error_type' => get_class($e),
                'duration_ms' => $duration,
            ]);
        }
    }

    /**
     * Set channel price override using attribute system
     */
    protected function setChannelPriceOverride(ProductVariant $variant, string $attributeKey, float $price): void
    {
        $result = $variant->setAttributeValue($attributeKey, $price, [
            'source' => 'channel_pricing_action',
            'updated_by' => auth()->id() ?? 'system',
        ]);

        if (!$result) {
            throw new \Exception("Failed to set attribute value for {$attributeKey}");
        }
    }

    /**
     * Remove channel price override using attribute system
     */
    protected function removeChannelPriceOverride(ProductVariant $variant, string $attributeKey): void
    {
        // Use the same method as setting null to properly remove the attribute
        $variant->setAttributeValue($attributeKey, null, [
            'source' => 'channel_pricing_action',
            'updated_by' => auth()->id() ?? 'system',
        ]);
    }

    /**
     * Static helper method for easy usage
     */
    public static function run(ProductVariant $variant, string $channelCode, ?float $price): array
    {
        $action = new static();
        return $action->handle($variant, $channelCode, $price);
    }

    /**
     * Get available channels for pricing
     */
    public static function getAvailableChannels(): array
    {
        return SalesChannel::active()
            ->get()
            ->map(function ($channel) {
                return [
                    'code' => $channel->code,
                    'name' => $channel->name,
                    'attribute_key' => $channel->code . '_price',
                ];
            })
            ->toArray();
    }

    /**
     * Validate if channel code is valid
     */
    public static function isValidChannel(string $channelCode): bool
    {
        return SalesChannel::where('code', $channelCode)->active()->exists();
    }

}