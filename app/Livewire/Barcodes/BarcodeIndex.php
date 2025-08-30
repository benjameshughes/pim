<?php

namespace App\Livewire\Barcodes;

use App\Models\Barcode;
use App\Actions\Barcodes\MarkBarcodesAssigned;
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
    
    public function mount()
    {
        // Authorize viewing barcodes
        $this->authorize('view-barcodes');
    }
    public string $assignedFilter = 'all'; // all, assigned, unassigned
    public int $perPage = 20;
    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';
    
    public bool $showMarkAssignedModal = false;
    public string $upToBarcode = '';
    public int $markCount = 40000;
    public bool $setEmptyTitles = false;
    public string $defaultTitle = 'Assigned Barcode';

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
    
    public function openMarkAssignedModal()
    {
        $this->showMarkAssignedModal = true;
        $this->upToBarcode = '';
        $this->markCount = 40000;
        $this->setEmptyTitles = false;
        $this->defaultTitle = 'Assigned Barcode';
    }
    
    public function closeMarkAssignedModal()
    {
        $this->showMarkAssignedModal = false;
        $this->upToBarcode = '';
        $this->markCount = 40000;
    }
    
    public function markBarcodesAssigned()
    {
        // Authorize editing barcodes
        $this->authorize('edit-barcodes');
        
        $action = new MarkBarcodesAssigned();
        
        if (!empty($this->upToBarcode)) {
            $updated = $action->execute($this->upToBarcode, null, $this->setEmptyTitles ? $this->defaultTitle : null);
            $this->dispatch('success', "Marked {$updated} barcodes up to '{$this->upToBarcode}' as assigned");
        } else {
            $updated = $action->execute(null, $this->markCount, $this->setEmptyTitles ? $this->defaultTitle : null);
            $this->dispatch('success', "Marked {$updated} barcodes as assigned");
        }
        
        $this->closeMarkAssignedModal();
        $this->resetPage();
    }
}
