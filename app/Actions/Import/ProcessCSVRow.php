<?php

namespace App\Actions\Import;

use App\Actions\Import\AttributeAssignmentAction;
use App\Actions\Import\ExtractDimensions;
use App\Actions\Barcodes\AssignBarcode;
use App\Actions\Pricing\AssignPricing;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Log;

/**
 * 📝 PROCESS CSV ROW ACTION
 *
 * Handles processing of a single CSV row into products and variants
 * Coordinates all the sub-actions needed for a complete import
 */
class ProcessCSVRow
{
    private CreateOrUpdateProduct $createProduct;
    private CreateOrUpdateVariant $createVariant;
    private AttributeAssignmentAction $assignAttributes;
    private AssignBarcode $assignBarcode;
    private AssignPricing $assignPricing;
    private ExtractParentInfo $extractParentInfo;

    public function __construct()
    {
        $this->createProduct = new CreateOrUpdateProduct();
        $this->createVariant = new CreateOrUpdateVariant();
        $this->assignAttributes = new AttributeAssignmentAction();
        $this->assignBarcode = new AssignBarcode();
        $this->assignPricing = new AssignPricing();
        $this->extractParentInfo = new ExtractParentInfo(new ExtractDimensions());
    }

    /**
     * Process a single CSV row
     *
     * @param array $row Raw CSV row data
     * @param array $mappings Column mappings
     * @param array $adHocAttributes Ad-hoc attribute mappings
     * @param array $headers CSV headers
     * @param callable|null $statusCallback Status update callback
     * @return array Processing results
     */
    public function execute(
        array $row, 
        array $mappings, 
        array $adHocAttributes, 
        array $headers, 
        ?callable $statusCallback = null
    ): array {
        try {
            // Extract data from row using mappings
            $data = $this->extractRowData($row, $mappings);

            if (!$data['sku'] || !$data['title']) {
                return [
                    'success' => false,
                    'action' => 'skipped',
                    'reason' => 'Missing required SKU or title'
                ];
            }

            $statusCallback && $statusCallback('extracting_info', "Extracting info for {$data['sku']}");

            // Extract parent SKU and product info
            $parentInfo = $this->extractParentInfo->execute($data);

            $statusCallback && $statusCallback('creating_product', "Processing product {$parentInfo['parent_sku']}");

            // Create or update parent product
            $product = $this->createProduct->execute($parentInfo);

            $statusCallback && $statusCallback('creating_variant', "Creating variant {$data['sku']}");

            // Create or update variant
            $variant = $this->createVariant->execute($product, $data, $parentInfo);

            $statusCallback && $statusCallback('assigning_attributes', "Assigning attributes to {$data['sku']}");

            // Assign attributes to product and variant
            $this->assignAttributes->execute($product, $variant, $row, $mappings, $adHocAttributes, $headers);

            $statusCallback && $statusCallback('assigning_barcode', "Assigning barcode to {$data['sku']}");

            // Assign barcode to variant
            $csvBarcode = $data['barcode'] ?? null;
            $this->assignBarcode->execute($variant, $csvBarcode);

            $statusCallback && $statusCallback('assigning_pricing', "Setting price for {$data['sku']}");

            // Assign pricing to variant
            if (!empty($data['price'])) {
                $price = $this->parsePrice($data['price']);
                if ($price && $price > 0) {
                    $this->assignPricing->execute($variant, $price);
                }
            }

            return [
                'success' => true,
                'action' => 'processed',
                'product_created' => $product->wasRecentlyCreated,
                'variant_created' => $variant->wasRecentlyCreated,
                'sku' => $data['sku'],
                'product_name' => $parentInfo['product_name']
            ];

        } catch (\Exception $e) {
            Log::error('Row processing failed', [
                'sku' => $data['sku'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'action' => 'error',
                'error' => $e->getMessage(),
                'sku' => $data['sku'] ?? 'unknown'
            ];
        }
    }

    /**
     * Extract data from CSV row using column mappings
     */
    private function extractRowData(array $row, array $mappings): array
    {
        $data = [];

        foreach ($mappings as $field => $columnIndex) {
            $data[$field] = ($columnIndex !== '') ? ($row[$columnIndex] ?? '') : '';
        }

        return $data;
    }

    /**
     * Parse price from string (handles various formats)
     */
    private function parsePrice(?string $priceString): ?float
    {
        if (empty($priceString)) {
            return null;
        }

        // Remove currency symbols and extract numeric value
        $cleaned = preg_replace('/[^\d.,]/', '', $priceString);
        $cleaned = str_replace(',', '.', $cleaned);

        return is_numeric($cleaned) ? (float) $cleaned : null;
    }
}