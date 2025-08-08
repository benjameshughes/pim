<?php

namespace App\Services\SmartRecommendations\Analyzers;

use App\Models\ProductVariant;
use App\Services\SmartRecommendations\Actions\AssignMissingBarcodesAction;
use App\Services\SmartRecommendations\Actions\SuggestMissingAttributesAction;
use App\Services\SmartRecommendations\Contracts\AnalyzerInterface;
use App\Services\SmartRecommendations\DTOs\Recommendation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DataCompletenessAnalyzer implements AnalyzerInterface
{
    public function analyze(array $variantIds = []): Collection
    {
        $recommendations = collect();

        // Get variants to analyze
        $variants = $this->getVariants($variantIds);

        if ($variants->isEmpty()) {
            return $recommendations;
        }

        // Check for missing barcodes
        $recommendations = $recommendations->merge($this->analyzeMissingBarcodes($variants));

        // Check for missing pricing
        $recommendations = $recommendations->merge($this->analyzeMissingPricing($variants));

        // Check for missing images
        $recommendations = $recommendations->merge($this->analyzeMissingImages($variants));

        // Check for missing key attributes
        $recommendations = $recommendations->merge($this->analyzeMissingAttributes($variants));

        return $recommendations;
    }

    public function getType(): string
    {
        return 'data_completeness';
    }

    public function getName(): string
    {
        return 'Data Completeness';
    }

    protected function getVariants(array $variantIds): Collection
    {
        $query = ProductVariant::with(['barcodes', 'pricing', 'variantImages', 'attributes', 'product']);

        if (! empty($variantIds)) {
            $query->whereIn('id', $variantIds);
        }

        return $query->get();
    }

    protected function analyzeMissingBarcodes(Collection $variants): Collection
    {
        $missingBarcodes = $variants->filter(fn ($variant) => $variant->barcodes->isEmpty());

        if ($missingBarcodes->isEmpty()) {
            return collect();
        }

        return collect([
            new Recommendation(
                id: 'missing_barcodes_'.now()->timestamp,
                type: $this->getType(),
                priority: 'critical',
                title: 'Missing Barcodes Detected',
                description: "Variants without barcodes can't be synced to marketplaces",
                affectedCount: $missingBarcodes->count(),
                impactScore: 95, // Very high impact - blocks marketplace sync
                effortScore: 10, // Very easy - auto-assign from pool
                metadata: [
                    'variant_ids' => $missingBarcodes->pluck('id')->toArray(),
                    'products_affected' => $missingBarcodes->pluck('product.name')->unique()->values()->toArray(),
                ],
                action: new AssignMissingBarcodesAction,
            ),
        ]);
    }

    protected function analyzeMissingPricing(Collection $variants): Collection
    {
        $missingPricing = $variants->filter(fn ($variant) => $variant->pricing->isEmpty());

        if ($missingPricing->isEmpty()) {
            return collect();
        }

        return collect([
            new Recommendation(
                id: 'missing_pricing_'.now()->timestamp,
                type: $this->getType(),
                priority: 'high',
                title: 'Missing Pricing Data',
                description: "Variants without pricing can't be listed on marketplaces",
                affectedCount: $missingPricing->count(),
                impactScore: 90,
                effortScore: 60, // Requires manual pricing decisions
                metadata: [
                    'variant_ids' => $missingPricing->pluck('id')->toArray(),
                    'products_affected' => $missingPricing->pluck('product.name')->unique()->values()->toArray(),
                ],
                action: new SuggestMissingAttributesAction('pricing'),
            ),
        ]);
    }

    protected function analyzeMissingImages(Collection $variants): Collection
    {
        $missingImages = $variants->filter(function ($variant) {
            // Check variant images (both database and media library)
            $hasVariantImages = $variant->variantImages->isNotEmpty() || $variant->getMedia('images')->isNotEmpty();

            // Check product images (stored as JSON array in database)
            $hasProductImages = ! empty($variant->product->images);

            return ! $hasVariantImages && ! $hasProductImages;
        });

        if ($missingImages->isEmpty()) {
            return collect();
        }

        return collect([
            new Recommendation(
                id: 'missing_images_'.now()->timestamp,
                type: $this->getType(),
                priority: 'high',
                title: 'Missing Product Images',
                description: 'Products without images have poor conversion rates',
                affectedCount: $missingImages->count(),
                impactScore: 80,
                effortScore: 70, // Requires image sourcing/photography
                metadata: [
                    'variant_ids' => $missingImages->pluck('id')->toArray(),
                    'products_affected' => $missingImages->pluck('product.name')->unique()->values()->toArray(),
                ],
                action: new SuggestMissingAttributesAction('images'),
            ),
        ]);
    }

    protected function analyzeMissingAttributes(Collection $variants): Collection
    {
        $recommendations = collect();

        // Find common attributes across the dataset
        $allAttributes = $variants->flatMap(fn ($variant) => $variant->attributes->pluck('attribute_key'))
            ->concat($variants->flatMap(fn ($variant) => $variant->product->attributes->pluck('attribute_key')))
            ->countBy()
            ->sortDesc();

        // Find attributes that are common (>50% usage) but missing from some variants
        foreach ($allAttributes as $attributeKey => $count) {
            $usage_percentage = ($count / $variants->count()) * 100;

            if ($usage_percentage >= 50 && $usage_percentage < 100) {
                $missing = $variants->filter(function ($variant) use ($attributeKey) {
                    return ! $variant->attributes->pluck('attribute_key')->contains($attributeKey) &&
                           ! $variant->product->attributes->pluck('attribute_key')->contains($attributeKey);
                });

                if ($missing->isNotEmpty()) {
                    $recommendations->push(new Recommendation(
                        id: 'missing_attribute_'.Str::slug($attributeKey).'_'.now()->timestamp,
                        type: $this->getType(),
                        priority: 'medium',
                        title: "Missing '{$attributeKey}' Attribute",
                        description: "This attribute is used by {$usage_percentage}% of your products",
                        affectedCount: $missing->count(),
                        impactScore: min(70, $usage_percentage), // Higher usage = higher impact
                        effortScore: 30, // Medium effort to add attributes
                        metadata: [
                            'attribute_key' => $attributeKey,
                            'usage_percentage' => round($usage_percentage, 1),
                            'variant_ids' => $missing->pluck('id')->toArray(),
                        ],
                        action: new SuggestMissingAttributesAction($attributeKey),
                    ));
                }
            }
        }

        return $recommendations;
    }
}
