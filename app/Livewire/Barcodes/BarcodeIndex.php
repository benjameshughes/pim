<?php

namespace App\Livewire\Barcodes;

use App\Models\Barcode;
use Livewire\Component;
use Livewire\WithPagination;

class BarcodeIndex extends Component
{
    use WithPagination;

    public $search = '';

    public $type = 'all';

    public $status = 'all';

    public function render()
    {
        $barcodes = Barcode::query()
            ->with(['productVariant.product'])
            ->when($this->search, fn ($query) => $query->where('barcode', 'like', '%'.$this->search.'%')
                ->orWhereHas('productVariant', fn ($q) => $q->where('sku', 'like', '%'.$this->search.'%')
                    ->orWhere('color', 'like', '%'.$this->search.'%')
                    ->orWhereHas('product', fn ($pq) => $pq->where('name', 'like', '%'.$this->search.'%'))))
            ->when($this->type !== 'all', fn ($query) => $query->where('type', $this->type))
            ->when($this->status !== 'all', fn ($query) => $query->where('status', $this->status))
            ->orderBy('updated_at', 'desc')
            ->paginate(20);

        return view('livewire.barcodes.barcode-index', compact('barcodes'));
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingType()
    {
        $this->resetPage();
    }

    public function updatingStatus()
    {
        $this->resetPage();
    }
}
