<?php

namespace App\Livewire\Products;

use App\Models\Product;
use Livewire\Component;

class ProductOverview extends Component
{
    public Product $product;

    public function mount(Product $product)
    {
        // Authorize viewing product details
        $this->authorize('view-product-details');
        
        $this->product = $product->load(['variants']);
    }

    public function render()
    {
        return view('livewire.products.product-overview');
    }
}
