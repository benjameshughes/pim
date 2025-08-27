<?php

namespace App\Livewire\Barcodes;

use App\Models\Barcode;
use App\Events\Barcodes\BarcodeImportProgress;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\On;

/**
 * 📊 BARCODE CSV IMPORT WITH COLUMN MAPPING
 *
 * Simple CSV import with drag & drop and column mapping
 */
class BarcodeImport extends Component
{
    use WithFileUploads;

    public $csvFile;

    protected $rules = [
        'csvFile' => 'required|file|mimes:csv,txt|max:10240'
    ];
    public $csvData = [];
    public $columnMapping = [];
    public $availableColumns = [];
    public $step = 1; // 1: Upload, 2: Map, 3: Import
    public $importResults = [];
    public $totalRows = 0;

    // Available database columns for mapping
    public $databaseColumns = [
        'barcode' => 'Barcode (Required)',
        'sku' => 'SKU',
        'title' => 'Title/Description',
        'is_assigned' => 'Assigned Status',
    ];

    public function updatedCsvFile()
    {
        $this->validate([
            'csvFile' => 'required|file|mimes:csv,txt|max:10240'
        ]);

        $this->parseCsv();
    }

    public function parseCsv()
    {
        try {
            $path = $this->csvFile->getRealPath();
            $handle = fopen($path, 'rb');
            
            if (!$handle) {
                $this->dispatch('error', 'Cannot open CSV file');
                return;
            }

            // Get headers from first row only
            $this->availableColumns = fgetcsv($handle);
            
            if (empty($this->availableColumns)) {
                $this->dispatch('error', 'CSV file appears to be empty');
                fclose($handle);
                return;
            }
            
            // Get total row count (fast!)
            $this->totalRows = count(file($path)) - 1; // -1 for header row
            
            // Get sample data (next 5 rows for preview)
            $this->csvData = [];
            for ($i = 0; $i < 5 && ($row = fgetcsv($handle)) !== false; $i++) {
                $this->csvData[] = $row;
            }
            
            fclose($handle);
            
            // Initialize column mapping with smart defaults
            $this->initializeMapping();
            
            $this->step = 2;
        } catch (\Exception $e) {
            $this->dispatch('error', 'Error reading CSV file: ' . $e->getMessage());
        }
    }

    public function initializeMapping()
    {
        // Smart mapping based on common column names (CSV index => DB field)
        foreach ($this->availableColumns as $index => $column) {
            $lowerColumn = strtolower(trim($column));
            
            if (str_contains($lowerColumn, 'barcode') || str_contains($lowerColumn, 'number')) {
                $this->columnMapping[$index] = 'barcode';
            } elseif (str_contains($lowerColumn, 'sku')) {
                $this->columnMapping[$index] = 'sku';
            } elseif (str_contains($lowerColumn, 'name') || str_contains($lowerColumn, 'product name') || str_contains($lowerColumn, 'title')) {
                $this->columnMapping[$index] = 'title';
            } elseif (str_contains($lowerColumn, 'description')) {
                $this->columnMapping[$index] = 'title';
            } elseif (str_contains($lowerColumn, 'assign')) {
                $this->columnMapping[$index] = 'is_assigned';
            }
        }
    }

    public $importId;
    public $isImporting = false;
    public $importProgress = [];
    public $progressCount = 0;
    
    public function mount()
    {
        // Get importId and totalRows from URL parameters
        $this->importId = request('id');
        $this->totalRows = request('total', 0);
        
        // If we have an importId, we're on step 3 (importing)
        if ($this->importId) {
            $this->step = 3;
            $this->isImporting = true;
        }
    }

    public function importBarcodes()
    {
        // Validate required mapping
        if (!in_array('barcode', $this->columnMapping)) {
            $this->dispatch('error', 'Barcode column mapping is required');
            return;
        }

        try {
            // Generate unique import ID
            $this->importId = uniqid('barcode_import_');
            $this->isImporting = true;
            
            // Set initial heartbeat
            \Cache::put("import_heartbeat_{$this->importId}", now(), 60);
            
            
            // Make sure imports directory exists
            if (!file_exists(storage_path('app/imports'))) {
                mkdir(storage_path('app/imports'), 0755, true);
            }
            
            // Use the temporary file directly instead of trying to store it
            $tempPath = $this->csvFile->getRealPath();
            
            // Copy to permanent location for queue processing
            $fileName = 'import_' . $this->importId . '.csv';
            $fullPath = storage_path('app/imports/' . $fileName);
            
            if (!copy($tempPath, $fullPath)) {
                throw new \Exception("Failed to copy CSV file for processing");
            }
            
            // Flip mapping for job: [0 => 'barcode'] becomes ['barcode' => 0]
            $flippedMapping = array_flip($this->columnMapping);
            
            // Dispatch the queue job with clean mapping: ['barcode' => 0, 'sku' => 8, 'title' => 2]
            \App\Jobs\ProcessBarcodeImport::dispatch($fullPath, $this->importId, $flippedMapping);
            
            $this->step = 3;
            $this->dispatch('success', 'Import started! Processing in background...');
            
            // Add importId and totalRows to URL so component can listen to the right channel
            $this->redirectRoute('barcodes.import', ['id' => $this->importId, 'total' => $this->totalRows], navigate: true);

        } catch (\Exception $e) {
            $this->dispatch('error', 'Import failed: ' . $e->getMessage());
            $this->isImporting = false;
        }
    }

    public function getListeners()
    {
        \Log::info("getListeners called with importId: " . ($this->importId ?? 'null'));
        
        if (!$this->importId) {
            return [];
        }
        
        \Log::info("Registering listener: echo:barcode-import.{$this->importId},.BarcodeImportProgress");
        
        return [
            "echo:barcode-import.{$this->importId},.BarcodeImportProgress" => 'updateProgress',
        ];
    }
    
    public function updateProgress($event)
    {
        \Log::info("Received progress event", ['event' => $event, 'currentImportId' => $this->importId]);
        
        // Send heartbeat to keep job alive
        \Cache::put("import_heartbeat_{$this->importId}", now(), 60);
        
        \Log::info("Updating progress count to: " . $event['processed']);
        $this->progressCount = $event['processed'];
        
        if ($event['status'] === 'completed') {
            $this->isImporting = false;
            $this->importResults = [
                'imported' => $event['processed'],
                'processed' => $event['processed'],
                'skipped' => 0,
                'errors' => []
            ];
        }
    }

    private function mapRowData($row)
    {
        $data = [];
        
        foreach ($this->columnMapping as $csvIndex => $dbColumn) {
            if ($dbColumn && isset($row[$csvIndex])) {
                $data[$dbColumn] = trim($row[$csvIndex]);
            }
        }

        return $data;
    }

    private function convertToBoolean($value)
    {
        $value = strtolower(trim($value));
        return in_array($value, ['1', 'true', 'yes', 'assigned']);
    }

    public function startOver()
    {
        $this->reset();
        $this->step = 1;
    }


    public function render()
    {
        return view('livewire.barcodes.barcode-import');
    }
}
