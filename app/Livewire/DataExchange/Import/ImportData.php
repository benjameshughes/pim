<?php

namespace App\Livewire\DataExchange\Import;

use App\Events\ProductImported;
use App\Events\ProductVariantImported;
use App\Exceptions\ImportException;
use App\Models\BarcodePool;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesChannel;
use App\Models\Pricing;
use App\Models\Marketplace;
use App\Models\MarketplaceVariant;
use App\Models\MarketplaceBarcode;
use App\Models\ProductAttribute;
use App\Models\VariantAttribute;
use App\Services\ProductAttributeExtractorV2;
use App\Services\AutoParentCreator;
use App\Services\ImportMappingCache;
use App\Services\ProductNameGrouping;
use App\Services\BarcodeDetector;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

#[Layout('components.layouts.app')]
class ImportData extends Component
{
    use WithFileUploads;

    public $step = 1; // 1: Upload, 2: Worksheet Selection, 3: Column Mapping, 4: Dry Run, 5: Import Progress
    
    #[Validate('required|file|mimes:xlsx,xls,csv|max:10240')]
    public $file;
    
    public $availableWorksheets = [];
    public $selectedWorksheets = []; // Array for multiple selections
    public $headers = [];
    public $sampleData = [];
    public $columnMapping = [];
    private $cachedFileData = null;
    public $importProgress = 0;
    public $importStatus = '';
    public $importErrors = [];
    public $importWarnings = [];
    public $importProgressDetails = [];
    public $dryRunResults = [];
    public $autoAssignGS1Barcodes = true;
    public $smartAttributeExtraction = true;
    public $autoCreateParents = true;
    public $autoGenerateParentMode = false; // NEW: treats all rows as variants
    public $importMode = 'create_only'; // 'create_only', 'update_existing', 'create_or_update'
    public $allData = [];
    
    // Import mode options
    public $importModeOptions = [
        'create_only' => 'Create Only (Skip existing SKUs)',
        'update_existing' => 'Update Only (Skip non-existing SKUs)', 
        'create_or_update' => 'Create or Update (Upsert all records)'
    ];

    // Available fields for mapping
    public $availableFields = [
        'product_name' => 'Product Name',
        'description' => 'Description',
        'is_parent' => 'Is Parent (true/false or 1/0)',
        'parent_name' => 'Parent Product Name',
        'product_features_1' => 'Product Feature 1',
        'product_features_2' => 'Product Feature 2',
        'product_features_3' => 'Product Feature 3',
        'product_features_4' => 'Product Feature 4',
        'product_features_5' => 'Product Feature 5',
        'product_details_1' => 'Product Detail 1',
        'product_details_2' => 'Product Detail 2',
        'product_details_3' => 'Product Detail 3',
        'product_details_4' => 'Product Detail 4',
        'product_details_5' => 'Product Detail 5',
        'variant_sku' => 'Variant SKU',
        'variant_color' => 'Variant Color',
        'variant_size' => 'Variant Size',
        'retail_price' => 'Retail Price',
        'cost_price' => 'Cost Price',
        'stock_level' => 'Stock Level',
        'package_length' => 'Package Length',
        'package_width' => 'Package Width', 
        'package_height' => 'Package Height',
        'package_weight' => 'Package Weight',
        'image_urls' => 'Image URLs (comma separated)',
        'barcode' => 'Barcode',
        'barcode_type' => 'Barcode Type',
        'status' => 'Status',
        // Marketplace Fields
        'ebay_title' => 'eBay Title',
        'ebay_description' => 'eBay Description',
        'ebay_price' => 'eBay Price Override',
        'ebay_bo_title' => 'eBay Business Outlet Title',
        'ebay_bo_description' => 'eBay Business Outlet Description',
        'ebay_bo_price' => 'eBay Business Outlet Price',
        'amazon_title' => 'Amazon Title',
        'amazon_description' => 'Amazon Description', 
        'amazon_price' => 'Amazon Price Override',
        'amazon_fba_title' => 'Amazon FBA Title',
        'amazon_fba_description' => 'Amazon FBA Description',
        'amazon_fba_price' => 'Amazon FBA Price',
        'onbuy_title' => 'OnBuy Title',
        'onbuy_description' => 'OnBuy Description',
        'onbuy_price' => 'OnBuy Price Override',
        'website_title' => 'Website Title',
        'website_description' => 'Website Description',
        'website_price' => 'Website Price Override',
        // Marketplace Identifiers
        'amazon_asin' => 'Amazon ASIN',
        'amazon_fba_asin' => 'Amazon FBA ASIN',
        'ebay_item_id' => 'eBay Item ID',
        'ebay_bo_item_id' => 'eBay BO Item ID',
        'onbuy_product_id' => 'OnBuy Product ID',
        // Product Attributes (flexible key-value pairs)
        'attribute_material' => 'Material',
        'attribute_fabric_type' => 'Fabric Type',
        'attribute_operation_type' => 'Operation Type',
        'attribute_mount_type' => 'Mount Type',
        'attribute_child_safety' => 'Child Safety Features',
        'attribute_room_darkening' => 'Room Darkening Level',
        'attribute_fire_rating' => 'Fire Rating',
        'attribute_warranty_years' => 'Warranty (Years)',
        'attribute_installation_required' => 'Installation Required',
        'attribute_custom_size_available' => 'Custom Sizes Available',
        // Variant Attributes (specific to individual variants)
        'variant_attribute_width_mm' => 'Width (mm)',
        'variant_attribute_drop_mm' => 'Drop (mm)',
        'variant_attribute_chain_length' => 'Chain Length',
        'variant_attribute_slat_width' => 'Slat Width',
        'variant_attribute_fabric_pattern' => 'Fabric Pattern',
        'variant_attribute_opacity_level' => 'Opacity Level',
    ];

    public function analyzeFile()
    {
        $this->validate();

        try {
            Log::info('Ultra-conservative file analysis', [
                'file' => $this->file->getClientOriginalName(),
                'size' => $this->file->getSize(),
                'memory_before' => memory_get_usage(true)
            ]);

            // ULTRA CONSERVATIVE APPROACH: Don't read the file AT ALL during analysis
            // Just store file info and let user proceed with manual configuration
            
            $fileExtension = strtolower($this->file->getClientOriginalExtension());
            
            if (in_array($fileExtension, ['xlsx', 'xls'])) {
                // For Excel files, show worksheet selection interface with generic options
                $this->createGenericWorksheetOptions();
            } else {
                // For CSV, skip directly to manual column mapping
                $this->setupGenericCsvImport();
            }
            
        } catch (\Exception $e) {
            $this->addError('file', 'Error processing file: ' . $e->getMessage());
            Log::error('Import file analysis failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $this->file ? $this->file->getClientOriginalName() : 'unknown'
            ]);
            
            // Reset to step 1 to allow retry
            $this->step = 1;
            $this->file = null;
            $this->cachedFileData = null;
        }
    }

    private function createGenericWorksheetOptions()
    {
        try {
            // Use PhpSpreadsheet to get just the worksheet names - super lightweight!
            $reader = IOFactory::createReader('Xlsx');
            $worksheetNames = $reader->listWorksheetNames($this->file->getRealPath());
            
            Log::info('Found worksheet names', [
                'total_sheets' => count($worksheetNames),
                'sheet_names' => array_slice($worksheetNames, 0, 10) // Log first 10 for debugging
            ]);

            $this->availableWorksheets = [];
            
            foreach ($worksheetNames as $index => $worksheetName) {
                $this->availableWorksheets[] = [
                    'index' => $index,
                    'name' => $worksheetName ?: "Sheet " . ($index + 1),
                    'headers' => 'Unknown',
                    'rows' => 'Unknown',
                    'preview' => 'Will be determined during import'
                ];
            }

            Log::info('Created worksheet options from real sheet names', [
                'total_worksheets' => count($this->availableWorksheets)
            ]);
            
        } catch (\Exception $e) {
            Log::warning('Could not read worksheet names, using fallback', [
                'error' => $e->getMessage()
            ]);
            
            // Fallback to generic options if sheet name reading fails
            $this->availableWorksheets = [
                [
                    'index' => 0,
                    'name' => 'Sheet 1 (Unable to read names)',
                    'headers' => 'Unknown',
                    'rows' => 'Unknown',
                    'preview' => 'Click to select and configure columns manually'
                ]
            ];
        }

        $this->step = 2; // Show worksheet selection
    }

    private function setupGenericCsvImport()
    {
        // For CSV, skip worksheet selection and go to manual column setup
        $this->selectedWorksheet = 0;
        $this->headers = []; // Empty headers - will be loaded later
        $this->sampleData = []; // No sample data
        
        // Load saved mappings
        $this->loadSavedMappings();
        
        Log::info('Setup generic CSV import');
        $this->step = 3; // Go straight to column mapping
    }

    private function analyzeExcelWorksheets()
    {
        // Let's try a different approach - read just the first few rows to get better info
        try {
            // Read only first 2 rows of all sheets to get headers + one sample row
            $allSheets = Excel::toArray(null, $this->file);
            
            Log::info('Excel sheets structure', [
                'sheet_count' => count($allSheets),
                'memory_after_read' => memory_get_usage(true)
            ]);

            $this->availableWorksheets = [];
            
            foreach ($allSheets as $index => $sheetData) {
                if (!empty($sheetData) && !empty($sheetData[0])) {
                    // Get actual headers from first row
                    $headers = $sheetData[0];
                    $headerStrings = array_map(function($header) {
                        return is_array($header) ? 'Array' : (string)$header;
                    }, $headers);
                    
                    // Filter out empty headers
                    $nonEmptyHeaders = array_filter($headerStrings, function($header) {
                        return !empty(trim($header)) && $header !== 'Array';
                    });
                    
                    $this->availableWorksheets[] = [
                        'index' => $index,
                        'name' => "Sheet " . ($index + 1),
                        'headers' => count($nonEmptyHeaders),
                        'rows' => count($sheetData) - 1, // Subtract header row
                        'preview' => !empty($nonEmptyHeaders) ? 
                            implode(', ', array_slice($nonEmptyHeaders, 0, 3)) : 
                            'No valid headers'
                    ];
                } else {
                    // Empty sheet
                    $this->availableWorksheets[] = [
                        'index' => $index,
                        'name' => "Sheet " . ($index + 1),
                        'headers' => 0,
                        'rows' => 0,
                        'preview' => 'Empty sheet'
                    ];
                }
            }

            // If multiple worksheets, show selection step
            if (count($this->availableWorksheets) > 1) {
                $this->step = 2;
                return;
            } else {
                // Single worksheet, auto-select it
                $this->selectedWorksheet = 0;
                $this->loadWorksheetHeaders();
            }
            
        } catch (\Exception $e) {
            // Fallback to headers-only approach if full read fails
            Log::warning('Full sheet read failed, falling back to headers only', ['error' => $e->getMessage()]);
            $this->analyzeExcelWorksheetsHeadersOnly();
        }
    }

    private function analyzeExcelWorksheetsHeadersOnly()
    {
        // Fallback method using just headers
        $headings = (new HeadingRowImport)->toArray($this->file);
        
        $this->availableWorksheets = [];
        
        foreach ($headings as $index => $sheetHeaders) {
            if (!empty($sheetHeaders)) {
                $this->availableWorksheets[] = [
                    'index' => $index,
                    'name' => "Sheet " . ($index + 1),
                    'headers' => 'Unknown',
                    'rows' => 'Unknown',
                    'preview' => 'Headers only mode - click to select'
                ];
            }
        }

        if (count($this->availableWorksheets) > 1) {
            $this->step = 2;
            return;
        } else {
            $this->selectedWorksheet = 0;
            $this->loadWorksheetHeaders();
        }
    }

    private function analyzeCsvFile()
    {
        // For CSV, we can safely read just the first few rows
        $headings = (new HeadingRowImport)->toArray($this->file);
        
        if (empty($headings[0])) {
            $this->addError('file', 'The CSV file appears to be empty.');
            return;
        }

        // Convert any array values to strings
        $this->headers = array_map(function($header) {
            return is_array($header) ? 'Array_Value' : (string)$header;
        }, $headings[0]);
        
        $this->selectedWorksheet = 0;
        
        Log::info('CSV headers loaded', [
            'header_count' => count($this->headers),
            'first_few_headers' => array_slice($this->headers, 0, 5)
        ]);
        
        // Get just 3 sample rows without loading everything
        $this->loadSampleData();
    }

    private function loadWorksheetHeaders()
    {
        // Load headers for the selected worksheet
        $headings = (new HeadingRowImport)->toArray($this->file);
        $rawHeaders = $headings[$this->selectedWorksheet] ?? [];
        
        if (empty($rawHeaders)) {
            $this->addError('file', 'The selected worksheet appears to have no headers.');
            return;
        }

        // Convert any array values to strings
        $this->headers = array_map(function($header) {
            return is_array($header) ? 'Array_Value' : (string)$header;
        }, $rawHeaders);

        Log::info('Headers loaded', [
            'header_count' => count($this->headers),
            'first_few_headers' => array_slice($this->headers, 0, 5)
        ]);

        $this->loadSampleData();
    }

    private function loadSampleData()
    {
        // For now, just create empty sample data to avoid memory issues
        // We'll show column mapping without sample data
        $this->sampleData = [];
        
        // Don't store allData here - we'll load it in chunks during actual import
        
        // Load saved mappings and settings
        $this->loadSavedMappings();
        
        // Initialize column mapping
        foreach ($this->headers as $index => $header) {
            if (!isset($this->columnMapping[$index])) {
                $this->columnMapping[$index] = $this->guessFieldMapping($header);
            }
        }

        Log::info('File analysis completed successfully - headers only mode', [
            'headers' => count($this->headers),
            'memory_final' => memory_get_usage(true)
        ]);
        
        $this->step = 3; // Go to column mapping
    }

    public function toggleWorksheet($worksheetIndex)
    {
        if (in_array($worksheetIndex, $this->selectedWorksheets)) {
            // Remove from selection
            $this->selectedWorksheets = array_values(array_diff($this->selectedWorksheets, [$worksheetIndex]));
        } else {
            // Add to selection
            $this->selectedWorksheets[] = $worksheetIndex;
        }
        
        Log::info('Worksheet selection toggled', [
            'worksheet_index' => $worksheetIndex,
            'selected_worksheets' => $this->selectedWorksheets,
            'total_selected' => count($this->selectedWorksheets)
        ]);
    }

    public function selectAllWorksheets()
    {
        $this->selectedWorksheets = array_column($this->availableWorksheets, 'index');
        
        Log::info('All worksheets selected', [
            'selected_worksheets' => $this->selectedWorksheets,
            'total_selected' => count($this->selectedWorksheets)
        ]);
    }

    public function deselectAllWorksheets()
    {
        $this->selectedWorksheets = [];
        
        Log::info('All worksheets deselected');
    }

    public function proceedWithSelectedSheets()
    {
        if (empty($this->selectedWorksheets)) {
            $this->addError('selectedWorksheets', 'Please select at least one worksheet to import.');
            return;
        }

        Log::info('Proceeding with selected worksheets', [
            'selected_count' => count($this->selectedWorksheets),
            'worksheets' => $this->selectedWorksheets
        ]);

        // Load headers from the first selected worksheet for column mapping
        $this->loadHeadersForSelectedSheets();

        // Move to column mapping step
        $this->step = 3;
    }

    private function loadHeadersForSelectedSheets()
    {
        try {
            // Get headers from the first selected worksheet to use for column mapping
            $firstSelectedSheet = $this->selectedWorksheets[0];
            $worksheetName = $this->availableWorksheets[$firstSelectedSheet]['name'];
            
            Log::info('Loading headers from first selected sheet', [
                'sheet_index' => $firstSelectedSheet,
                'worksheet_name' => $worksheetName
            ]);

            // Use PhpSpreadsheet directly for memory-efficient header reading
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);
            
            // Load only the specific worksheet
            $reader->setLoadSheetsOnly([$worksheetName]);
            
            $spreadsheet = $reader->load($this->file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            
            // Get only the first row (headers)
            $rawHeaders = [];
            $highestColumn = $worksheet->getHighestColumn();
            $columnIterator = $worksheet->getColumnIterator('A', $highestColumn);
            
            foreach ($columnIterator as $column) {
                $cellValue = $worksheet->getCell($column->getColumnIndex() . '1')->getCalculatedValue();
                if ($cellValue !== null && trim($cellValue) !== '') {
                    $rawHeaders[] = (string)$cellValue;
                } else {
                    // Stop at first empty header to avoid processing unnecessary columns
                    break;
                }
            }
            
            if (empty($rawHeaders)) {
                $this->addError('selectedWorksheets', 'The selected worksheet appears to have no headers.');
                return;
            }

            $this->headers = $rawHeaders;
            
            Log::info('Headers loaded successfully', [
                'header_count' => count($this->headers),
                'headers' => array_slice($this->headers, 0, 10) // Log first 10
            ]);
            
            // Clean up memory
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $worksheet, $reader);
            
        } catch (\Exception $e) {
            Log::error('Failed to load headers', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->headers = [];
            $this->addError('selectedWorksheets', 'Could not load headers from selected sheets. Error: ' . $e->getMessage());
        }

        // Load saved mappings and initialize column mapping
        $this->loadSavedMappings();
        
        // Initialize column mapping for all headers
        foreach ($this->headers as $index => $header) {
            if (!isset($this->columnMapping[$index])) {
                $this->columnMapping[$index] = $this->guessFieldMapping($header);
            }
        }

        // Set empty sample data since we're not loading data for memory reasons
        $this->sampleData = [];
    }

    private function loadDataForDryRun()
    {
        try {
            Log::info('Loading data for dry run from selected worksheets', [
                'selected_worksheets' => $this->selectedWorksheets
            ]);

            $allData = [];
            
            // Load data from all selected worksheets using streaming approach
            foreach ($this->selectedWorksheets as $worksheetIndex) {
                try {
                    $worksheetName = $this->availableWorksheets[$worksheetIndex]['name'];
                    
                    Log::info('Loading data from worksheet for dry run', [
                        'worksheet_index' => $worksheetIndex,
                        'worksheet_name' => $worksheetName
                    ]);
                    
                    // Use PhpSpreadsheet directly for memory-efficient reading
                    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
                    $reader->setReadDataOnly(true);
                    $reader->setReadEmptyCells(false);
                    
                    // Load only the specific worksheet
                    $reader->setLoadSheetsOnly([$worksheetName]);
                    
                    $spreadsheet = $reader->load($this->file->getRealPath());
                    $worksheet = $spreadsheet->getActiveSheet();
                    
                    // Read headers from this specific sheet first
                    $sheetHeaders = [];
                    $columnIterator = $worksheet->getColumnIterator('A');
                    $colIndex = 0;
                    foreach ($columnIterator as $column) {
                        if ($colIndex >= count($this->headers)) {
                            break;
                        }
                        $cellValue = $worksheet->getCell($column->getColumnIndex() . '1')->getCalculatedValue();
                        $sheetHeaders[] = (string)$cellValue;
                        $colIndex++;
                    }

                    // Get the dimensions but limit to first 100 rows for dry run
                    $maxRow = min($worksheet->getHighestRow(), 101); // 1 header + 100 data rows max
                    
                    // Read rows starting from row 2 (skip header)
                    for ($row = 2; $row <= $maxRow; $row++) {
                        $rowData = [];
                        $hasData = false;
                        
                        // Read each column in this row up to the number of sheet headers
                        $columnIterator = $worksheet->getColumnIterator('A');
                        $colIndex = 0;
                        foreach ($columnIterator as $column) {
                            if ($colIndex >= count($sheetHeaders)) {
                                break;
                            }
                            $cellValue = $worksheet->getCell($column->getColumnIndex() . $row)->getCalculatedValue();
                            $rowData[] = $cellValue;
                            if ($cellValue !== null && trim($cellValue) !== '') {
                                $hasData = true;
                            }
                            $colIndex++;
                        }
                        
                        // Only add rows that have some data, store with headers
                        if ($hasData) {
                            $allData[] = [
                                'data' => $rowData,
                                'headers' => $sheetHeaders
                            ];
                        }
                        
                        // Stop if we have enough rows for dry run
                        if (count($allData) >= 100) {
                            break;
                        }
                    }
                    
                    Log::info('Loaded sheet data for dry run', [
                        'worksheet_index' => $worksheetIndex,
                        'rows_loaded' => count($allData)
                    ]);
                    
                    // Clean up memory
                    $spreadsheet->disconnectWorksheets();
                    unset($spreadsheet, $worksheet, $reader);
                    
                } catch (\Exception $e) {
                    Log::error('Failed to load sheet data for dry run', [
                        'worksheet_index' => $worksheetIndex,
                        'error' => $e->getMessage()
                    ]);
                }
                
                // Stop if we have enough rows for dry run
                if (count($allData) >= 100) {
                    break;
                }
            }
            
            Log::info('Dry run data loaded', [
                'total_rows' => count($allData)
            ]);
            
            return $allData;
            
        } catch (\Exception $e) {
            Log::error('Failed to load data for dry run', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    private function loadWorksheetHeadersSafely()
    {
        try {
            // Try to read just the first row of the selected worksheet
            $headings = (new HeadingRowImport)->toArray($this->file);
            
            if (isset($headings[$this->selectedWorksheet]) && !empty($headings[$this->selectedWorksheet])) {
                $rawHeaders = $headings[$this->selectedWorksheet];
                
                // Convert to strings and clean up
                $this->headers = array_map(function($header) {
                    return is_array($header) ? 'Array_Value' : (string)$header;
                }, $rawHeaders);
                
                Log::info('Headers loaded successfully', [
                    'header_count' => count($this->headers),
                    'first_headers' => array_slice($this->headers, 0, 3)
                ]);
            } else {
                // No headers found, create manual setup
                $this->headers = [];
                Log::info('No headers found, will use manual setup');
            }
            
        } catch (\Exception $e) {
            Log::warning('Could not load headers, proceeding with manual setup', [
                'error' => $e->getMessage()
            ]);
            $this->headers = [];
        }

        // Always proceed to column mapping regardless
        $this->sampleData = []; // No sample data in conservative mode
        $this->loadSavedMappings();
        $this->step = 3;
    }

    private function processWorksheet()
    {
        try {
            // Use cached data instead of re-reading file
            $data = $this->cachedFileData[$this->selectedWorksheet] ?? throw ImportException::csvParsingFailed('Unable to read selected worksheet');
            
            if (empty($data)) {
                $this->addError('file', 'The selected worksheet appears to be empty.');
                return;
            }

            $this->headers = array_shift($data); // First row as headers
            $this->sampleData = array_slice($data, 0, 3); // First 3 data rows
            $this->allData = $data; // Store all data for processing
            
            // Load saved mappings and settings
            $this->loadSavedMappings();
            
            // Initialize column mapping (combine saved + guessed)
            foreach ($this->headers as $index => $header) {
                if (!isset($this->columnMapping[$index])) {
                    $this->columnMapping[$index] = $this->guessFieldMapping($header);
                }
            }

            // Move to column mapping step (was step 2, now step 3)
            $this->step = 3;
        } catch (\Exception $e) {
            $this->addError('file', 'Error processing worksheet: ' . $e->getMessage());
            Log::error('Worksheet processing failed', [
                'error' => $e->getMessage(),
                'worksheet' => $this->selectedWorksheet
            ]);
        }
    }

    private function guessFieldMapping($header)
    {
        $header = strtolower(trim($header));
        
        // Smart mapping based on common column names
        $mappings = [
            'name' => 'product_name',
            'product' => 'product_name',
            'title' => 'product_name',
            'description' => 'description',
            'desc' => 'description',
            'is parent' => 'is_parent',
            'parent' => 'is_parent',
            'type' => 'is_parent',
            'parent name' => 'parent_name',
            'parent product' => 'parent_name',
            'sku' => 'variant_sku',
            'color' => 'variant_color',
            'colour' => 'variant_color',
            'size' => 'variant_size',
            'retail price' => 'retail_price',
            'price' => 'retail_price',
            'cost price' => 'cost_price',
            'cost' => 'cost_price',
            'stock' => 'stock_level',
            'quantity' => 'stock_level',
            'length' => 'package_length',
            'width' => 'package_width',
            'height' => 'package_height',
            'weight' => 'package_weight',
            'image' => 'image_urls',
            'images' => 'image_urls',
            'photo' => 'image_urls',
            'barcode' => 'barcode',
            'upc' => 'barcode',
            'ean' => 'barcode',
            'barcode_type' => 'barcode_type',
            'status' => 'status',
        ];

        // Check for feature patterns - "Item Feature 1", "feature 1", etc.
        if (preg_match('/(?:item\s+)?feature\s+(\d+)/i', $header, $matches)) {
            $num = $matches[1];
            if ($num >= 1 && $num <= 5) {
                return "product_features_{$num}";
            }
        }

        // Check for detail patterns - "Finer Detail 1", "detail 1", etc.
        if (preg_match('/(?:finer\s+)?detail\s+(\d+)/i', $header, $matches)) {
            $num = $matches[1];
            if ($num >= 1 && $num <= 5) {
                return "product_details_{$num}";
            }
        }

        // Check for marketplace-specific patterns
        if (preg_match('/ebay.*bo.*title/i', $header) || preg_match('/ebay.*business.*outlet.*title/i', $header)) {
            return 'ebay_bo_title';
        }
        if (preg_match('/ebay.*bo.*description/i', $header) || preg_match('/ebay.*business.*outlet.*description/i', $header)) {
            return 'ebay_bo_description';
        }
        if (preg_match('/ebay.*bo.*price/i', $header) || preg_match('/ebay.*business.*outlet.*price/i', $header)) {
            return 'ebay_bo_price';
        }
        if (preg_match('/ebay.*title/i', $header)) {
            return 'ebay_title';
        }
        if (preg_match('/ebay.*description/i', $header)) {
            return 'ebay_description';
        }
        if (preg_match('/ebay.*price/i', $header)) {
            return 'ebay_price';
        }
        if (preg_match('/amazon.*fba.*title/i', $header)) {
            return 'amazon_fba_title';
        }
        if (preg_match('/amazon.*fba.*description/i', $header)) {
            return 'amazon_fba_description';
        }
        if (preg_match('/amazon.*fba.*price/i', $header)) {
            return 'amazon_fba_price';
        }
        if (preg_match('/amazon.*title/i', $header)) {
            return 'amazon_title';
        }
        if (preg_match('/amazon.*description/i', $header)) {
            return 'amazon_description';
        }
        if (preg_match('/amazon.*price/i', $header)) {
            return 'amazon_price';
        }
        if (preg_match('/onbuy.*title/i', $header)) {
            return 'onbuy_title';
        }
        if (preg_match('/onbuy.*description/i', $header)) {
            return 'onbuy_description';
        }
        if (preg_match('/onbuy.*price/i', $header)) {
            return 'onbuy_price';
        }
        if (preg_match('/website.*title/i', $header)) {
            return 'website_title';
        }
        if (preg_match('/website.*description/i', $header)) {
            return 'website_description';
        }
        if (preg_match('/website.*price/i', $header)) {
            return 'website_price';
        }

        // Check for marketplace identifiers
        if (preg_match('/amazon.*fba.*asin/i', $header)) {
            return 'amazon_fba_asin';
        }
        if (preg_match('/amazon.*asin/i', $header) || preg_match('/asin/i', $header)) {
            return 'amazon_asin';
        }
        if (preg_match('/ebay.*bo.*item.*id/i', $header) || preg_match('/ebay.*business.*outlet.*item/i', $header)) {
            return 'ebay_bo_item_id';
        }
        if (preg_match('/ebay.*item.*id/i', $header) || preg_match('/ebay.*id/i', $header)) {
            return 'ebay_item_id';
        }
        if (preg_match('/onbuy.*product.*id/i', $header) || preg_match('/onbuy.*id/i', $header)) {
            return 'onbuy_product_id';
        }

        // Check for attribute patterns - match common window shade attributes
        $attributePatterns = [
            'material' => 'attribute_material',
            'fabric' => 'attribute_fabric_type',
            'operation' => 'attribute_operation_type',
            'mount' => 'attribute_mount_type',
            'child.*safety' => 'attribute_child_safety',
            'room.*darkening' => 'attribute_room_darkening',
            'blackout' => 'attribute_room_darkening',
            'fire.*rating' => 'attribute_fire_rating',
            'warranty' => 'attribute_warranty_years',
            'installation' => 'attribute_installation_required',
            'custom.*size' => 'attribute_custom_size_available',
        ];

        foreach ($attributePatterns as $pattern => $field) {
            if (preg_match('/' . $pattern . '/i', $header)) {
                return $field;
            }
        }

        // Check for variant-specific attributes
        $variantAttributePatterns = [
            'width.*mm' => 'variant_attribute_width_mm',
            'drop.*mm' => 'variant_attribute_drop_mm',
            'height.*mm' => 'variant_attribute_drop_mm',
            'chain.*length' => 'variant_attribute_chain_length',
            'slat.*width' => 'variant_attribute_slat_width',
            'fabric.*pattern' => 'variant_attribute_fabric_pattern',
            'opacity' => 'variant_attribute_opacity_level',
        ];

        foreach ($variantAttributePatterns as $pattern => $field) {
            if (preg_match('/' . $pattern . '/i', $header)) {
                return $field;
            }
        }

        foreach ($mappings as $key => $field) {
            if (str_contains($header, $key)) {
                return $field;
            }
        }

        return ''; // No mapping
    }

    public function runDryRun()
    {
        // Save current mappings and settings to cache
        $this->saveMappingsToCache();
        
        $this->step = 4;
        $this->dryRunResults = [
            'valid_rows' => 0,
            'error_rows' => 0,
            'warnings' => [],
            'errors' => [],
            'products_to_create' => 0,
            'products_to_update' => 0,
            'products_to_skip' => 0,
            'variants_to_create' => 0,
            'variants_to_update' => 0,
            'variants_to_skip' => 0,
            'barcodes_needed' => 0,
        ];

        $products = [];
        $variants = [];
        $errors = [];
        $warnings = [];

        // Load data from selected worksheets for dry run
        $allData = $this->loadDataForDryRun();
        
        foreach ($allData as $rowIndex => $rowWithHeaders) {
            $rowNum = $rowIndex + 2; // +2 because of header row and 0-based index
            $mappedData = $this->mapRowData($rowWithHeaders['data'], $rowWithHeaders['headers']);
            
            // Validate required fields
            $rowErrors = $this->validateRow($mappedData, $rowNum);
            
            if (!empty($rowErrors)) {
                $this->dryRunResults['error_rows']++;
                $errors = array_merge($errors, $rowErrors);
            } else {
                $this->dryRunResults['valid_rows']++;
                
                // Check product action based on import mode
                $productName = $mappedData['product_name'] ?? '';
                $productExists = Product::where('name', $productName)->exists();
                
                if (!isset($products[$productName])) {
                    $products[$productName] = true;
                    
                    switch ($this->importMode) {
                        case 'create_only':
                            if ($productExists) {
                                $this->dryRunResults['products_to_skip']++;
                            } else {
                                $this->dryRunResults['products_to_create']++;
                            }
                            break;
                        case 'update_existing':
                            if ($productExists) {
                                $this->dryRunResults['products_to_update']++;
                            } else {
                                $this->dryRunResults['products_to_skip']++;
                            }
                            break;
                        case 'create_or_update':
                            if ($productExists) {
                                $this->dryRunResults['products_to_update']++;
                            } else {
                                $this->dryRunResults['products_to_create']++;
                            }
                            break;
                    }
                }
                
                // Enhanced variant checking that matches actual import logic
                $variantSku = $mappedData['variant_sku'] ?? '';
                if ($variantSku) {
                    // Create unique key for variant (either by SKU or by product+color+size)
                    $variantKey = $this->getVariantKey($mappedData, $productName);
                    
                    if (!isset($variants[$variantKey])) {
                        $variants[$variantKey] = true;
                        
                        // Smart attribute extraction for dry run (same as actual import)
                        $extractedColor = $mappedData['variant_color'] ?? null;
                        $extractedSize = $mappedData['variant_size'] ?? null;
                        
                        if ($this->smartAttributeExtraction && (!$extractedColor || !$extractedSize)) {
                            $extracted = ProductAttributeExtractorV2::extractAttributes($mappedData['product_name']);
                            
                            if (!$extractedColor && $extracted['color']) {
                                $extractedColor = $extracted['color'];
                            }
                            
                            // V2 extractor returns width/drop instead of size
                            if (!$extractedSize) {
                                if ($extracted['width'] && $extracted['drop']) {
                                    $extractedSize = $extracted['width'] . ' x ' . $extracted['drop'];
                                } elseif ($extracted['width']) {
                                    $extractedSize = $extracted['width'];
                                } elseif ($extracted['drop']) {
                                    $extractedSize = $extracted['drop'];
                                }
                            }
                        }
                        
                        // Check variant existence using same logic as actual import
                        $variantAction = $this->predictVariantAction($variantSku, $productName, $extractedColor, $extractedSize);
                        
                        switch ($variantAction) {
                            case 'create':
                                $this->dryRunResults['variants_to_create']++;
                                break;
                            case 'update':
                                $this->dryRunResults['variants_to_update']++;
                                break;
                            case 'skip':
                                $this->dryRunResults['variants_to_skip']++;
                                break;
                        }
                        
                        // Check if barcode is needed (only for new variants)
                        if (empty($mappedData['barcode']) && $this->autoAssignGS1Barcodes && $variantAction === 'create') {
                            $this->dryRunResults['barcodes_needed']++;
                        }
                    }
                }
            }
            
            // Stop after checking first 100 rows for performance
            if ($rowIndex >= 99) {
                $warnings[] = "Only checked first 100 rows for performance. Full validation will occur during import.";
                break;
            }
        }

        // Check GS1 barcode availability
        $poolStats = BarcodePool::getStats();
        if ($this->dryRunResults['barcodes_needed'] > $poolStats['available']) {
            $errors[] = "Need {$this->dryRunResults['barcodes_needed']} GS1 barcodes but only {$poolStats['available']} available in pool.";
        }

        $this->dryRunResults['errors'] = $errors;
        $this->dryRunResults['warnings'] = $warnings;
        
        Log::info('Dry run completed', $this->dryRunResults);
    }

    private function mapRowData($row, $sheetHeaders = null)
    {
        // If no sheet headers provided, use the main headers (for backward compatibility)
        $headers = $sheetHeaders ?? $this->headers;
        
        // Build the mapping index using our action
        $headerToFieldMapping = app(\App\Actions\Import\BuildMappingIndex::class)
            ->execute($this->headers, $this->columnMapping);
        
        // Map the row data using our action
        $mapped = app(\App\Actions\Import\MapRowToFields::class)
            ->execute($row, $headers, $headerToFieldMapping);
        
        // Debug logging for first few rows
        static $debugCount = 0;
        if ($debugCount < 10) {
            Log::info("Header-based mapping debug", [
                'debug_count' => $debugCount,
                'original_headers' => array_slice($this->headers, 0, 5),
                'current_sheet_headers' => array_slice($headers, 0, 5),
                'header_to_field_mapping' => $headerToFieldMapping,
                'row_data_raw' => $row,
                'row_data_sample' => is_array($row) ? array_slice($row, 0, 5) : $row,
                'headers_sample' => array_slice($headers, 0, 5),
                'mapped_result' => $mapped
            ]);
            $debugCount++;
        }
        
        return $mapped;
    }

    private function validateRow($data, $rowNum)
    {
        $errors = [];
        
        // Required fields
        if (empty($data['product_name'])) {
            $errors[] = "Row {$rowNum}: Product name is required";
        }
        
        if (empty($data['variant_sku'])) {
            $errors[] = "Row {$rowNum}: Variant SKU is required";
        }
        
        // Check for duplicate SKUs in database (respect import mode)
        if (!empty($data['variant_sku'])) {
            $variantExists = ProductVariant::where('sku', $data['variant_sku'])->exists();
            
            if ($variantExists && $this->importMode === 'create_only') {
                // In create_only mode, existing SKUs are not an error, they'll be skipped
            } elseif (!$variantExists && $this->importMode === 'update_existing') {
                // In update_existing mode, non-existing SKUs are not an error, they'll be skipped
            } elseif ($variantExists && $this->importMode === 'update_existing') {
                // Existing SKU in update mode is fine
            } elseif ($this->importMode === 'create_or_update') {
                // Both scenarios are fine in upsert mode
            }
        }
        
        // Check for duplicate color/size combinations (respect import mode)
        if (!empty($data['product_name']) && (!empty($data['variant_color']) || !empty($data['variant_size']))) {
            // For dry run, we need to simulate the product lookup
            $product = Product::where('name', $data['product_name'])->first();
            if ($product) {
                $colorSizeExists = ProductVariant::where('product_id', $product->id)
                    ->where('color', $data['variant_color'])
                    ->where('size', $data['variant_size'])
                    ->exists();
                
                if ($colorSizeExists && $this->importMode === 'create_only') {
                    // In create_only mode, existing color/size combos will be skipped
                } elseif (!$colorSizeExists && $this->importMode === 'update_existing') {
                    // In update_existing mode, non-existing color/size combos will be skipped
                } elseif ($this->importMode === 'create_or_update') {
                    // Both scenarios are fine in upsert mode
                }
            }
        }
        
        // Validate numeric fields
        $numericFields = ['retail_price', 'cost_price', 'stock_level', 'package_length', 'package_width', 'package_height', 'package_weight'];
        foreach ($numericFields as $field) {
            if (!empty($data[$field]) && !is_numeric($data[$field])) {
                $errors[] = "Row {$rowNum}: {$field} must be numeric, got '{$data[$field]}'";
            }
        }
        
        return $errors;
    }

    public function startActualImport()
    {
        $this->step = 5;
        $this->importProgress = 0;
        $this->importStatus = 'Starting import...';
        $this->importErrors = [];
        $this->importWarnings = [];

        // Validate that critical fields are mapped
        $mappedFields = array_values($this->columnMapping);
        
        // Debug logging to understand column mapping state
        Log::info("Import start - column mapping debug", [
            'columnMapping' => $this->columnMapping,
            'mappedFields' => $mappedFields,
            'headers' => $this->headers,
            'step' => $this->step
        ]);
        
        if (!in_array('variant_sku', $mappedFields)) {
            $this->importErrors[] = 'Import cannot proceed: Variant SKU column is not mapped. Please go back to Step 2 and map a column to "Variant SKU". Current mappings: ' . json_encode($this->columnMapping);
            return;
        }
        
        if (!in_array('product_name', $mappedFields)) {
            $this->importErrors[] = 'Import cannot proceed: Product Name column is not mapped. Please go back to Step 2 and map a column to "Product Name". Current mappings: ' . json_encode($this->columnMapping);
            return;
        }

        // For sequential processing, we don't load all data upfront
        $this->importStatus = 'Preparing for sequential import...';
        $this->importProgress = 5;
        
        // Basic validation that we have worksheets selected
        if (empty($this->selectedWorksheets)) {
            $this->importErrors[] = 'No worksheets selected for import. Please go back and select at least one worksheet.';
            return;
        }
        
        Log::info("Starting sequential import", [
            'selected_worksheets' => count($this->selectedWorksheets),
            'worksheet_names' => array_map(function($index) {
                return $this->availableWorksheets[$index]['name'] ?? "Sheet $index";
            }, $this->selectedWorksheets)
        ]);
        
        DB::beginTransaction();
        
        if ($this->autoGenerateParentMode) {
            $this->runTwoPhaseImport();
        } else {
            $this->runStandardImport();
        }
        
        DB::commit();
    }

    private function runTwoPhaseImport()
    {
        Log::info("Starting sequential two-phase import (auto-generate parent mode)");
        
        $totalCreated = 0;
        $totalParents = 0;
        $allErrors = [];
        $totalSheets = count($this->selectedWorksheets);
        
        // Initialize progress details
        $this->importProgressDetails = [
            'current_sheet' => 'Starting two-phase import...',
            'processed_sheets' => 0,
            'total_sheets' => $totalSheets,
            'products_created' => 0
        ];

        foreach ($this->selectedWorksheets as $sheetIndex => $worksheetIndex) {
            $worksheetName = $this->availableWorksheets[$worksheetIndex]['name'];
            
            Log::info("Processing sheet in two-phase mode", [
                'sheet_number' => $sheetIndex + 1,
                'total_sheets' => $totalSheets,
                'worksheet_name' => $worksheetName
            ]);
            
            // Update progress
            $this->importProgressDetails['current_sheet'] = $worksheetName;
            $this->importStatus = "Two-phase processing sheet {$worksheetName} (" . ($sheetIndex + 1) . "/{$totalSheets})";
            
            // Load data for just this sheet
            $sheetData = $this->loadSingleSheetData($worksheetIndex);
            
            if (empty($sheetData)) {
                Log::warning("No data found in sheet {$worksheetName}, skipping");
                continue;
            }
            
            // Phase 1: Create parent products for this sheet
            $this->importStatus = "Phase 1: Creating parents for {$worksheetName}...";
            $sheetParentGroups = $this->groupSheetDataByParents($sheetData);
            $sheetCreatedParents = [];
            
            foreach ($sheetParentGroups as $parentKey => $productGroup) {
                // Skip empty groups
                if (empty($productGroup)) {
                    Log::warning("Skipping empty product group", [
                        'parent_key' => $parentKey,
                        'sheet' => $worksheetName
                    ]);
                    continue;
                }
                
                $variantDataArray = array_column($productGroup, 'data');
                
                // Skip if no variant data extracted
                if (empty($variantDataArray)) {
                    Log::warning("Skipping group with no variant data", [
                        'parent_key' => $parentKey,
                        'sheet' => $worksheetName,
                        'product_group_count' => count($productGroup)
                    ]);
                    continue;
                }
                
                $parent = $this->createParentProductFromSimilarityGroup($parentKey, $variantDataArray);
                $sheetCreatedParents[$parentKey] = $parent;
                $totalParents++;
                
                Log::info("Created parent for sheet", [
                    'sheet' => $worksheetName,
                    'parent_id' => $parent->id, 
                    'parent_name' => $parent->name,
                    'variant_count' => count($variantDataArray)
                ]);
            }
            
            // Phase 2: Create variants for this sheet
            $this->importStatus = "Phase 2: Creating variants for {$worksheetName}...";
            $sheetCreated = 0;
            
            foreach ($sheetData as $rowIndex => $rowWithHeaders) {
                try {
                    $rowNum = $rowIndex + 2;
                    $mappedData = $this->mapRowData($rowWithHeaders['data'], $rowWithHeaders['headers']);
                    
                    // Find parent within this sheet's groups
                    $parentKey = $this->findParentKeyForVariant($mappedData, $sheetParentGroups);
                    $parent = $sheetCreatedParents[$parentKey] ?? null;
                    
                    if (!$parent) {
                        $parent = AutoParentCreator::createParentFromVariant($mappedData);
                        $sheetCreatedParents[$parentKey] = $parent;
                        $totalParents++;
                    }
                    
                    $this->createVariantForParent($parent, $mappedData, $rowNum);
                    $sheetCreated++;
                    $totalCreated++;
                    
                } catch (\Exception $e) {
                    $allErrors[] = "Sheet {$worksheetName}, Row {$rowNum}: " . $e->getMessage();
                }
            }
            
            // Update progress
            $this->importProgressDetails['processed_sheets'] = $sheetIndex + 1;
            $this->importProgressDetails['products_created'] = $totalCreated;
            $this->importProgress = (($sheetIndex + 1) / $totalSheets) * 100;
            
            Log::info("Completed two-phase processing for sheet", [
                'worksheet_name' => $worksheetName,
                'parents_created' => count($sheetCreatedParents),
                'variants_created' => $sheetCreated
            ]);
        }
        
        $this->importProgress = 100;
        $this->importStatus = "Two-phase import completed! Created {$totalParents} parent products with {$totalCreated} variants.";
        $this->importProgressDetails['current_sheet'] = 'Completed';
        
        if (!empty($allErrors)) {
            $this->importErrors = $allErrors;
        }
        
        Log::info("Sequential two-phase import completed", [
            'total_sheets_processed' => $totalSheets,
            'total_parents_created' => $totalParents,
            'total_variants_created' => $totalCreated,
            'total_errors' => count($allErrors)
        ]);
    }

    private function runStandardImport()
    {
        Log::info("Starting sequential sheet import (standard mode)");
        
        $totalCreated = 0;
        $totalUpdated = 0;
        $totalSkipped = 0;
        $allErrors = [];
        $totalSheets = count($this->selectedWorksheets);
        
        // Initialize progress details
        $this->importProgressDetails = [
            'current_sheet' => 'Starting...',
            'processed_sheets' => 0,
            'total_sheets' => $totalSheets,
            'products_created' => 0
        ];

        foreach ($this->selectedWorksheets as $sheetIndex => $worksheetIndex) {
            $worksheetName = $this->availableWorksheets[$worksheetIndex]['name'];
            
            Log::info("Processing sheet sequentially", [
                'sheet_number' => $sheetIndex + 1,
                'total_sheets' => $totalSheets,
                'worksheet_name' => $worksheetName
            ]);
            
            // Update progress
            $this->importProgressDetails['current_sheet'] = $worksheetName;
            $this->importStatus = "Processing sheet {$worksheetName} (" . ($sheetIndex + 1) . "/{$totalSheets})";
            
            // Load data for just this sheet
            $sheetData = $this->loadSingleSheetData($worksheetIndex);
            
            if (empty($sheetData)) {
                Log::warning("No data found in sheet {$worksheetName}, skipping");
                continue;
            }
            
            // Process this sheet completely before moving to next
            $sheetResults = $this->processSheetData($sheetData, $worksheetName);
            
            // Aggregate results
            $totalCreated += $sheetResults['created'];
            $totalUpdated += $sheetResults['updated'];
            $totalSkipped += $sheetResults['skipped'];
            $allErrors = array_merge($allErrors, $sheetResults['errors']);
            
            // Update progress
            $this->importProgressDetails['processed_sheets'] = $sheetIndex + 1;
            $this->importProgressDetails['products_created'] = $totalCreated;
            $this->importProgress = (($sheetIndex + 1) / $totalSheets) * 100;
            
            Log::info("Completed sheet processing", [
                'worksheet_name' => $worksheetName,
                'sheet_results' => $sheetResults,
                'total_so_far' => $totalCreated
            ]);
        }
        
        $this->importProgress = 100;
        $this->importStatus = "Import completed! Processed {$totalSheets} sheets. Created {$totalCreated} items.";
        $this->importProgressDetails['current_sheet'] = 'Completed';
        
        if (!empty($allErrors)) {
            $this->importErrors = $allErrors;
        }
        
        Log::info("Sequential import completed", [
            'total_sheets_processed' => $totalSheets,
            'total_created' => $totalCreated,
            'total_updated' => $totalUpdated,
            'total_skipped' => $totalSkipped,
            'total_errors' => count($allErrors)
        ]);
    }

    private function processRow($data, $rowNum)
    {
        // Check if this is auto-generate parent mode or explicit parent/child mode
        if ($this->autoGenerateParentMode) {
            // Auto-generate mode: treat ALL rows as variants, create parents as needed
            return $this->processVariantRow($data, $rowNum);
        } else {
            // Explicit mode: check if row is parent or child based on is_parent field
            $isParent = $this->isParentRow($data);
            
            if ($isParent) {
                // This is a parent product row
                return $this->processParentRow($data, $rowNum);
            } else {
                // This is a variant row
                return $this->processVariantRow($data, $rowNum);
            }
        }
    }

    private function isParentRow($data)
    {
        $isParentValue = $data['is_parent'] ?? null;
        
        if ($isParentValue === null) {
            // If no is_parent field, assume it's a variant unless it has no SKU
            return empty($data['variant_sku']);
        }
        
        // Handle various ways to express "true"
        return in_array(strtolower($isParentValue), ['true', '1', 'yes', 'parent']);
    }

    private function processParentRow($data, $rowNum)
    {
        // Create/update parent product only
        $product = $this->handleProductImport($data);
        Log::info("Processed parent product row", ['product_name' => $data['product_name'] ?? 'N/A', 'row' => $rowNum]);
        return $product;
    }

    private function processVariantRow($data, $rowNum)
    {
        // In explicit parent/child mode, find the appropriate parent product
        if (!$this->autoGenerateParentMode) {
            $product = $this->findParentForVariant($data);
            if (!$product) {
                throw ImportException::variantCreationFailed($data['variant_sku'] ?? 'Unknown SKU', "No parent product found for variant '{$data['product_name']}' at row {$rowNum}");
            }
        } else {
            // Auto-generate parent mode: create/find product as before
            $product = $this->handleProductImport($data);
            if (!$product) {
                return null; // Skip this row based on import mode
            }
        }

        // Smart attribute extraction if enabled and missing color/size
        $extractedColor = $data['variant_color'] ?? null;
        $extractedSize = $data['variant_size'] ?? null;
        
        if ($this->smartAttributeExtraction && (!$extractedColor || !$extractedSize)) {
            $extracted = ProductAttributeExtractorV2::extractAttributes($data['product_name']);
            
            // Only use extracted values if original is missing
            if (!$extractedColor && $extracted['color']) {
                $extractedColor = $extracted['color'];
                Log::info("Extracted color '{$extracted['color']}' from product name: {$data['product_name']}");
            }
            
            // V2 extractor returns width/drop instead of size
            if (!$extractedSize) {
                if ($extracted['width'] && $extracted['drop']) {
                    $extractedSize = $extracted['width'] . ' x ' . $extracted['drop'];
                    Log::info("Extracted dimensions '{$extractedSize}' from product name: {$data['product_name']}");
                } elseif ($extracted['width']) {
                    $extractedSize = $extracted['width'];
                    Log::info("Extracted width '{$extractedSize}' from product name: {$data['product_name']}");
                } elseif ($extracted['drop']) {
                    $extractedSize = $extracted['drop'];
                    Log::info("Extracted drop '{$extractedSize}' from product name: {$data['product_name']}");
                }
            }
        }

        // Handle variant based on import mode
        $variant = $this->handleVariantImport($product, $data, $extractedColor, $extractedSize);
        if (!$variant) {
            return null; // Skip this row based on import mode
        }

        // Handle barcode (respect import modes and avoid duplicates)
        if (!empty($data['barcode'])) {
            // Check if this barcode already exists for this variant
            $existingBarcode = $variant->barcodes()->where('barcode', $data['barcode'])->first();
            
            if (!$existingBarcode) {
                // Auto-detect barcode type if not provided
                $barcodeType = $data['barcode_type'] ?? BarcodeDetector::detectBarcodeType($data['barcode']);
                
                // Create new barcode if it doesn't exist
                $variant->barcodes()->create([
                    'barcode' => $data['barcode'],
                    'barcode_type' => $barcodeType,
                ]);
                Log::info("Added barcode to variant", [
                    'sku' => $variant->sku, 
                    'barcode' => $data['barcode'],
                    'type' => $barcodeType,
                    'auto_detected' => !isset($data['barcode_type'])
                ]);
            } else {
                Log::info("Barcode already exists for variant, skipping", ['sku' => $variant->sku, 'barcode' => $data['barcode']]);
            }
        } elseif ($this->autoAssignGS1Barcodes) {
            // Only auto-assign barcodes if variant doesn't already have any
            $existingBarcodeCount = $variant->barcodes()->count();
            
            if ($existingBarcodeCount === 0) {
                $nextBarcode = BarcodePool::getNextAvailable('EAN13');
                if ($nextBarcode) {
                    $variant->barcodes()->create([
                        'barcode' => $nextBarcode->barcode,
                        'type' => 'EAN13',
                    ]);
                    $nextBarcode->markAsUsed($variant->id);
                    Log::info("Auto-assigned GS1 barcode to variant", ['sku' => $variant->sku, 'barcode' => $nextBarcode->barcode]);
                }
            } else {
                Log::info("Variant already has barcodes, skipping auto-assignment", ['sku' => $variant->sku, 'barcode_count' => $existingBarcodeCount]);
            }
        }

        // Handle pricing (avoid duplicates)
        if (!empty($data['retail_price'])) {
            $defaultChannel = SalesChannel::where('slug', 'website')->first();
            if ($defaultChannel) {
                // Check if pricing already exists for this variant and marketplace
                $existingPricing = Pricing::where('product_variant_id', $variant->id)
                    ->where('marketplace', 'website')
                    ->first();
                
                if ($existingPricing) {
                    // Update existing pricing
                    $existingPricing->update([
                        'retail_price' => $data['retail_price'],
                        'cost_price' => $data['cost_price'] ?? null,
                        'vat_percentage' => 20.00,
                        'vat_inclusive' => true,
                    ]);
                    Log::info("Updated pricing for variant", ['sku' => $variant->sku, 'price' => $data['retail_price']]);
                } else {
                    // Create new pricing
                    Pricing::create([
                        'product_variant_id' => $variant->id,
                        'marketplace' => 'website',
                        'retail_price' => $data['retail_price'],
                        'cost_price' => $data['cost_price'] ?? null,
                        'vat_percentage' => 20.00,
                        'vat_inclusive' => true,
                    ]);
                    Log::info("Added pricing for variant", ['sku' => $variant->sku, 'price' => $data['retail_price']]);
                }
            }
        }

        // Handle marketplace variants
        $this->handleMarketplaceVariants($variant, $data);
        
        // Handle marketplace barcodes/identifiers
        $this->handleMarketplaceBarcodes($variant, $data);
        
        // Handle product and variant attributes
        $this->handleProductAttributes($product, $data);
        $this->handleVariantAttributes($variant, $data);

        // Dispatch ProductImported event for background image processing
        ProductImported::dispatch($product, $data);
        
        return $product;
    }

    private function handleProductImport($data)
    {
        // If auto-generate parent mode is enabled, always auto-create parents
        if ($this->autoGenerateParentMode) {
            return $this->handleAutoGenerateParentMode($data);
        }
        
        // Explicit parent/child mode - check for parent_name field
        if (!empty($data['parent_name'])) {
            // This row specifies a parent name, try to find/create it
            $parentName = $data['parent_name'];
            Log::info("Looking for parent by name", ['parent_name' => $parentName]);
            
            switch ($this->importMode) {
                case 'create_only':
                    $existing = Product::where('name', $parentName)->first();
                    if ($existing) {
                        Log::info("Found existing parent: {$parentName}");
                        return $existing;
                    }
                    // Create new parent
                    return Product::create([
                        'name' => $parentName,
                        'slug' => Str::slug($parentName),
                        'status' => 'active',
                        'parent_sku' => null // This is a parent product
                    ]);

                case 'update_existing':
                    $existing = Product::where('name', $parentName)->first();
                    if (!$existing) {
                        Log::info("Parent not found, skipping: {$parentName}");
                        return null; // Skip if parent doesn't exist
                    }
                    return $existing;

                case 'create_or_update':
                default:
                    return Product::updateOrCreate(
                        ['name' => $parentName],
                        [
                            'slug' => Str::slug($parentName),
                            'status' => 'active',
                            'parent_sku' => null // This is a parent product
                        ]
                    );
            }
        }

        // Standard product handling with provided name (explicit parent/child mode)
        $productData = [
            'description' => $data['description'] ?? '',
            'product_features_1' => $data['product_features_1'] ?? null,
            'product_features_2' => $data['product_features_2'] ?? null,
            'product_features_3' => $data['product_features_3'] ?? null,
            'product_features_4' => $data['product_features_4'] ?? null,
            'product_features_5' => $data['product_features_5'] ?? null,
            'product_details_1' => $data['product_details_1'] ?? null,
            'product_details_2' => $data['product_details_2'] ?? null,
            'product_details_3' => $data['product_details_3'] ?? null,
            'product_details_4' => $data['product_details_4'] ?? null,
            'product_details_5' => $data['product_details_5'] ?? null,
            'status' => $data['status'] ?? 'active',
        ];

        // Extract parent SKU from variant SKU if available
        if (!empty($data['variant_sku']) && preg_match('/^(\d{3})-\d{3}$/', $data['variant_sku'], $matches)) {
            $productData['parent_sku'] = $matches[1];
        }

        switch ($this->importMode) {
            case 'create_only':
                $existing = Product::where('name', $data['product_name'])->first();
                if ($existing) {
                    Log::info("Skipping existing product: {$data['product_name']}");
                    return null; // Skip existing products
                }
                return Product::create(array_merge(['name' => $data['product_name'], 'slug' => $this->generateUniqueSlug($data['product_name'])], $productData));

            case 'update_existing':
                $existing = Product::where('name', $data['product_name'])->first();
                if (!$existing) {
                    Log::info("Skipping non-existing product: {$data['product_name']}");
                    return null; // Skip non-existing products
                }
                $existing->update($productData);
                return $existing;

            case 'create_or_update':
            default:
                return Product::updateOrCreate(
                    ['name' => $data['product_name']],
                    array_merge(['slug' => $this->generateUniqueSlug($data['product_name'])], $productData)
                );
        }
    }

    private function handleAutoGenerateParentMode($data)
    {
        // In auto-generate mode, always create parents from variant data
        // Similar to the original autoCreateParents logic but more aggressive
        
        // If no product name provided, create parent from variant data
        if (empty($data['product_name'])) {
            Log::info("Auto-generating parent from variant data", ['variant_sku' => $data['variant_sku'] ?? 'N/A']);
            return AutoParentCreator::createParentFromVariant($data);
        }

        // If product name provided but has SKU pattern, try to find parent by SKU pattern first
        if (!empty($data['variant_sku'])) {
            if (preg_match('/^(\d{3})-\d{3}$/', $data['variant_sku'], $matches)) {
                $parentSku = $matches[1];
                $existingParent = Product::where('parent_sku', $parentSku)->first();
                
                if ($existingParent) {
                    Log::info("Found existing parent by SKU", ['parent_sku' => $parentSku, 'parent_name' => $existingParent->name]);
                    return $existingParent;
                }
                
                // Create parent if it doesn't exist and we're in create modes
                if (in_array($this->importMode, ['create_only', 'create_or_update'])) {
                    Log::info("Auto-creating parent from SKU pattern", ['parent_sku' => $parentSku]);
                    return AutoParentCreator::createParentFromVariant($data);
                }
            }
        }

        // Fall back to using provided product name as parent
        $productData = [
            'description' => $data['description'] ?? '',
            'status' => 'active',
            'parent_sku' => null, // This is a parent
        ];

        // Extract parent SKU from variant SKU if available
        if (!empty($data['variant_sku']) && preg_match('/^(\d{3})-\d{3}$/', $data['variant_sku'], $matches)) {
            $productData['parent_sku'] = null; // Still a parent, but we'll use the pattern for organization
        }

        switch ($this->importMode) {
            case 'create_only':
                $existing = Product::where('name', $data['product_name'])->first();
                if ($existing) {
                    Log::info("Found existing parent: {$data['product_name']}");
                    return $existing;
                }
                return Product::create(array_merge(['name' => $data['product_name'], 'slug' => $this->generateUniqueSlug($data['product_name'])], $productData));

            case 'update_existing':
                $existing = Product::where('name', $data['product_name'])->first();
                if (!$existing) {
                    Log::info("Parent not found, creating anyway in auto-generate mode: {$data['product_name']}");
                    return Product::create(array_merge(['name' => $data['product_name'], 'slug' => $this->generateUniqueSlug($data['product_name'])], $productData));
                }
                $existing->update($productData);
                return $existing;

            case 'create_or_update':
            default:
                return Product::updateOrCreate(
                    ['name' => $data['product_name']],
                    array_merge(['slug' => $this->generateUniqueSlug($data['product_name'])], $productData)
                );
        }
    }

    private function handleVariantImport($product, $data, $extractedColor, $extractedSize)
    {
        if (empty($data['variant_sku'])) {
            Log::warning("No SKU provided for product: {$product->name}");
            return null;
        }

        // Create variant data without color/size (now attributes)
        $variantData = [
            'product_id' => $product->id,
            'stock_level' => $data['stock_level'] ?? 0,
            'package_length' => $data['package_length'] ?? null,
            'package_width' => $data['package_width'] ?? null,
            'package_height' => $data['package_height'] ?? null,
            'package_weight' => $data['package_weight'] ?? null,
        ];
        
        // Extract width/drop from the V2 extractor if available
        $extracted = ProductAttributeExtractorV2::extractAttributes($data['product_name'] ?? '');
        $extractedWidth = $extracted['width'] ?? null;
        $extractedDrop = $extracted['drop'] ?? null;

        switch ($this->importMode) {
            case 'create_only':
                // Check both SKU and color/size combination constraints
                $existingBySku = ProductVariant::where('sku', $data['variant_sku'])->first();
                if ($existingBySku) {
                    Log::info("Skipping existing variant SKU: {$data['variant_sku']}");
                    return null;
                }
                
                // Check for existing variant by color/width/drop combination using attributes
                $existingByAttributes = ProductVariant::where('product_id', $product->id)
                    ->whereHas('attributes', function($query) use ($extractedColor) {
                        $query->where('attribute_key', 'color')->where('attribute_value', $extractedColor);
                    })
                    ->whereHas('attributes', function($query) use ($extractedWidth) {
                        if ($extractedWidth) {
                            $query->where('attribute_key', 'width')->where('attribute_value', $extractedWidth);
                        }
                    })
                    ->whereHas('attributes', function($query) use ($extractedDrop) {
                        if ($extractedDrop) {
                            $query->where('attribute_key', 'drop')->where('attribute_value', $extractedDrop);
                        }
                    })
                    ->first();
                if ($existingByAttributes) {
                    Log::info("Skipping existing variant color/dimensions combination: {$product->name} - {$extractedColor} {$extractedWidth}x{$extractedDrop}");
                    return null;
                }
                
                $variant = ProductVariant::create(array_merge(['sku' => $data['variant_sku']], $variantData));
                
                // Set attributes using the attribute system
                if ($extractedColor) {
                    $variant->setVariantAttributeValue('color', $extractedColor, 'string', 'core');
                }
                if ($extractedWidth) {
                    $variant->setVariantAttributeValue('width', $extractedWidth, 'number', 'core');
                }
                if ($extractedDrop) {
                    $variant->setVariantAttributeValue('drop', $extractedDrop, 'number', 'core');
                }
                
                Log::info("Created new variant", ['sku' => $variant->sku, 'mode' => 'create_only']);
                
                // Dispatch events for image processing
                Log::info("About to dispatch ProductVariantImported event (create_only)", [
                    'variant_id' => $variant->id,
                    'variant_sku' => $variant->sku,
                    'has_image_data' => !empty(array_intersect_key($data, array_flip([
                        'image_url', 'image_urls', 'image_1', 'image_2', 'image_3', 'image_4', 'image_5',
                        'main_image', 'product_image', 'photo_url', 'picture_url', 'images'
                    ]))),
                    'all_data_keys' => array_keys($data),
                    'image_urls_value' => $data['image_urls'] ?? 'NOT_SET'
                ]);
                ProductVariantImported::dispatch($variant, $data);
                
                return $variant;

            case 'update_existing':
                // Try to find by SKU first, then by color/size combination
                $existing = ProductVariant::where('sku', $data['variant_sku'])->first();
                
                if (!$existing) {
                    // Try to find by color/width/drop combination as fallback using attributes
                    $existing = ProductVariant::where('product_id', $product->id)
                        ->whereHas('attributes', function($query) use ($extractedColor) {
                            $query->where('attribute_key', 'color')->where('attribute_value', $extractedColor);
                        })
                        ->whereHas('attributes', function($query) use ($extractedWidth) {
                            if ($extractedWidth) {
                                $query->where('attribute_key', 'width')->where('attribute_value', $extractedWidth);
                            }
                        })
                        ->whereHas('attributes', function($query) use ($extractedDrop) {
                            if ($extractedDrop) {
                                $query->where('attribute_key', 'drop')->where('attribute_value', $extractedDrop);
                            }
                        })
                        ->first();
                }
                
                if (!$existing) {
                    Log::info("Skipping non-existing variant: {$data['variant_sku']} or {$product->name} - {$extractedColor} {$extractedWidth}x{$extractedDrop}");
                    return null;
                }
                
                $existing->update(array_merge(['sku' => $data['variant_sku']], $variantData));
                
                // Update attributes using the attribute system
                if ($extractedColor) {
                    $existing->setVariantAttributeValue('color', $extractedColor, 'string', 'core');
                }
                if ($extractedWidth) {
                    $existing->setVariantAttributeValue('width', $extractedWidth, 'number', 'core');
                }
                if ($extractedDrop) {
                    $existing->setVariantAttributeValue('drop', $extractedDrop, 'number', 'core');
                }
                
                return $existing;

            case 'create_or_update':
            default:
                // Try SKU first, then color/size combination
                $existing = ProductVariant::where('sku', $data['variant_sku'])->first();
                
                if (!$existing) {
                    $existing = ProductVariant::where('product_id', $product->id)
                        ->whereHas('attributes', function($query) use ($extractedColor) {
                            $query->where('attribute_key', 'color')->where('attribute_value', $extractedColor);
                        })
                        ->whereHas('attributes', function($query) use ($extractedWidth) {
                            if ($extractedWidth) {
                                $query->where('attribute_key', 'width')->where('attribute_value', $extractedWidth);
                            }
                        })
                        ->whereHas('attributes', function($query) use ($extractedDrop) {
                            if ($extractedDrop) {
                                $query->where('attribute_key', 'drop')->where('attribute_value', $extractedDrop);
                            }
                        })
                        ->first();
                }
                
                if ($existing) {
                    // Update existing variant
                    $existing->update(array_merge(['sku' => $data['variant_sku']], $variantData));
                    
                    // Update attributes using the attribute system
                    if ($extractedColor) {
                        $existing->setVariantAttributeValue('color', $extractedColor, 'string', 'core');
                    }
                    if ($extractedWidth) {
                        $existing->setVariantAttributeValue('width', $extractedWidth, 'number', 'core');
                    }
                    if ($extractedDrop) {
                        $existing->setVariantAttributeValue('drop', $extractedDrop, 'number', 'core');
                    }
                    
                    Log::info("Updated existing variant: {$existing->sku} with new data");
                    
                    // Dispatch events for image processing (for updates too)
                    Log::info("About to dispatch ProductVariantImported event (update)", [
                        'variant_id' => $existing->id,
                        'variant_sku' => $existing->sku,
                        'has_image_data' => !empty(array_intersect_key($data, array_flip([
                            'image_url', 'image_urls', 'image_1', 'image_2', 'image_3', 'image_4', 'image_5',
                            'main_image', 'product_image', 'photo_url', 'picture_url', 'images'
                        ])))
                    ]);
                    ProductVariantImported::dispatch($existing, $data);
                    
                    return $existing;
                } else {
                    // Create new variant
                    $variant = ProductVariant::create(array_merge(['sku' => $data['variant_sku']], $variantData));
                    
                    // Set attributes using the attribute system
                    if ($extractedColor) {
                        $variant->setVariantAttributeValue('color', $extractedColor, 'string', 'core');
                    }
                    if ($extractedWidth) {
                        $variant->setVariantAttributeValue('width', $extractedWidth, 'number', 'core');
                    }
                    if ($extractedDrop) {
                        $variant->setVariantAttributeValue('drop', $extractedDrop, 'number', 'core');
                    }
                    
                    Log::info("Created new variant", ['sku' => $variant->sku, 'mode' => 'create_or_update']);
                    
                    // Dispatch events for image processing
                    Log::info("About to dispatch ProductVariantImported event (create_or_update)", [
                        'variant_id' => $variant->id,
                        'variant_sku' => $variant->sku,
                        'has_image_data' => !empty(array_intersect_key($data, array_flip([
                            'image_url', 'image_urls', 'image_1', 'image_2', 'image_3', 'image_4', 'image_5',
                            'main_image', 'product_image', 'photo_url', 'picture_url', 'images'
                        ])))
                    ]);
                    ProductVariantImported::dispatch($variant, $data);
                    
                    return $variant;
                }
        }
    }

    /**
     * Generate a unique key for variant tracking in dry run
     */
    private function getVariantKey($mappedData, $productName)
    {
        $sku = $mappedData['variant_sku'] ?? '';
        $color = $mappedData['variant_color'] ?? '';
        $size = $mappedData['variant_size'] ?? '';
        
        // Use SKU as primary key, fallback to product+color+size
        if ($sku) {
            return "sku:{$sku}";
        } else {
            return "combo:{$productName}:{$color}:{$size}";
        }
    }

    /**
     * Predict what action will be taken for a variant during import
     */
    private function predictVariantAction($variantSku, $productName, $extractedColor, $extractedSize)
    {
        // Get the product to check color/size combinations
        $product = Product::where('name', $productName)->first();
        
        switch ($this->importMode) {
            case 'create_only':
                // Check both SKU and color/size combination constraints
                $existingBySku = ProductVariant::where('sku', $variantSku)->exists();
                if ($existingBySku) {
                    return 'skip';
                }
                
                if ($product && ($extractedColor || $extractedSize)) {
                    // Check for existing variant by color/size combination using attributes
                    $query = ProductVariant::where('product_id', $product->id);
                    
                    if ($extractedColor) {
                        $query->whereHas('attributes', function($q) use ($extractedColor) {
                            $q->where('attribute_key', 'color')->where('attribute_value', $extractedColor);
                        });
                    }
                    
                    if ($extractedSize) {
                        $query->whereHas('attributes', function($q) use ($extractedSize) {
                            $q->where('attribute_key', 'size')->where('attribute_value', $extractedSize);
                        });
                    }
                    
                    if ($query->exists()) {
                        return 'skip';
                    }
                }
                
                return 'create';

            case 'update_existing':
                // Try to find by SKU first, then by color/size combination
                $existing = ProductVariant::where('sku', $variantSku)->exists();
                
                if (!$existing && $product && ($extractedColor || $extractedSize)) {
                    // Try to find by color/size combination as fallback using attributes
                    $query = ProductVariant::where('product_id', $product->id);
                    
                    if ($extractedColor) {
                        $query->whereHas('attributes', function($q) use ($extractedColor) {
                            $q->where('attribute_key', 'color')->where('attribute_value', $extractedColor);
                        });
                    }
                    
                    if ($extractedSize) {
                        $query->whereHas('attributes', function($q) use ($extractedSize) {
                            $q->where('attribute_key', 'size')->where('attribute_value', $extractedSize);
                        });
                    }
                    
                    $existing = $query->exists();
                }
                
                return $existing ? 'update' : 'skip';

            case 'create_or_update':
            default:
                // Try SKU first, then color/size combination
                $existing = ProductVariant::where('sku', $variantSku)->exists();
                
                if (!$existing && $product && ($extractedColor || $extractedSize)) {
                    $query = ProductVariant::where('product_id', $product->id);
                    
                    if ($extractedColor) {
                        $query->whereHas('attributes', function($q) use ($extractedColor) {
                            $q->where('attribute_key', 'color')->where('attribute_value', $extractedColor);
                        });
                    }
                    
                    if ($extractedSize) {
                        $query->whereHas('attributes', function($q) use ($extractedSize) {
                            $q->where('attribute_key', 'size')->where('attribute_value', $extractedSize);
                        });
                    }
                    
                    $existing = $query->exists();
                }
                
                return $existing ? 'update' : 'create';
        }
    }

    /**
     * Load saved mappings and settings from cache
     */
    private function loadSavedMappings()
    {
        $mappingCache = new ImportMappingCache();
        
        // Apply saved mappings to current headers
        $savedMappings = $mappingCache->applyMappingsToHeaders($this->headers);
        if (!empty($savedMappings)) {
            $this->columnMapping = array_merge($this->columnMapping, $savedMappings);
        }
        
        // Load saved import settings
        $savedSettings = $mappingCache->getImportSettings();
        if (!empty($savedSettings)) {
            $this->importMode = $savedSettings['importMode'] ?? $this->importMode;
            $this->smartAttributeExtraction = $savedSettings['smartAttributeExtraction'] ?? $this->smartAttributeExtraction;
            $this->autoCreateParents = $savedSettings['autoCreateParents'] ?? $this->autoCreateParents;
            $this->autoGenerateParentMode = $savedSettings['autoGenerateParentMode'] ?? $this->autoGenerateParentMode;
            $this->autoAssignGS1Barcodes = $savedSettings['autoAssignGS1Barcodes'] ?? $this->autoAssignGS1Barcodes;
        }
    }

    /**
     * Save current mappings and settings to cache
     */
    private function saveMappingsToCache()
    {
        $mappingCache = new ImportMappingCache();
        
        // Save headers for future matching
        $mappingCache->saveHeaders($this->headers);
        
        // Save column mappings and import settings
        $importSettings = [
            'importMode' => $this->importMode,
            'smartAttributeExtraction' => $this->smartAttributeExtraction,
            'autoCreateParents' => $this->autoCreateParents,
            'autoGenerateParentMode' => $this->autoGenerateParentMode,
            'autoAssignGS1Barcodes' => $this->autoAssignGS1Barcodes,
        ];
        
        $mappingCache->saveMapping($this->columnMapping, $importSettings);
    }

    /**
     * Get mapping statistics for display
     */
    public function getMappingStats()
    {
        $mappingCache = new ImportMappingCache();
        return $mappingCache->getMappingStats();
    }

    /**
     * Clear saved mappings
     */
    public function clearSavedMappings()
    {
        $mappingCache = new ImportMappingCache();
        $mappingCache->clearMapping();
        
        // Reset to default guessed mappings
        $this->columnMapping = [];
        foreach ($this->headers as $index => $header) {
            $this->columnMapping[$index] = $this->guessFieldMapping($header);
        }
    }

    /**
     * Group import data by parent products for two-phase import using similarity-based grouping
     */
    private function groupDataByParents(): array
    {
        // Map all data first
        $mappedDataArray = [];
        foreach ($this->allData as $rowIndex => $row) {
            $mappedData = $this->mapRowData($row);
            if (!empty($mappedData['product_name'])) {
                $mappedDataArray[] = $mappedData;
            }
        }
        
        // Use ProductNameGrouping service for intelligent similarity-based grouping
        $similarityGroups = ProductNameGrouping::groupSimilarProducts($mappedDataArray);
        
        // Convert similarity groups to parent groups format expected by the rest of the code
        $parentGroups = [];
        foreach ($similarityGroups as $group) {
            $parentInfo = $group['parent_info'];
            $parentKey = $this->createParentKeyFromInfo($parentInfo);
            $parentGroups[$parentKey] = $group['products'];
        }
        
        Log::info("Grouped data using similarity-based algorithm", [
            'total_groups' => count($parentGroups),
            'algorithm' => 'ProductNameGrouping::groupSimilarProducts'
        ]);
        
        return $parentGroups;
    }

    /**
     * Group sheet data by parent products for sequential two-phase import
     */
    private function groupSheetDataByParents(array $sheetData): array
    {
        // Map sheet data first
        $mappedDataArray = [];
        foreach ($sheetData as $rowIndex => $rowWithHeaders) {
            $mappedData = $this->mapRowData($rowWithHeaders['data'], $rowWithHeaders['headers']);
            if (!empty($mappedData['product_name'])) {
                $mappedDataArray[] = [
                    'data' => $mappedData,
                    'headers' => $rowWithHeaders['headers']
                ];
            }
        }
        
        // Extract just the data for grouping
        $dataForGrouping = array_column($mappedDataArray, 'data');
        
        // Use ProductNameGrouping service for intelligent similarity-based grouping
        $similarityGroups = ProductNameGrouping::groupSimilarProducts($dataForGrouping);
        
        // Convert similarity groups to parent groups format expected by the rest of the code
        $parentGroups = [];
        foreach ($similarityGroups as $group) {
            $parentInfo = $group['parent_info'];
            $parentKey = $this->createParentKeyFromInfo($parentInfo);
            
            // Reconstruct the grouped data with headers
            $groupProducts = [];
            foreach ($group['products'] as $product) {
                // Find the original row with headers
                foreach ($mappedDataArray as $mappedRow) {
                    if ($mappedRow['data'] === $product) {
                        $groupProducts[] = $mappedRow;
                        break;
                    }
                }
            }
            $parentGroups[$parentKey] = $groupProducts;
        }
        
        Log::info("Grouped sheet data using similarity-based algorithm", [
            'total_groups' => count($parentGroups),
            'algorithm' => 'ProductNameGrouping::groupSimilarProducts'
        ]);
        
        return $parentGroups;
    }

    /**
     * Create a parent key from similarity-based parent info
     */
    private function createParentKeyFromInfo(array $parentInfo): string
    {
        // Use SKU if available, otherwise use name hash
        if (!empty($parentInfo['sku'])) {
            return "sku:{$parentInfo['sku']}";
        } else {
            return "name:" . md5(strtolower(trim($parentInfo['name'])));
        }
    }

    /**
     * Generate a parent key for grouping variant data
     */
    private function getParentKeyForData(array $data): string
    {
        // Method 1: Use SKU pattern if available (001-001 → parent: 001)
        if (!empty($data['variant_sku']) && preg_match('/^(\d{3})-\d{3}$/', $data['variant_sku'], $matches)) {
            return "sku:{$matches[1]}";
        }
        
        // Method 2: Use product name with smart extraction
        if (!empty($data['product_name'])) {
            $parentName = AutoParentCreator::extractParentNameFromVariantName($data['product_name']);
            Log::info("Parent grouping", ['original' => $data['product_name'], 'extracted' => $parentName, 'key' => "name:" . md5(strtolower(trim($parentName)))]);
            return "name:" . md5(strtolower(trim($parentName)));
        }
        
        // Method 3: Fallback to a generic group
        return "generic:default";
    }

    /**
     * Find which parent key this variant belongs to based on similarity groups
     */
    private function findParentKeyForVariant(array $mappedData, array $parentGroups): string
    {
        foreach ($parentGroups as $parentKey => $productGroup) {
            foreach ($productGroup as $product) {
                $productData = $product['data'];
                
                // Check for exact match by SKU or name
                if (isset($productData['variant_sku']) && isset($mappedData['variant_sku']) && 
                    $productData['variant_sku'] === $mappedData['variant_sku']) {
                    return $parentKey;
                }
                
                if (isset($productData['product_name']) && isset($mappedData['product_name']) && 
                    $productData['product_name'] === $mappedData['product_name']) {
                    return $parentKey;
                }
            }
        }
        
        // Fallback to old method if not found in groups
        return $this->getParentKeyForData($mappedData);
    }

    /**
     * Create a parent product from similarity-based grouped variant data
     */
    private function createParentProductFromSimilarityGroup(string $parentKey, array $variantDataArray): Product
    {
        // Check if we have variant data
        if (empty($variantDataArray)) {
            throw new \Exception("Cannot create parent product from similarity group: no variant data provided");
        }
        
        // Get similarity-based parent info by re-running grouping on this subset
        $similarityGroups = ProductNameGrouping::groupSimilarProducts($variantDataArray);
        
        if (empty($similarityGroups)) {
            // Fallback to old method
            return $this->createParentProduct($parentKey, $variantDataArray);
        }
        
        // Use the first (and likely only) group's parent info
        $parentInfo = $similarityGroups[0]['parent_info'];
        
        // Check if parent already exists
        $existingParent = null;
        if (!empty($parentInfo['sku'])) {
            $existingParent = Product::whereNull('parent_sku')->where('name', $parentInfo['name'])->first();
        } else {
            $existingParent = Product::where('name', $parentInfo['name'])->whereNull('parent_sku')->first();
        }
        
        if ($existingParent) {
            switch ($this->importMode) {
                case 'create_only':
                    Log::info("Found existing parent in create_only mode", ['parent_name' => $parentInfo['name']]);
                    return $existingParent;
                case 'update_existing':
                case 'create_or_update':
                    Log::info("Updating existing parent", ['parent_name' => $parentInfo['name']]);
                    $existingParent->update([
                        'description' => $parentInfo['description'] ?? $existingParent->description,
                        'status' => 'active',
                    ]);
                    return $existingParent;
            }
        }
        
        // Create new parent using similarity-based parent info
        $parentData = [
            'name' => $parentInfo['name'],
            'slug' => $this->generateUniqueSlug($parentInfo['name']),
            'parent_sku' => null, // Parent products have null parent_sku
            'description' => $parentInfo['description'] ?? "Auto-generated parent for {$parentInfo['name']}",
            'status' => 'active',
            'auto_generated' => true,
        ];
        
        // Copy product features and details from base data if available
        if (isset($parentInfo['base_data'])) {
            $baseData = $parentInfo['base_data'];
            $featureFields = ['product_features_1', 'product_features_2', 'product_features_3', 'product_features_4', 'product_features_5'];
            $detailFields = ['product_details_1', 'product_details_2', 'product_details_3', 'product_details_4', 'product_details_5'];
            
            foreach (array_merge($featureFields, $detailFields) as $field) {
                if (isset($baseData[$field])) {
                    $parentData[$field] = $baseData[$field];
                }
            }
        }
        
        Log::info("Creating parent using similarity-based algorithm", [
            'parent_name' => $parentInfo['name'],
            'parent_sku' => $parentInfo['sku'],
            'variant_count' => count($variantDataArray)
        ]);
        
        return Product::create($parentData);
    }

    /**
     * Create a parent product from grouped variant data (legacy method)
     */
    private function createParentProduct(string $parentKey, array $variantDataArray): Product
    {
        // Check if we have variant data
        if (empty($variantDataArray)) {
            throw new \Exception("Cannot create parent product: no variant data provided");
        }
        
        // Use the first variant's data as the base for parent
        $firstVariant = $variantDataArray[0];
        
        // Extract parent information
        if (str_starts_with($parentKey, 'sku:')) {
            $parentSku = substr($parentKey, 4);
            $parentName = AutoParentCreator::extractParentNameFromVariantName($firstVariant['product_name'] ?? "Product {$parentSku}");
        } else {
            $parentSku = null;
            $parentName = AutoParentCreator::extractParentNameFromVariantName($firstVariant['product_name'] ?? 'Product Group');
        }
        
        // Check if parent already exists (respect import modes)
        $existingParent = null;
        if ($parentSku) {
            // For SKU-based parents, look by parent_sku but ensure it's actually a parent (parent_sku should be null)
            $existingParent = Product::whereNull('parent_sku')->where('name', $parentName)->first();
        } else {
            $existingParent = Product::where('name', $parentName)->whereNull('parent_sku')->first();
        }
        
        if ($existingParent) {
            switch ($this->importMode) {
                case 'create_only':
                    Log::info("Found existing parent in create_only mode", ['parent_name' => $parentName]);
                    return $existingParent;
                case 'update_existing':
                case 'create_or_update':
                    Log::info("Updating existing parent", ['parent_name' => $parentName]);
                    $existingParent->update([
                        'description' => $firstVariant['description'] ?? $existingParent->description,
                        'status' => 'active',
                    ]);
                    return $existingParent;
            }
        }
        
        // Create new parent product
        $parentData = [
            'name' => $parentName,
            'slug' => Str::slug($parentName),
            'parent_sku' => null, // Parent products have null parent_sku
            'description' => $firstVariant['description'] ?? "Auto-generated parent for {$parentName}",
            'status' => 'active',
            'auto_generated' => true,
            // Copy product features and details from first variant
            'product_features_1' => $firstVariant['product_features_1'] ?? null,
            'product_features_2' => $firstVariant['product_features_2'] ?? null,
            'product_features_3' => $firstVariant['product_features_3'] ?? null,
            'product_features_4' => $firstVariant['product_features_4'] ?? null,
            'product_features_5' => $firstVariant['product_features_5'] ?? null,
            'product_details_1' => $firstVariant['product_details_1'] ?? null,
            'product_details_2' => $firstVariant['product_details_2'] ?? null,
            'product_details_3' => $firstVariant['product_details_3'] ?? null,
            'product_details_4' => $firstVariant['product_details_4'] ?? null,
            'product_details_5' => $firstVariant['product_details_5'] ?? null,
        ];
        
        return Product::create($parentData);
    }

    /**
     * Find the appropriate parent product for a variant in explicit parent/child mode
     */
    private function findParentForVariant(array $data): ?Product
    {
        // Method 1: Check for explicit parent_name field
        if (!empty($data['parent_name'])) {
            $existing = Product::where('name', $data['parent_name'])->whereNull('parent_sku')->first();
            if ($existing) {
                return $existing;
            }
            
            // If autoCreateParents is enabled and no parent found, create it
            if ($this->autoCreateParents && in_array($this->importMode, ['create_only', 'create_or_update'])) {
                Log::info("Auto-creating parent from parent_name field", ['parent_name' => $data['parent_name']]);
                return Product::create([
                    'name' => $data['parent_name'],
                    'slug' => Str::slug($data['parent_name']),
                    'status' => 'active',
                    'parent_sku' => null,
                    'auto_generated' => true
                ]);
            }
            
            return null;
        }
        
        // Method 2: Find parent by SKU pattern (001-001 → parent SKU: 001)
        if (!empty($data['variant_sku']) && preg_match('/^(\d{3})-\d{3}$/', $data['variant_sku'], $matches)) {
            $parentSku = $matches[1];
            
            // Look for a parent product that might use this SKU pattern
            // Since parents don't have parent_sku set, we need to find them differently
            // Look for any parent product that has variants with this SKU pattern
            $existingVariant = ProductVariant::where('sku', 'LIKE', $parentSku . '-%')->first();
            if ($existingVariant) {
                return $existingVariant->product;
            }
            
            // If autoCreateParents is enabled and no parent found, create using AutoParentCreator
            if ($this->autoCreateParents && in_array($this->importMode, ['create_only', 'create_or_update'])) {
                Log::info("Auto-creating parent from SKU pattern", ['parent_sku' => $parentSku]);
                return AutoParentCreator::createParentFromVariant($data);
            }
        }
        
        // Method 3: Use product_name to find/create parent
        if (!empty($data['product_name'])) {
            $existing = Product::where('name', $data['product_name'])->whereNull('parent_sku')->first();
            if ($existing) {
                return $existing;
            }
            
            // If autoCreateParents is enabled and no parent found, create from variant data
            if ($this->autoCreateParents && in_array($this->importMode, ['create_only', 'create_or_update'])) {
                Log::info("Auto-creating parent from product_name", ['product_name' => $data['product_name']]);
                return AutoParentCreator::createParentFromVariant($data);
            }
        }
        
        // Method 4: Find the most recently created parent product
        // This assumes the parent was created in a previous row in the same import
        $recentParent = Product::whereNull('parent_sku')
            ->orderBy('created_at', 'desc')
            ->first();
            
        if ($recentParent) {
            Log::info("Using most recent parent for variant", [
                'variant_name' => $data['product_name'],
                'parent_name' => $recentParent->name
            ]);
            return $recentParent;
        }
        
        return null;
    }

    /**
     * Create a variant and link it to its parent product
     */
    private function createVariantForParent(Product $parent, array $data, int $rowNum): ProductVariant
    {
        if (empty($data['variant_sku'])) {
            $availableFields = implode(', ', array_keys($data));
            $message = 'Variant SKU is required but not found in mapped data. ';
            $message .= "Available mapped fields: {$availableFields}. ";
            $message .= 'Please ensure you have mapped a column to "Variant SKU" in the column mapping step.';
            throw ImportException::variantCreationFailed('', $message);
        }

        // Smart attribute extraction
        $extractedColor = $data['variant_color'] ?? null;
        $extractedSize = $data['variant_size'] ?? null;
        
        if ($this->smartAttributeExtraction && (!$extractedColor || !$extractedSize)) {
            $extracted = ProductAttributeExtractorV2::extractAttributes($data['product_name'] ?? '');
            
            if (!$extractedColor && $extracted['color']) {
                $extractedColor = $extracted['color'];
            }
            
            // V2 extractor returns width/drop instead of size
            if (!$extractedSize) {
                if ($extracted['width'] && $extracted['drop']) {
                    $extractedSize = $extracted['width'] . ' x ' . $extracted['drop'];
                } elseif ($extracted['width']) {
                    $extractedSize = $extracted['width'];
                } elseif ($extracted['drop']) {
                    $extractedSize = $extracted['drop'];
                }
            }
        }

        // Create variant data without color/size (now attributes)  
        $variantData = [
            'product_id' => $parent->id, // Link to parent product
            'sku' => $data['variant_sku'],
            'stock_level' => $data['stock_level'] ?? 0,
            'package_length' => $data['package_length'] ?? null,
            'package_width' => $data['package_width'] ?? null,
            'package_height' => $data['package_height'] ?? null,
            'package_weight' => $data['package_weight'] ?? null,
            'status' => $data['status'] ?? 'active',
        ];
        
        // Extract width/drop from the V2 extractor if available
        $extracted = ProductAttributeExtractorV2::extractAttributes($data['product_name'] ?? '');
        $extractedWidth = $extracted['width'] ?? null;
        $extractedDrop = $extracted['drop'] ?? null;

        // Handle existing variants based on import mode
        $existingVariant = null;
        
        // Check for existing variant by SKU
        $existingBySku = ProductVariant::where('sku', $data['variant_sku'])->first();
        
        // Check for existing variant by color/width/drop combination within this parent using attributes
        $existingByAttributes = ProductVariant::where('product_id', $parent->id)
            ->whereHas('attributes', function($query) use ($extractedColor) {
                $query->where('attribute_key', 'color')->where('attribute_value', $extractedColor);
            })
            ->whereHas('attributes', function($query) use ($extractedWidth) {
                if ($extractedWidth) {
                    $query->where('attribute_key', 'width')->where('attribute_value', $extractedWidth);
                }
            })
            ->whereHas('attributes', function($query) use ($extractedDrop) {
                if ($extractedDrop) {
                    $query->where('attribute_key', 'drop')->where('attribute_value', $extractedDrop);
                }
            })
            ->first();
        
        $existingVariant = $existingBySku ?: $existingByAttributes;

        if ($existingVariant) {
            switch ($this->importMode) {
                case 'create_only':
                    Log::info("Skipping existing variant in create_only mode", ['sku' => $data['variant_sku']]);
                    return $existingVariant;
                    
                case 'update_existing':
                case 'create_or_update':
                    Log::info("Updating existing variant", ['sku' => $data['variant_sku']]);
                    $existingVariant->update($variantData);
                    
                    // Update attributes using the attribute system
                    if ($extractedColor) {
                        $existingVariant->setVariantAttributeValue('color', $extractedColor, 'string', 'core');
                    }
                    if ($extractedWidth) {
                        $existingVariant->setVariantAttributeValue('width', $extractedWidth, 'number', 'core');
                    }
                    if ($extractedDrop) {
                        $existingVariant->setVariantAttributeValue('drop', $extractedDrop, 'number', 'core');
                    }
                    
                    $variant = $existingVariant;
                    break;
            }
        } else {
            if ($this->importMode === 'update_existing') {
                throw ImportException::variantCreationFailed($data['variant_sku'], "Variant does not exist and import mode is update_existing");
            }
            
            $variant = ProductVariant::create($variantData);
            
            // Set attributes using the attribute system
            if ($extractedColor) {
                $variant->setVariantAttributeValue('color', $extractedColor, 'string', 'core');
            }
            if ($extractedWidth) {
                $variant->setVariantAttributeValue('width', $extractedWidth, 'number', 'core');
            }
            if ($extractedDrop) {
                $variant->setVariantAttributeValue('drop', $extractedDrop, 'number', 'core');
            }
            
            Log::info("Created new variant", ['sku' => $variant->sku, 'parent_id' => $parent->id]);
        }

        // Handle barcode assignment
        $this->handleVariantBarcode($variant, $data);
        
        // Handle pricing
        $this->handleVariantPricing($variant, $data);
        
        // Handle marketplace variants
        $this->handleMarketplaceVariants($variant, $data);
        
        // Handle marketplace barcodes/identifiers
        $this->handleMarketplaceBarcodes($variant, $data);
        
        // Handle product and variant attributes
        $this->handleProductAttributes($variant->product, $data);
        $this->handleVariantAttributes($variant, $data);
        
        // Dispatch events for image processing
        Log::info("About to dispatch ProductVariantImported event", [
            'variant_id' => $variant->id,
            'variant_sku' => $variant->sku,
            'has_image_data' => !empty(array_intersect_key($data, array_flip([
                'image_url', 'image_urls', 'image_1', 'image_2', 'image_3', 'image_4', 'image_5',
                'main_image', 'product_image', 'photo_url', 'picture_url', 'images'
            ]))),
            'all_data_keys' => array_keys($data),
            'image_urls_value' => $data['image_urls'] ?? 'NOT_SET'
        ]);
        ProductVariantImported::dispatch($variant, $data);
        
        return $variant;
    }

    /**
     * Handle barcode assignment for variant
     */
    private function handleVariantBarcode(ProductVariant $variant, array $data): void
    {
        if (!empty($data['barcode'])) {
            $existingBarcode = $variant->barcodes()->where('barcode', $data['barcode'])->first();
            
            if (!$existingBarcode) {
                // Auto-detect barcode type if not provided
                $barcodeType = $data['barcode_type'] ?? BarcodeDetector::detectBarcodeType($data['barcode']);
                
                // Get barcode info for validation
                $barcodeInfo = BarcodeDetector::getBarcodeInfo($data['barcode']);
                
                if (!$barcodeInfo['is_valid']) {
                    Log::warning("Invalid barcode detected", [
                        'barcode' => $data['barcode'],
                        'detected_type' => $barcodeType,
                        'sku' => $variant->sku
                    ]);
                }
                
                $variant->barcodes()->create([
                    'barcode' => $data['barcode'],
                    'barcode_type' => $barcodeType,
                ]);
                
                Log::info("Added barcode to variant", [
                    'sku' => $variant->sku, 
                    'barcode' => $data['barcode'],
                    'type' => $barcodeType,
                    'auto_detected' => !isset($data['barcode_type'])
                ]);
            }
        } elseif ($this->autoAssignGS1Barcodes && $variant->barcodes()->count() === 0) {
            $nextBarcode = BarcodePool::getNextAvailable('EAN13');
            if ($nextBarcode) {
                $variant->barcodes()->create([
                    'barcode' => $nextBarcode->barcode,
                    'type' => 'EAN13',
                ]);
                $nextBarcode->markAsUsed($variant->id);
                
                Log::info("Auto-assigned GS1 barcode to variant", [
                    'sku' => $variant->sku, 
                    'barcode' => $nextBarcode->barcode
                ]);
            }
        }
    }

    /**
     * Handle pricing assignment for variant
     */
    private function handleVariantPricing(ProductVariant $variant, array $data): void
    {
        if (!empty($data['retail_price'])) {
            $existingPricing = Pricing::where('product_variant_id', $variant->id)
                ->where('marketplace', 'website')
                ->first();
            
            $pricingData = [
                'product_variant_id' => $variant->id,
                'marketplace' => 'website',
                'retail_price' => $data['retail_price'],
                'cost_price' => $data['cost_price'] ?? null,
                'vat_percentage' => 20.00,
                'vat_inclusive' => true,
            ];
            
            if ($existingPricing) {
                $existingPricing->update($pricingData);
            } else {
                Pricing::create($pricingData);
            }
        }
    }

    public function resetImport()
    {
        $this->reset();
        $this->step = 1;
    }

    public function debugColumnMapping()
    {
        // Force log the debug info
        Log::info('MANUAL DEBUG - Column Mapping State', [
            'columnMapping' => $this->columnMapping,
            'headers' => $this->headers,
            'availableFields' => array_keys($this->availableFields),
            'mappedFields' => array_values($this->columnMapping),
            'step' => $this->step
        ]);
        
        $this->importErrors = [
            'Debug Info Logged - Check Laravel logs!',
            'Column Mapping: ' . json_encode($this->columnMapping),
            'Headers: ' . json_encode($this->headers),
            'Available Fields: ' . json_encode(array_keys($this->availableFields)),
            'Mapped Fields: ' . json_encode(array_values($this->columnMapping)),
        ];
    }

    private function generateNextSequentialSku(): ?string
    {
        // First, try to detect the prefix from existing SKUs in this import batch
        $prefix = $this->detectSkuPrefix();
        
        if (!$prefix) {
            Log::warning("Could not detect SKU prefix from import data");
            return null;
        }
        
        // Find the highest sequence number from both database and current import
        $highestNumber = $this->findHighestSkuNumber($prefix);
        
        // Generate next sequential SKU
        $nextNumber = $highestNumber + 1;
        $nextSku = sprintf('%s-%03d', $prefix, $nextNumber);
        
        Log::info("Generated sequential SKU", [
            'prefix' => $prefix,
            'highest_existing' => $highestNumber,
            'generated_sku' => $nextSku
        ]);
        
        return $nextSku;
    }
    
    private function detectSkuPrefix(): ?string
    {
        // Check current import data for existing SKUs with pattern XXX-YYY
        // Use raw column mapping to avoid recursion
        foreach ($this->allData as $row) {
            foreach ($this->columnMapping as $columnIndex => $fieldName) {
                if ($fieldName === 'variant_sku' && isset($row[$columnIndex]) && !empty($row[$columnIndex])) {
                    $sku = $row[$columnIndex];
                    if (preg_match('/^(\d{3})-\d{3}$/', $sku, $matches)) {
                        return $matches[1];
                    }
                }
            }
        }
        
        // Fallback: check database for most recent SKU pattern
        $recentVariants = ProductVariant::where('sku', 'LIKE', '___-___')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();
            
        foreach ($recentVariants as $variant) {
            if (preg_match('/^(\d{3})-\d{3}$/', $variant->sku, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }
    
    private function findHighestSkuNumber(string $prefix): int
    {
        $highestFromDb = 0;
        $highestFromImport = 0;
        
        // Check database for highest number with this prefix
        $dbVariants = ProductVariant::where('sku', 'LIKE', $prefix . '-%')->get();
        foreach ($dbVariants as $variant) {
            if (preg_match('/^' . preg_quote($prefix, '/') . '-(\d{3})$/', $variant->sku, $matches)) {
                $highestFromDb = max($highestFromDb, (int)$matches[1]);
            }
        }
        
        // Check current import batch for highest number (avoid recursion)
        foreach ($this->allData as $row) {
            foreach ($this->columnMapping as $columnIndex => $fieldName) {
                if ($fieldName === 'variant_sku' && isset($row[$columnIndex]) && !empty($row[$columnIndex])) {
                    $sku = $row[$columnIndex];
                    if (preg_match('/^' . preg_quote($prefix, '/') . '-(\d{3})$/', $sku, $matches)) {
                        $highestFromImport = max($highestFromImport, (int)$matches[1]);
                    }
                }
            }
        }
        
        return max($highestFromDb, $highestFromImport);
    }

    /**
     * Handle marketplace variants creation from import data
     */
    private function handleMarketplaceVariants(ProductVariant $variant, array $data): void
    {
        // Define mapping of data fields to marketplaces
        $marketplaceMappings = [
            'ebay' => ['ebay_title', 'ebay_description', 'ebay_price'],
            'ebay_bo' => ['ebay_bo_title', 'ebay_bo_description', 'ebay_bo_price'], 
            'amazon' => ['amazon_title', 'amazon_description', 'amazon_price'],
            'amazon_fba' => ['amazon_fba_title', 'amazon_fba_description', 'amazon_fba_price'],
            'onbuy' => ['onbuy_title', 'onbuy_description', 'onbuy_price'],
            'website' => ['website_title', 'website_description', 'website_price'],
        ];

        foreach ($marketplaceMappings as $marketplaceCode => $fields) {
            $titleField = $fields[0];
            $descriptionField = $fields[1];
            $priceField = $fields[2];

            // Only create marketplace variant if we have at least a title
            if (!empty($data[$titleField])) {
                // Find the marketplace
                $marketplace = Marketplace::where('code', $marketplaceCode)->first();
                if (!$marketplace) {
                    Log::warning("Marketplace not found for code: {$marketplaceCode}");
                    continue;
                }

                // Check if marketplace variant already exists
                $existingMV = MarketplaceVariant::where('variant_id', $variant->id)
                    ->where('marketplace_id', $marketplace->id)
                    ->first();

                $marketplaceData = [
                    'variant_id' => $variant->id,
                    'marketplace_id' => $marketplace->id,
                    'title' => $data[$titleField],
                    'description' => $data[$descriptionField] ?? null,
                    'price_override' => !empty($data[$priceField]) ? $data[$priceField] : null,
                    'status' => 'active',
                    'marketplace_data' => json_encode([
                        'source' => 'import',
                        'imported_at' => now()->toISOString()
                    ])
                ];

                if ($existingMV) {
                    // Update existing marketplace variant
                    $existingMV->update($marketplaceData);
                    Log::info("Updated marketplace variant", [
                        'sku' => $variant->sku,
                        'marketplace' => $marketplace->name,
                        'title' => $data[$titleField]
                    ]);
                } else {
                    // Create new marketplace variant
                    MarketplaceVariant::create($marketplaceData);
                    Log::info("Created marketplace variant", [
                        'sku' => $variant->sku,
                        'marketplace' => $marketplace->name,
                        'title' => $data[$titleField]
                    ]);
                }
            }
        }
    }

    /**
     * Handle marketplace barcodes/identifiers creation from import data
     */
    private function handleMarketplaceBarcodes(ProductVariant $variant, array $data): void
    {
        // Define mapping of data fields to marketplace identifiers
        $identifierMappings = [
            'amazon_asin' => ['amazon', 'asin'],
            'amazon_fba_asin' => ['amazon_fba', 'asin'],
            'ebay_item_id' => ['ebay', 'item_id'],
            'ebay_bo_item_id' => ['ebay_bo', 'item_id'],
            'onbuy_product_id' => ['onbuy', 'product_id'],
        ];

        foreach ($identifierMappings as $field => $marketplaceInfo) {
            $marketplaceCode = $marketplaceInfo[0];
            $identifierType = $marketplaceInfo[1];

            if (!empty($data[$field])) {
                // Find the marketplace
                $marketplace = Marketplace::where('code', $marketplaceCode)->first();
                if (!$marketplace) {
                    Log::warning("Marketplace not found for code: {$marketplaceCode}");
                    continue;
                }

                // Check if marketplace barcode already exists
                $existingMB = MarketplaceBarcode::where('variant_id', $variant->id)
                    ->where('marketplace_id', $marketplace->id)
                    ->where('identifier_type', $identifierType)
                    ->first();

                $barcodeData = [
                    'variant_id' => $variant->id,
                    'marketplace_id' => $marketplace->id,
                    'identifier_type' => $identifierType,
                    'identifier_value' => $data[$field],
                    'is_active' => true,
                ];

                if ($existingMB) {
                    // Update existing marketplace barcode
                    $existingMB->update($barcodeData);
                    Log::info("Updated marketplace identifier", [
                        'sku' => $variant->sku,
                        'marketplace' => $marketplace->name,
                        'type' => $identifierType,
                        'value' => $data[$field]
                    ]);
                } else {
                    // Create new marketplace barcode
                    MarketplaceBarcode::create($barcodeData);
                    Log::info("Created marketplace identifier", [
                        'sku' => $variant->sku,
                        'marketplace' => $marketplace->name,
                        'type' => $identifierType,
                        'value' => $data[$field]
                    ]);
                }
            }
        }
    }

    /**
     * Handle product attributes creation from import data
     */
    private function handleProductAttributes(Product $product, array $data): void
    {
        // Define product-level attributes
        $productAttributeFields = [
            'attribute_material' => 'material',
            'attribute_fabric_type' => 'fabric_type',
            'attribute_operation_type' => 'operation_type',
            'attribute_mount_type' => 'mount_type',
            'attribute_child_safety' => 'child_safety',
            'attribute_room_darkening' => 'room_darkening',
            'attribute_fire_rating' => 'fire_rating',
            'attribute_warranty_years' => 'warranty_years',
            'attribute_installation_required' => 'installation_required',
            'attribute_custom_size_available' => 'custom_size_available',
        ];

        foreach ($productAttributeFields as $field => $attributeKey) {
            if (!empty($data[$field])) {
                // Check if product attribute already exists
                $existingAttr = ProductAttribute::where('product_id', $product->id)
                    ->where('attribute_key', $attributeKey)
                    ->first();

                // Determine data type based on content
                $dataType = $this->determineDataType($data[$field]);
                
                $attributeData = [
                    'product_id' => $product->id,
                    'attribute_key' => $attributeKey,
                    'attribute_value' => $data[$field],
                    'data_type' => $dataType,
                    'category' => $this->getCategoryForAttribute($attributeKey),
                ];

                if ($existingAttr) {
                    // Update existing product attribute
                    $existingAttr->update($attributeData);
                    Log::info("Updated product attribute", [
                        'product' => $product->name,
                        'key' => $attributeKey,
                        'value' => $data[$field]
                    ]);
                } else {
                    // Create new product attribute
                    ProductAttribute::create($attributeData);
                    Log::info("Created product attribute", [
                        'product' => $product->name,
                        'key' => $attributeKey,
                        'value' => $data[$field]
                    ]);
                }
            }
        }
    }

    /**
     * Handle variant attributes creation from import data
     */
    private function handleVariantAttributes(ProductVariant $variant, array $data): void
    {
        // Define variant-level attributes
        $variantAttributeFields = [
            'variant_attribute_width_mm' => 'width_mm',
            'variant_attribute_drop_mm' => 'drop_mm',
            'variant_attribute_chain_length' => 'chain_length',
            'variant_attribute_slat_width' => 'slat_width',
            'variant_attribute_fabric_pattern' => 'fabric_pattern',
            'variant_attribute_opacity_level' => 'opacity_level',
        ];

        foreach ($variantAttributeFields as $field => $attributeKey) {
            if (!empty($data[$field])) {
                // Check if variant attribute already exists
                $existingAttr = VariantAttribute::where('variant_id', $variant->id)
                    ->where('attribute_key', $attributeKey)
                    ->first();

                // Determine data type based on content
                $dataType = $this->determineDataType($data[$field]);
                
                $attributeData = [
                    'variant_id' => $variant->id,
                    'attribute_key' => $attributeKey,
                    'attribute_value' => $data[$field],
                    'data_type' => $dataType,
                    'category' => $this->getCategoryForAttribute($attributeKey),
                ];

                if ($existingAttr) {
                    // Update existing variant attribute
                    $existingAttr->update($attributeData);
                    Log::info("Updated variant attribute", [
                        'sku' => $variant->sku,
                        'key' => $attributeKey,
                        'value' => $data[$field]
                    ]);
                } else {
                    // Create new variant attribute
                    VariantAttribute::create($attributeData);
                    Log::info("Created variant attribute", [
                        'sku' => $variant->sku,
                        'key' => $attributeKey,
                        'value' => $data[$field]
                    ]);
                }
            }
        }
    }

    /**
     * Determine data type of attribute value
     */
    private function determineDataType(string $value): string
    {
        // Check if it's a number
        if (is_numeric($value)) {
            return 'number';
        }
        
        // Check if it's a boolean-like value
        $booleanValues = ['true', 'false', 'yes', 'no', '1', '0'];
        if (in_array(strtolower(trim($value)), $booleanValues)) {
            return 'boolean';
        }
        
        // Check if it looks like JSON
        if (str_starts_with(trim($value), '{') || str_starts_with(trim($value), '[')) {
            $decoded = json_decode($value);
            if (json_last_error() === JSON_ERROR_NONE) {
                return 'json';
            }
        }
        
        // Default to string
        return 'string';
    }

    /**
     * Get category for attribute based on attribute key
     */
    private function getCategoryForAttribute(string $attributeKey): string
    {
        $physicalAttributes = ['width_mm', 'drop_mm', 'chain_length', 'slat_width', 'fabric_pattern'];
        $functionalAttributes = ['operation_type', 'mount_type', 'room_darkening', 'opacity_level'];
        $complianceAttributes = ['fire_rating', 'child_safety'];
        
        if (in_array($attributeKey, $physicalAttributes)) {
            return 'physical';
        } elseif (in_array($attributeKey, $functionalAttributes)) {
            return 'functional';
        } elseif (in_array($attributeKey, $complianceAttributes)) {
            return 'compliance';
        }
        
        return 'general';
    }

    public function testUpload()
    {
        Log::info('Test upload triggered', [
            'step' => $this->step,
            'file' => $this->file ? $this->file->getClientOriginalName() : 'no file',
            'memory' => memory_get_usage(true)
        ]);
        
        session()->flash('message', 'Test completed - check logs');
    }

    private function loadSingleSheetData($worksheetIndex)
    {
        try {
            $worksheetName = $this->availableWorksheets[$worksheetIndex]['name'];
            
            Log::info('Loading single sheet data for sequential processing', [
                'worksheet_index' => $worksheetIndex,
                'worksheet_name' => $worksheetName
            ]);
            
            // Use PhpSpreadsheet directly for memory-efficient reading
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);
            
            // Load only the specific worksheet
            $reader->setLoadSheetsOnly([$worksheetName]);
            
            $spreadsheet = $reader->load($this->file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            
            // Read headers from this specific sheet
            $sheetHeaders = [];
            $columnIterator = $worksheet->getColumnIterator('A');
            $colIndex = 0;
            foreach ($columnIterator as $column) {
                if ($colIndex >= count($this->headers)) {
                    break;
                }
                $cellValue = $worksheet->getCell($column->getColumnIndex() . '1')->getCalculatedValue();
                $sheetHeaders[] = (string)$cellValue;
                $colIndex++;
            }

            $sheetData = [];
            $maxRow = $worksheet->getHighestRow();
            
            // Read all rows starting from row 2 (skip header)
            for ($row = 2; $row <= $maxRow; $row++) {
                $rowData = [];
                $hasData = false;
                
                // Read each column in this row up to the number of headers we have
                $columnIterator = $worksheet->getColumnIterator('A');
                $colIndex = 0;
                foreach ($columnIterator as $column) {
                    if ($colIndex >= count($sheetHeaders)) {
                        break;
                    }
                    
                    $cellValue = $worksheet->getCell($column->getColumnIndex() . $row)->getCalculatedValue();
                    $rowData[] = $cellValue;
                    if ($cellValue !== null && trim($cellValue) !== '') {
                        $hasData = true;
                    }
                    $colIndex++;
                }
                
                // Only add rows that have some data
                if ($hasData) {
                    $sheetData[] = [
                        'data' => $rowData,
                        'headers' => $sheetHeaders
                    ];
                }
            }
            
            Log::info('Loaded single sheet data', [
                'worksheet_name' => $worksheetName,
                'rows_with_data' => count($sheetData),
                'total_rows_in_sheet' => $maxRow - 1
            ]);
            
            // Clean up memory
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $worksheet, $reader);
            
            return $sheetData;
            
        } catch (\Exception $e) {
            Log::error('Failed to load single sheet data', [
                'worksheet_index' => $worksheetIndex,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    private function processSheetData($sheetData, $worksheetName)
    {
        Log::info("Processing sheet data sequentially", [
            'worksheet_name' => $worksheetName,
            'row_count' => count($sheetData)
        ]);
        
        $sheetResults = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => []
        ];
        
        foreach ($sheetData as $rowIndex => $rowWithHeaders) {
            try {
                $rowNum = $rowIndex + 2; // +2 because of header row and 0-based index
                $mappedData = $this->mapRowData($rowWithHeaders['data'], $rowWithHeaders['headers']);
                
                // Process each row using the same logic as before
                $result = $this->processRow($mappedData, $rowNum);
                
                if ($result) {
                    $sheetResults['created']++;
                } else {
                    $sheetResults['skipped']++;
                }
                
            } catch (\Exception $e) {
                $sheetResults['errors'][] = "Row {$rowNum}: " . $e->getMessage();
                Log::error("Error processing row in sheet {$worksheetName}", [
                    'row' => $rowNum,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        Log::info("Completed processing sheet", [
            'worksheet_name' => $worksheetName,
            'results' => $sheetResults
        ]);
        
        return $sheetResults;
    }

    private function loadAllDataForImport()
    {
        try {
            Log::info('Loading all data for import from selected worksheets', [
                'selected_worksheets' => $this->selectedWorksheets
            ]);

            $allData = [];
            
            // Load data from all selected worksheets
            foreach ($this->selectedWorksheets as $worksheetIndex) {
                try {
                    $worksheetName = $this->availableWorksheets[$worksheetIndex]['name'];
                    
                    Log::info('Loading full data from worksheet for import', [
                        'worksheet_index' => $worksheetIndex,
                        'worksheet_name' => $worksheetName
                    ]);
                    
                    // Use PhpSpreadsheet directly for memory-efficient reading
                    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
                    $reader->setReadDataOnly(true);
                    $reader->setReadEmptyCells(false);
                    
                    // Load only the specific worksheet
                    $reader->setLoadSheetsOnly([$worksheetName]);
                    
                    $spreadsheet = $reader->load($this->file->getRealPath());
                    $worksheet = $spreadsheet->getActiveSheet();
                    
                    // Get all data from this sheet
                    $highestColumn = $worksheet->getHighestColumn();
                    $maxRow = $worksheet->getHighestRow();
                    
                    // Read headers from this specific sheet
                    $sheetHeaders = [];
                    $columnIterator = $worksheet->getColumnIterator('A');
                    $colIndex = 0;
                    foreach ($columnIterator as $column) {
                        if ($colIndex >= count($this->headers)) {
                            break;
                        }
                        $cellValue = $worksheet->getCell($column->getColumnIndex() . '1')->getCalculatedValue();
                        $sheetHeaders[] = (string)$cellValue;
                        $colIndex++;
                    }

                    // Read all rows starting from row 2 (skip header)
                    for ($row = 2; $row <= $maxRow; $row++) {
                        $rowData = [];
                        $hasData = false;
                        
                        // Read each column in this row up to the number of headers we have
                        $columnIterator = $worksheet->getColumnIterator('A');
                        $colIndex = 0;
                        foreach ($columnIterator as $column) {
                            if ($colIndex >= count($sheetHeaders)) {
                                break; // Stop when we've read all header columns
                            }
                            
                            $cellValue = $worksheet->getCell($column->getColumnIndex() . $row)->getCalculatedValue();
                            $rowData[] = $cellValue;
                            if ($cellValue !== null && trim($cellValue) !== '') {
                                $hasData = true;
                            }
                            $colIndex++;
                        }
                        
                        // Only add rows that have some data, and store with sheet headers
                        if ($hasData) {
                            $allData[] = [
                                'data' => $rowData,
                                'headers' => $sheetHeaders
                            ];
                        }
                    }
                    
                    // Debug: Check if headers match expectations
                    $sheetHeaders = [];
                    $columnIterator = $worksheet->getColumnIterator('A');
                    $colIndex = 0;
                    foreach ($columnIterator as $column) {
                        if ($colIndex >= count($this->headers)) {
                            break;
                        }
                        $cellValue = $worksheet->getCell($column->getColumnIndex() . '1')->getCalculatedValue();
                        $sheetHeaders[] = (string)$cellValue;
                        $colIndex++;
                    }
                    
                    Log::info('Loaded sheet data for import', [
                        'worksheet_index' => $worksheetIndex,
                        'worksheet_name' => $worksheetName,
                        'rows_loaded' => $maxRow - 1, // -1 for header
                        'rows_with_data' => count($allData),
                        'sheet_headers' => array_slice($sheetHeaders, 0, 5),
                        'expected_headers' => array_slice($this->headers, 0, 5),
                        'headers_match' => array_slice($sheetHeaders, 0, 5) === array_slice($this->headers, 0, 5)
                    ]);
                    
                    // Clean up memory
                    $spreadsheet->disconnectWorksheets();
                    unset($spreadsheet, $worksheet, $reader);
                    
                } catch (\Exception $e) {
                    Log::error('Failed to load sheet data for import', [
                        'worksheet_index' => $worksheetIndex,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            Log::info('Import data loading completed', [
                'total_rows' => count($allData),
                'worksheets_processed' => count($this->selectedWorksheets)
            ]);
            
            return $allData;
            
        } catch (\Exception $e) {
            Log::error('Failed to load data for import', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }


    /**
     * Generate a unique slug, handling duplicates gracefully
     */
    private function generateUniqueSlug(string $name, string $context = 'product'): string
    {
        $baseSlug = Str::slug($name);
        
        // If base slug is empty, create a fallback
        if (empty($baseSlug)) {
            $baseSlug = $context . '-' . time();
        }
        
        $slug = $baseSlug;
        $counter = 1;
        
        // Keep checking until we find a unique slug
        while (Product::where('slug', $slug)->exists()) {
            $counter++;
            $slug = $baseSlug . '-' . $counter;
            
            // Prevent infinite loops
            if ($counter > 1000) {
                $slug = $baseSlug . '-' . uniqid();
                break;
            }
        }
        
        // Log slug conflicts for user feedback
        if ($counter > 1) {
            $message = "Slug conflict resolved: '{$name}' → '{$slug}' (similar product name exists)";
            Log::info($message);
            
            // Add to import warnings for user feedback
            if (!isset($this->dryRunResults['warnings'])) {
                $this->dryRunResults['warnings'] = [];
            }
            $this->dryRunResults['warnings'][] = $message;
            $this->importWarnings[] = $message;
        }
        
        return $slug;
    }

    public function checkImportProgress()
    {
        // This method is called by wire:poll.1s during import
        // The progress is updated by the actual import process
        // We just need to ensure the component stays reactive
        
        if ($this->importProgress >= 100) {
            // Stop polling when import is complete
            $this->importStatus = 'Import completed successfully!';
            
            // Optionally redirect or show completion message
            if ($this->importProgress == 100 && !str_contains($this->importStatus, 'completed')) {
                session()->flash('import_success', 'Import completed! ' . 
                    (isset($this->importProgressDetails['products_created']) ? 
                        $this->importProgressDetails['products_created'] . ' products processed.' : 
                        'All products processed successfully.'));
            }
        }
        
        // Log current status for debugging
        Log::debug('Import progress check', [
            'progress' => $this->importProgress,
            'status' => $this->importStatus,
            'step' => $this->step,
            'details' => $this->importProgressDetails
        ]);
    }

    public function render()
    {
        return view('livewire.data-exchange.import.import-data');
    }
}
