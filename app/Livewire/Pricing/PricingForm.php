<?php

namespace App\Livewire\Pricing;

use Livewire\Component;

class PricingForm extends Component
{
    public function mount()
    {
        // Authorize editing pricing
        $this->authorize('edit-pricing');
    }

    public function render()
    {
        return view('livewire.pricing.pricing-form');
    }
}
