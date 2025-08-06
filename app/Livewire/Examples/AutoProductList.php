<?php

namespace App\Livewire\Examples;

use App\Models\Product;
use App\StackedList\Concerns\HasAutoStackedList;
use Livewire\Component;
use Livewire\WithPagination;

class AutoProductList extends Component
{
    use WithPagination;
    use HasAutoStackedList;

    // Define the model to auto-generate from
    protected string $autoStackedListModel = Product::class;

    public function mount()
    {
        // Customize the auto-generation
        $this->hideColumns(['description', 'features', 'slug', 'metadata'])
             ->badgeColumns(['featured', 'is_active'])
             ->listTitle('Auto-Generated Products')
             ->listSubtitle('This list was automatically generated from the Product model');
    }

    public function render()
    {
        $products = Product::with(['variants'])
            ->withCount('variants')
            ->paginate(15);

        return view('livewire.examples.auto-product-list', [
            'products' => $products,
            'config' => $this->getAutoStackedListConfig()
        ]);
    }

    // You can still define custom methods for actions
    public function activateProduct($productId)
    {
        Product::find($productId)->update(['status' => 'active']);
        session()->flash('success', 'Product activated successfully!');
    }

    public function deactivateProduct($productId)
    {
        Product::find($productId)->update(['status' => 'inactive']);
        session()->flash('success', 'Product deactivated successfully!');
    }
}