<?php

namespace App\Livewire\Products\Wizard;

use App\Models\Product;
use Livewire\Component;

/**
 * 💰 PRICING & STOCK STEP - Placeholder
 *
 * Simplified placeholder for pricing and stock management.
 * Will be enhanced later with more sophisticated pricing options.
 */
class PricingStockStep extends Component
{
    // Component state
    public array $stepData = [];

    public bool $isActive = false;

    public int $currentStep = 4;

    public bool $isEditMode = false;

    public ?Product $product = null;

    // Form data
    public array $variant_pricing = [];

    /**
     * 🎪 MOUNT
     */
    public function mount(
        array $stepData = [],
        bool $isActive = false,
        int $currentStep = 4,
        bool $isEditMode = false,
        ?Product $product = null
    ): void {
        $this->stepData = $stepData;
        $this->isActive = $isActive;
        $this->currentStep = $currentStep;
        $this->isEditMode = $isEditMode;
        $this->product = $product;

        // Load existing data
        if (! empty($stepData)) {
            $this->variant_pricing = $stepData['variant_pricing'] ?? [];
        }
    }

    /**
     * 🎯 COMPLETE STEP
     */
    public function completeStep(): void
    {
        $this->dispatch('step-completed', 4, $this->getFormData());
    }

    /**
     * 📊 GET FORM DATA
     */
    protected function getFormData(): array
    {
        return [
            'variant_pricing' => $this->variant_pricing,
        ];
    }

    /**
     * 🎨 RENDER
     */
    public function render()
    {
        return view('livewire.products.wizard.pricing-stock-step');
    }
}
