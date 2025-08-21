<?php

namespace App\Services\SkuLinking;

use App\Models\MarketplaceLink;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SyncAccount;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 🏗️ MARKETPLACE HIERARCHY SERVICE
 *
 * Manages complex hierarchical relationships between products, variants, and their
 * marketplace equivalents. Ensures data integrity, consistency, and proper parent-child
 * relationships across all marketplace integrations.
 */
class MarketplaceHierarchyService
{
    /**
     * 🔄 SYNC PRODUCT HIERARCHY TO MARKETPLACE
     *
     * Create complete hierarchical structure for a product and all its variants
     */
    public function syncProductHierarchyToMarketplace(
        Product $product,
        SyncAccount $syncAccount,
        array $marketplaceProductData,
        array $marketplaceVariants = []
    ): array {
        return DB::transaction(function () use ($product, $syncAccount, $marketplaceProductData, $marketplaceVariants) {
            Log::info('🏗️ Syncing product hierarchy to marketplace', [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'marketplace' => $syncAccount->channel,
                'variants_count' => count($marketplaceVariants),
            ]);

            // 1. Create or update product-level link
            $productLink = $this->createOrUpdateProductLink($product, $syncAccount, $marketplaceProductData);

            // 2. Create or update variant-level links
            $variantLinks = [];
            foreach ($marketplaceVariants as $variantData) {
                $variant = $this->findVariantBySku($product, $variantData['internal_sku']);
                if ($variant) {
                    $variantLinks[] = $this->createOrUpdateVariantLink(
                        $variant,
                        $syncAccount,
                        $variantData,
                        $productLink
                    );
                }
            }

            // 3. Handle orphaned variant links (variants that no longer exist in marketplace)
            $this->handleOrphanedVariantLinks($productLink, collect($variantLinks));

            return [
                'product_link' => $productLink,
                'variant_links' => $variantLinks,
                'synced_variants' => count($variantLinks),
                'status' => 'success',
            ];
        });
    }

    /**
     * 📦 CREATE OR UPDATE PRODUCT LINK
     */
    private function createOrUpdateProductLink(
        Product $product,
        SyncAccount $syncAccount,
        array $marketplaceData
    ): MarketplaceLink {
        $link = MarketplaceLink::where('linkable_type', Product::class)
            ->where('linkable_id', $product->id)
            ->where('sync_account_id', $syncAccount->id)
            ->first();

        if (! $link) {
            $link = MarketplaceLink::findOrCreateFor($product, $syncAccount);
        }

        $link->update([
            'internal_sku' => $product->parent_sku ?? 'NO-SKU',
            'external_sku' => $marketplaceData['sku'] ?? $product->parent_sku,
            'external_product_id' => $marketplaceData['product_id'],
            'link_status' => 'linked',
            'linked_at' => now(),
            'marketplace_data' => $marketplaceData,
        ]);

        return $link;
    }

    /**
     * 🎨 CREATE OR UPDATE VARIANT LINK
     */
    private function createOrUpdateVariantLink(
        ProductVariant $variant,
        SyncAccount $syncAccount,
        array $variantData,
        MarketplaceLink $parentLink
    ): MarketplaceLink {
        $link = MarketplaceLink::where('linkable_type', ProductVariant::class)
            ->where('linkable_id', $variant->id)
            ->where('sync_account_id', $syncAccount->id)
            ->first();

        if (! $link) {
            $link = MarketplaceLink::findOrCreateFor($variant, $syncAccount, $parentLink);
        } else {
            // Ensure parent link is set correctly
            $link->update(['parent_link_id' => $parentLink->id]);
        }

        $link->update([
            'internal_sku' => $variant->sku,
            'external_sku' => $variantData['sku'],
            'external_product_id' => $variantData['product_id'] ?? $parentLink->external_product_id,
            'external_variant_id' => $variantData['variant_id'],
            'link_status' => 'linked',
            'linked_at' => now(),
            'marketplace_data' => $variantData,
        ]);

        return $link;
    }

    /**
     * 🔍 FIND VARIANT BY SKU
     */
    private function findVariantBySku(Product $product, string $sku): ?ProductVariant
    {
        return $product->variants()->where('sku', $sku)->first();
    }

    /**
     * 🧹 HANDLE ORPHANED VARIANT LINKS
     *
     * Mark variant links as unlinked if they no longer exist in marketplace
     */
    private function handleOrphanedVariantLinks(MarketplaceLink $productLink, SupportCollection $currentVariantLinks): void
    {
        $currentVariantIds = $currentVariantLinks->pluck('linkable_id');

        $orphanedLinks = MarketplaceLink::where('parent_link_id', $productLink->id)
            ->whereNotIn('linkable_id', $currentVariantIds)
            ->get();

        foreach ($orphanedLinks as $orphanedLink) {
            Log::warning('🧹 Orphaned variant link found', [
                'variant_id' => $orphanedLink->linkable_id,
                'external_sku' => $orphanedLink->external_sku,
                'marketplace' => $orphanedLink->syncAccount->channel,
            ]);

            $orphanedLink->update([
                'link_status' => 'unlinked',
                'linked_at' => null,
            ]);
        }
    }

    /**
     * 🔄 REBUILD HIERARCHY
     *
     * Rebuild hierarchy for all marketplace links of a specific marketplace
     */
    public function rebuildHierarchyForMarketplace(SyncAccount $syncAccount): array
    {
        Log::info('🔄 Rebuilding hierarchy for marketplace', [
            'marketplace' => $syncAccount->channel,
            'account_id' => $syncAccount->id,
        ]);

        $results = [
            'product_links_processed' => 0,
            'variant_links_fixed' => 0,
            'orphaned_links_found' => 0,
            'errors' => [],
        ];

        // Get all product-level links for this marketplace
        $productLinks = MarketplaceLink::where('sync_account_id', $syncAccount->id)
            ->where('link_level', 'product')
            ->with(['linkable', 'childLinks'])
            ->get();

        foreach ($productLinks as $productLink) {
            try {
                $this->rebuildProductHierarchy($productLink);
                $results['product_links_processed']++;
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'product_link_id' => $productLink->id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Fix orphaned variant links (variant links without proper parent)
        $orphanedVariants = MarketplaceLink::where('sync_account_id', $syncAccount->id)
            ->where('link_level', 'variant')
            ->whereNull('parent_link_id')
            ->with('linkable.product')
            ->get();

        foreach ($orphanedVariants as $orphanedVariant) {
            $parentProduct = $orphanedVariant->linkable->product ?? null;
            if ($parentProduct) {
                $parentLink = MarketplaceLink::where('linkable_type', Product::class)
                    ->where('linkable_id', $parentProduct->id)
                    ->where('sync_account_id', $syncAccount->id)
                    ->first();

                if ($parentLink) {
                    $orphanedVariant->update(['parent_link_id' => $parentLink->id]);
                    $results['variant_links_fixed']++;
                } else {
                    $results['orphaned_links_found']++;
                }
            }
        }

        return $results;
    }

    /**
     * 🏗️ REBUILD PRODUCT HIERARCHY
     */
    private function rebuildProductHierarchy(MarketplaceLink $productLink): void
    {
        if ($productLink->linkable_type !== Product::class) {
            return;
        }

        $product = $productLink->linkable;
        if (! $product) {
            return;
        }

        // Ensure all variant links for this product have correct parent reference
        $variantLinks = MarketplaceLink::where('linkable_type', ProductVariant::class)
            ->where('sync_account_id', $productLink->sync_account_id)
            ->whereHas('linkable', function ($q) use ($product) {
                $q->where('product_id', $product->id);
            })
            ->get();

        foreach ($variantLinks as $variantLink) {
            if ($variantLink->parent_link_id !== $productLink->id) {
                $variantLink->update(['parent_link_id' => $productLink->id]);
            }
        }
    }

    /**
     * 📊 GET HIERARCHY STATISTICS
     *
     * Get comprehensive statistics about marketplace hierarchy health
     */
    public function getHierarchyStatistics(?SyncAccount $syncAccount = null): array
    {
        $query = MarketplaceLink::query();

        if ($syncAccount) {
            $query->where('sync_account_id', $syncAccount->id);
        }

        $stats = [
            'total_links' => $query->count(),
            'product_links' => $query->clone()->where('link_level', 'product')->count(),
            'variant_links' => $query->clone()->where('link_level', 'variant')->count(),
            'hierarchical_links' => $query->clone()->whereNotNull('parent_link_id')->count(),
            'orphaned_variants' => $query->clone()
                ->where('link_level', 'variant')
                ->whereNull('parent_link_id')
                ->count(),
            'linked_status' => $query->clone()->where('link_status', 'linked')->count(),
            'pending_status' => $query->clone()->where('link_status', 'pending')->count(),
            'failed_status' => $query->clone()->where('link_status', 'failed')->count(),
            'unlinked_status' => $query->clone()->where('link_status', 'unlinked')->count(),
        ];

        // Calculate hierarchy completion percentage
        $stats['hierarchy_completion'] = $stats['variant_links'] > 0
            ? round(($stats['hierarchical_links'] / $stats['variant_links']) * 100, 2)
            : 100;

        // Get per-marketplace breakdown if not filtered by marketplace
        if (! $syncAccount) {
            $stats['by_marketplace'] = $this->getStatsByMarketplace();
        }

        return $stats;
    }

    /**
     * 📊 GET STATS BY MARKETPLACE
     */
    private function getStatsByMarketplace(): array
    {
        return MarketplaceLink::query()
            ->join('sync_accounts', 'marketplace_links.sync_account_id', '=', 'sync_accounts.id')
            ->groupBy('sync_accounts.channel')
            ->selectRaw('
                sync_accounts.channel,
                count(*) as total,
                count(case when link_level = "product" then 1 end) as product_links,
                count(case when link_level = "variant" then 1 end) as variant_links,
                count(case when parent_link_id is not null then 1 end) as hierarchical_links
            ')
            ->get()
            ->keyBy('channel')
            ->toArray();
    }

    /**
     * 🔍 VALIDATE HIERARCHY INTEGRITY
     *
     * Check for inconsistencies in marketplace link hierarchy
     */
    public function validateHierarchyIntegrity(?SyncAccount $syncAccount = null): array
    {
        $issues = [];
        $query = MarketplaceLink::query();

        if ($syncAccount) {
            $query->where('sync_account_id', $syncAccount->id);
        }

        // 1. Check for variant links without parent
        $orphanedVariants = $query->clone()
            ->where('link_level', 'variant')
            ->whereNull('parent_link_id')
            ->with(['linkable.product', 'syncAccount'])
            ->get();

        foreach ($orphanedVariants as $orphaned) {
            $issues[] = [
                'type' => 'orphaned_variant',
                'link_id' => $orphaned->id,
                'description' => "Variant link {$orphaned->id} has no parent product link",
                'marketplace' => $orphaned->syncAccount->channel,
                'variant_sku' => $orphaned->internal_sku,
            ];
        }

        // 2. Check for variant links with invalid parent
        $invalidParents = $query->clone()
            ->where('link_level', 'variant')
            ->whereNotNull('parent_link_id')
            ->whereDoesntHave('parentLink')
            ->with(['linkable.product', 'syncAccount'])
            ->get();

        foreach ($invalidParents as $invalid) {
            $issues[] = [
                'type' => 'invalid_parent',
                'link_id' => $invalid->id,
                'description' => "Variant link {$invalid->id} references non-existent parent {$invalid->parent_link_id}",
                'marketplace' => $invalid->syncAccount->channel,
                'variant_sku' => $invalid->internal_sku,
            ];
        }

        // 3. Check for product links with variants in different marketplace
        $crossMarketplaceIssues = $query->clone()
            ->where('link_level', 'product')
            ->with(['childLinks', 'syncAccount'])
            ->get()
            ->filter(function ($productLink) {
                return $productLink->childLinks->some(function ($child) use ($productLink) {
                    return $child->sync_account_id !== $productLink->sync_account_id;
                });
            });

        foreach ($crossMarketplaceIssues as $issue) {
            $issues[] = [
                'type' => 'cross_marketplace_variants',
                'link_id' => $issue->id,
                'description' => "Product link {$issue->id} has variant links in different marketplaces",
                'marketplace' => $issue->syncAccount->channel,
                'product_sku' => $issue->internal_sku,
            ];
        }

        return [
            'total_issues' => count($issues),
            'issues' => $issues,
            'status' => count($issues) === 0 ? 'healthy' : 'issues_found',
        ];
    }

    /**
     * 🔧 FIX HIERARCHY ISSUES
     *
     * Automatically fix common hierarchy issues
     */
    public function fixHierarchyIssues(array $issueIds = []): array
    {
        $fixed = [];
        $failed = [];

        $validation = $this->validateHierarchyIntegrity();
        $issuesToFix = empty($issueIds)
            ? $validation['issues']
            : collect($validation['issues'])->whereIn('link_id', $issueIds)->toArray();

        foreach ($issuesToFix as $issue) {
            try {
                $result = $this->fixSingleIssue($issue);
                if ($result) {
                    $fixed[] = $issue;
                } else {
                    $failed[] = $issue;
                }
            } catch (\Exception $e) {
                $failed[] = array_merge($issue, ['error' => $e->getMessage()]);
            }
        }

        return [
            'fixed_count' => count($fixed),
            'failed_count' => count($failed),
            'fixed_issues' => $fixed,
            'failed_issues' => $failed,
        ];
    }

    /**
     * 🔧 FIX SINGLE ISSUE
     */
    private function fixSingleIssue(array $issue): bool
    {
        $link = MarketplaceLink::find($issue['link_id']);
        if (! $link) {
            return false;
        }

        switch ($issue['type']) {
            case 'orphaned_variant':
                return $this->fixOrphanedVariant($link);

            case 'invalid_parent':
                return $this->fixInvalidParent($link);

            case 'cross_marketplace_variants':
                return $this->fixCrossMarketplaceVariants($link);

            default:
                return false;
        }
    }

    /**
     * 🔧 FIX ORPHANED VARIANT
     */
    private function fixOrphanedVariant(MarketplaceLink $variantLink): bool
    {
        if ($variantLink->linkable_type !== ProductVariant::class) {
            return false;
        }

        $variant = $variantLink->linkable;
        if (! $variant || ! $variant->product) {
            return false;
        }

        // Find or create parent product link
        $parentLink = MarketplaceLink::where('linkable_type', Product::class)
            ->where('linkable_id', $variant->product->id)
            ->where('sync_account_id', $variantLink->sync_account_id)
            ->first();

        if (! $parentLink) {
            // Create parent product link
            $parentLink = MarketplaceLink::findOrCreateFor($variant->product, $variantLink->syncAccount);
            $parentLink->update([
                'internal_sku' => $variant->product->parent_sku ?? 'NO-SKU',
                'external_sku' => $variant->product->parent_sku ?? 'NO-SKU',
                'link_status' => 'pending', // Will need manual linking
            ]);
        }

        $variantLink->update(['parent_link_id' => $parentLink->id]);

        return true;
    }

    /**
     * 🔧 FIX INVALID PARENT
     */
    private function fixInvalidParent(MarketplaceLink $variantLink): bool
    {
        // Clear the invalid parent reference
        $variantLink->update(['parent_link_id' => null]);

        // Then try to fix as orphaned variant
        return $this->fixOrphanedVariant($variantLink);
    }

    /**
     * 🔧 FIX CROSS MARKETPLACE VARIANTS
     */
    private function fixCrossMarketplaceVariants(MarketplaceLink $productLink): bool
    {
        // This is a complex issue - for now, just log it for manual review
        Log::warning('Cross-marketplace variants detected - manual review required', [
            'product_link_id' => $productLink->id,
            'product_sku' => $productLink->internal_sku,
            'marketplace' => $productLink->syncAccount->channel,
        ]);

        return false; // Requires manual intervention
    }
}
