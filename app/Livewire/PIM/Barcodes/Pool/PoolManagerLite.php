<?php

namespace App\Livewire\Pim\Barcodes\Pool;

use App\Models\BarcodePool;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class PoolManagerLite extends Component
{
    use WithPagination;

    public $search = '';

    public $statusFilter = '';

    protected $paginationTheme = 'bootstrap';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function render()
    {
        // Ultra-simple query - no relationships loaded upfront
        $query = BarcodePool::select(['id', 'barcode', 'barcode_type', 'status', 'assigned_to_variant_id', 'assigned_at'])
            ->when($this->search, function ($query) {
                $query->where('barcode', 'like', '%'.$this->search.'%');
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->orderBy('id');

        // Get basic stats with raw queries
        $stats = $this->getBasicStats();

        return view('livewire.pim.barcodes.pool.pool-manager-lite', [
            'barcodes' => $query->paginate(25), // Smaller page size
            'stats' => $stats,
        ]);
    }

    private function getBasicStats()
    {
        // Ultra-simple stats to minimize memory usage
        try {
            return [
                'total' => DB::scalar('SELECT COUNT(*) FROM barcode_pools'),
                'available' => DB::scalar('SELECT COUNT(*) FROM barcode_pools WHERE status = "available"'),
                'assigned' => DB::scalar('SELECT COUNT(*) FROM barcode_pools WHERE status = "assigned"'),
            ];
        } catch (\Exception $e) {
            // If stats fail, return zeros to prevent view errors
            return [
                'total' => 0,
                'available' => 0,
                'assigned' => 0,
            ];
        }
    }
}
