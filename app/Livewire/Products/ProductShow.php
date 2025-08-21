<?php

namespace App\Livewire\Products;

use App\Models\Product;
use App\UI\Components\Tab;
use App\UI\Components\TabSet;
use Livewire\Component;

class ProductShow extends Component
{
    public Product $product;

    public function mount(Product $product)
    {
        $this->product = $product->load([
            'variants.barcodes',
            'shopifySyncStatus', // Keep for backward compatibility
            'syncStatuses.syncAccount',
            'syncLogs' => function ($query) {
                $query->with('syncAccount')->latest()->limit(10);
            },
        ]);
    }

    public function deleteProduct()
    {
        $productName = $this->product->name;
        $this->product->delete();

        $this->dispatch('success', "Product '{$productName}' deleted successfully! 🗑️");

        return $this->redirect(route('products.index'), navigate: true);
    }

    public function duplicateProduct()
    {
        $newProduct = $this->product->replicate();
        $newProduct->name = $this->product->name.' (Copy)';
        $newProduct->parent_sku = $this->product->parent_sku.'-COPY';
        $newProduct->save();

        $this->dispatch('success', 'Product duplicated successfully! ✨');

        return $this->redirect(route('products.show', $newProduct), navigate: true);
    }

    public function updateShopifyPricing(int $syncAccountId, array $options = [])
    {
        // Get Shopify sync account
        $syncAccount = \App\Models\SyncAccount::find($syncAccountId);
        
        if (!$syncAccount || $syncAccount->channel !== 'shopify') {
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

        // Dispatch the pricing update job
        \App\Jobs\UpdateShopifyPricingJob::dispatch($this->product, $syncAccount, $options);

        $this->dispatch('success', "Shopify pricing update initiated! 💰 Updating pricing for {$linkedColorsCount} linked colors.");

        // Refresh the product data to show updated sync logs
        $this->mount($this->product->fresh());
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

                Tab::make('marketplace')
                    ->label('Marketplace')
                    ->icon('globe-alt')
                    ->badge($this->getLinkedAccountsCount())
                    ->badgeColor($this->getMarketplaceBadgeColor()),

                Tab::make('attributes')
                    ->label('Attributes')
                    ->icon('tag')
                    ->badge($this->getAttributesCount())
                    ->badgeColor($this->getAttributesCount() > 0 ? 'blue' : 'gray'),

                Tab::make('images')
                    ->label('Images')
                    ->icon('photo')
                    ->badge($this->getImagesCount())
                    ->hidden($this->getImagesCount() === 0),

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
            ->where('external_product_id', '!=', null)
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

    private function getAttributesCount(): int
    {
        return $this->product->attributes()->count();
    }

    private function getImagesCount(): int
    {
        return $this->product->images()->count();
    }

    private function getRecentActivityCount(): int
    {
        return $this->product->syncLogs()
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
    }

    private function getActivityBadgeColor(): string
    {
        $recentFailures = $this->product->syncLogs()
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
