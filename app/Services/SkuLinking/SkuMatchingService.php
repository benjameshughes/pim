<?php

namespace App\Services\SkuLinking;

use App\Models\MarketplaceLink;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SkuLink;
use App\Models\SyncAccount;
use Illuminate\Database\Eloquent\Model;

/**
 * 🔍 ENHANCED SKU MATCHING SERVICE
 *
 * Enhanced service for hierarchical SKU linking supporting both product-level
 * and variant-level marketplace connections with proper parent-child relationships.
 */
class SkuMatchingService
{
    /**
     * 🔗 CREATE SKU LINK
     *
     * Create a manual SKU link between internal and external SKUs
     */
    public function createSkuLink(Product $product, SyncAccount $syncAccount, string $internalSku, string $externalSku): SkuLink
    {
        return SkuLink::create([
            'product_id' => $product->id,
            'sync_account_id' => $syncAccount->id,
            'internal_sku' => $internalSku,
            'external_sku' => $externalSku,
            'link_status' => 'pending',
        ]);
    }

    /**
     * 📋 GET ALL LINKS FOR PRODUCT
     *
     * Get all marketplace links for a given product
     */
    public function getLinksForProduct(Product $product): \Illuminate\Database\Eloquent\Collection
    {
        return SkuLink::where('product_id', $product->id)
            ->with(['syncAccount:id,channel,display_name'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * 🏢 GET LINKS BY MARKETPLACE
     *
     * Get all links for a specific marketplace
     */
    public function getLinksByMarketplace(string $marketplace): \Illuminate\Database\Eloquent\Collection
    {
        return SkuLink::whereHas('syncAccount', fn ($q) => $q->where('channel', $marketplace))
            ->with(['product:id,name,parent_sku', 'syncAccount:id,channel,display_name'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * ✅ LINK PRODUCT
     *
     * Mark a SKU link as active/linked
     */
    public function linkProduct(SkuLink $skuLink, ?string $externalProductId = null): void
    {
        $skuLink->markAsLinked($externalProductId);
    }

    /**
     * ❌ UNLINK PRODUCT
     *
     * Mark a SKU link as unlinked
     */
    public function unlinkProduct(SkuLink $skuLink): void
    {
        $skuLink->unlink();
    }

    /**
     * 📊 GET BASIC STATISTICS
     *
     * Get simple statistics for reporting
     */
    public function getStatistics(): array
    {
        return [
            'total_links' => SkuLink::count(),
            'linked' => SkuLink::linked()->count(),
            'pending' => SkuLink::pending()->count(),
            'failed' => SkuLink::failed()->count(),
            'by_marketplace' => $this->getStatsByMarketplace(),
        ];
    }

    /**
     * 📊 GET STATS BY MARKETPLACE
     */
    private function getStatsByMarketplace(): array
    {
        return SkuLink::query()
            ->join('sync_accounts', 'sku_links.sync_account_id', '=', 'sync_accounts.id')
            ->groupBy('sync_accounts.channel')
            ->selectRaw('sync_accounts.channel, count(*) as total')
            ->get()
            ->keyBy('channel')
            ->toArray();
    }

    /**
     * 🔍 FIND EXISTING LINK
     *
     * Check if a link already exists for product + marketplace
     */
    public function findExistingLink(Product $product, SyncAccount $syncAccount): ?SkuLink
    {
        return SkuLink::where('product_id', $product->id)
            ->where('sync_account_id', $syncAccount->id)
            ->first();
    }

    /**
     * 🗑️ DELETE LINK
     *
     * Permanently delete a SKU link
     */
    public function deleteLink(SkuLink $skuLink): void
    {
        $skuLink->delete();
    }

    /**
     * 🔗 AUTO-LINK BY EXACT SKU MATCHING
     *
     * Find products with variants that have SKUs matching marketplace patterns.
     * Creates links for exact matches and common variations.
     */
    public function autoLinkByExactSku(): int
    {
        $linksCreated = 0;

        // Get all active sync accounts
        $syncAccounts = SyncAccount::active()->get();

        // Get all products that don't already have links and have variants with SKUs
        $products = Product::whereDoesntHave('skuLinks')
            ->whereHas('variants', function ($q) {
                $q->whereNotNull('sku');
            })
            ->with(['variants' => function ($q) {
                $q->whereNotNull('sku');
            }])
            ->get();

        foreach ($products as $product) {
            foreach ($product->variants as $variant) {
                foreach ($syncAccounts as $syncAccount) {
                    // Check if link already exists for this product + marketplace
                    $existingLink = $this->findExistingLink($product, $syncAccount);
                    if ($existingLink) {
                        continue; // Skip if link already exists
                    }

                    // Try to find a matching external SKU
                    $externalSku = $this->findMatchingExternalSku($variant->sku, $syncAccount);

                    if ($externalSku) {
                        // Create the link
                        $this->createSkuLink($product, $syncAccount, $variant->sku, $externalSku);
                        $linksCreated++;
                        break; // Only create one link per product per marketplace
                    }
                }
            }
        }

        return $linksCreated;
    }

    /**
     * 🔍 FIND MATCHING EXTERNAL SKU
     *
     * Simple matching logic for common SKU variations
     */
    private function findMatchingExternalSku(string $internalSku, SyncAccount $syncAccount): ?string
    {
        // Generate possible external SKU variations
        $variations = $this->generateSkuVariations($internalSku);

        // For now, we'll simulate finding matches
        // In a real implementation, this would query marketplace APIs
        foreach ($variations as $variation) {
            // Simulate a match found (you'd replace this with actual API calls)
            if ($this->simulateMarketplaceMatch($variation, $syncAccount)) {
                return $variation;
            }
        }

        return null;
    }

    /**
     * 🔄 GENERATE SKU VARIATIONS
     *
     * Generate common SKU format variations for matching
     */
    private function generateSkuVariations(string $baseSku): array
    {
        $variations = [];
        $cleaned = strtoupper(trim($baseSku));

        // Original SKU
        $variations[] = $cleaned;

        // Remove dashes and underscores
        $noDashes = str_replace(['-', '_'], '', $cleaned);
        if ($noDashes !== $cleaned) {
            $variations[] = $noDashes;
        }

        // Add dashes (if not already present)
        if (strlen($cleaned) >= 6 && ! str_contains($cleaned, '-')) {
            $withDash = substr($cleaned, 0, 3).'-'.substr($cleaned, 3);
            $variations[] = $withDash;
        }

        // Add underscores (if not already present)
        if (strlen($cleaned) >= 6 && ! str_contains($cleaned, '_')) {
            $withUnderscore = substr($cleaned, 0, 3).'_'.substr($cleaned, 3);
            $variations[] = $withUnderscore;
        }

        return array_unique($variations);
    }

    /**
     * 🎯 SIMULATE MARKETPLACE MATCH
     *
     * Simulate finding a match in marketplace (replace with real API calls)
     */
    private function simulateMarketplaceMatch(string $sku, SyncAccount $syncAccount): bool
    {
        // For demonstration, we'll create some predictable matches
        // In reality, this would make API calls to check if SKU exists in marketplace

        // Simulate that 30% of SKUs have matches
        $hash = crc32($sku.$syncAccount->channel);

        return ($hash % 10) < 3;
    }

    // ================================
    // 🆕 ENHANCED HIERARCHICAL METHODS
    // ================================

    /**
     * 🔗 CREATE MARKETPLACE LINK (NEW)
     *
     * Create a marketplace link for either product or variant
     */
    public function createMarketplaceLink(
        Model $linkable,
        SyncAccount $syncAccount,
        string $internalSku,
        string $externalSku,
        ?MarketplaceLink $parentLink = null
    ): MarketplaceLink {
        return MarketplaceLink::findOrCreateFor($linkable, $syncAccount, $parentLink);
    }

    /**
     * 🏗️ CREATE HIERARCHICAL LINK
     *
     * Create both product and variant links with proper hierarchy
     */
    public function createHierarchicalLink(
        Product $product,
        ProductVariant $variant,
        SyncAccount $syncAccount,
        string $externalProductId,
        string $externalVariantId,
        string $externalSku
    ): array {
        return MarketplaceLink::createHierarchicalLink(
            $product,
            $variant,
            $syncAccount,
            $externalProductId,
            $externalVariantId,
            $externalSku
        );
    }

    /**
     * 🔍 FIND EXISTING MARKETPLACE LINK
     *
     * Find existing marketplace link for product or variant
     */
    public function findExistingMarketplaceLink(Model $linkable, SyncAccount $syncAccount): ?MarketplaceLink
    {
        return MarketplaceLink::where('linkable_type', get_class($linkable))
            ->where('linkable_id', $linkable->id)
            ->where('sync_account_id', $syncAccount->id)
            ->first();
    }

    /**
     * 📋 GET MARKETPLACE LINKS FOR LINKABLE
     *
     * Get all marketplace links for a product or variant
     */
    public function getMarketplaceLinksFor(Model $linkable): \Illuminate\Database\Eloquent\Collection
    {
        return MarketplaceLink::where('linkable_type', get_class($linkable))
            ->where('linkable_id', $linkable->id)
            ->with(['syncAccount:id,channel,display_name', 'parentLink', 'childLinks'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * 🏢 GET MARKETPLACE LINKS BY MARKETPLACE
     *
     * Get all marketplace links for a specific marketplace with hierarchy
     */
    public function getMarketplaceLinksByMarketplace(string $marketplace): \Illuminate\Database\Eloquent\Collection
    {
        return MarketplaceLink::whereHas('syncAccount', fn ($q) => $q->where('channel', $marketplace))
            ->with([
                'linkable',
                'syncAccount:id,channel,display_name',
                'parentLink',
                'childLinks.linkable',
            ])
            ->orderBy('link_level')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * 🔄 AUTO-LINK BY HIERARCHICAL SKU MATCHING
     *
     * Enhanced auto-linking that creates both product and variant links
     */
    public function autoLinkHierarchical(): int
    {
        $linksCreated = 0;

        // Get all active sync accounts
        $syncAccounts = SyncAccount::active()->get();

        // Get products with variants that don't have marketplace links
        $products = Product::whereDoesntHave('marketplaceLinks')
            ->whereHas('variants', function ($q) {
                $q->whereNotNull('sku')
                    ->whereDoesntHave('marketplaceLinks');
            })
            ->with(['variants' => function ($q) {
                $q->whereNotNull('sku')
                    ->whereDoesntHave('marketplaceLinks');
            }])
            ->get();

        foreach ($products as $product) {
            foreach ($syncAccounts as $syncAccount) {
                // Try to find marketplace data for this product
                $marketplaceData = $this->findMarketplaceProductData($product, $syncAccount);

                if ($marketplaceData) {
                    // Create product-level link
                    $productLink = $this->createProductLink($product, $syncAccount, $marketplaceData);
                    $linksCreated++;

                    // Create variant-level links
                    foreach ($product->variants as $variant) {
                        $variantData = $this->findMarketplaceVariantData($variant, $marketplaceData, $syncAccount);

                        if ($variantData) {
                            $this->createVariantLink($variant, $syncAccount, $variantData, $productLink);
                            $linksCreated++;
                        }
                    }
                }
            }
        }

        return $linksCreated;
    }

    /**
     * 📦 CREATE PRODUCT LINK
     */
    private function createProductLink(Product $product, SyncAccount $syncAccount, array $marketplaceData): MarketplaceLink
    {
        $link = MarketplaceLink::findOrCreateFor($product, $syncAccount);

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
     * 🎨 CREATE VARIANT LINK
     */
    private function createVariantLink(
        ProductVariant $variant,
        SyncAccount $syncAccount,
        array $variantData,
        MarketplaceLink $parentLink
    ): MarketplaceLink {
        $link = MarketplaceLink::findOrCreateFor($variant, $syncAccount, $parentLink);

        $link->update([
            'internal_sku' => $variant->sku,
            'external_sku' => $variantData['sku'],
            'external_product_id' => $variantData['product_id'],
            'external_variant_id' => $variantData['variant_id'],
            'link_status' => 'linked',
            'linked_at' => now(),
            'marketplace_data' => $variantData,
        ]);

        return $link;
    }

    /**
     * 🔍 FIND MARKETPLACE PRODUCT DATA
     *
     * Simulate finding product data in marketplace
     */
    private function findMarketplaceProductData(Product $product, SyncAccount $syncAccount): ?array
    {
        // Simulate API call to find product
        if ($this->simulateMarketplaceMatch($product->parent_sku ?? 'NO-SKU', $syncAccount)) {
            return [
                'product_id' => 'ext_prod_'.$product->id.'_'.$syncAccount->id,
                'sku' => $product->parent_sku ?? 'NO-SKU',
                'title' => $product->name,
                'marketplace' => $syncAccount->channel,
            ];
        }

        return null;
    }

    /**
     * 🎨 FIND MARKETPLACE VARIANT DATA
     *
     * Simulate finding variant data in marketplace
     */
    private function findMarketplaceVariantData(ProductVariant $variant, array $productData, SyncAccount $syncAccount): ?array
    {
        // Simulate API call to find variant
        if ($this->simulateMarketplaceMatch($variant->sku, $syncAccount)) {
            return [
                'product_id' => $productData['product_id'],
                'variant_id' => 'ext_var_'.$variant->id.'_'.$syncAccount->id,
                'sku' => $variant->sku,
                'title' => $variant->display_title,
                'marketplace' => $syncAccount->channel,
            ];
        }

        return null;
    }

    /**
     * 📊 GET ENHANCED STATISTICS
     *
     * Get comprehensive statistics including hierarchy information
     */
    public function getEnhancedStatistics(): array
    {
        $baseStats = $this->getStatistics();

        $marketplaceStats = [
            'total_marketplace_links' => MarketplaceLink::count(),
            'product_links' => MarketplaceLink::products()->count(),
            'variant_links' => MarketplaceLink::variants()->count(),
            'linked_marketplace' => MarketplaceLink::linked()->count(),
            'pending_marketplace' => MarketplaceLink::pending()->count(),
            'failed_marketplace' => MarketplaceLink::failed()->count(),
            'hierarchical_links' => MarketplaceLink::whereNotNull('parent_link_id')->count(),
        ];

        return array_merge($baseStats, $marketplaceStats);
    }

    /**
     * 🗂️ GET HIERARCHY FOR PRODUCT
     *
     * Get complete marketplace link hierarchy for a product
     */
    public function getHierarchyForProduct(Product $product): array
    {
        $productLinks = $product->marketplaceLinks()->with(['syncAccount', 'childLinks.linkable'])->get();
        $hierarchy = [];

        foreach ($productLinks as $productLink) {
            $hierarchy[] = [
                'product_link' => $productLink,
                'variant_links' => $productLink->childLinks,
                'marketplace' => $productLink->syncAccount->channel,
                'status' => $productLink->link_status,
            ];
        }

        return $hierarchy;
    }

    /**
     * ✅ LINK MARKETPLACE ITEM
     *
     * Mark a marketplace link as active/linked
     */
    public function linkMarketplaceItem(MarketplaceLink $marketplaceLink, ?array $externalIds = null): void
    {
        $marketplaceLink->markAsLinked(
            $externalIds['product_id'] ?? null,
            $externalIds['variant_id'] ?? null
        );
    }

    /**
     * ❌ UNLINK MARKETPLACE ITEM
     *
     * Mark a marketplace link as unlinked
     */
    public function unlinkMarketplaceItem(MarketplaceLink $marketplaceLink): void
    {
        $marketplaceLink->unlink();
    }
}
