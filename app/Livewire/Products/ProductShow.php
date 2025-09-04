<?php

namespace App\Livewire\Products;

use App\Facades\Activity;
use App\Livewire\Traits\TracksUserInteractions;
use App\Models\Product;
use App\Services\Marketplace\Facades\Sync;
use App\UI\Components\Tab;
use App\UI\Components\TabSet;
use Livewire\Component;

class ProductShow extends Component
{
    use TracksUserInteractions;

    public Product $product;

    public function mount(Product $product)
    {
        // Authorize viewing product details
        $this->authorize('view-product-details');

        // 🚀 CONSOLIDATE ALL RELATIONSHIP LOADING - Single query for all tab data
        $this->product = $product->load([
            'variants.barcode',  // For variants tab (ProductVariantsTab needs variants.barcode)
            'images',            // For overview tab and images tab  
            'attributes',        // For attributes tab
            'syncStatuses.syncAccount', // For marketplace tab
            'syncLogs' => function ($query) {
                // Load enough sync logs for both marketplace (10) and history (50) tabs
                $query->with('syncAccount')->latest()->limit(50);
            },
        ]);
    }

    public function deleteProduct()
    {
        // Track this critical button click
        $this->trackButtonClick('Delete Product', [
            'product_name' => $this->product->name,
            'product_sku' => $this->product->parent_sku,
            'variant_count' => $this->product->variants()->count(),
        ]);

        // Authorize product deletion
        $this->authorize('delete-products');

        $productName = $this->product->name;
        $productSku = $this->product->parent_sku;
        $variantCount = $this->product->variants()->count();
        $userName = auth()->user()?->name ?? 'System';

        // Log before deletion
        Activity::log()
            ->by(auth()->id())
            ->customEvent('product.deleted', $this->product)
            ->description("{$productName} (SKU: {$productSku}) deleted by {$userName} including {$variantCount} variants")
            ->with([
                'product_name' => $productName,
                'product_sku' => $productSku,
                'variants_deleted' => $variantCount,
                'user_name' => $userName,
            ])
            ->save();

        $this->product->delete();

        $this->dispatch('success', "Product '{$productName}' deleted successfully! 🗑️");

        return $this->redirect(route('products.index'), navigate: true);
    }

    public function duplicateProduct()
    {
        // Track this button click
        $this->trackButtonClick('Duplicate Product', [
            'product_name' => $this->product->name,
            'product_sku' => $this->product->parent_sku,
        ]);

        $originalName = $this->product->name;
        $originalSku = $this->product->parent_sku;
        $userName = auth()->user()?->name ?? 'System';

        $newProduct = $this->product->replicate();
        $newProduct->name = $this->product->name.' (Copy)';
        $newProduct->parent_sku = $this->product->parent_sku.'-COPY';
        $newProduct->save();

        // Log the duplication with gorgeous detail
        Activity::log()
            ->by(auth()->id())
            ->customEvent('product.duplicated', $newProduct)
            ->description("{$originalName} duplicated to {$newProduct->name} by {$userName} (SKU: {$originalSku} → {$newProduct->parent_sku})")
            ->with([
                'original_product_id' => $this->product->id,
                'original_product_name' => $originalName,
                'original_product_sku' => $originalSku,
                'new_product_name' => $newProduct->name,
                'new_product_sku' => $newProduct->parent_sku,
                'user_name' => $userName,
            ])
            ->save();

        $this->dispatch('success', 'Product duplicated successfully! ✨');

        return $this->redirect(route('products.show', $newProduct), navigate: true);
    }

    public function updateShopifyPricing(int $syncAccountId, array $options = [])
    {
        // Track this sync button click
        $syncAccount = \App\Models\SyncAccount::find($syncAccountId);
        $this->trackButtonClick('Update Shopify Pricing', [
            'product_name' => $this->product->name,
            'product_sku' => $this->product->parent_sku,
            'sync_account_id' => $syncAccountId,
            'sync_account_name' => $syncAccount?->name,
            'options' => $options,
        ]);

        // Get Shopify sync account

        if (! $syncAccount || $syncAccount->channel !== 'shopify') {
            $this->dispatch('error', 'Invalid Shopify account selected');

            return;
        }

        // Check if product has MarketplaceLinks for this account
        $linkedColorsCount = $this->product->marketplaceLinks()
            ->where('sync_account_id', $syncAccountId)
            ->where('link_level', 'product')
            ->whereNotNull('marketplace_data->color_filter')
            ->count();

        if ($linkedColorsCount === 0) {
            $this->dispatch('error', 'No color links found for this Shopify account. Link colors first.');

            return;
        }

        try {
            // Use Sync facade to update pricing properly
            $result = Sync::shopify($syncAccount->name)->update($this->product->id)->pricing($options)->push();

            // 📝 Log Shopify pricing sync activity with gorgeous detail
            $userName = auth()->user()?->name ?? 'System';
            $description = "{$this->product->name} Shopify pricing sync completed by {$userName} for {$linkedColorsCount} linked colors on {$syncAccount->name}";

            Activity::log()
                ->by(auth()->id())
                ->customEvent('sync.shopify_pricing_update_completed', $this->product)
                ->description($description)
                ->with([
                    'sync_account_id' => $syncAccount->id,
                    'sync_account_name' => $syncAccount->name,
                    'channel' => 'shopify',
                    'linked_colors_count' => $linkedColorsCount,
                    'options' => $options,
                    'product_sku' => $this->product->parent_sku,
                    'product_name' => $this->product->name,
                    'user_name' => $userName,
                    'result' => $result->toArray(),
                ])
                ->save();

            if ($result->success) {
                $this->dispatch('success', "Shopify pricing updated successfully! 💰 Updated pricing for {$linkedColorsCount} linked colors.");
            } else {
                $this->dispatch('error', "Shopify pricing update failed: {$result->message}");
            }

        } catch (\Exception $e) {
            $this->dispatch('error', "Shopify pricing update failed: {$e->getMessage()}");
        }

        // Refresh the product data to show updated sync logs
        $this->mount($this->product->fresh());
    }

    /**
     * Get count of variants with channel pricing overrides
     */
    public function getChannelPricingOverridesCount(): int
    {
        // Cache the expensive calculation for 5 minutes
        $cacheKey = "product_channel_pricing_overrides_count_{$this->product->id}_{$this->product->updated_at->timestamp}";
        
        return cache()->remember($cacheKey, now()->addMinutes(5), function () {
            $overrideCount = 0;

            foreach ($this->product->variants as $variant) {
                $channelPrices = $variant->getAllChannelPrices();
                foreach ($channelPrices as $channelData) {
                    if ($channelData['has_override']) {
                        $overrideCount++;
                        break; // Count each variant only once
                    }
                }
            }

            return $overrideCount;
        });
    }

    /**
     * Get count of Shopify MarketplaceLinks for the product
     */
    public function getShopifyLinkedColorsCount(int $syncAccountId): int
    {
        return $this->product->marketplaceLinks()
            ->where('sync_account_id', $syncAccountId)
            ->where('link_level', 'product')
            ->whereNotNull('marketplace_data->color_filter')
            ->count();
    }

    /**
     * Check if product has any Shopify links
     */
    public function hasShopifyLinks(): bool
    {
        return $this->product->marketplaceLinks()
            ->whereHas('syncAccount', function ($query) {
                $query->where('channel', 'shopify');
            })
            ->where('link_level', 'product')
            ->whereNotNull('marketplace_data->color_filter')
            ->exists();
    }

    public function getProductTabsProperty()
    {
        return TabSet::make()
            ->baseRoute('products.show')
            ->defaultRouteParameters(['product' => $this->product->id])
            ->wireNavigate(true)
            ->tabs([
                Tab::make('overview')
                    ->label('Overview')
                    ->icon('home'),

                Tab::make('variants')
                    ->label('Variants')
                    ->icon('squares-2x2')
                    ->badge($this->product->variants->count())
                    ->badgeColor($this->product->variants->count() > 0 ? 'blue' : 'gray'),

                Tab::make('pricing')
                    ->label('Pricing')
                    ->icon('currency-pound')
                    ->badge($this->getChannelPricingOverridesCount())
                    ->badgeColor($this->getChannelPricingOverridesCount() > 0 ? 'green' : 'gray'),

                Tab::make('marketplace')
                    ->label('Marketplace')
                    ->icon('globe-alt')
                    ->badge($this->getLinkedAccountsCount())
                    ->badgeColor($this->getMarketplaceBadgeColor()),

                Tab::make('attributes')
                    ->label('Attributes')
                    ->icon('tag')
                    ->badge($this->product->attributes->count())
                    ->badgeColor($this->product->attributes->count() > 0 ? 'blue' : 'gray'),

                Tab::make('images')
                    ->label('Images')
                    ->icon('photo')
                    ->badge(\App\Facades\Images::product($this->product)->count())
                    ->badgeColor(\App\Facades\Images::product($this->product)->count() > 0 ? 'blue' : 'gray'),

                Tab::make('history')
                    ->label('History')
                    ->icon('clock')
                    ->badge($this->getRecentActivityCount())
                    ->badgeColor($this->getActivityBadgeColor()),
            ]);
    }

    private function getLinkedAccountsCount(): int
    {
        return $this->product->syncStatuses
            ->whereNotNull('external_product_id')
            ->count();
    }

    private function getMarketplaceBadgeColor(): string
    {
        $failedCount = $this->product->syncStatuses->where('sync_status', 'failed')->count();
        $linkedCount = $this->getLinkedAccountsCount();

        if ($failedCount > 0) {
            return 'red';
        }
        if ($linkedCount > 0) {
            return 'green';
        }

        return 'gray';
    }


    private function getRecentActivityCount(): int
    {
        return $this->product->syncLogs
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
    }

    private function getActivityBadgeColor(): string
    {
        $recentFailures = $this->product->syncLogs
            ->where('created_at', '>=', now()->subDays(7))
            ->where('status', 'failed')
            ->count();

        return $recentFailures > 0 ? 'red' : 'blue';
    }

    public function render()
    {
        return view('livewire.products.product-show');
    }
}
