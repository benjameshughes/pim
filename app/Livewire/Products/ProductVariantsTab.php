<?php

namespace App\Livewire\Products;

use App\Models\Product;
use Livewire\Component;

class ProductVariantsTab extends Component
{
    public Product $product;

    public function mount(Product $product)
    {
        $this->product = $product->load(['variants.barcodes']);
    }

    public function render()
    {
        return view('livewire.products.product-variants-tab');
    }
}
