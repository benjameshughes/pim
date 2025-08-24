<?php

namespace App\Livewire\Products;

use App\Models\Product;
use Livewire\Component;

/**
 * 🖼️ PRODUCT IMAGES TAB
 *
 * Display and manage images for a specific product
 */
class ProductImages extends Component
{
    public Product $product;

    public function mount(Product $product)
    {
        $this->product = $product->load(['images']);
    }

    public function render()
    {
        return view('livewire.products.product-images');
    }
}
