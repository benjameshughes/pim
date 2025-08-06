<?php

namespace App\Livewire\Forms;

use Livewire\Attributes\Validate;
use Livewire\Form;

class ImportConfigurationForm extends Form
{
    #[Validate(['required', 'file', 'mimes:xlsx,xls,csv', 'max:102400'])]
    public $file;
    
    #[Validate(['required', 'array', 'min:1'])]
    public array $selectedWorksheets = [];
    
    #[Validate(['required', 'array'])]
    public array $columnMapping = [];
    
    #[Validate(['required', 'in:create_only,update_existing,create_or_update'])]
    public string $importMode = 'create_only';
    
    #[Validate(['boolean'])]
    public bool $enableAutoParentCreation = true;
    
    #[Validate(['boolean'])]
    public bool $enableSmartAttributeExtraction = true;
    
    #[Validate(['boolean'])]
    public bool $enableAutoBarcodeAssignment = false;
    
    #[Validate(['nullable', 'integer', 'min:1', 'max:1000'])]
    public ?int $batchSize = 100;
    
    #[Validate(['boolean'])]
    public bool $skipHeaderRow = true;
    
    public array $availableFields = [
        'variant_sku' => 'Variant SKU',
        'product_name' => 'Product Name',
        'variant_color' => 'Color',
        'variant_size' => 'Size',
        'stock_quantity' => 'Stock Quantity',
        'retail_price' => 'Retail Price',
        'barcode' => 'Barcode',
        'weight' => 'Weight',
        'length' => 'Length',
        'width' => 'Width',
        'height' => 'Height',
        'image_urls' => 'Image URLs'
    ];
    
    public function resetToDefaults(): void
    {
        $this->selectedWorksheets = [];
        $this->columnMapping = [];
        $this->importMode = 'create_only';
        $this->enableAutoParentCreation = true;
        $this->enableSmartAttributeExtraction = true;
        $this->enableAutoBarcodeAssignment = false;
        $this->batchSize = 100;
        $this->skipHeaderRow = true;
    }
    
    public function setColumnMapping(array $mapping): void
    {
        $this->columnMapping = $mapping;
    }
    
    public function addWorksheet(string $worksheetName): void
    {
        if (!in_array($worksheetName, $this->selectedWorksheets)) {
            $this->selectedWorksheets[] = $worksheetName;
        }
    }
    
    public function removeWorksheet(string $worksheetName): void
    {
        $this->selectedWorksheets = array_values(
            array_filter($this->selectedWorksheets, fn($name) => $name !== $worksheetName)
        );
    }
    
    public function hasRequiredMappings(): bool
    {
        $hasSku = !empty($this->columnMapping['variant_sku']);
        $hasName = !empty($this->columnMapping['product_name']);
        
        return $hasSku || $hasName;
    }
    
    public function getImportModeDescription(): string
    {
        return match($this->importMode) {
            'create_only' => 'Skip existing SKUs and create new products/variants only',
            'update_existing' => 'Update existing records only, skip new ones',
            'create_or_update' => 'Create new records and update existing ones (upsert)',
            default => 'Unknown mode'
        };
    }
    
    public function toImportRequest(): array
    {
        return [
            'selectedWorksheets' => $this->selectedWorksheets,
            'columnMapping' => $this->columnMapping,
            'importMode' => $this->importMode,
            'enableAutoParentCreation' => $this->enableAutoParentCreation,
            'enableSmartAttributeExtraction' => $this->enableSmartAttributeExtraction,
            'enableAutoBarcodeAssignment' => $this->enableAutoBarcodeAssignment,
            'batchSize' => $this->batchSize ?? 100,
            'skipHeaderRow' => $this->skipHeaderRow,
        ];
    }
}