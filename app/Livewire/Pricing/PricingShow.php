<?php

namespace App\Livewire\Pricing;

use Livewire\Component;

class PricingShow extends Component
{
    public function mount()
    {
        // Authorize viewing pricing
        $this->authorize('view-pricing');
    }
    
    public function render()
    {
        return view('livewire.pricing.pricing-show');
    }
}
