<?php

namespace App\Livewire\Variants;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Validation\Rule;
use Livewire\Component;

class VariantForm extends Component
{
    public ProductVariant $variant;

    public bool $isEditing = false;

    // ✨ VARIANT FORM FIELDS
    public $product_id = '';

    public $sku = '';

    public $title = '';

    public $color = '';

    public $width = '';

    public $drop = '';

    public $max_drop = '';

    public $price = '';

    public $stock_level = 0;

    public $status = 'active';

    public function mount(?ProductVariant $variant = null)
    {
        // Authorize based on mode (create or edit)
        if ($variant && $variant->exists) {
            $this->authorize('edit-variants');
        } else {
            $this->authorize('create-variants');
        }
        
        if ($variant && $variant->exists) {
            $this->variant = $variant;
            $this->isEditing = true;
            $this->fill($variant->toArray());
        } else {
            $this->variant = new ProductVariant;
        }
    }

    /**
     * Get dynamic validation rules that handle both create and edit modes
     */
    protected function rules()
    {
        return [
            'product_id' => 'required|exists:products,id',
            'sku' => [
                'required',
                'string',
                'max:255',
                Rule::unique('product_variants', 'sku')->ignore($this->isEditing ? $this->variant->id : null),
            ],
            'title' => 'nullable|string|max:255',
            'color' => 'required|string|max:100',
            'width' => 'required|numeric|min:1',
            'drop' => 'nullable|numeric|min:1',
            'max_drop' => 'nullable|numeric|min:1',
            'price' => 'required|numeric|min:0',
            'stock_level' => 'required|numeric|min:0',
            'status' => 'required|in:active,inactive',
        ];
    }

    public function save()
    {
        // Authorize save operation
        if ($this->isEditing) {
            $this->authorize('edit-variants');
        } else {
            $this->authorize('create-variants');
        }
        
        $this->validate();

        try {
            if ($this->isEditing) {
                $this->variant->update($this->only([
                    'product_id', 'sku', 'title', 'color',
                    'width', 'drop', 'max_drop', 'price', 'stock_level', 'status',
                ]));

                $this->dispatch('success', 'Variant updated successfully! ✨');
            } else {
                ProductVariant::create($this->only([
                    'product_id', 'sku', 'title', 'color',
                    'width', 'drop', 'max_drop', 'price', 'stock_level', 'status',
                ]));

                $this->dispatch('success', 'Variant created successfully! 🎉');
            }

            // 🧠 SMART TOAST handles persistence automatically!
            return redirect()->route('variants.index');
        } catch (\Exception $e) {
            $this->dispatch('error', 'Failed to save variant: '.$e->getMessage());
        }
    }

    public function render()
    {
        $products = Product::orderBy('name')->get();

        // ✨ PHOENIX INTELLIGENCE: Get existing colors and widths for suggestions
        $existingColors = ProductVariant::distinct()->pluck('color')->sort()->filter()->values();
        $existingWidths = ProductVariant::distinct()->pluck('width')->sort()->unique()->values();

        return view('livewire.variants.variant-form', compact('products', 'existingColors', 'existingWidths'));
    }
}
