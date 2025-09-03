<?php

namespace App\Livewire\Products;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesChannel;
use App\Services\Pricing\ChannelPricingService;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * 💰 PRODUCT PRICING COMPONENT
 *
 * Beautiful channel pricing management interface for products and variants.
 * Features pricing table, channel price modal, and bulk operations.
 */
class ProductPricing extends Component
{
    public Product $product;

    public Collection $variants;

    public Collection $channels;

    // Modal state
    public bool $showPriceModal = false;

    public ?ProductVariant $selectedVariant = null;

    public string $selectedChannel = '';

    public ?float $modalPrice = null;

    // Base price modal state
    public bool $showBasePriceModal = false;

    public ?ProductVariant $selectedBaseVariant = null;

    public ?float $basePriceValue = null;

    // Bulk operations modals
    public bool $showBulkMarkupModal = false;

    public bool $showBulkDiscountModal = false;

    // Markup modal properties
    public string $markupChannel = '';

    public ?float $markupPercentage = null;

    public ?float $markupFixedPrice = null;

    public string $markupPriceType = 'percentage'; // 'percentage' or 'fixed'

    // Discount modal properties
    public string $discountChannel = '';

    public ?float $discountPercentage = null;

    public ?float $discountFixedPrice = null;

    public string $discountPriceType = 'percentage'; // 'percentage' or 'fixed'

    // Table filters
    public string $searchVariants = '';

    public string $filterChannel = '';

    public bool $showOnlyOverrides = false;

    protected $listeners = [
        'channel-price-updated' => 'handlePriceUpdate',
        'refresh-pricing' => 'handlePriceUpdate',
    ];

    public function mount(Product $product)
    {
        // Authorize viewing pricing
        $this->authorize('view-pricing');

        // 🚀 NO RELATIONSHIP LOADING - ProductShow already loaded variants.barcode
        $this->product = $product;
        $this->variants = $this->product->variants;
        $this->channels = SalesChannel::active()->get();
    }

    public function openPriceModal($variantId, string $channelCode)
    {
        // Authorize editing pricing
        $this->authorize('edit-pricing');

        $variant = ProductVariant::find($variantId);
        if (! $variant) {
            $this->dispatch('notify', message: 'Variant not found', type: 'error');

            return;
        }

        $this->selectedVariant = $variant;
        $this->selectedChannel = $channelCode;
        $this->modalPrice = $variant->getChannelPrice($channelCode);

        // If the price equals default price, it's not an override
        if ($this->modalPrice == $variant->getRetailPrice() && ! $variant->hasChannelOverride($channelCode)) {
            $this->modalPrice = null;
        }

        $this->showPriceModal = true;
    }

    public function savePriceModal()
    {
        $this->validate([
            'modalPrice' => 'nullable|numeric|min:0.01',
        ]);

        if (! $this->selectedVariant || ! $this->selectedChannel) {
            $this->dispatch('notify', message: 'Invalid variant or channel selected', type: 'error');

            return;
        }

        $channelPricingService = app(ChannelPricingService::class);

        $result = $channelPricingService->setPriceForChannel(
            $this->selectedVariant,
            $this->selectedChannel,
            $this->modalPrice
        );

        if ($result['success']) {
            $channel = $this->channels->firstWhere('code', $this->selectedChannel);
            $action = $result['data']['action'];

            if ($action === 'removed') {
                $this->dispatch('notify', message: "Removed {$channel->name} price override for {$this->selectedVariant->sku} ✨", type: 'success');
            } else {
                $this->dispatch('notify', message: "Updated {$channel->name} price for {$this->selectedVariant->sku}: £{$this->modalPrice} 💰", type: 'success');
            }
        } else {
            $this->dispatch('notify', message: $result['message'], type: 'error');
        }

        $this->closePriceModal();
        $this->dispatch('channel-price-updated');
    }

    public function closePriceModal()
    {
        $this->showPriceModal = false;
        $this->selectedVariant = null;
        $this->selectedChannel = '';
        $this->modalPrice = null;
        $this->resetValidation();
    }

    public function openBasePriceModal($variantId)
    {
        // Authorize editing pricing
        $this->authorize('edit-pricing');

        $variant = ProductVariant::find($variantId);
        if (! $variant) {
            $this->dispatch('notify', message: 'Variant not found', type: 'error');

            return;
        }

        $this->selectedBaseVariant = $variant;
        $this->basePriceValue = $variant->getRetailPrice();
        $this->showBasePriceModal = true;
    }

    public function saveBasePriceModal()
    {
        $this->validate([
            'basePriceValue' => 'required|numeric|min:0.01',
        ]);

        if (! $this->selectedBaseVariant) {
            $this->dispatch('notify', message: 'Invalid variant selected', type: 'error');

            return;
        }

        $oldPrice = $this->selectedBaseVariant->price;
        $this->selectedBaseVariant->update(['price' => $this->basePriceValue]);

        // Refresh the variants collection to pick up the price change
        $this->product = $this->product->fresh(['variants']);
        $this->variants = $this->product->variants;

        $this->dispatch('notify', message: "Updated retail price for {$this->selectedBaseVariant->sku}: £{$oldPrice} → £{$this->basePriceValue} 💰", type: 'success');

        $this->closeBasePriceModal();
    }

    public function closeBasePriceModal()
    {
        $this->showBasePriceModal = false;
        $this->selectedBaseVariant = null;
        $this->basePriceValue = null;
        $this->resetValidation();
    }

    public function removeChannelOverride($variantId, string $channelCode)
    {
        // Authorize editing pricing
        $this->authorize('edit-pricing');

        $variant = ProductVariant::find($variantId);
        if (! $variant) {
            $this->dispatch('notify', message: 'Variant not found', type: 'error');

            return;
        }

        $channelPricingService = app(ChannelPricingService::class);
        $result = $channelPricingService->removeChannelPriceOverride($variant, $channelCode);

        if ($result['success']) {
            $channel = $this->channels->firstWhere('code', $channelCode);
            $this->dispatch('notify', message: "Removed {$channel->name} price override for {$variant->sku} ✨", type: 'success');
            $this->dispatch('channel-price-updated');
        } else {
            $this->dispatch('notify', message: $result['message'], type: 'error');
        }
    }

    public function openBulkMarkupModal()
    {
        $this->showBulkMarkupModal = true;
        $this->markupChannel = '';
        $this->markupPercentage = null;
        $this->markupFixedPrice = null;
        $this->markupPriceType = 'percentage';
    }

    public function openBulkDiscountModal()
    {
        $this->showBulkDiscountModal = true;
        $this->discountChannel = '';
        $this->discountPercentage = null;
        $this->discountFixedPrice = null;
        $this->discountPriceType = 'percentage';
    }

    public function saveBulkMarkup()
    {
        // Authorize bulk pricing updates
        $this->authorize('bulk-update-pricing');

        $validationRules = ['markupChannel' => 'required|string'];

        if ($this->markupPriceType === 'percentage') {
            $validationRules['markupPercentage'] = 'required|numeric|min:0|max:1000';
        } else {
            $validationRules['markupFixedPrice'] = 'required|numeric|min:0.01';
        }

        $this->validate($validationRules);

        $channelPricingService = app(ChannelPricingService::class);
        $variants = $this->getFilteredVariants();

        if ($this->markupPriceType === 'percentage') {
            $result = $channelPricingService->applyMarkupToChannel($variants, $this->markupChannel, $this->markupPercentage);
            $message = "Applied {$this->markupPercentage}% markup";
        } else {
            $result = $channelPricingService->setFixedPriceForChannel($variants, $this->markupChannel, $this->markupFixedPrice);
            $message = "Set fixed price £{$this->markupFixedPrice}";
        }

        if ($result['success']) {
            $channel = $this->channels->firstWhere('code', $this->markupChannel);
            $count = $result['data']['summary']['updates_successful'];
            $this->dispatch('notify', message: "{$message} to {$count} variants for {$channel->name} 🎉", type: 'success');
            $this->dispatch('channel-price-updated');
        } else {
            $this->dispatch('notify', message: $result['message'], type: 'error');
        }

        $this->closeBulkMarkupModal();
    }

    public function saveBulkDiscount()
    {
        // Authorize bulk pricing updates
        $this->authorize('bulk-update-pricing');

        $validationRules = ['discountChannel' => 'required|string'];

        if ($this->discountPriceType === 'percentage') {
            $validationRules['discountPercentage'] = 'required|numeric|min:0|max:100';
        } else {
            $validationRules['discountFixedPrice'] = 'required|numeric|min:0.01';
        }

        $this->validate($validationRules);

        $channelPricingService = app(ChannelPricingService::class);
        $variants = $this->getFilteredVariants();

        if ($this->discountPriceType === 'percentage') {
            $result = $channelPricingService->applyDiscountToChannel($variants, $this->discountChannel, $this->discountPercentage);
            $message = "Applied {$this->discountPercentage}% discount";
        } else {
            $result = $channelPricingService->setFixedPriceForChannel($variants, $this->discountChannel, $this->discountFixedPrice);
            $message = "Set fixed price £{$this->discountFixedPrice}";
        }

        if ($result['success']) {
            $channel = $this->channels->firstWhere('code', $this->discountChannel);
            $count = $result['data']['summary']['updates_successful'];
            $this->dispatch('notify', message: "{$message} to {$count} variants for {$channel->name} 🎉", type: 'success');
            $this->dispatch('channel-price-updated');
        } else {
            $this->dispatch('notify', message: $result['message'], type: 'error');
        }

        $this->closeBulkDiscountModal();
    }

    public function closeBulkMarkupModal()
    {
        $this->showBulkMarkupModal = false;
        $this->markupChannel = '';
        $this->markupPercentage = null;
        $this->markupFixedPrice = null;
        $this->markupPriceType = 'percentage';
        $this->resetValidation();
    }

    public function closeBulkDiscountModal()
    {
        $this->showBulkDiscountModal = false;
        $this->discountChannel = '';
        $this->discountPercentage = null;
        $this->discountFixedPrice = null;
        $this->discountPriceType = 'percentage';
        $this->resetValidation();
    }

    public function getFilteredVariants(): Collection
    {
        $variants = $this->variants;

        // Search filter
        if ($this->searchVariants) {
            $variants = $variants->filter(function ($variant) {
                return str_contains(strtolower($variant->sku), strtolower($this->searchVariants)) ||
                       str_contains(strtolower($variant->color), strtolower($this->searchVariants));
            });
        }

        // Override filter
        if ($this->showOnlyOverrides) {
            $variants = $variants->filter(function ($variant) {
                $channelPrices = $variant->getAllChannelPrices();
                foreach ($channelPrices as $channelData) {
                    if ($channelData['has_override']) {
                        return true;
                    }
                }

                return false;
            });
        }

        return $variants;
    }

    public function getPricingTableData(): array
    {
        // 🚀 CACHE: Pricing table data calculation is expensive - cache for 5 minutes
        $cacheKey = "product_pricing_table_{$this->product->id}_{$this->product->updated_at->timestamp}_{$this->searchVariants}_{$this->showOnlyOverrides}";
        
        return cache()->remember($cacheKey, now()->addMinutes(5), function () {
            $variants = $this->getFilteredVariants();
            $tableData = [];

            foreach ($variants as $variant) {
                $channelPrices = $variant->getAllChannelPrices();

                $variantData = [
                    'variant' => $variant,
                    'default_price' => $variant->getRetailPrice(),
                    'channels' => [],
                    'has_any_override' => false,
                ];

                $retailPrice = $variant->getRetailPrice();
                
                foreach ($this->channels as $channel) {
                    $channelData = $channelPrices[$channel->code] ?? null;

                    $variantData['channels'][$channel->code] = [
                        'price' => $channelData ? $channelData['effective_price'] : $retailPrice,
                        'has_override' => $channelData ? $channelData['has_override'] : false,
                        'markup_percentage' => $retailPrice > 0 && $channelData
                            ? round((($channelData['effective_price'] - $retailPrice) / $retailPrice) * 100, 1)
                            : 0,
                    ];

                    if ($channelData && $channelData['has_override']) {
                        $variantData['has_any_override'] = true;
                    }
                }

                $tableData[] = $variantData;
            }

            return $tableData;
        });
    }

    public function getChannelSummary(): array
    {
        // 🚀 CACHE: Channel summary calculation loops through all variants - cache for 5 minutes
        $cacheKey = "product_channel_summary_{$this->product->id}_{$this->product->updated_at->timestamp}";
        
        return cache()->remember($cacheKey, now()->addMinutes(5), function () {
            $summary = [];

            foreach ($this->channels as $channel) {
                $overrideCount = 0;
                $totalVariants = $this->variants->count();

                foreach ($this->variants as $variant) {
                    if ($variant->hasChannelOverride($channel->code)) {
                        $overrideCount++;
                    }
                }

                $summary[$channel->code] = [
                    'channel' => $channel,
                    'overrides_count' => $overrideCount,
                    'using_default_count' => $totalVariants - $overrideCount,
                    'percentage_with_overrides' => $totalVariants > 0 ? round(($overrideCount / $totalVariants) * 100, 1) : 0,
                ];
            }

            return $summary;
        });
    }

    /**
     * 🚀 CACHE INVALIDATION - Clear pricing caches when data changes
     */
    public function handlePriceUpdate()
    {
        $this->clearPricingCaches();
        $this->product = $this->product->fresh();
        $this->variants = $this->product->variants;
    }

    private function clearPricingCaches(): void
    {
        $baseKeys = [
            "product_pricing_table_{$this->product->id}",
            "product_channel_summary_{$this->product->id}",
        ];
        
        // Clear all variations of cache keys
        foreach ($baseKeys as $baseKey) {
            // Use wildcard pattern to clear all variations
            cache()->forget($baseKey . '*');
        }
    }

    public function render()
    {
        return view('livewire.products.product-pricing', [
            'pricingData' => $this->getPricingTableData(),
            'channelSummary' => $this->getChannelSummary(),
        ]);
    }
}
