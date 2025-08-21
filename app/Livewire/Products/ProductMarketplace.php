<?php

namespace App\Livewire\Products;

use App\Models\Product;
use Livewire\Component;

class ProductMarketplace extends Component
{
    public Product $product;

    public function mount(Product $product)
    {
        $this->product = $product->load([
            'syncStatuses.syncAccount',
            'syncLogs' => function ($query) {
                $query->with('syncAccount')->latest()->limit(10);
            },
        ]);
    }

    public function render()
    {
        return view('livewire.products.product-marketplace');
    }
}
