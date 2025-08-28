<?php

namespace App\Livewire\Import;

use App\Actions\Import\SimpleImportAction;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * 🚀 SIMPLE PRODUCT IMPORT - No Bloat, Just Results!
 *
 * Clean, straightforward CSV import for products and variants
 */
class SimpleProductImport extends Component
{
    use WithFileUploads;

    // File upload (validation handled manually due to Livewire temp storage)
    public $file;

    // Import state
    public $step = 'upload'; // upload, mapping, importing, complete

    public $importing = false;

    public $progress = 0;

    // Real-time import tracking
    public $importId = null;

    public $currentAction = '';

    public $currentItem = '';

    public $importStats = [
        'products_created' => 0,
        'products_updated' => 0,
        'variants_created' => 0,
        'variants_updated' => 0,
        'errors' => 0,
        'skipped_rows' => 0,
    ];

    // CSV data
    public $headers = [];

    public $sampleData = [];

    // Column mapping (simple dropdowns)
    public $mappings = [
        'sku' => '',
        'title' => '',
        'barcode' => '',
        'price' => '',
        'brand' => '',
    ];

    // Import results
    public $results = null;

    public function mount()
    {
        // Get importId from URL parameters (when redirected back from import start)
        $this->importId = request('id');
        
        // If we have an importId, we're on step 3 (importing)
        if ($this->importId) {
            $this->step = 'importing';
            $this->importing = true;
        }
    }

    /**
     * Handle file upload and analyze headers
     */
    public function updatedFile()
    {
        if (! $this->file) {
            $this->reset(['step', 'headers', 'sampleData']);

            return;
        }

        try {
            // Read first few rows to get headers and sample data
            $path = $this->file->getRealPath();

            if (! file_exists($path) || ! is_readable($path)) {
                throw new \Exception("File not accessible: {$path}");
            }

            $csv = array_map(function ($line) {
                return str_getcsv($line, ',', '"', '\\');
            }, file($path));

            $this->headers = array_shift($csv); // First row is headers
            $this->sampleData = array_slice($csv, 0, 3); // First 3 data rows for preview

            \Log::info('File analyzed successfully', [
                'headers' => $this->headers,
                'sample_rows' => count($this->sampleData),
                'path' => $path,
            ]);

            // Try to auto-map common columns
            $this->autoMapColumns();

            $this->step = 'mapping';

        } catch (\Exception $e) {
            \Log::error('File analysis failed', [
                'error' => $e->getMessage(),
                'file_path' => $this->file ? $this->file->getRealPath() : 'no file',
            ]);

            $this->addError('file', 'Failed to read file: '.$e->getMessage());
            $this->reset(['step', 'headers', 'sampleData']);
        }
    }

    /**
     * Auto-map columns based on common names
     */
    private function autoMapColumns()
    {
        $commonMappings = [
            'sku' => ['linnworks sku', 'caecus sku', 'sku', 'item code'],
            'title' => ['item title', 'title', 'name', 'product name'],
            'barcode' => ['caecus barcode', 'barcode', 'ean', 'gtin'],
            'price' => ['retail price', 'price', 'cost', 'amount'],
            'brand' => ['brand', 'manufacturer', 'vendor'],
        ];

        foreach ($commonMappings as $field => $patterns) {
            foreach ($this->headers as $index => $header) {
                foreach ($patterns as $pattern) {
                    if (stripos($header, $pattern) !== false) {
                        $this->mappings[$field] = $index;
                        break 2; // Found match, move to next field
                    }
                }
            }
        }
    }

    /**
     * Execute the import
     */
    public function executeImport()
    {
        \Log::info('🔥 EXECUTE IMPORT CALLED - METHOD ENTRY');

        // Skip standard Livewire validation as file may be in temp storage
        // $this->validate();

        \Log::info('📋 CHECKING MAPPINGS', ['mappings' => $this->mappings]);

        // Validate required mappings (check if they're mapped to valid columns)
        // Note: Column 0 is valid, so we check for empty string specifically
        if ($this->mappings['sku'] === '' || $this->mappings['title'] === '') {
            \Log::error('❌ MAPPING VALIDATION FAILED - Required fields not mapped', [
                'sku_value' => $this->mappings['sku'],
                'title_value' => $this->mappings['title'],
                'sku_mapped' => ($this->mappings['sku'] !== ''),
                'title_mapped' => ($this->mappings['title'] !== ''),
            ]);
            $this->addError('mappings', 'SKU and Title columns are required');

            return;
        }

        \Log::info('📄 CHECKING FILE', ['file_exists' => ($this->file ? 'YES' : 'NO')]);

        // Validate file exists
        if (! $this->file) {
            \Log::error('❌ FILE VALIDATION FAILED');
            $this->addError('file', 'No file uploaded');

            return;
        }

        \Log::info('✅ VALIDATIONS PASSED - PROCEEDING WITH IMPORT');

        try {
            // Generate unique import ID and start import
            $this->importId = uniqid('import_' . time() . '_');
            $this->importing = true;
            
            // Get the correct file path for Livewire uploaded files
            $filePath = $this->file->getRealPath();

            // Verify file exists and is readable
            if (! file_exists($filePath) || ! is_readable($filePath)) {
                throw new \Exception("Uploaded file not found or not readable: {$filePath}");
            }

            \Log::info('🚀 Starting import execution', [
                'file_name' => $this->file->getClientOriginalName(),
                'file_size' => $this->file->getSize(),
                'mappings' => $this->mappings,
                'importId' => $this->importId,
            ]);

            // Copy file to permanent location for processing
            $fileName = 'import_' . $this->importId . '.csv';
            $permanentPath = storage_path('app/imports/' . $fileName);
            
            // Make sure imports directory exists
            if (!is_dir(dirname($permanentPath))) {
                mkdir(dirname($permanentPath), 0755, true);
            }
            
            if (!copy($filePath, $permanentPath)) {
                throw new \Exception('Failed to copy file to permanent location');
            }

            $action = new SimpleImportAction;

            $this->results = $action->execute([
                'file' => $permanentPath,
                'mappings' => $this->mappings,
                'importId' => $this->importId,
            ]);

            \Log::info('✅ Import completed successfully', $this->results);

            $this->step = 'complete';
            $this->importing = false;
            
            // Redirect with importId so component can listen to real-time updates
            $this->redirectRoute('import.products', ['id' => $this->importId], navigate: true);

        } catch (\Exception $e) {
            \Log::error('💥 Import failed with exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file_path' => $this->file ? $this->file->getRealPath() : 'no file',
            ]);

            $this->results = [
                'success' => false,
                'message' => 'Import failed: '.$e->getMessage(),
                'created_products' => 0,
                'updated_products' => 0,
                'created_variants' => 0,
                'updated_variants' => 0,
            ];

            $this->step = 'complete';
            $this->importing = false;
        }
    }

    /**
     * Get real-time event listeners
     */
    public function getListeners()
    {
        if (!$this->importId) {
            return [];
        }
        
        return [
            "echo:product-import.{$this->importId},.ProductImportProgress" => 'updateImportProgress',
        ];
    }

    /**
     * Handle real-time import progress updates
     */
    public function updateImportProgress($event)
    {
        \Log::info('📡 Received import progress update', $event);
        
        $this->progress = $event['percentage'] ?? 0;
        $this->currentAction = $event['currentAction'] ?? '';
        $this->currentItem = $event['currentItem'] ?? '';
        
        if (isset($event['stats'])) {
            $this->importStats = array_merge($this->importStats, $event['stats']);
        }
        
        // Handle completion
        if ($event['status'] === 'completed') {
            $this->importing = false;
            $this->step = 'complete';
            $this->progress = 100;
        }
        
        // Handle errors
        if ($event['status'] === 'error') {
            $this->importing = false;
            $this->step = 'complete';
            $this->addError('import', $event['currentItem'] ?? 'Import failed');
        }
    }

    /**
     * Test method to verify button clicks work
     */
    public function testButtonClick()
    {
        \Log::info('🧪 TEST BUTTON CLICKED - This method was called successfully!');
    }

    /**
     * Reset and start over
     */
    public function startOver()
    {
        $this->reset();
        $this->step = 'upload';
    }

    /**
     * Get column options for mapping dropdowns
     */
    public function getColumnOptions()
    {
        $options = ['' => 'Skip this column'];

        foreach ($this->headers as $index => $header) {
            $options[$index] = $header;
        }

        return $options;
    }

    /**
     * Get sample data for a specific column
     */
    public function getSampleData($columnIndex)
    {
        if ($columnIndex === '' || ! isset($this->sampleData[0][$columnIndex])) {
            return ['N/A', 'N/A', 'N/A'];
        }

        return array_map(function ($row) use ($columnIndex) {
            return $row[$columnIndex] ?? 'N/A';
        }, $this->sampleData);
    }

    public function render()
    {
        return view('livewire.import.simple-product-import', [
            'columnOptions' => $this->getColumnOptions(),
        ]);
    }
}
