<?php

namespace App\Services;

use App\DTOs\Import\ImportRequest;
use App\DTOs\Import\ImportResult;
use App\Actions\Import\CreateParentProduct;
use App\Actions\Import\CreateProductVariant;
use App\Services\ProductNameGrouping;
use Illuminate\Support\Facades\Log;

class ProductImportService
{
    public function __construct(
        private CreateParentProduct $createParentProduct
    ) {}

    /**
     * Import products and variants with progress tracking
     */
    public function importProducts(array $mappedData, ImportRequest $request, callable $progressCallback = null): ImportResult
    {
        $result = new ImportResult();
        
        Log::info('Starting product import', [
            'total_rows' => count($mappedData),
            'import_mode' => $request->importMode,
            'auto_generate_parents' => $request->autoGenerateParentMode
        ]);
        
        if ($request->autoGenerateParentMode) {
            return $this->runTwoPhaseImport($mappedData, $request, $result, $progressCallback);
        } else {
            return $this->runStandardImport($mappedData, $request, $result, $progressCallback);
        }
    }

    /**
     * Two-phase import: Create all parents first, then variants
     */
    private function runTwoPhaseImport(array $mappedData, ImportRequest $request, ImportResult $result, callable $progressCallback = null): ImportResult
    {
        Log::info('Starting two-phase import (auto-generate parent mode)');
        
        // Phase 1: Group data and create parent products
        $parentGroups = ProductNameGrouping::groupSimilarProducts($mappedData);
        $createdParents = [];
        
        foreach ($parentGroups as $parentKey => $productGroup) {
            $variantDataArray = array_column($productGroup, 'data');
            $parent = $this->createParentProduct->execute($variantDataArray[0]);
            $createdParents[$parentKey] = $parent;
            
            $result->incrementProductsCreated();
            
            Log::info("Created parent product using grouping", [
                'parent_id' => $parent->id,
                'parent_name' => $parent->name,
                'variant_count' => count($variantDataArray)
            ]);
        }
        
        // Phase 2: Create all variants and link them to parents
        $processed = 0;
        foreach ($mappedData as $rowData) {
            // Find which parent this variant belongs to
            $parentKey = $this->findParentKeyForVariant($rowData, $parentGroups);
            $parent = $createdParents[$parentKey] ?? null;
            
            if ($parent) {
                // Create variant and link to parent
                $this->processVariantRow($rowData, $parent, $result);
            } else {
                Log::warning("No parent found for variant", ['variant_sku' => $rowData['variant_sku'] ?? 'N/A']);
                $result->addError("No parent found for variant: " . ($rowData['variant_sku'] ?? 'N/A'));
            }
            
            $processed++;
            
            // Update progress
            if ($progressCallback && $processed % 10 === 0) {
                $progress = ($processed / count($mappedData)) * 100;
                $progressCallback([
                    'progress' => $progress,
                    'status' => "Processing variants... ({$processed}/" . count($mappedData) . ")",
                    'processed_rows' => $processed
                ]);
            }
        }
        
        return $result;
    }

    /**
     * Standard import: Process each row individually
     */
    private function runStandardImport(array $mappedData, ImportRequest $request, ImportResult $result, callable $progressCallback = null): ImportResult
    {
        Log::info('Starting standard import');
        
        $processed = 0;
        foreach ($mappedData as $rowData) {
            $this->processRow($rowData, $result, $request);
            $processed++;
            
            // Update progress
            if ($progressCallback && $processed % 10 === 0) {
                $progress = ($processed / count($mappedData)) * 100;
                $progressCallback([
                    'progress' => $progress,
                    'status' => "Processing rows... ({$processed}/" . count($mappedData) . ")",
                    'processed_rows' => $processed
                ]);
            }
        }
        
        return $result;
    }

    /**
     * Process a single row of import data
     */
    private function processRow(array $rowData, ImportResult $result, ImportRequest $request): void
    {
        try {
            if ($this->isParentRow($rowData)) {
                $this->processParentRow($rowData, $result, $request);
            } else {
                $this->processVariantRow($rowData, null, $result, $request);
            }
        } catch (\Exception $e) {
            Log::error('Error processing row', [
                'error' => $e->getMessage(),
                'row_data' => $rowData
            ]);
            $result->addError("Error processing row: " . $e->getMessage());
        }
    }

    /**
     * Determine if a row represents a parent product
     */
    private function isParentRow(array $rowData): bool
    {
        return !empty($rowData['is_parent']) && 
               (strtolower($rowData['is_parent']) === 'true' || $rowData['is_parent'] === '1');
    }

    /**
     * Process a parent product row
     */
    private function processParentRow(array $rowData, ImportResult $result, ImportRequest $request): void
    {
        $parent = $this->createParentProduct->execute($rowData);
        $result->incrementProductsCreated();
        
        Log::info("Created parent product", [
            'parent_id' => $parent->id,
            'parent_name' => $parent->name
        ]);
    }

    /**
     * Process a variant row
     */
    private function processVariantRow(array $rowData, $parent = null, ImportResult $result, ImportRequest $request = null): void
    {
        // If no parent provided, find or create one
        if (!$parent) {
            $parent = $this->findOrCreateParentForVariant($rowData, $request);
        }
        
        // Create the variant
        $variant = app(CreateProductVariant::class)->execute($rowData, $parent);
        $result->incrementVariantsCreated();
        
        Log::info("Created variant", [
            'variant_id' => $variant->id,
            'variant_sku' => $variant->sku,
            'parent_id' => $parent->id
        ]);
    }

    /**
     * Find or create parent product for a variant
     */
    private function findOrCreateParentForVariant(array $rowData, ImportRequest $request)
    {
        // Try to find existing parent by name
        if (!empty($rowData['parent_name'])) {
            $parent = \App\Models\Product::where('name', $rowData['parent_name'])->first();
            if ($parent) {
                return $parent;
            }
        }
        
        // Create new parent
        return $this->createParentProduct->execute($rowData);
    }

    /**
     * Find parent key for variant in grouped data
     */
    private function findParentKeyForVariant(array $rowData, array $parentGroups): ?string
    {
        foreach ($parentGroups as $parentKey => $productGroup) {
            foreach ($productGroup as $groupItem) {
                if (isset($groupItem['data']['variant_sku']) && 
                    $groupItem['data']['variant_sku'] === $rowData['variant_sku']) {
                    return $parentKey;
                }
            }
        }
        
        return null;
    }
}