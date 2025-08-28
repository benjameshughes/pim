<?php

namespace App\Livewire\Variants;

use App\Models\ProductVariant;
use Livewire\Component;

class VariantShow extends Component
{
    public ProductVariant $variant;

    public function mount(ProductVariant $variant)
    {
        $this->variant = $variant->load([
            'product',
            'barcode',
            'pricingRecords.salesChannel',
            'product.variants' => function ($query) {
                $query->select('id', 'product_id', 'sku', 'color', 'width', 'drop', 'price', 'status')
                    ->orderBy('sku');
            },
        ]);
    }

    public function deleteVariant()
    {
        $variantName = $this->variant->sku.' - '.($this->variant->color ?? 'No Color');
        $productId = $this->variant->product_id;

        $this->variant->delete();

        $this->dispatch('success', "Variant '{$variantName}' deleted successfully! 🗑️");

        return $this->redirect(route('products.show', $productId), navigate: true);
    }

    public function duplicateVariant()
    {
        $newVariant = $this->variant->replicate();
        $newVariant->sku = $this->variant->sku.'-COPY';
        $newVariant->save();

        $this->dispatch('success', 'Variant duplicated successfully! ✨');

        return $this->redirect(route('variants.show', $newVariant), navigate: true);
    }

    public function render()
    {
        return view('livewire.variants.variant-show');
    }
}
