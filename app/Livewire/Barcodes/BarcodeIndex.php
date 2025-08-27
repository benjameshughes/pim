<?php

namespace App\Livewire\Barcodes;

use App\Models\Barcode;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * 📊 BARCODE MANAGEMENT INDEX
 *
 * Clean barcode listing with search and filtering
 */
class BarcodeIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public string $assignedFilter = 'all'; // all, assigned, unassigned
    public int $perPage = 20;
    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';

    public function render()
    {
        $barcodes = $this->paginate();

        return view('livewire.barcodes.barcode-index', compact('barcodes'));
    }

    public function paginate()
    {
        return Barcode::query()
            ->when($this->search, fn ($q) => $q->where('barcode', 'like', "%{$this->search}%")
                ->orWhere('sku', 'like', "%{$this->search}%")
                ->orWhere('title', 'like', "%{$this->search}%"))
            ->when($this->assignedFilter === 'assigned', fn ($q) => $q->where('is_assigned', true))
            ->when($this->assignedFilter === 'unassigned', fn ($q) => $q->where('is_assigned', false))
            ->with('variant.product')
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

    public function getStatsProperty()
    {
        return [
            'total' => Barcode::count(),
            'assigned' => Barcode::where('is_assigned', true)->count(),
            'unassigned' => Barcode::where('is_assigned', false)->count(),
        ];
    }
}
