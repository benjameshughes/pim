<?php

namespace App\Livewire\Products;

use App\Models\Product;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * ✨ UNIFIED PRODUCT & VARIANTS INDEX - COLLECTION POWERED
 *
 * Elegant unified interface with expandable variant rows
 * Uses Laravel Collections for clean data handling
 */
class ProductIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = 'all';

    public int $perPage = 15;

    public string $sortField = 'updated_at';

    public string $sortDirection = 'desc';

    // Simple expandable state
    public array $expandedProducts = [];

    public function render()
    {
        $products = $this->paginate();

        return view('livewire.products.product-index', compact('products'));
    }

    public function paginate()
    {
        return Product::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('parent_sku', 'like', "%{$this->search}%")
                ->orWhere('description', 'like', "%{$this->search}%")
                ->orWhereHas('variants', fn ($vq) => $vq->where('sku', 'like', "%{$this->search}%")
                    ->orWhere('color', 'like', "%{$this->search}%")))
            ->when($this->status !== 'all', fn ($q) => $q->where('status', $this->status))
            ->with(['shopifySyncStatus'])
            ->withCount('variants')
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);
    }

    public function updating()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    /**
     * 🎯 Dead simple expand/collapse
     */
    public function toggleExpand(int $productId): void
    {
        if (isset($this->expandedProducts[$productId])) {
            // Collapse: Remove from array
            unset($this->expandedProducts[$productId]);
        } else {
            // Expand: Fetch and store variants
            $product = Product::find($productId);
            if ($product) {
                $this->expandedProducts[$productId] = $product->variants()
                    ->with(['pricing'])
                    ->get()
                    ->toArray();
            }
        }
    }
}
