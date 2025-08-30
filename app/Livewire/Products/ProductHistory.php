<?php

namespace App\Livewire\Products;

use App\Models\Product;
use Livewire\Component;

class ProductHistory extends Component
{
    public Product $product;

    public function mount(Product $product)
    {
        // Authorize viewing product history
        $this->authorize('view-product-history');

        $this->product = $product->load([
            'syncLogs' => function ($query) {
                $query->with('syncAccount')->latest()->limit(50);
            },
        ]);
    }

    public function render()
    {
        return view('livewire.products.product-history');
    }
}
