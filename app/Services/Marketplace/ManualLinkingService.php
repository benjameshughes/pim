<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceLink;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SyncAccount;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Log;

/**
 * 🔗 MANUAL LINKING SERVICE
 *
 * Handles business logic for manual marketplace linking operations.
 * Provides SKU matching suggestions and linking functionality.
 */
class ManualLinkingService
{
    /**
     * Get marketplace items for manual linking interface
     *
     * @return EloquentCollection<int, MarketplaceLink>
     */
    public function getMarketplaceItems(SyncAccount $syncAccount, string $searchQuery = ''): EloquentCollection
    {
        $query = MarketplaceLink::where('sync_account_id', $syncAccount->id)
            ->where('link_status', 'pending')
            ->with(['linkable'])
            ->latest('updated_at');

        if (! empty($searchQuery)) {
            $query->where(function ($q) use ($searchQuery) {
                $q->where('external_sku', 'like', "%{$searchQuery}%")
                    ->orWhereJsonContains('marketplace_data->title', $searchQuery);
            });
        }

        return $query->take(50)->get();
    }

    /**
     * Get internal products for linking
     *
     * @return EloquentCollection<int, Product>
     */
    public function getInternalProducts(string $searchQuery = ''): EloquentCollection
    {
        $query = Product::with(['variants'])
            ->latest('updated_at');

        if (! empty($searchQuery)) {
            $query->where(function ($q) use ($searchQuery) {
                $q->where('name', 'like', "%{$searchQuery}%")
                    ->orWhere('parent_sku', 'like', "%{$searchQuery}%");
            });
        }

        return $query->take(50)->get();
    }

    /**
     * Get internal variants for linking
     *
     * @return EloquentCollection<int, ProductVariant>
     */
    public function getInternalVariants(string $searchQuery = ''): EloquentCollection
    {
        $query = ProductVariant::with(['product'])
            ->latest('updated_at');

        if (! empty($searchQuery)) {
            $query->where(function ($q) use ($searchQuery) {
                $q->where('sku', 'like', "%{$searchQuery}%")
                    ->orWhere('color', 'like', "%{$searchQuery}%")
                    ->orWhere('size', 'like', "%{$searchQuery}%");
            });
        }

        return $query->take(50)->get();
    }

    /**
     * Generate smart linking suggestions based on SKU similarity
     *
     * @return array<int, array<string, mixed>>
     */
    public function generateLinkingSuggestions(SyncAccount $syncAccount): array
    {
        $marketplaceItems = $this->getMarketplaceItems($syncAccount);
        $internalProducts = Product::all(['id', 'name', 'parent_sku']);
        $internalVariants = ProductVariant::with(['product:id,name'])
            ->get(['id', 'sku', 'product_id', 'color', 'size']);

        Log::debug('Generating linking suggestions', [
            'sync_account_id' => $syncAccount->id,
            'marketplace_items_count' => $marketplaceItems->count(),
            'internal_products_count' => $internalProducts->count(),
            'internal_variants_count' => $internalVariants->count(),
        ]);

        // Debug: Check a few marketplace items for null IDs
        $nullIdCount = 0;
        foreach ($marketplaceItems->take(5) as $item) {
            if (! $item->id) {
                $nullIdCount++;
                Log::warning('Found marketplace item with null ID', [
                    'external_sku' => $item->external_sku,
                    'sync_account_id' => $item->sync_account_id,
                    'link_status' => $item->link_status,
                ]);
            }
        }

        if ($nullIdCount > 0) {
            Log::error('Found marketplace items with null IDs', [
                'null_count_in_sample' => $nullIdCount,
                'total_items' => $marketplaceItems->count(),
            ]);
        }

        $suggestions = [];

        foreach ($marketplaceItems as $marketplaceItem) {
            $suggestion = $this->findBestMatch($marketplaceItem, $internalProducts, $internalVariants);

            if ($suggestion) {
                $suggestions[] = $suggestion;
            }
        }

        // Sort by confidence score (highest first)
        usort($suggestions, fn ($a, $b) => $b['confidence'] <=> $a['confidence']);

        return $suggestions;
    }

    /**
     * Find the best match for a marketplace item
     */
    /**
     * @param  EloquentCollection<int, Product>  $internalProducts
     * @param  EloquentCollection<int, ProductVariant>  $internalVariants
     * @return array<string, mixed>|null
     */
    protected function findBestMatch(
        MarketplaceLink $marketplaceItem,
        EloquentCollection $internalProducts,
        EloquentCollection $internalVariants
    ): ?array {
        // Validate marketplace item has required data
        if (! $marketplaceItem->id || ! $marketplaceItem->external_sku) {
            Log::warning('Skipping marketplace item with missing required data', [
                'marketplace_item_id' => $marketplaceItem->id,
                'external_sku' => $marketplaceItem->external_sku,
                'sync_account_id' => $marketplaceItem->sync_account_id,
            ]);

            return null;
        }

        $bestMatch = null;
        $highestConfidence = 0;

        // Try exact SKU matches first (variants)
        foreach ($internalVariants as $variant) {
            $confidence = $this->calculateSkuSimilarity($marketplaceItem->external_sku, $variant->sku);

            if ($confidence > $highestConfidence && $confidence >= 80) {
                $bestMatch = [
                    'marketplace_link_id' => $marketplaceItem->id,
                    'marketplace_sku' => $marketplaceItem->external_sku,
                    'marketplace_title' => $marketplaceItem->marketplace_data['title'] ?? 'Unknown',
                    'internal_type' => 'variant',
                    'internal_id' => $variant->id,
                    'internal_sku' => $variant->sku,
                    'internal_name' => $variant->product->name ?? 'Unknown Product',
                    'confidence' => $confidence,
                    'match_reason' => $confidence >= 95 ? 'Exact SKU match' : 'High SKU similarity',
                ];
                $highestConfidence = $confidence;
            }
        }

        // Try product SKU matches if no high-confidence variant match
        if ($highestConfidence < 90) {
            foreach ($internalProducts as $product) {
                if (! $product->parent_sku) {
                    continue;
                }

                $confidence = $this->calculateSkuSimilarity($marketplaceItem->external_sku, $product->parent_sku);

                if ($confidence > $highestConfidence && $confidence >= 70) {
                    $bestMatch = [
                        'marketplace_link_id' => $marketplaceItem->id,
                        'marketplace_sku' => $marketplaceItem->external_sku,
                        'marketplace_title' => $marketplaceItem->marketplace_data['title'] ?? 'Unknown',
                        'internal_type' => 'product',
                        'internal_id' => $product->id,
                        'internal_sku' => $product->parent_sku,
                        'internal_name' => $product->name,
                        'confidence' => $confidence,
                        'match_reason' => 'Product SKU similarity',
                    ];
                    $highestConfidence = $confidence;
                }
            }
        }

        return $bestMatch;
    }

    /**
     * Calculate SKU similarity percentage
     */
    protected function calculateSkuSimilarity(string $sku1, string $sku2): int
    {
        if (empty($sku1) || empty($sku2)) {
            return 0;
        }

        // Normalize SKUs (uppercase, remove special chars)
        $normalizedSku1 = preg_replace('/[^A-Z0-9]/', '', strtoupper($sku1));
        $normalizedSku2 = preg_replace('/[^A-Z0-9]/', '', strtoupper($sku2));

        // Handle preg_replace returning null
        if ($normalizedSku1 === null || $normalizedSku2 === null) {
            return 0;
        }

        // Exact match
        if ($normalizedSku1 === $normalizedSku2) {
            return 100;
        }

        // Check if one contains the other
        if (str_contains($normalizedSku1, $normalizedSku2) || str_contains($normalizedSku2, $normalizedSku1)) {
            return 90;
        }

        // Calculate similarity using similar_text
        $similarity = 0;
        similar_text($normalizedSku1, $normalizedSku2, $similarity);

        return (int) round($similarity);
    }

    /**
     * Link marketplace item to internal item
     */
    public function linkItems(int $marketplaceLinkId, string $internalItemType, int $internalItemId): void
    {
        $marketplaceLink = MarketplaceLink::findOrFail($marketplaceLinkId);

        if ($internalItemType === 'product') {
            $internalItem = Product::findOrFail($internalItemId);
            $internalSku = $internalItem->parent_sku ?? 'NO-SKU';
        } else {
            $internalItem = ProductVariant::findOrFail($internalItemId);
            $internalSku = $internalItem->sku;
        }

        $marketplaceLink->update([
            'linkable_type' => get_class($internalItem),
            'linkable_id' => $internalItem->id,
            'internal_sku' => $internalSku,
            'link_status' => 'linked',
            'linked_at' => now(),
            'linked_by' => auth()->user()->name ?? 'System',
        ]);

        Log::info('Marketplace item linked successfully', [
            'marketplace_link_id' => $marketplaceLinkId,
            'external_sku' => $marketplaceLink->external_sku,
            'internal_type' => $internalItemType,
            'internal_id' => $internalItemId,
            'internal_sku' => $internalSku,
        ]);
    }

    /**
     * Unlink marketplace item
     */
    public function unlinkItem(int $marketplaceLinkId): void
    {
        $marketplaceLink = MarketplaceLink::findOrFail($marketplaceLinkId);

        $marketplaceLink->update([
            'linkable_type' => null,
            'linkable_id' => null,
            'internal_sku' => null,
            'link_status' => 'pending',
            'linked_at' => null,
            'linked_by' => null,
        ]);

        Log::info('Marketplace item unlinked successfully', [
            'marketplace_link_id' => $marketplaceLinkId,
            'external_sku' => $marketplaceLink->external_sku,
        ]);
    }

    /**
     * Apply a linking suggestion
     *
     * @param  array<string, mixed>  $suggestion
     */
    public function applySuggestion(array $suggestion): void
    {
        // Validate required fields before proceeding
        if (empty($suggestion['marketplace_link_id'])) {
            Log::error('Cannot apply suggestion: marketplace_link_id is null or empty', [
                'suggestion' => $suggestion,
            ]);
            throw new \InvalidArgumentException('Invalid suggestion: marketplace_link_id is required but was null or empty');
        }

        if (empty($suggestion['internal_type']) || empty($suggestion['internal_id'])) {
            Log::error('Cannot apply suggestion: missing internal item information', [
                'suggestion' => $suggestion,
            ]);
            throw new \InvalidArgumentException('Invalid suggestion: internal_type and internal_id are required');
        }

        $this->linkItems(
            $suggestion['marketplace_link_id'],
            $suggestion['internal_type'],
            $suggestion['internal_id']
        );

        Log::info('Applied linking suggestion', [
            'marketplace_link_id' => $suggestion['marketplace_link_id'],
            'confidence' => $suggestion['confidence'],
            'match_reason' => $suggestion['match_reason'],
        ]);
    }

    /**
     * Apply multiple suggestions in bulk
     *
     * @param  array<int, array<string, mixed>>  $suggestions
     * @return array<string, int>
     */
    public function bulkApplySuggestions(array $suggestions, int $minConfidence = 95): array
    {
        $applied = 0;
        $skipped = 0;

        foreach ($suggestions as $suggestion) {
            if ($suggestion['confidence'] >= $minConfidence) {
                try {
                    $this->applySuggestion($suggestion);
                    $applied++;
                } catch (\Exception $e) {
                    Log::error('Failed to apply suggestion', [
                        'suggestion' => $suggestion,
                        'error' => $e->getMessage(),
                    ]);
                    $skipped++;
                }
            } else {
                $skipped++;
            }
        }

        Log::info('Bulk suggestions applied', [
            'applied' => $applied,
            'skipped' => $skipped,
            'min_confidence' => $minConfidence,
        ]);

        return [
            'applied' => $applied,
            'skipped' => $skipped,
            'total_suggestions' => count($suggestions),
        ];
    }

    /**
     * Quick link variant by SKU match
     */
    public function quickLinkVariant(int $marketplaceLinkId): bool
    {
        $marketplaceLink = MarketplaceLink::findOrFail($marketplaceLinkId);

        // Try to find exact SKU match
        $variant = ProductVariant::where('sku', $marketplaceLink->external_sku)->first();

        if ($variant) {
            $this->linkItems($marketplaceLinkId, 'variant', $variant->id);

            return true;
        }

        return false;
    }
}
