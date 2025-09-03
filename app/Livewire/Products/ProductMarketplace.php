<?php

namespace App\Livewire\Products;

use App\Models\Product;
use Livewire\Component;

class ProductMarketplace extends Component
{
    public Product $product;

    public function mount(Product $product)
    {
        // Authorize viewing marketplace connections
        $this->authorize('view-marketplace-connections');

        // 🚀 NO RELATIONSHIP LOADING - ProductShow already loaded syncStatuses.syncAccount and syncLogs
        $this->product = $product;
    }

    public function render()
    {
        return view('livewire.products.product-marketplace');
    }
}
