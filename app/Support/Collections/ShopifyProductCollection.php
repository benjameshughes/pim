<?php

namespace App\Support\Collections;

use App\Actions\Shopify\Sync\PushProductsToShopifyAction;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

/**
 * 💅✨ SHOPIFY PRODUCT COLLECTION - PURE SASS & ELEGANCE ✨💅
 *
 * Darling, this collection is SERVING looks and functionality!
 * Every method is a mood, every filter is a statement.
 * We don't just manage products - we ORCHESTRATE them! 🎭
 */
class ShopifyProductCollection extends Collection
{
    /**
     * 💎 Products that are ready to SLAY on Shopify
     * These are the stars ready for their debut!
     */
    public function syncable(): self
    {
        return $this->filter(function (Product $product) {
            // Must have variants (no empty stages!)
            if ($product->variants->isEmpty()) {
                return false;
            }

            // Must have a name (no nameless divas!)
            if (empty($product->name)) {
                return false;
            }

            // Must be active (no bench warmers!)
            if ($product->status !== 'active') {
                return false;
            }

            return true;
        });
    }

    /**
     * 🌟 Products that haven't graced the Shopify stage yet
     * Fresh faces ready for their spotlight moment!
     */
    public function unsynced(): self
    {
        return $this->filter(function (Product $product) {
            // No Shopify sync record = virgin territory, honey!
            return $product->shopifySyncStatus === null ||
                   $product->shopifySyncStatus->sync_status === 'never_synced';
        });
    }

    /**
     * 🔄 Products that need a GLOW UP (been modified since last sync)
     * These queens need their makeup refreshed!
     */
    public function needsUpdate(): self
    {
        return $this->filter(function (Product $product) {
            $syncStatuses = $product->shopifySyncStatus;

            if ($syncStatuses->isEmpty()) {
                return true;
            } // Never synced = needs update

            // Get the most recent sync status
            $latestSync = $syncStatuses->sortByDesc('last_synced_at')->first();

            if (! $latestSync || ! $latestSync->last_synced_at) {
                return true;
            }

            // If product updated after last sync = time for a refresh!
            return $product->updated_at > $latestSync->last_synced_at;
        });
    }

    /**
     * 💫 Load variants because we don't do solo acts
     * Every star needs their ensemble cast!
     */
    public function withVariants(): self
    {
        return $this->load(['variants.barcodes', 'variants.pricing']);
    }

    /**
     * 📊 Group by sync status - organize the chaos, darling!
     * Every diva needs their proper place in the lineup
     */
    public function groupByStatus(): array
    {
        $groups = [
            'synced' => collect(),
            'pending' => collect(),
            'failed' => collect(),
            'never_synced' => collect(),
        ];

        foreach ($this->items as $product) {
            $status = $product->shopifySyncStatus?->sync_status ?? 'never_synced';
            $groups[$status]->push($product);
        }

        return $groups;
    }

    /**
     * 🚀 BATCH EXPORT TO SHOPIFY - The grand finale!
     * Send these beauties to their Shopify debut
     */
    public function exportToShopify(bool $dryRun = false): array
    {
        // Only export the syncable ones - we have STANDARDS!
        $readyProducts = $this->syncable();

        if ($readyProducts->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No products ready for their Shopify debut! ✨',
                'total' => 0,
                'exported' => 0,
            ];
        }

        // Use our existing action - because we're not reinventing the wheel, honey!
        $pushAction = new PushProductsToShopifyAction;

        $results = $pushAction->execute(
            $readyProducts->pluck('id')->toArray(),
            $dryRun
        );

        return [
            'success' => true,
            'message' => $dryRun
                ? "Would export {$readyProducts->count()} fabulous products! 💅"
                : "Exported {$readyProducts->count()} products to Shopify! 🎉",
            'total' => $readyProducts->count(),
            'exported' => $results['successful'] ?? 0,
            'failed' => $results['failed'] ?? [],
        ];
    }

    /**
     * 🎯 Products missing essential details (the fixer-uppers)
     * These need some TLC before they're stage-ready
     */
    public function incomplete(): self
    {
        return $this->filter(function (Product $product) {
            // No description = no story = no stage time!
            if (empty($product->description)) {
                return true;
            }

            // No variants with pricing = no business model!
            if ($product->variants->filter(fn ($v) => $v->pricing->isNotEmpty())->isEmpty()) {
                return true;
            }

            // No barcodes = tracking nightmare!
            if ($product->variants->filter(fn ($v) => $v->barcodes->isNotEmpty())->isEmpty()) {
                return true;
            }

            return false;
        });
    }

    /**
     * 💰 Products with premium pricing (the high-end boutique)
     * These are the luxury items with attitude!
     */
    public function premium(float $threshold = 100.00): self
    {
        return $this->filter(function (Product $product) use ($threshold) {
            $maxPrice = $product->variants
                ->flatMap(fn ($variant) => $variant->pricing)
                ->max('retail_price');

            return $maxPrice >= $threshold;
        });
    }

    /**
     * ⚡ Products with low stock (the urgency beauties)
     * These need attention before they vanish!
     */
    public function lowStock(int $threshold = 5): self
    {
        return $this->filter(function (Product $product) use ($threshold) {
            $totalStock = $product->variants->sum('stock_level');

            return $totalStock <= $threshold && $totalStock > 0;
        });
    }

    /**
     * 🎨 Filter by specific attributes (the custom queens)
     * Because sometimes you need EXACTLY what you want
     */
    public function withAttribute(string $key, mixed $value = null): self
    {
        return $this->filter(function (Product $product) use ($key, $value) {
            $hasAttribute = $product->variants->flatMap(fn ($variant) => $variant->attributes)
                ->where('attribute_key', $key);

            if ($value === null) {
                return $hasAttribute->isNotEmpty();
            }

            return $hasAttribute->where('attribute_value', $value)->isNotEmpty();
        });
    }

    /**
     * 📈 Sort by sync priority (the VIP list)
     * Most important products get front row seats!
     */
    public function bySyncPriority(): self
    {
        return $this->sortBy(function (Product $product) {
            $score = 0;

            // Active products get priority
            if ($product->status === 'active') {
                $score += 100;
            }

            // Products with more variants = more important
            $score += $product->variants->count() * 10;

            // Recently updated = hot and fresh
            $daysSinceUpdate = now()->diffInDays($product->updated_at);
            $score += max(0, 30 - $daysSinceUpdate);

            // Products with images = ready for the spotlight
            if ($product->variants->flatMap(fn ($v) => $v->images ?? [])->isNotEmpty()) {
                $score += 20;
            }

            return -$score; // Negative for descending sort
        })->values();
    }
}
