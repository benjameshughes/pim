<?php

namespace App\Livewire\BulkOperations;

use Illuminate\Database\Eloquent\Model;

/**
 * 🚀 BULK PRICING OPERATION
 *
 * Dedicated full-page component for bulk pricing operations.
 * Supports fixed pricing, percentage adjustments, and formula-based pricing.
 * Handles both products and variants with appropriate field mapping.
 */
class BulkPricingOperation extends BaseBulkOperation
{
    // Form data for pricing operations
    /** @var array<string, string|float|null> */
    public array $pricingData = [
        'update_type' => 'fixed',
        'new_price' => null,
        'percentage' => null,
        'formula' => null,
    ];

    // Preview calculation
    public ?float $previewPrice = null;

    /**
     * 🎯 Initialize pricing operation
     */
    public function mount(string $targetType, mixed $selectedItems): void
    {
        parent::mount($targetType, $selectedItems);
        $this->updatePreview();
    }

    /**
     * 💰 Apply bulk pricing operation
     */
    public function applyBulkPricing(): void
    {
        if (empty($this->selectedItems)) {
            return;
        }

        $this->validate([
            'pricingData.update_type' => 'required|in:fixed,percentage,formula',
            'pricingData.new_price' => 'required_if:pricingData.update_type,fixed|nullable|numeric|min:0',
            'pricingData.percentage' => 'required_if:pricingData.update_type,percentage|nullable|numeric',
            'pricingData.formula' => 'required_if:pricingData.update_type,formula|nullable|string',
        ]);

        $this->executeBulkOperation(
            operation: fn (Model $item) => $this->updateItemPrice($item),
            operationType: 'updated pricing for'
        );
    }

    /**
     * 💱 Update individual item price
     *
     * @param  \App\Models\Product|\App\Models\ProductVariant  $item
     */
    private function updateItemPrice(Model $item): void
    {
        $priceField = $this->targetType === 'products' ? 'retail_price' : 'price';
        $currentPrice = (float) $item->{$priceField};
        $newPrice = $this->calculateNewPrice($currentPrice);

        if ($newPrice !== null && $newPrice >= 0) {
            $item->update([$priceField => round($newPrice, 2)]);
        }
    }

    /**
     * 🧮 Calculate new price based on update type
     */
    private function calculateNewPrice(float $currentPrice): ?float
    {
        switch ($this->pricingData['update_type']) {
            case 'fixed':
                return (float) $this->pricingData['new_price'];

            case 'percentage':
                $percentage = (float) $this->pricingData['percentage'];

                return $currentPrice * (1 + $percentage / 100);

            case 'formula':
                return $this->evaluateFormula($currentPrice, (string) $this->pricingData['formula']);

            default:
                return null;
        }
    }

    /**
     * 🔢 Safely evaluate pricing formula
     */
    private function evaluateFormula(float $currentPrice, string $formula): float
    {
        // Replace 'price' with current price value
        $formula = str_replace('price', (string) $currentPrice, $formula);

        // Basic safety check - only allow basic math operations
        if (! preg_match('/^[\d\s\+\-\*\/\(\)\.]+$/', $formula)) {
            return $currentPrice; // Invalid formula, keep current price
        }

        try {
            // Use eval carefully with sanitized input
            $newPrice = null;
            eval('$newPrice = '.$formula.';');

            return (float) $newPrice;
        } catch (\Exception $e) {
            return $currentPrice; // Fallback to current price on error
        }
    }

    /**
     * 👁️ Update price preview when form data changes
     */
    public function updatedPricingData(): void
    {
        $this->updatePreview();
    }

    /**
     * 🔮 Calculate preview price for display
     */
    private function updatePreview(): void
    {
        if ($this->pricingData['update_type'] === 'fixed' && $this->pricingData['new_price']) {
            $this->previewPrice = (float) $this->pricingData['new_price'];
        } elseif ($this->pricingData['update_type'] === 'percentage' && $this->pricingData['percentage']) {
            // Use example price of $100 for percentage preview
            $examplePrice = 100.0;
            $percentage = (float) $this->pricingData['percentage'];
            $this->previewPrice = $examplePrice * (1 + $percentage / 100);
        } elseif ($this->pricingData['update_type'] === 'formula' && $this->pricingData['formula']) {
            // Use example price of $100 for formula preview
            $this->previewPrice = $this->evaluateFormula(100.0, (string) $this->pricingData['formula']);
        } else {
            $this->previewPrice = null;
        }
    }

    /**
     * 📊 Get average current price of selected items
     */
    public function getAverageCurrentPriceProperty(): float
    {
        $items = $this->getSelectedItemsCollection();
        $priceField = $this->targetType === 'products' ? 'retail_price' : 'price';

        $total = $items->sum($priceField);
        $count = $items->count();

        return $count > 0 ? $total / $count : 0;
    }

    /**
     * 🎨 Render the bulk pricing operation component
     */
    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.bulk-operations.bulk-pricing-operation');
    }
}
