<?php

namespace App\Livewire\PIM\Barcodes\Pool;

use App\Models\BarcodePool;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

#[Layout('components.layouts.app')]
class PoolManager extends Component
{
    use WithFileUploads, WithPagination;

    public $file;
    public $barcodeType = 'EAN13';
    public $search = '';
    public $statusFilter = '';
    public $importing = false;
    public $importResults = [];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function importBarcodes()
    {
        $this->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
            'barcodeType' => 'required|in:EAN13,EAN8,UPC,CODE128,CODE39,CODABAR',
        ]);

        $this->importing = true;

        try {
            $barcodes = [];

            // Handle different file types
            $extension = $this->file->getClientOriginalExtension();
            
            if (in_array($extension, ['xlsx', 'xls', 'csv'])) {
                // Excel/CSV files
                $data = Excel::toArray(null, $this->file)[0];
                
                foreach ($data as $row) {
                    if (is_array($row) && !empty($row[0])) {
                        $barcodes[] = $row[0]; // Assume barcodes are in first column
                    }
                }
            } else {
                // Text files - one barcode per line
                $content = file_get_contents($this->file->getRealPath());
                $barcodes = array_filter(array_map('trim', explode("\n", $content)));
            }

            // Import barcodes
            $this->importResults = BarcodePool::importBarcodes($barcodes, $this->barcodeType);
            
            session()->flash('success', 
                "Import completed! Imported: {$this->importResults['imported']}, " .
                "Skipped: {$this->importResults['skipped']}"
            );

        } catch (\Exception $e) {
            session()->flash('error', 'Import failed: ' . $e->getMessage());
        }

        $this->importing = false;
        $this->file = null;
    }

    public function releaseBarcode($poolId)
    {
        $pool = BarcodePool::findOrFail($poolId);
        
        if ($pool->release()) {
            session()->flash('success', 'Barcode released back to pool.');
        } else {
            session()->flash('error', 'Failed to release barcode.');
        }
    }

    public function deleteFromPool($poolId)
    {
        $pool = BarcodePool::findOrFail($poolId);
        
        if ($pool->status === 'assigned') {
            session()->flash('error', 'Cannot delete assigned barcode. Release it first.');
            return;
        }
        
        $pool->delete();
        session()->flash('success', 'Barcode removed from pool.');
    }

    public function downloadSampleFile()
    {
        $sampleData = [
            '1234567890123',
            '2345678901234', 
            '3456789012345',
            '4567890123456',
            '5678901234567',
        ];

        $filename = 'barcode_sample.csv';
        $content = "Barcode\n" . implode("\n", $sampleData);

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Debug method to monitor memory usage - remove in production
     */
    private function logMemoryUsage(string $stage): void
    {
        if (config('app.debug')) {
            $memory = round(memory_get_usage(true) / 1024 / 1024, 2);
            $peak = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
            logger()->info("BarcodePoolManager [{$stage}]: {$memory}MB current, {$peak}MB peak");
        }
    }

    public function render()
    {
        $this->logMemoryUsage('start');
        $query = BarcodePool::query()
            ->select([
                'barcode_pools.id',
                'barcode_pools.barcode',
                'barcode_pools.barcode_type',
                'barcode_pools.status',
                'barcode_pools.assigned_to_variant_id',
                'barcode_pools.assigned_at',
            ])
            ->when($this->search, function ($query) {
                $searchTerm = '%' . $this->search . '%';
                
                $query->where(function ($subQuery) use ($searchTerm) {
                    $subQuery->where('barcode_pools.barcode', 'like', $searchTerm)
                        ->orWhereExists(function ($exists) use ($searchTerm) {
                            $exists->select('id')
                                ->from('product_variants')
                                ->join('products', 'products.id', '=', 'product_variants.product_id')
                                ->whereColumn('product_variants.id', 'barcode_pools.assigned_to_variant_id')
                                ->where(function ($whereClause) use ($searchTerm) {
                                    $whereClause->where('products.name', 'like', $searchTerm)
                                        ->orWhere('product_variants.sku', 'like', $searchTerm);
                                });
                        });
                });
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->orderBy('id');

        $this->logMemoryUsage('before_pagination');
        
        // Use cursor pagination for better memory efficiency with large datasets
        $barcodes = $query->cursorPaginate(50);
        
        $this->logMemoryUsage('after_pagination');
        
        // Only load relationships after pagination to minimize memory usage
        $barcodes->load([
            'assignedVariant:id,product_id,sku',
            'assignedVariant.product:id,name'
        ]);

        $this->logMemoryUsage('after_relationships');

        return view('livewire.pim.barcodes.pool.pool-manager', [
            'barcodes' => $barcodes,
            'stats' => BarcodePool::getStats(),
            'barcodeTypes' => ['EAN13', 'EAN8', 'UPC', 'CODE128', 'CODE39', 'CODABAR'],
            'statuses' => BarcodePool::STATUSES,
        ]);
    }
}