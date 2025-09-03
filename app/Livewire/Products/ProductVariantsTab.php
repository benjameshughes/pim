<?php

namespace App\Livewire\Products;

use App\Models\Product;
use Livewire\Component;

class ProductVariantsTab extends Component
{
    public Product $product;

    public function mount(Product $product)
    {
        // Authorize viewing variants
        $this->authorize('view-variants');

        // 🚀 NO RELATIONSHIP LOADING - ProductShow already loaded variants.barcode
        $this->product = $product;
    }

    public function render()
    {
        return view('livewire.products.product-variants-tab');
    }
}
