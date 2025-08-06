<?php

namespace App\Livewire\PIM\Barcodes\Pool;

use App\Actions\Import\ImportBarcodePoolAction;
use App\Models\BarcodePool;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Exceptions\ImportException;
use Illuminate\Support\Facades\Storage;

#[Layout('components.layouts.app')]
class BarcodePoolImport extends Component
{
    use WithFileUploads;

    // File upload properties
    #[Validate('required|file|mimes:xlsx,xls,csv,txt|max:102400')] // 100MB max
    public $file;

    // Import configuration
    #[Validate('required|in:EAN13,EAN8,UPC,CODE128,CODE39,CODABAR')]
    public $barcodeType = 'EAN13';

    #[Validate('nullable|integer|min:1')]
    public $legacyThreshold = 40000;

    #[Validate('boolean')]
    public $clearExisting = false;

    #[Validate('boolean')]
    public $validateFormat = true;

    #[Validate('string|max:500')]
    public $legacyNotes = 'Imported from GS1 spreadsheet - legacy archive';

    // UI state
    public $importing = false;
    public $importComplete = false;
    public $importResults = [];
    public $showAdvanced = false;
    public $progressMessage = '';
    public $progressPercent = 0;

    // Statistics
    public $poolStats = [];
    public $batchStats = [];

    public function mount()
    {
        $this->refreshStats();
    }

    public function refreshStats()
    {
        $this->poolStats = BarcodePool::getStats();
        $this->batchStats = BarcodePool::getBatchStats();
    }

    public function toggleAdvanced()
    {
        $this->showAdvanced = !$this->showAdvanced;
    }

    public function importBarcodes()
    {
        $this->validate();

        $this->importing = true;
        $this->importComplete = false;
        $this->importResults = [];

        try {
            // Store the uploaded file temporarily
            $filePath = $this->file->store('temp-imports', 'local');
            $fullPath = Storage::disk('local')->path($filePath);

            // Execute the chunked import action for memory efficiency
            $action = new ImportBarcodePoolAction();
            $this->importResults = $action->executeChunked(
                filePath: $fullPath,
                barcodeType: $this->barcodeType,
                legacyThreshold: $this->legacyThreshold,
                options: [
                    'clear_existing' => $this->clearExisting,
                    'validate_format' => $this->validateFormat,
                    'legacy_notes' => $this->legacyNotes,
                    'chunk_size' => 1000,
                ]
            );

            // Clean up temporary file
            Storage::disk('local')->delete($filePath);

            $this->importComplete = true;
            $this->refreshStats();

            // Flash success message
            $summary = $this->importResults['summary'];
            session()->flash('success', 
                "Import completed successfully! " .
                "Total: {$summary['total_imported']}, " .
                "Available: {$summary['available_for_assignment']}, " .
                "Legacy Archived: {$summary['legacy_archived']}"
            );

        } catch (ImportException $e) {
            session()->flash('error', 'Import failed: ' . $e->getMessage());
        } catch (\Exception $e) {
            session()->flash('error', 'An unexpected error occurred: ' . $e->getMessage());
        } finally {
            $this->importing = false;
            $this->file = null;
        }
    }

    public function clearResults()
    {
        $this->importComplete = false;
        $this->importResults = [];
        $this->file = null;
    }

    public function clearPool()
    {
        try {
            $deletedCount = BarcodePool::where('status', '!=', 'assigned')->count();
            BarcodePool::where('status', '!=', 'assigned')->delete();
            
            $this->refreshStats();
            
            session()->flash('success', "Cleared {$deletedCount} barcodes from pool (kept assigned barcodes).");
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to clear pool: ' . $e->getMessage());
        }
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

    public function render()
    {
        return view('livewire.pim.barcodes.pool.barcode-pool-import', [
            'barcodeTypes' => [
                'EAN13' => 'EAN-13 (13 digits)',
                'EAN8' => 'EAN-8 (8 digits)', 
                'UPC' => 'UPC (12 digits)',
                'CODE128' => 'Code 128',
                'CODE39' => 'Code 39',
                'CODABAR' => 'Codabar',
            ],
        ]);
    }
}