<?php

namespace App\Services\Shopify;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SyncAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * 💰 SHOPIFY PRICING SYNC SERVICE
 *
 * Specialized service for handling Shopify pricing synchronization logic.
 * Provides pricing mapping, validation, and transformation utilities.
 * Complements the UpdateShopifyPricingAction with additional business logic.
 */
class ShopifyPricingSyncService
{
    protected SyncAccount $syncAccount;

    public function __construct(SyncAccount $syncAccount)
    {
        $this->syncAccount = $syncAccount;
    }

    /**
     * 🎯 Get pricing strategy for the sync account
     */
    public function getPricingStrategy(): string
    {
        // Check account metadata for pricing strategy
        $strategy = $this->syncAccount->metadata['pricing_strategy'] ?? 'base_price';
        
        return match ($strategy) {
            'channel_specific' => 'channel_price',
            'sale_price' => 'sale_price',
            'cost_plus_margin' => 'calculated_price',
            default => 'base_price',
        };
    }

    /**
     * 💲 Calculate price for a variant based on strategy
     */
    public function calculatePrice(ProductVariant $variant, string $strategy = null): ?float
    {
        $strategy = $strategy ?? $this->getPricingStrategy();
        
        // Get the variant's pricing record for this sync account
        $pricing = $variant->pricing()
            ->where('sales_channel_id', $this->syncAccount->id)
            ->first();
        
        // Fallback to default pricing
        if (!$pricing) {
            $pricing = $variant->pricing()->whereNull('sales_channel_id')->first();
        }

        if (!$pricing) {
            // Ultimate fallback to variant price field
            Log::info("Using variant price fallback for {$variant->sku}");
            return $variant->price;
        }

        return match ($strategy) {
            'base_price' => $pricing->base_price,
            'channel_price' => $pricing->channel_price ?? $pricing->base_price,
            'sale_price' => $this->calculateSalePrice($pricing),
            'calculated_price' => $this->calculateMarginPrice($pricing),
            default => $pricing->base_price,
        };
    }

    /**
     * 🏷️ Calculate sale price with discount logic
     */
    protected function calculateSalePrice($pricing): float
    {
        $basePrice = $pricing->sale_price ?? $pricing->base_price;
        
        // If sale period is active, use sale price
        if ($pricing->sale_starts_at && $pricing->sale_ends_at) {
            $now = now();
            if ($now->between($pricing->sale_starts_at, $pricing->sale_ends_at)) {
                return $basePrice;
            }
        }
        
        // Otherwise use base price
        return $pricing->base_price;
    }

    /**
     * 📊 Calculate price with margin/markup logic
     */
    protected function calculateMarginPrice($pricing): float
    {
        if ($pricing->cost_price && $pricing->markup_percentage) {
            return $pricing->cost_price * (1 + ($pricing->markup_percentage / 100));
        }
        
        return $pricing->base_price;
    }

    /**
     * 🔄 Build price comparison data
     */
    public function buildPriceComparison(ProductVariant $variant): array
    {
        $currentShopifyPrice = $this->getCurrentShopifyPrice($variant);
        $newPrice = $this->calculatePrice($variant);
        
        return [
            'variant_sku' => $variant->sku,
            'current_shopify_price' => $currentShopifyPrice,
            'new_price' => $newPrice,
            'price_change' => $newPrice ? ($newPrice - ($currentShopifyPrice ?? 0)) : 0,
            'price_change_percentage' => $currentShopifyPrice 
                ? (($newPrice - $currentShopifyPrice) / $currentShopifyPrice * 100) 
                : 0,
        ];
    }

    /**
     * 🔍 Get current price from Shopify (via API call)
     */
    protected function getCurrentShopifyPrice(ProductVariant $variant): ?float
    {
        // This would require a Shopify API call to get current pricing
        // For now, return null - could be enhanced to fetch real-time pricing
        return null;
    }

    /**
     * ✅ Validate pricing data before sync
     */
    public function validatePricingData(Collection $variants): array
    {
        $validationResults = [
            'valid_variants' => collect(),
            'invalid_variants' => collect(),
            'warnings' => [],
        ];
        
        foreach ($variants as $variant) {
            $price = $this->calculatePrice($variant);
            
            if ($price === null) {
                $validationResults['invalid_variants']->push([
                    'variant' => $variant,
                    'reason' => 'No pricing data found',
                ]);
                continue;
            }
            
            if ($price <= 0) {
                $validationResults['invalid_variants']->push([
                    'variant' => $variant,
                    'reason' => 'Price must be greater than zero',
                ]);
                continue;
            }
            
            // Check for unusually high/low prices
            if ($price > 10000) {
                $validationResults['warnings'][] = "High price detected for {$variant->sku}: £{$price}";
            }
            
            if ($price < 0.01) {
                $validationResults['warnings'][] = "Very low price detected for {$variant->sku}: £{$price}";
            }
            
            $validationResults['valid_variants']->push($variant);
        }
        
        return $validationResults;
    }

    /**
     * 📋 Generate pricing update summary
     */
    public function generateUpdateSummary(Product $product, Collection $marketplaceLinks): array
    {
        $summary = [
            'product_name' => $product->name,
            'total_colors' => $marketplaceLinks->count(),
            'colors' => [],
            'total_variants' => 0,
            'estimated_updates' => 0,
            'pricing_strategy' => $this->getPricingStrategy(),
        ];
        
        foreach ($marketplaceLinks as $link) {
            $color = $link->marketplace_data['color_filter'] ?? 'Unknown';
            $colorVariants = $product->variants()->where('color', $color)->get();
            
            $validation = $this->validatePricingData($colorVariants);
            
            $summary['colors'][$color] = [
                'shopify_product_id' => $link->external_product_id,
                'total_variants' => $colorVariants->count(),
                'valid_variants' => $validation['valid_variants']->count(),
                'invalid_variants' => $validation['invalid_variants']->count(),
                'warnings' => $validation['warnings'],
            ];
            
            $summary['total_variants'] += $colorVariants->count();
            $summary['estimated_updates'] += $validation['valid_variants']->count();
        }
        
        return $summary;
    }

    /**
     * 🏷️ Apply pricing rules based on account settings
     */
    public function applyPricingRules(float $basePrice): float
    {
        $rules = $this->syncAccount->metadata['pricing_rules'] ?? [];
        $finalPrice = $basePrice;
        
        foreach ($rules as $rule) {
            $finalPrice = match ($rule['type']) {
                'percentage_markup' => $finalPrice * (1 + ($rule['value'] / 100)),
                'fixed_markup' => $finalPrice + $rule['value'],
                'percentage_discount' => $finalPrice * (1 - ($rule['value'] / 100)),
                'fixed_discount' => $finalPrice - $rule['value'],
                'minimum_price' => max($finalPrice, $rule['value']),
                'maximum_price' => min($finalPrice, $rule['value']),
                'round_to' => round($finalPrice / $rule['value']) * $rule['value'],
                default => $finalPrice,
            };
        }
        
        return max(0.01, $finalPrice); // Ensure minimum price
    }

    /**
     * 📊 Get pricing statistics for the product
     */
    public function getPricingStatistics(Product $product): array
    {
        $variants = $product->variants;
        $prices = $variants->map(fn($v) => $this->calculatePrice($v))->filter();
        
        if ($prices->isEmpty()) {
            return [
                'count' => 0,
                'min' => null,
                'max' => null,
                'average' => null,
            ];
        }
        
        return [
            'count' => $prices->count(),
            'min' => $prices->min(),
            'max' => $prices->max(),
            'average' => $prices->average(),
            'median' => $prices->sort()->values()[$prices->count() / 2] ?? null,
        ];
    }
}