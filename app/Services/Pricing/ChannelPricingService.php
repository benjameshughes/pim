<?php

namespace App\Services\Pricing;

use App\Actions\Products\Pricing\BulkUpdateChannelPricingAction;
use App\Actions\Products\Pricing\GetChannelPriceAction;
use App\Actions\Products\Pricing\SetChannelPriceAction;
use App\Actions\Products\Pricing\SyncChannelAttributesAction;
use App\Models\ProductVariant;
use App\Models\SalesChannel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 🎯 CHANNEL PRICING SERVICE
 *
 * Main orchestration service for channel-specific pricing using the attribute system.
 * Provides a clean, unified API for all channel pricing operations.
 *
 * This replaces the complex Pricing table approach with simple attribute-based pricing.
 */
class ChannelPricingService
{
    /**
     * Get price for a specific channel (with fallback to default)
     */
    public function getPriceForChannel(ProductVariant $variant, ?string $channelCode = null): float
    {
        return GetChannelPriceAction::getPrice($variant, $channelCode);
    }

    /**
     * Get detailed pricing information for a channel
     */
    public function getPricingDetails(ProductVariant $variant, ?string $channelCode = null): array
    {
        $result = GetChannelPriceAction::run($variant, $channelCode);

        return $result['success'] ? $result['data'] : [];
    }

    /**
     * Set price for a specific channel
     */
    public function setPriceForChannel(ProductVariant $variant, string $channelCode, ?float $price): array
    {
        return SetChannelPriceAction::run($variant, $channelCode, $price);
    }

    /**
     * Remove channel price override (variant will use default price)
     */
    public function removeChannelPriceOverride(ProductVariant $variant, string $channelCode): array
    {
        return $this->setPriceForChannel($variant, $channelCode, null);
    }

    /**
     * Check if variant has channel-specific pricing
     */
    public function hasChannelOverride(ProductVariant $variant, string $channelCode): bool
    {
        return GetChannelPriceAction::hasChannelOverride($variant, $channelCode);
    }

    /**
     * Get all channel prices for a variant
     */
    public function getAllChannelPrices(ProductVariant $variant): array
    {
        return GetChannelPriceAction::getAllChannelPricesFor($variant);
    }

    /**
     * Apply markup percentage to channel pricing for variants
     */
    public function applyMarkupToChannel(Collection $variants, string $channelCode, float $markupPercentage): array
    {
        Log::info('🎯 Applying channel markup', [
            'channel_code' => $channelCode,
            'markup_percentage' => $markupPercentage,
            'variants_count' => $variants->count(),
        ]);

        return BulkUpdateChannelPricingAction::applyMarkupToChannel($variants, $channelCode, $markupPercentage);
    }

    /**
     * Apply discount percentage to channel pricing for variants
     */
    public function applyDiscountToChannel(Collection $variants, string $channelCode, float $discountPercentage): array
    {
        $pricingData = [
            [
                'channel_code' => $channelCode,
                'type' => 'percentage_discount',
                'value' => $discountPercentage,
            ],
        ];

        $action = new BulkUpdateChannelPricingAction;

        return $action->handle($variants, $pricingData);
    }

    /**
     * Set fixed price for channel across multiple variants
     */
    public function setFixedPriceForChannel(Collection $variants, string $channelCode, float $price): array
    {
        $pricingData = [
            [
                'channel_code' => $channelCode,
                'type' => 'fixed',
                'value' => $price,
            ],
        ];

        $action = new BulkUpdateChannelPricingAction;

        return $action->handle($variants, $pricingData);
    }

    /**
     * Apply multiple pricing rules across variants and channels
     */
    public function applyPricingRules(Collection $variants, array $pricingRules): array
    {
        $action = new BulkUpdateChannelPricingAction;

        return $action->handle($variants, $pricingRules);
    }

    /**
     * Get available sales channels for pricing
     */
    public function getAvailableChannels(): Collection
    {
        return SalesChannel::active()->get();
    }

    /**
     * Get channel price summary for a product (all variants)
     */
    public function getProductChannelPricingSummary(int $productId): array
    {
        $variants = ProductVariant::where('product_id', $productId)->get();
        $channels = $this->getAvailableChannels();

        $summary = [
            'product_id' => $productId,
            'variants_count' => $variants->count(),
            'channels' => [],
        ];

        foreach ($channels as $channel) {
            $channelSummary = [
                'code' => $channel->code,
                'name' => $channel->name,
                'variants_with_override' => 0,
                'variants_using_default' => 0,
                'price_range' => ['min' => null, 'max' => null],
                'prices' => [],
            ];

            foreach ($variants as $variant) {
                $price = $this->getPriceForChannel($variant, $channel->code);
                $hasOverride = $this->hasChannelOverride($variant, $channel->code);

                if ($hasOverride) {
                    $channelSummary['variants_with_override']++;
                } else {
                    $channelSummary['variants_using_default']++;
                }

                $channelSummary['prices'][] = $price;

                if ($channelSummary['price_range']['min'] === null || $price < $channelSummary['price_range']['min']) {
                    $channelSummary['price_range']['min'] = $price;
                }
                if ($channelSummary['price_range']['max'] === null || $price > $channelSummary['price_range']['max']) {
                    $channelSummary['price_range']['max'] = $price;
                }
            }

            $summary['channels'][$channel->code] = $channelSummary;
        }

        return $summary;
    }

    /**
     * Sync channel attributes (create/update attribute definitions from active channels)
     */
    public function syncChannelAttributes(bool $forceUpdate = false): array
    {
        $action = new SyncChannelAttributesAction;

        return $action->handle($forceUpdate);
    }

    /**
     * Get pricing strategy recommendations for a channel
     */
    public function getPricingRecommendations(ProductVariant $variant, string $channelCode): array
    {
        $channel = SalesChannel::where('code', $channelCode)->first();
        if (! $channel) {
            return [];
        }

        $defaultPrice = $variant->price;
        $currentChannelPrice = $this->getPriceForChannel($variant, $channelCode);
        $hasOverride = $this->hasChannelOverride($variant, $channelCode);

        $recommendations = [];

        // Channel-specific markup recommendations
        $recommendedMarkups = match ($channelCode) {
            'ebay' => ['low' => 5, 'medium' => 10, 'high' => 15],
            'amazon' => ['low' => 8, 'medium' => 12, 'high' => 18],
            'shopify' => ['low' => 0, 'medium' => 5, 'high' => 10],
            'wholesale' => ['low' => -10, 'medium' => -5, 'high' => 0],
            default => ['low' => 0, 'medium' => 5, 'high' => 10],
        };

        foreach ($recommendedMarkups as $level => $markup) {
            $recommendedPrice = $defaultPrice * (1 + ($markup / 100));
            $recommendations[] = [
                'level' => $level,
                'markup_percentage' => $markup,
                'recommended_price' => round($recommendedPrice, 2),
                'difference_from_default' => round($recommendedPrice - $defaultPrice, 2),
                'is_current' => $hasOverride && abs($currentChannelPrice - $recommendedPrice) < 0.01,
            ];
        }

        return [
            'channel_code' => $channelCode,
            'channel_name' => $channel->name,
            'default_price' => $defaultPrice,
            'current_channel_price' => $currentChannelPrice,
            'has_override' => $hasOverride,
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Export channel pricing data for analysis
     */
    public function exportChannelPricingData(Collection $variants): array
    {
        $channels = $this->getAvailableChannels();
        $exportData = [];

        foreach ($variants as $variant) {
            $variantData = [
                'variant_id' => $variant->id,
                'variant_sku' => $variant->sku,
                'product_name' => $variant->product->name,
                'default_price' => $variant->price,
            ];

            foreach ($channels as $channel) {
                $price = $this->getPriceForChannel($variant, $channel->code);
                $hasOverride = $this->hasChannelOverride($variant, $channel->code);

                $variantData[$channel->code.'_price'] = $price;
                $variantData[$channel->code.'_has_override'] = $hasOverride;
                $variantData[$channel->code.'_markup_percentage'] = $variant->price > 0
                    ? round((($price - $variant->price) / $variant->price) * 100, 2)
                    : 0;
            }

            $exportData[] = $variantData;
        }

        return $exportData;
    }

    /**
     * Cache key for channel pricing
     */
    protected function getCacheKey(string $type, $identifier): string
    {
        return "channel_pricing:{$type}:{$identifier}";
    }

    /**
     * Clear pricing cache for a variant
     */
    public function clearVariantPricingCache(ProductVariant $variant): void
    {
        Cache::forget($this->getCacheKey('variant', $variant->id));
        Cache::forget($this->getCacheKey('variant_channels', $variant->id));
    }
}
