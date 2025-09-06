<?php

namespace App\Livewire\Marketplace\Wizard;

use Livewire\Component;

/**
 * Step1MarketplaceSelect
 *
 * Renders marketplace cards for selection and emits an event to the parent
 * wizard when a marketplace is chosen.
 */
class Step1MarketplaceSelect extends Component
{
    /** @var array<int, array<string,mixed>> */
    public array $availableMarketplaces = [];

    public function mount(array $availableMarketplaces): void
    {
        $this->availableMarketplaces = $availableMarketplaces;
    }

    /** Emit selection to parent wizard using Livewire v3 dispatch. */
    public function choose(string $type): void
    {
        // Livewire v3: dispatch events without targeting; parent #[On] listener will catch it
        $this->dispatch('marketplaceSelected', type: $type);
    }

    public function render()
    {
        return view('livewire.marketplace.wizard.step1-marketplace-select');
    }
}
