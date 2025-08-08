<?php

namespace App\Livewire\Pim\Pricing;

use App\Models\Marketplace;
use App\Models\MarketplaceVariant;
use App\Models\Pricing;
use App\Models\SalesChannel;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

// Layout handled by wrapper template
class PricingManager extends Component
{
    use WithPagination;

    public $search = '';

    public $channelFilter = '';

    public $profitabilityFilter = '';

    public $showUnprofitable = false;

    // Bulk edit properties
    public $selectedPricing = [];

    public $bulkEditMode = false;

    public $bulkVatPercentage = '';

    public $bulkChannelFeePercentage = '';

    public $bulkShippingCost = '';

    // Marketplace pricing properties
    public $showMarketplacePricing = false;

    public $selectedMarketplace = '';

    public $priceAdjustmentPercentage = '';

    public $currencyFilter = 'GBP';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingChannelFilter()
    {
        $this->resetPage();
    }

    public function updatingProfitabilityFilter()
    {
        $this->resetPage();
    }

    public function toggleBulkEdit()
    {
        $this->bulkEditMode = ! $this->bulkEditMode;
        $this->selectedPricing = [];
    }

    public function selectAllPricing()
    {
        $pricingIds = $this->getPricingQuery()->pluck('id')->toArray();
        $this->selectedPricing = $pricingIds;
    }

    public function deselectAllPricing()
    {
        $this->selectedPricing = [];
    }

    public function recalculatePricing($pricingId)
    {
        $pricing = Pricing::findOrFail($pricingId);
        $pricing->recalculateAndSave();

        session()->flash('success', 'Pricing recalculated successfully!');
    }

    public function bulkRecalculate()
    {
        if (empty($this->selectedPricing)) {
            session()->flash('error', 'Please select pricing entries to recalculate.');

            return;
        }

        $count = 0;
        foreach ($this->selectedPricing as $pricingId) {
            $pricing = Pricing::find($pricingId);
            if ($pricing) {
                $pricing->recalculateAndSave();
                $count++;
            }
        }

        $this->selectedPricing = [];
        session()->flash('success', "Recalculated {$count} pricing entries successfully!");
    }

    public function bulkUpdateFields()
    {
        if (empty($this->selectedPricing)) {
            session()->flash('error', 'Please select pricing entries to update.');

            return;
        }

        $updates = [];
        if ($this->bulkVatPercentage !== '') {
            $updates['vat_percentage'] = $this->bulkVatPercentage;
        }
        if ($this->bulkChannelFeePercentage !== '') {
            $updates['channel_fee_percentage'] = $this->bulkChannelFeePercentage;
        }
        if ($this->bulkShippingCost !== '') {
            $updates['shipping_cost'] = $this->bulkShippingCost;
        }

        if (empty($updates)) {
            session()->flash('error', 'Please enter values to update.');

            return;
        }

        $count = 0;
        foreach ($this->selectedPricing as $pricingId) {
            $pricing = Pricing::find($pricingId);
            if ($pricing) {
                $pricing->fill($updates);
                $pricing->recalculateAndSave();
                $count++;
            }
        }

        $this->selectedPricing = [];
        $this->bulkVatPercentage = '';
        $this->bulkChannelFeePercentage = '';
        $this->bulkShippingCost = '';

        session()->flash('success', "Updated {$count} pricing entries successfully!");
    }

    public function syncMarketplacePricing()
    {
        if (! $this->selectedMarketplace) {
            session()->flash('error', 'Please select a marketplace.');

            return;
        }

        $marketplace = Marketplace::where('code', $this->selectedMarketplace)->first();
        if (! $marketplace) {
            session()->flash('error', 'Marketplace not found.');

            return;
        }

        $adjustmentPercentage = (float) $this->priceAdjustmentPercentage;
        $count = 0;

        foreach ($this->selectedPricing as $pricingId) {
            $pricing = Pricing::find($pricingId);
            if (! $pricing) {
                continue;
            }

            // Find or create marketplace variant
            $marketplaceVariant = MarketplaceVariant::firstOrCreate([
                'variant_id' => $pricing->product_variant_id,
                'marketplace_id' => $marketplace->id,
            ], [
                'marketplace_sku' => $pricing->productVariant->sku.'-'.$marketplace->code,
                'status' => 'draft',
            ]);

            // Calculate price with adjustment
            $adjustedPrice = $pricing->retail_price;
            if ($adjustmentPercentage != 0) {
                $adjustedPrice = $pricing->retail_price * (1 + ($adjustmentPercentage / 100));
            }

            $marketplaceVariant->update([
                'price_override' => $adjustedPrice,
                'last_synced_at' => now(),
            ]);

            $count++;
        }

        $this->selectedPricing = [];
        session()->flash('success', "Synced {$count} items to {$marketplace->name} marketplace!");
    }

    public function toggleMarketplacePricing()
    {
        $this->showMarketplacePricing = ! $this->showMarketplacePricing;
        $this->selectedPricing = [];
    }

    public function deletePricing($pricingId)
    {
        $pricing = Pricing::findOrFail($pricingId);
        $pricing->delete();

        session()->flash('success', 'Pricing entry deleted successfully!');
    }

    private function getPricingQuery()
    {
        return Pricing::with(['productVariant.product', 'salesChannel'])
            ->when($this->search, function ($query) {
                $query->whereHas('productVariant.product', function ($q) {
                    $q->where('name', 'like', '%'.$this->search.'%');
                })->orWhereHas('productVariant', function ($q) {
                    $q->where('sku', 'like', '%'.$this->search.'%');
                })->orWhere('marketplace', 'like', '%'.$this->search.'%');
            })
            ->when($this->channelFilter, function ($query) {
                $query->where('marketplace', $this->channelFilter);
            })
            ->when($this->profitabilityFilter === 'profitable', function ($query) {
                $query->where('profit_amount', '>', 0);
            })
            ->when($this->profitabilityFilter === 'unprofitable', function ($query) {
                $query->where('profit_amount', '<=', 0);
            })
            ->orderBy('created_at', 'desc');
    }

    public function render()
    {
        $pricing = $this->getPricingQuery()->paginate(20);

        // Calculate summary statistics
        $totalPricing = Pricing::count();
        $profitablePricing = Pricing::where('profit_amount', '>', 0)->count();
        $averageMargin = Pricing::where('profit_margin_percentage', '>', 0)
            ->avg('profit_margin_percentage');

        // Marketplace statistics
        $marketplaceStats = [];
        $marketplaces = Marketplace::where('status', 'active')->get();
        foreach ($marketplaces as $marketplace) {
            if ($marketplace->code === 'shopify') {
                // Special handling for Shopify using ShopifyProductSync
                $totalSyncs = \App\Models\ShopifyProductSync::where('sync_status', 'synced')->count();
                $recentSyncs = \App\Models\ShopifyProductSync::where('last_synced_at', '>=', now()->subHours(24))->count();

                $marketplaceStats[$marketplace->code] = [
                    'name' => $marketplace->name,
                    'variants' => $totalSyncs,
                    'synced_recently' => $recentSyncs,
                    'platform' => $marketplace->platform,
                    'failed_syncs' => \App\Models\ShopifyProductSync::where('sync_status', 'failed')->count(),
                ];
            } else {
                // Regular marketplace variant handling
                $marketplaceVariants = MarketplaceVariant::where('marketplace_id', $marketplace->id)->count();
                $marketplaceStats[$marketplace->code] = [
                    'name' => $marketplace->name,
                    'variants' => $marketplaceVariants,
                    'synced_recently' => MarketplaceVariant::where('marketplace_id', $marketplace->id)
                        ->where('last_synced_at', '>=', now()->subHours(24))
                        ->count(),
                    'platform' => $marketplace->platform,
                    'failed_syncs' => 0,
                ];
            }
        }

        return view('livewire.pim.pricing.pricing-manager', [
            'pricing' => $pricing,
            'salesChannels' => SalesChannel::where('is_active', true)->get(),
            'marketplaces' => $marketplaces,
            'stats' => [
                'total' => $totalPricing,
                'profitable' => $profitablePricing,
                'unprofitable' => $totalPricing - $profitablePricing,
                'average_margin' => $averageMargin ? round($averageMargin, 1) : 0,
            ],
            'marketplaceStats' => $marketplaceStats,
        ]);
    }
}
