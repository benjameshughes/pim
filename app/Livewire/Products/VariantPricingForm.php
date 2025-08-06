<?php

namespace App\Livewire\Products;

use App\Models\Pricing;
use App\Models\ProductVariant;
use App\Models\SalesChannel;
use Livewire\Attributes\Validate;
use Livewire\Component;

class VariantPricingForm extends Component
{
    public ProductVariant $variant;
    public $selectedChannel = 'website';
    
    #[Validate('required|numeric|min:0')]
    public $retail_price = '';
    
    #[Validate('nullable|numeric|min:0')]
    public $cost_price = '';
    
    #[Validate('required|numeric|min:0|max:100')]
    public $vat_percentage = 20.00;
    
    public $vat_inclusive = true;
    
    #[Validate('nullable|numeric|min:0')]
    public $shipping_cost = '';
    
    #[Validate('nullable|numeric|min:0|max:100')]
    public $channel_fee_percentage = '';

    public function mount(ProductVariant $variant)
    {
        $this->variant = $variant;
        $this->loadExistingPricing();
    }

    public function updatedSelectedChannel()
    {
        $this->loadExistingPricing();
    }

    public function loadExistingPricing()
    {
        $pricing = $this->variant->pricing()
            ->where('marketplace', $this->selectedChannel)
            ->first();

        if ($pricing) {
            $this->retail_price = $pricing->retail_price;
            $this->cost_price = $pricing->cost_price;
            $this->vat_percentage = $pricing->vat_percentage;
            $this->vat_inclusive = $pricing->vat_inclusive;
            $this->shipping_cost = $pricing->shipping_cost;
            $this->channel_fee_percentage = $pricing->channel_fee_percentage;
        } else {
            // Load defaults from sales channel
            $channel = SalesChannel::where('slug', $this->selectedChannel)->first();
            if ($channel) {
                $this->channel_fee_percentage = $channel->default_fee_percentage;
            }
            
            // Reset other fields
            $this->retail_price = '';
            $this->cost_price = '';
            $this->shipping_cost = '';
        }
    }

    public function savePricing()
    {
        $this->validate();

        $pricing = $this->variant->pricing()
            ->where('marketplace', $this->selectedChannel)
            ->first();

        if (!$pricing) {
            $pricing = new Pricing([
                'product_variant_id' => $this->variant->id,
                'marketplace' => $this->selectedChannel,
            ]);
        }

        $pricing->fill([
            'retail_price' => $this->retail_price,
            'cost_price' => $this->cost_price,
            'vat_percentage' => $this->vat_percentage,
            'vat_inclusive' => $this->vat_inclusive,
            'shipping_cost' => $this->shipping_cost,
            'channel_fee_percentage' => $this->channel_fee_percentage,
        ]);

        $pricing->recalculateAndSave();

        session()->flash('success', 'Pricing saved and calculated successfully!');
    }

    public function deletePricing()
    {
        $pricing = $this->variant->pricing()
            ->where('marketplace', $this->selectedChannel)
            ->first();

        if ($pricing) {
            $pricing->delete();
            $this->loadExistingPricing();
            session()->flash('success', 'Pricing deleted successfully!');
        }
    }

    public function render()
    {
        $channels = SalesChannel::where('is_active', true)->get();
        $currentPricing = $this->variant->pricing()
            ->where('marketplace', $this->selectedChannel)
            ->first();

        // Calculate preview if we have retail price
        $preview = null;
        if ($this->retail_price) {
            $preview = new Pricing([
                'retail_price' => $this->retail_price,
                'cost_price' => $this->cost_price,
                'vat_percentage' => $this->vat_percentage,
                'vat_inclusive' => $this->vat_inclusive,
                'shipping_cost' => $this->shipping_cost,
                'channel_fee_percentage' => $this->channel_fee_percentage,
            ]);
            $preview->calculatePricing();
        }

        return view('livewire.products.variant-pricing-form', [
            'channels' => $channels,
            'currentPricing' => $currentPricing,
            'preview' => $preview,
        ]);
    }
}
