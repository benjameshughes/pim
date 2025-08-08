<?php

namespace App\Livewire\Pim\Barcodes;

use App\Models\Barcode;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

// Layout handled by wrapper template
class BarcodeIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $typeFilter = '';

    public int $perPage = 25;

    protected $listeners = [
        'refreshList' => '$refresh',
    ];

    /**
     * Update search and reset pagination
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Update filter and reset pagination
     */
    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Get barcodes query with filters
     */
    public function getBarcodes()
    {
        $query = Barcode::query()
            ->with(['variant.product:id,name']);

        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->where('barcode', 'like', "%{$this->search}%")
                    ->orWhereHas('variant.product', function ($productQuery) {
                        $productQuery->where('name', 'like', "%{$this->search}%");
                    });
            });
        }

        if (! empty($this->typeFilter)) {
            $query->where('barcode_type', $this->typeFilter);
        }

        return $query->latest()->paginate($this->perPage);
    }

    /**
     * Render the component
     */
    public function render()
    {
        return view('livewire.pim.barcodes.barcode-index', [
            'barcodes' => $this->getBarcodes(),
            'barcodeTypes' => ['EAN13', 'UPC', 'CODE128'],
        ]);
    }
}
