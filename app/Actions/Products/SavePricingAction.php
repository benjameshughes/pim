<?php

namespace App\Actions\Products;

use App\Models\Pricing;
use App\Models\ProductVariant;
use App\Exceptions\ProductWizard\ProductSaveException;

/**
 * 💰 SAVE PRICING ACTION
 * 
 * Handles Step 4: Stock and Pricing
 * - Individual variant pricing and stock management  
 * - Bulk update capabilities
 * - Integration with existing Pricing model
 * 
 * Follows ProductWizard.md specification for Step 4
 */
class SavePricingAction
{
    public function execute(array $pricingData): array
    {
        try {
            $updatedRecords = 0;
            
            foreach ($pricingData as $variantId => $pricing) {
                // Update variant stock level
                if (isset($pricing['stock_level'])) {
                    ProductVariant::where('id', $variantId)
                        ->update(['stock_level' => $pricing['stock_level']]);
                }
                
                // Update/create pricing record
                if (isset($pricing['retail_price'])) {
                    Pricing::updateOrCreate(
                        ['variant_id' => $variantId],
                        [
                            'retail_price' => $pricing['retail_price'],
                            'cost_price' => $pricing['cost_price'] ?? null,
                            'currency' => 'GBP',
                            'vat_rate' => 20, // Default UK VAT rate
                        ]
                    );
                    $updatedRecords++;
                }
            }

            return [
                'success' => true,
                'updated_records' => $updatedRecords,
                'message' => "Pricing and stock updated for {$updatedRecords} variants"
            ];
        } catch (\Exception $e) {
            throw ProductSaveException::pricingUpdateFailed($e);
        }
    }

    /**
     * Bulk update pricing for multiple variants
     * Per ProductWizard.md: "ability to bulk update"
     */
    public function bulkUpdate(array $variantIds, array $pricingData): array
    {
        try {
            foreach ($variantIds as $variantId) {
                // Apply same pricing to all selected variants
                if (isset($pricingData['retail_price'])) {
                    Pricing::updateOrCreate(
                        ['variant_id' => $variantId],
                        [
                            'retail_price' => $pricingData['retail_price'],
                            'cost_price' => $pricingData['cost_price'] ?? null,
                            'currency' => 'GBP',
                            'vat_rate' => 20,
                        ]
                    );
                }

                // Update stock if provided
                if (isset($pricingData['stock_level'])) {
                    ProductVariant::where('id', $variantId)
                        ->update(['stock_level' => $pricingData['stock_level']]);
                }
            }

            return [
                'success' => true,
                'updated_variants' => count($variantIds),
                'message' => 'Bulk pricing update completed for ' . count($variantIds) . ' variants'
            ];
        } catch (\Exception $e) {
            throw ProductSaveException::bulkPricingUpdateFailed($e);
        }
    }
}