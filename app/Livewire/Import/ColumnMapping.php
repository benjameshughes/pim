<?php

namespace App\Livewire\Import;

use App\Models\ImportSession;
use App\Jobs\Import\DryRunJob;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ColumnMapping extends Component
{
    public ImportSession $session;
    public array $columnMapping = [];
    public array $fileHeaders = [];
    public array $sampleData = [];
    public bool $processing = false;

    protected $rules = [
        'columnMapping' => 'array',
        'columnMapping.*' => 'nullable|string',
    ];

    public function mount(ImportSession $session)
    {
        // Ensure user owns this session
        if ($session->user_id !== Auth::id()) {
            abort(403);
        }

        $this->session = $session;
        $this->loadFileAnalysis();
        $this->loadExistingMapping();
    }

    public function loadFileAnalysis()
    {
        if ($this->session->file_analysis) {
            $analysis = $this->session->file_analysis;
            $this->fileHeaders = $analysis['headers'] ?? [];
            $this->sampleData = $analysis['sample_data'] ?? [];
        }
    }

    public function loadExistingMapping()
    {
        $this->columnMapping = $this->session->column_mapping ?? [];
        
        // If no mapping exists, try to use suggested mapping
        if (empty($this->columnMapping) && isset($this->session->file_analysis['suggested_mapping'])) {
            $this->columnMapping = $this->session->file_analysis['suggested_mapping'];
        }
    }

    public function saveMapping()
    {
        $this->validate();

        try {
            $this->processing = true;

            // Filter out empty mappings
            $cleanMapping = array_filter($this->columnMapping, function($value) {
                return !empty($value);
            });

            // Ensure we have at least essential fields mapped
            $this->validateEssentialFields($cleanMapping);

            $this->session->update([
                'column_mapping' => $this->columnMapping,
                'status' => 'awaiting_processing',
            ]);

            Log::info('Column mapping saved', [
                'session_id' => $this->session->session_id,
                'mapping_count' => count($cleanMapping),
                'mapped_fields' => array_values($cleanMapping),
            ]);

            $this->dispatch('toast', [
                'type' => 'success',
                'message' => 'Column mapping saved successfully!',
            ]);

            $this->dispatch('mapping-saved', [
                'session_id' => $this->session->session_id,
            ]);

        } catch (\Exception $e) {
            Log::error('Column mapping save failed', [
                'session_id' => $this->session->session_id,
                'error' => $e->getMessage(),
            ]);

            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Failed to save mapping: ' . $e->getMessage(),
            ]);
        } finally {
            $this->processing = false;
        }
    }

    public function startDryRun()
    {
        if (empty(array_filter($this->columnMapping))) {
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Please map at least one column before starting dry run.',
            ]);
            return;
        }

        try {
            $this->saveMapping();
            
            $this->session->update(['status' => 'dry_run']);
            
            // Dispatch dry run job
            DryRunJob::dispatch($this->session)->onQueue('imports');

            Log::info('Dry run started from column mapping', [
                'session_id' => $this->session->session_id,
            ]);

            $this->dispatch('toast', [
                'type' => 'success',
                'message' => 'Dry run started! Redirecting to progress view...',
            ]);

            // Redirect to show page after a brief delay
            $this->dispatch('redirect-after-delay', [
                'url' => route('import.show', $this->session->session_id),
                'delay' => 2000,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to start dry run', [
                'session_id' => $this->session->session_id,
                'error' => $e->getMessage(),
            ]);

            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Failed to start dry run: ' . $e->getMessage(),
            ]);
        }
    }

    public function autoMap()
    {
        $this->columnMapping = $this->generateAutoMapping();
        
        $this->dispatch('toast', [
            'type' => 'success',
            'message' => 'Auto-mapping applied! Please review and adjust as needed.',
        ]);
    }

    public function clearMapping()
    {
        $this->columnMapping = array_fill(0, count($this->fileHeaders), '');
        
        $this->dispatch('toast', [
            'type' => 'info',
            'message' => 'Column mapping cleared.',
        ]);
    }

    public function getMappingStats()
    {
        $mapped = count(array_filter($this->columnMapping));
        $total = count($this->fileHeaders);
        $percentage = $total > 0 ? round(($mapped / $total) * 100) : 0;

        return [
            'mapped' => $mapped,
            'total' => $total,
            'percentage' => $percentage,
        ];
    }

    private function validateEssentialFields(array $mapping)
    {
        $essentialFields = ['product_name', 'variant_sku'];
        $mappedFields = array_values($mapping);
        
        $missing = array_diff($essentialFields, $mappedFields);
        
        if (!empty($missing)) {
            throw new \Exception(
                'Essential fields missing: ' . implode(', ', $missing) . 
                '. Please map these fields before proceeding.'
            );
        }
    }

    private function generateAutoMapping(): array
    {
        $mapping = array_fill(0, count($this->fileHeaders), '');
        
        $fieldPatterns = [
            'product_name' => [
                '/^product\s*name$/i',
                '/^name$/i',
                '/^title$/i',
                '/^product$/i',
            ],
            'variant_sku' => [
                '/^sku$/i',
                '/^variant\s*sku$/i',
                '/^product\s*sku$/i',
                '/^code$/i',
                '/^item\s*code$/i',
            ],
            'description' => [
                '/^description$/i',
                '/^desc$/i',
                '/^details$/i',
            ],
            'variant_color' => [
                '/^colou?r$/i',
                '/^variant\s*colou?r$/i',
            ],
            'variant_size' => [
                '/^size$/i',
                '/^variant\s*size$/i',
                '/^dimensions$/i',
            ],
            'retail_price' => [
                '/^price$/i',
                '/^retail\s*price$/i',
                '/^sell\s*price$/i',
                '/^selling\s*price$/i',
            ],
            'cost_price' => [
                '/^cost$/i',
                '/^cost\s*price$/i',
                '/^buy\s*price$/i',
            ],
            'barcode' => [
                '/^barcode$/i',
                '/^ean$/i',
                '/^upc$/i',
                '/^gtin$/i',
            ],
            'stock_level' => [
                '/^stock$/i',
                '/^quantity$/i',
                '/^qty$/i',
                '/^inventory$/i',
            ],
        ];

        foreach ($this->fileHeaders as $index => $header) {
            $headerLower = strtolower(trim($header));
            
            foreach ($fieldPatterns as $field => $patterns) {
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $headerLower)) {
                        $mapping[$index] = $field;
                        break 2; // Break both loops
                    }
                }
            }
        }

        return $mapping;
    }

    public function render()
    {
        return view('livewire.import.column-mapping', [
            'availableFields' => $this->getAvailableFields(),
            'mappingStats' => $this->getMappingStats(),
        ]);
    }

    private function getAvailableFields(): array
    {
        return [
            'Core Fields' => [
                'product_name' => ['label' => 'Product Name', 'required' => true, 'description' => 'Main product title'],
                'variant_sku' => ['label' => 'Variant SKU', 'required' => true, 'description' => 'Unique product variant code'],
                'description' => ['label' => 'Description', 'required' => false, 'description' => 'Product description'],
            ],
            'Variant Attributes' => [
                'variant_color' => ['label' => 'Color', 'required' => false, 'description' => 'Product color'],
                'variant_size' => ['label' => 'Size', 'required' => false, 'description' => 'Product size or dimensions'],
            ],
            'Pricing' => [
                'retail_price' => ['label' => 'Retail Price', 'required' => false, 'description' => 'Selling price'],
                'cost_price' => ['label' => 'Cost Price', 'required' => false, 'description' => 'Purchase/cost price'],
            ],
            'Inventory' => [
                'stock_level' => ['label' => 'Stock Level', 'required' => false, 'description' => 'Current inventory quantity'],
                'barcode' => ['label' => 'Barcode', 'required' => false, 'description' => 'EAN, UPC, or other barcode'],
                'barcode_type' => ['label' => 'Barcode Type', 'required' => false, 'description' => 'EAN13, UPC, etc.'],
            ],
            'Dimensions & Weight' => [
                'package_length' => ['label' => 'Package Length', 'required' => false, 'description' => 'Package length (cm)'],
                'package_width' => ['label' => 'Package Width', 'required' => false, 'description' => 'Package width (cm)'],
                'package_height' => ['label' => 'Package Height', 'required' => false, 'description' => 'Package height (cm)'],
                'package_weight' => ['label' => 'Package Weight', 'required' => false, 'description' => 'Package weight (kg)'],
            ],
        ];
    }
}