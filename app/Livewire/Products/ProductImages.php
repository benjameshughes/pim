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
        // Authorize viewing images
        $this->authorize('view-images');

        // 🚀 NO RELATIONSHIP LOADING - ProductShow already loaded images
        $this->product = $product;
    }
    
    /**
     * 🔄 REFRESH IMAGES - Force reload images relationship
     */
    public function refreshImages()
    {
        $this->product = $this->product->fresh(['images']);
        $this->dispatch('success', 'Images refreshed successfully!');
    }

    public function render()
    {
        return view('livewire.products.product-images');
    }
}
