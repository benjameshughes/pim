<?php

namespace App\Services\Sync\Builders;

use App\Models\Product;

/**
 * 🏪 EBAY SYNC BUILDER
 *
 * Beautiful fluent API for eBay sync operations:
 *
 * Sync::ebay()->account('uk')->product($product)->push()
 * Sync::ebay()->account('us')->products($products)->listingType('auction')->push()
 * Sync::ebay()->dryRun()->product($product)->preview()
 */
class EbaySyncBuilder extends BaseSyncBuilder
{
    private string $listingType = 'fixed_price';

    private int $duration = 30; // days

    private array $categories = [];

    private bool $bestOffer = false;

    /**
     * 🎯 Get channel name
     */
    protected function getChannelName(): string
    {
        return 'ebay';
    }

    /**
     * 🏷️ Set listing type
     *
     * Usage: Sync::ebay()->listingType('auction')->product($product)->push()
     */
    public function listingType(string $type): self
    {
        if (! in_array($type, ['fixed_price', 'auction', 'buy_it_now'])) {
            throw new \InvalidArgumentException("Invalid listing type: {$type}");
        }

        $this->listingType = $type;

        return $this;
    }

    /**
     * ⏰ Set listing duration
     *
     * Usage: Sync::ebay()->duration(7)->product($product)->push()
     */
    public function duration(int $days): self
    {
        $this->duration = $days;

        return $this;
    }

    /**
     * 🏷️ Set eBay categories
     *
     * Usage: Sync::ebay()->categories(['12345', '67890'])->product($product)->push()
     */
    public function categories(array $categoryIds): self
    {
        $this->categories = $categoryIds;

        return $this;
    }

    /**
     * 💰 Enable best offer
     *
     * Usage: Sync::ebay()->bestOffer()->product($product)->push()
     */
    public function bestOffer(bool $enabled = true): self
    {
        $this->bestOffer = $enabled;

        return $this;
    }

    /**
     * 🚀 Push to eBay
     */
    public function push(): array
    {
        $this->validate();

        $log = $this->createSyncLog('push');

        try {
            if ($this->dryRun) {
                return $this->previewPush();
            }

            $result = $this->executePush();

            $log->markAsSuccessful(
                'Successfully pushed to eBay',
                $result
            );

            return $result;

        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * 🔽 Pull from eBay
     */
    public function pull(): array
    {
        $this->validate();

        $log = $this->createSyncLog('pull');

        try {
            $result = $this->executePull();

            $log->markAsSuccessful(
                'Successfully pulled from eBay',
                $result
            );

            return $result;

        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * 👀 Preview what would be pushed (dry run)
     */
    public function preview(): array
    {
        return $this->previewPush();
    }

    /**
     * 🚀 Execute push operation
     */
    private function executePush(): array
    {
        // TODO: Implement eBay push logic
        // This would integrate with your existing EbayConnectService

        if ($this->products->isNotEmpty()) {
            return $this->pushMultipleProducts();
        }

        return $this->pushSingleProduct();
    }

    /**
     * 📦 Push single product to eBay
     */
    private function pushSingleProduct(): array
    {
        $syncStatus = $this->findOrCreateSyncStatus($this->product);

        // TODO: Implement actual eBay API integration
        $ebayItemId = $this->createEbayListing($this->product);

        $syncStatus->markAsSynced(
            $ebayItemId,
            null,
            $ebayItemId, // eBay uses item ID as handle
            $this->buildEbayMetadata()
        );

        return [
            'success' => true,
            'product_id' => $this->product->id,
            'ebay_item_id' => $ebayItemId,
            'listing_type' => $this->listingType,
            'duration' => $this->duration,
        ];
    }

    /**
     * 📦 Push multiple products to eBay
     */
    private function pushMultipleProducts(): array
    {
        $results = [];

        foreach ($this->products as $product) {
            try {
                $result = $this->createEbayListing($product);

                $syncStatus = $this->findOrCreateSyncStatus($product);
                $syncStatus->markAsSynced($result, null, $result, $this->buildEbayMetadata());

                $results[] = [
                    'product_id' => $product->id,
                    'success' => true,
                    'ebay_item_id' => $result,
                ];

            } catch (\Exception $e) {
                $results[] = [
                    'product_id' => $product->id,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'success' => true,
            'batch_results' => $results,
            'summary' => $this->generateBatchSummary($results),
        ];
    }

    /**
     * 🔽 Execute pull operation
     */
    private function executePull(): array
    {
        // TODO: Implement eBay pull logic
        // This would get current eBay listing data and update PIM

        throw new \RuntimeException('eBay pull functionality not yet implemented');
    }

    /**
     * 👀 Preview push operation
     */
    private function previewPush(): array
    {
        $product = $this->product ?: $this->products->first();

        if (! $product) {
            throw new \InvalidArgumentException('No product specified for preview');
        }

        return [
            'preview' => true,
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->parent_sku,
            ],
            'ebay_listing' => [
                'title' => $this->generateEbayTitle($product),
                'listing_type' => $this->listingType,
                'duration' => $this->duration,
                'categories' => $this->categories,
                'best_offer' => $this->bestOffer,
                'variants' => $product->variants->count(),
            ],
            'account' => $this->getSyncAccount()->display_name,
        ];
    }

    /**
     * 🏪 Create eBay listing (placeholder)
     */
    private function createEbayListing(Product $product): string
    {
        // TODO: Implement actual eBay API integration
        // This is a placeholder that would integrate with EbayConnectService

        return 'ebay_item_'.$product->id.'_'.time();
    }

    /**
     * 🏷️ Generate eBay listing title
     */
    private function generateEbayTitle(Product $product): string
    {
        $title = $product->name;

        // Add brand if available
        if ($product->brand) {
            $title = "{$product->brand} {$title}";
        }

        // eBay title limit is 80 characters
        return substr($title, 0, 80);
    }

    /**
     * 📋 Build eBay metadata
     */
    private function buildEbayMetadata(): array
    {
        return [
            'listing_type' => $this->listingType,
            'duration' => $this->duration,
            'categories' => $this->categories,
            'best_offer' => $this->bestOffer,
            'created_via' => 'sync_api',
        ];
    }

    /**
     * 📊 Generate batch summary
     */
    private function generateBatchSummary(array $results): array
    {
        $successful = collect($results)->where('success', true)->count();
        $failed = collect($results)->where('success', false)->count();

        return [
            'total' => count($results),
            'successful' => $successful,
            'failed' => $failed,
            'success_rate' => count($results) > 0
                ? round(($successful / count($results)) * 100, 1)
                : 0,
        ];
    }
}
