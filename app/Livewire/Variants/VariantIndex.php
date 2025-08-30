<?php

namespace App\Livewire\Variants;

use App\Models\ProductVariant;
use Livewire\Component;
use Livewire\WithPagination;

class VariantIndex extends Component
{
    use WithPagination;

    public $search = '';

    public $status = 'all';

    public $color = 'all';
    
    public function mount()
    {
        // Authorize viewing variants
        $this->authorize('view-variants');
    }

    public function render()
    {
        // ✨ PHOENIX PAGINATION POWER
        $variants = ProductVariant::query()
            ->with(['product', 'barcode'])
            ->when($this->search, fn ($query) => $query->where('title', 'like', '%'.$this->search.'%')
                ->orWhere('sku', 'like', '%'.$this->search.'%')
                ->orWhere('color', 'like', '%'.$this->search.'%')
                ->orWhereHas('product', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%')))
            ->when($this->status !== 'all', fn ($query) => $query->where('status', $this->status))
            ->when($this->color !== 'all', fn ($query) => $query->where('color', $this->color))
            ->orderBy('updated_at', 'desc')
            ->paginate(20);

        // Define columns for our enhanced flux:table
        $columns = [
            ['key' => 'product.name', 'label' => 'Product', 'sortable' => true],
            ['key' => 'sku', 'label' => 'SKU', 'sortable' => true],
            ['key' => 'color', 'label' => 'Color', 'sortable' => true],
            ['key' => 'width', 'label' => 'Width (cm)', 'sortable' => true],
            ['key' => 'price', 'label' => 'Price', 'sortable' => true],
            ['key' => 'stock_level', 'label' => 'Stock', 'sortable' => true],
            ['key' => 'status', 'label' => 'Status', 'sortable' => true],
            ['key' => 'actions', 'label' => 'Actions', 'sortable' => false],
        ];

        // ✨ PHOENIX BULK ACTIONS
        $bulkActions = [
            ['key' => 'delete', 'label' => 'Delete Selected', 'icon' => 'trash'],
            ['key' => 'activate', 'label' => 'Activate', 'icon' => 'check-circle'],
            ['key' => 'deactivate', 'label' => 'Deactivate', 'icon' => 'x-circle'],
            ['key' => 'export', 'label' => 'Export CSV', 'icon' => 'arrow-down-tray'],
        ];

        // Get unique colors for filter (keeping for existing filters)
        $colors = ProductVariant::distinct()->orderBy('color')->pluck('color');

        return view('livewire.variants.variant-index', compact('variants', 'columns', 'colors', 'bulkActions'));
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatus()
    {
        $this->resetPage();
    }

    public function updatingColor()
    {
        $this->resetPage();
    }
}
