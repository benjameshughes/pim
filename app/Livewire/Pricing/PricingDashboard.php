<?php

namespace App\Livewire\Pricing;

use App\Models\Pricing;
use App\Models\SalesChannel;
use App\Services\Pricing\PriceCalculatorService;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * 💰🎭 PRICING DASHBOARD - THE FINANCIAL COMMAND CENTER! 🎭💰
 *
 * Honey, this dashboard is SERVING major pricing management energy!
 * Channel pricing, profit analysis, bulk updates - we do it ALL with SASS! ✨
 */
class PricingDashboard extends Component
{
    use WithPagination;

    // 🎯 Filter Properties
    public $activeTab = 'overview';

    public $selectedChannel = 'all';

    public $selectedProducts = [];

    public $selectedVariants = [];

    public $productSearchQuery = '';

    public $variantSearchQuery = '';

    public $searchQuery = '';

    public $profitabilityFilter = 'all';

    public $selectedPricing = [];

    public $showProductSearch = false;

    public $showVariantSearch = false;

    // 📊 Analysis Properties
    public $showProfitabilityAnalysis = false;

    public $showChannelComparison = false;

    public $analysisData = null;

    // 🚀 Bulk Actions
    public $bulkDiscountPercentage = 0;

    public $bulkMarkupPercentage = 0;

    public $showBulkActions = false;

    // 💰 Quick Actions
    public $isLoading = false;

    public $loadingMessage = '';

    protected PriceCalculatorService $priceCalculator;

    /**
     * 🎪 MOUNT - Initialize the component
     */
    public function mount()
    {
        // Authorize viewing pricing
        $this->authorize('view-pricing');
        
        $this->priceCalculator = app(PriceCalculatorService::class);
        $this->refreshAnalysis();
    }

    /**
     * 🎨 RENDER - The main performance
     */
    public function render()
    {
        $data = match ($this->activeTab) {
            'pricing' => $this->getPricingData(),
            'channels' => $this->getChannelsData(),
            'analysis' => $this->getAnalysisData(),
            'bulk' => $this->getBulkData(),
            default => $this->getOverviewData(),
        };

        return view('livewire.pricing.pricing-dashboard', $data);
    }

    /**
     * 📊 GET OVERVIEW DATA - Dashboard summary
     */
    protected function getOverviewData(): array
    {
        $allPricing = Pricing::active()
            ->with(['productVariant.product', 'salesChannel'])
            ->when(! empty($this->selectedProducts), function ($q) {
                $q->whereHas('productVariant', function ($subQuery) {
                    $subQuery->whereIn('product_id', $this->selectedProducts);
                });
            })
            ->when(! empty($this->selectedVariants), function ($q) {
                $q->whereIn('product_variant_id', $this->selectedVariants);
            })
            ->get();

        $stats = [
            'total_products' => $allPricing->count(),
            'total_revenue' => $allPricing->totalRevenue(),
            'total_profit' => $allPricing->totalProfit(),
            'average_margin' => $allPricing->averageProfitMargin(),
            'profitable_items' => $allPricing->profitable()->count(),
            'on_sale_items' => $allPricing->onSale()->count(),
        ];

        $channelBreakdown = $allPricing->channelComparison();
        $profitAnalysis = $allPricing->profitAnalysis();
        $priceDistribution = $allPricing->priceRangeAnalysis();

        return [
            'stats' => $stats,
            'channelBreakdown' => $channelBreakdown,
            'profitAnalysis' => $profitAnalysis,
            'priceDistribution' => $priceDistribution,
            'channels' => SalesChannel::active()->byPriority()->get(),
            'searchableProducts' => $this->getSearchableProducts(),
            'searchableVariants' => $this->getSearchableVariants(),
        ];
    }

    /**
     * 📋 GET PRICING DATA - Product pricing table
     */
    protected function getPricingData(): array
    {
        $query = Pricing::active()
            ->with(['productVariant.product', 'salesChannel'])
            ->when($this->searchQuery, function ($q) {
                $q->whereHas('productVariant.product', function ($subQuery) {
                    $subQuery->where('name', 'like', '%'.$this->searchQuery.'%');
                })->orWhereHas('productVariant', function ($subQuery) {
                    $subQuery->where('sku', 'like', '%'.$this->searchQuery.'%');
                });
            })
            ->when($this->selectedChannel !== 'all', function ($q) {
                if ($this->selectedChannel === 'null') {
                    $q->whereNull('sales_channel_id');
                } else {
                    $q->where('sales_channel_id', $this->selectedChannel);
                }
            })
            ->when(! empty($this->selectedProducts), function ($q) {
                $q->whereHas('productVariant', function ($subQuery) {
                    $subQuery->whereIn('product_id', $this->selectedProducts);
                });
            })
            ->when(! empty($this->selectedVariants), function ($q) {
                $q->whereIn('product_variant_id', $this->selectedVariants);
            })
            ->when($this->profitabilityFilter !== 'all', function ($q) {
                match ($this->profitabilityFilter) {
                    'profitable' => $q->where('profit_margin', '>', 0),
                    'low_margin' => $q->where('profit_margin', '<=', 10),
                    'high_margin' => $q->where('profit_margin', '>', 50),
                    'loss_making' => $q->where('profit_margin', '<=', 0),
                };
            });

        return [
            'pricing' => $query->paginate(20),
            'channels' => SalesChannel::active()->byPriority()->get(),
            'searchableProducts' => $this->getSearchableProducts(),
            'searchableVariants' => $this->getSearchableVariants(),
            'selectedCount' => count($this->selectedPricing),
        ];
    }

    /**
     * 🛍️ GET CHANNELS DATA - Sales channels management
     */
    protected function getChannelsData(): array
    {
        $channels = SalesChannel::active()->byPriority()->get();

        $channelStats = [];
        foreach ($channels as $channel) {
            $channelPricing = Pricing::forChannel($channel->id)->active()->get();
            $channelStats[$channel->id] = [
                'channel' => $channel,
                'stats' => $channelPricing->profitAnalysis(),
                'product_count' => $channelPricing->count(),
            ];
        }

        return [
            'channels' => $channels,
            'channelStats' => $channelStats,
        ];
    }

    /**
     * 📊 GET ANALYSIS DATA - Deep dive analytics
     */
    protected function getAnalysisData(): array
    {
        $allPricing = Pricing::with(['salesChannel'])->get(); // Removed ->active() scope for now

        return [
            'profitAnalysis' => $allPricing->profitAnalysis(),
            'channelComparison' => $allPricing->channelComparison(),
            'discountAnalysis' => $allPricing->discountAnalysis(),
            'optimizationSuggestions' => $allPricing->optimizationSuggestions(),
            'priceDistribution' => $allPricing->priceRangeAnalysis(),
        ];
    }

    /**
     * 🚀 GET BULK DATA - Bulk operations interface
     */
    protected function getBulkData(): array
    {
        return [
            'selectedCount' => count($this->selectedPricing),
            'channels' => SalesChannel::active()->byPriority()->get(),
        ];
    }

    /**
     * 🎭 SWITCH TAB - Change active tab
     */
    public function switchTab($tab)
    {
        $this->activeTab = $tab;
        $this->resetPage();

        if ($tab === 'analysis') {
            $this->refreshAnalysis();
        }
    }

    /**
     * ✨ TOGGLE PRICING SELECTION - Select/deselect pricing items
     */
    public function togglePricing($pricingId)
    {
        if (in_array($pricingId, $this->selectedPricing)) {
            $this->selectedPricing = array_filter(
                $this->selectedPricing,
                fn ($id) => $id !== $pricingId
            );
        } else {
            $this->selectedPricing[] = $pricingId;
        }

        $this->showBulkActions = count($this->selectedPricing) > 0;
    }

    /**
     * 🎯 SELECT ALL VISIBLE - Mass selection
     */
    public function selectAllVisible()
    {
        $visible = $this->getPricingData()['pricing']->pluck('id')->toArray();
        $this->selectedPricing = array_unique(array_merge($this->selectedPricing, $visible));
        $this->showBulkActions = true;
    }

    /**
     * 🗑️ CLEAR SELECTION - Reset selection
     */
    public function clearSelection()
    {
        $this->selectedPricing = [];
        $this->showBulkActions = false;
    }

    /**
     * 💸 APPLY BULK DISCOUNT - Apply discount to selected items
     */
    public function applyBulkDiscount()
    {
        // Authorize bulk pricing updates
        $this->authorize('bulk-update-pricing');
        
        if (empty($this->selectedPricing) || $this->bulkDiscountPercentage <= 0) {
            $this->dispatch('toast', [
                'type' => 'warning',
                'message' => 'Please select items and enter a valid discount percentage! 💅',
            ]);

            return;
        }

        $this->startLoading('Applying bulk discount...');

        try {
            $affected = Pricing::whereIn('id', $this->selectedPricing)->update([
                'discount_percentage' => $this->bulkDiscountPercentage,
                'sale_starts_at' => now(),
                'sale_ends_at' => now()->addDays(7), // 7-day sale
            ]);

            $this->dispatch('toast', [
                'type' => 'success',
                'message' => "Applied {$this->bulkDiscountPercentage}% discount to {$affected} items! 🎉",
            ]);

            $this->bulkDiscountPercentage = 0;
            $this->clearSelection();
            $this->refreshAnalysis();

        } catch (\Exception $e) {
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Bulk discount failed: '.$e->getMessage(),
            ]);
        }

        $this->stopLoading();
    }

    /**
     * 📈 APPLY BULK MARKUP - Apply markup to selected items
     */
    public function applyBulkMarkup()
    {
        // Authorize bulk pricing updates
        $this->authorize('bulk-update-pricing');
        
        if (empty($this->selectedPricing) || $this->bulkMarkupPercentage <= 0) {
            $this->dispatch('toast', [
                'type' => 'warning',
                'message' => 'Please select items and enter a valid markup percentage! 💅',
            ]);

            return;
        }

        $this->startLoading('Applying bulk markup...');

        try {
            $affected = 0;
            foreach ($this->selectedPricing as $pricingId) {
                $pricing = Pricing::find($pricingId);
                if ($pricing) {
                    $newPrice = $pricing->base_price * (1 + ($this->bulkMarkupPercentage / 100));
                    $pricing->update(['base_price' => $newPrice]);
                    $affected++;
                }
            }

            $this->dispatch('toast', [
                'type' => 'success',
                'message' => "Applied {$this->bulkMarkupPercentage}% markup to {$affected} items! 🚀",
            ]);

            $this->bulkMarkupPercentage = 0;
            $this->clearSelection();
            $this->refreshAnalysis();

        } catch (\Exception $e) {
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Bulk markup failed: '.$e->getMessage(),
            ]);
        }

        $this->stopLoading();
    }

    /**
     * 🔄 REFRESH ANALYSIS - Update cached analysis data
     */
    public function refreshAnalysis()
    {
        $allPricing = Pricing::with(['salesChannel'])->get(); // Removed ->active() scope for now
        $this->analysisData = [
            'profit' => $allPricing->profitAnalysis(),
            'channels' => $allPricing->channelComparison(),
            'discounts' => $allPricing->discountAnalysis(),
        ];
    }

    /**
     * 🎬 START LOADING - Show loading state
     */
    protected function startLoading($message)
    {
        $this->isLoading = true;
        $this->loadingMessage = $message;
    }

    /**
     * ⏹️ STOP LOADING - Hide loading state
     */
    protected function stopLoading()
    {
        $this->isLoading = false;
        $this->loadingMessage = '';
    }

    /**
     * 🔍 UPDATING SEARCH - Reset pagination when searching
     */
    public function updatingSearchQuery()
    {
        $this->resetPage();
    }

    /**
     * 🛍️ UPDATING CHANNEL - Reset pagination when filtering by channel
     */
    public function updatingSelectedChannel()
    {
        $this->resetPage();
    }

    /**
     * 🔍 GET SEARCHABLE PRODUCTS - Products with pricing data
     */
    protected function getSearchableProducts()
    {
        return \App\Models\Product::whereHas('variants.pricingRecords')
            ->when($this->productSearchQuery, function ($q) {
                $q->where('name', 'like', '%'.$this->productSearchQuery.'%');
            })
            ->orderBy('name')
            ->limit(10)
            ->get();
    }

    /**
     * 💎 GET SEARCHABLE VARIANTS - Variants with pricing data
     */
    protected function getSearchableVariants()
    {
        return \App\Models\ProductVariant::whereHas('pricingRecords')
            ->with('product')
            ->when($this->variantSearchQuery, function ($q) {
                $q->where(function ($subQuery) {
                    $subQuery->where('sku', 'like', '%'.$this->variantSearchQuery.'%')
                        ->orWhere('title', 'like', '%'.$this->variantSearchQuery.'%')
                        ->orWhereHas('product', function ($productQuery) {
                            $productQuery->where('name', 'like', '%'.$this->variantSearchQuery.'%');
                        });
                });
            })
            ->orderBy('sku')
            ->limit(10)
            ->get();
    }

    /**
     * ✨ ADD PRODUCT - Add product to selection
     */
    public function addProduct($productId)
    {
        if (! in_array($productId, $this->selectedProducts)) {
            $this->selectedProducts[] = $productId;
            $this->resetPage();
            $this->showProductSearch = false;
            $this->productSearchQuery = '';
        }
    }

    /**
     * ✨ REMOVE PRODUCT - Remove product from selection
     */
    public function removeProduct($productId)
    {
        $this->selectedProducts = array_values(array_filter($this->selectedProducts, fn ($id) => $id != $productId));
        $this->resetPage();
    }

    /**
     * 💎 ADD VARIANT - Add variant to selection
     */
    public function addVariant($variantId)
    {
        if (! in_array($variantId, $this->selectedVariants)) {
            $this->selectedVariants[] = $variantId;
            $this->resetPage();
            $this->showVariantSearch = false;
            $this->variantSearchQuery = '';
        }
    }

    /**
     * 💎 REMOVE VARIANT - Remove variant from selection
     */
    public function removeVariant($variantId)
    {
        $this->selectedVariants = array_values(array_filter($this->selectedVariants, fn ($id) => $id != $variantId));
        $this->resetPage();
    }

    /**
     * 🗑️ CLEAR ALL FILTERS - Reset all product/variant selections
     */
    public function clearAllFilters()
    {
        $this->selectedProducts = [];
        $this->selectedVariants = [];
        $this->resetPage();
    }

    /**
     * 📊 UPDATING PROFITABILITY FILTER - Reset pagination when filtering
     */
    public function updatingProfitabilityFilter()
    {
        $this->resetPage();
    }
}
