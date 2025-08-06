<?php

namespace App\Services\SmartRecommendations\Analyzers;

use App\Models\ProductVariant;
use App\Models\Marketplace;
use App\Services\SmartRecommendations\Contracts\AnalyzerInterface;
use App\Services\SmartRecommendations\DTOs\Recommendation;
use App\Services\SmartRecommendations\Actions\SuggestMissingAttributesAction;
use Illuminate\Support\Collection;

class MarketplaceReadinessAnalyzer implements AnalyzerInterface
{
    public function analyze(array $variantIds = []): Collection
    {
        $recommendations = collect();
        
        // Get variants to analyze
        $variants = $this->getVariants($variantIds);
        
        if ($variants->isEmpty()) {
            return $recommendations;
        }

        // Check marketplace-specific requirements
        $recommendations = $recommendations->merge($this->analyzeMarketplaceVariants($variants));
        
        // Check marketplace identifiers (ASIN, etc.)
        $recommendations = $recommendations->merge($this->analyzeMarketplaceIdentifiers($variants));
        
        // Check marketplace titles and descriptions
        $recommendations = $recommendations->merge($this->analyzeMarketplaceTitles($variants));

        return $recommendations;
    }

    public function getType(): string
    {
        return 'marketplace_readiness';
    }

    public function getName(): string
    {
        return 'Marketplace Readiness';
    }

    protected function getVariants(array $variantIds): Collection
    {
        $query = ProductVariant::with([
            'marketplaceVariants',
            'marketplaceBarcodes',
            'product',
            'barcodes',
            'pricing'
        ]);
        
        if (!empty($variantIds)) {
            $query->whereIn('id', $variantIds);
        }
        
        return $query->get();
    }

    protected function analyzeMarketplaceVariants(Collection $variants): Collection
    {
        $missingMarketplaceVariants = $variants->filter(fn($variant) => $variant->marketplaceVariants->isEmpty());
        
        if ($missingMarketplaceVariants->isEmpty()) {
            return collect();
        }

        return collect([
            new Recommendation(
                id: 'missing_marketplace_variants_' . now()->timestamp,
                type: $this->getType(),
                priority: 'high',
                title: 'Variants Not Configured for Marketplaces',
                description: "These variants aren't set up for any marketplace channels",
                affectedCount: $missingMarketplaceVariants->count(),
                impactScore: 85, // High impact - no marketplace presence
                effortScore: 20, // Easy to fix with bulk operations
                metadata: [
                    'variant_ids' => $missingMarketplaceVariants->pluck('id')->toArray(),
                    'products_affected' => $missingMarketplaceVariants->pluck('product.name')->unique()->values()->toArray(),
                    'available_marketplaces' => Marketplace::active()->pluck('name')->toArray(),
                ],
                action: new SuggestMissingAttributesAction('marketplace_setup'),
            )
        ]);
    }

    protected function analyzeMarketplaceIdentifiers(Collection $variants): Collection
    {
        $recommendations = collect();
        
        // Check for missing ASINs (Amazon)
        $missingASINs = $variants->filter(function($variant) {
            return $variant->marketplaceBarcodes->where('identifier_type', 'asin')->isEmpty();
        });

        if ($missingASINs->isNotEmpty()) {
            $recommendations->push(new Recommendation(
                id: 'missing_asins_' . now()->timestamp,
                type: $this->getType(),
                priority: 'medium',
                title: 'Missing Amazon ASINs',
                description: "Variants without ASINs can't be properly tracked on Amazon",
                affectedCount: $missingASINs->count(),
                impactScore: 70,
                effortScore: 50, // Requires marketplace research
                metadata: [
                    'variant_ids' => $missingASINs->pluck('id')->toArray(),
                    'identifier_type' => 'asin',
                    'marketplace' => 'Amazon',
                ],
                action: new SuggestMissingAttributesAction('asin'),
            ));
        }

        // Check for duplicate marketplace identifiers
        $duplicateIdentifiers = $variants->flatMap(fn($variant) => $variant->marketplaceBarcodes)
            ->groupBy('identifier_value')
            ->filter(fn($group) => $group->count() > 1);

        if ($duplicateIdentifiers->isNotEmpty()) {
            $recommendations->push(new Recommendation(
                id: 'duplicate_identifiers_' . now()->timestamp,
                type: $this->getType(),
                priority: 'critical',
                title: 'Duplicate Marketplace Identifiers',
                description: "Multiple variants share the same marketplace identifiers",
                affectedCount: $duplicateIdentifiers->sum(fn($group) => $group->count()),
                impactScore: 95, // Critical - causes marketplace conflicts
                effortScore: 40, // Requires manual review and correction
                metadata: [
                    'duplicate_count' => $duplicateIdentifiers->count(),
                    'affected_identifiers' => $duplicateIdentifiers->keys()->take(5)->toArray(),
                ],
                action: new SuggestMissingAttributesAction('resolve_duplicates'),
            ));
        }

        return $recommendations;
    }

    protected function analyzeMarketplaceTitles(Collection $variants): Collection
    {
        $recommendations = collect();
        
        // Find variants with incomplete titles (containing template placeholders)
        $incompleteTitles = $variants->filter(function($variant) {
            return $variant->marketplaceVariants->some(function($marketplaceVariant) {
                return str_contains($marketplaceVariant->title ?? '', '[') || 
                       str_contains($marketplaceVariant->description ?? '', '[');
            });
        });

        if ($incompleteTitles->isNotEmpty()) {
            $recommendations->push(new Recommendation(
                id: 'incomplete_titles_' . now()->timestamp,
                type: $this->getType(),
                priority: 'medium',
                title: 'Incomplete Marketplace Titles',
                description: "Titles contain unfilled template placeholders",
                affectedCount: $incompleteTitles->count(),
                impactScore: 60, // Affects marketplace presentation
                effortScore: 25, // Easy to fix with template system
                metadata: [
                    'variant_ids' => $incompleteTitles->pluck('id')->toArray(),
                    'template_placeholders_found' => true,
                ],
                action: new SuggestMissingAttributesAction('complete_titles'),
            ));
        }

        // Find variants without any marketplace titles
        $missingTitles = $variants->filter(function($variant) {
            return $variant->marketplaceVariants->every(function($marketplaceVariant) {
                return empty(trim($marketplaceVariant->title ?? ''));
            });
        });

        if ($missingTitles->isNotEmpty()) {
            $recommendations->push(new Recommendation(
                id: 'missing_titles_' . now()->timestamp,
                type: $this->getType(),
                priority: 'high',
                title: 'Missing Marketplace Titles',
                description: "Variants don't have titles for marketplace listings",
                affectedCount: $missingTitles->count(),
                impactScore: 85, // High impact - no searchable titles
                effortScore: 30, // Use template generation
                metadata: [
                    'variant_ids' => $missingTitles->pluck('id')->toArray(),
                ],
                action: new SuggestMissingAttributesAction('generate_titles'),
            ));
        }

        return $recommendations;
    }
}