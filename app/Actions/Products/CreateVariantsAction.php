<?php

namespace App\Actions\Products;

use App\Builders\VariantBuilder;
use App\Exceptions\ProductWizard\ProductSaveException;
use App\Jobs\AssignBarcodesJob;
use App\Models\Product;

/**
 * 🎨 CREATE VARIANTS ACTION
 *
 * Handles Step 2: Variant Generation
 * - Sequential SKU generation (000-001, 000-002, etc.)
 * - Color, width, drop attribute handling
 * - Integration with existing VariantBuilder
 * - Barcode assignment via job system
 *
 * Follows ProductWizard.md specification for Step 2
 */
class CreateVariantsAction
{
    public function execute(Product $product, array $variantData): array
    {
        try {
            $createdVariants = [];

            foreach ($variantData as $index => $variant) {
                // Use existing VariantBuilder for clean architecture
                $variantBuilder = new VariantBuilder($product);

                $createdVariant = $variantBuilder
                    ->sku($variant['sku'])
                    ->color($variant['color'] ?: 'Standard')
                    ->windowDimensions(
                        $variant['width'] ?: null,
                        $variant['drop'] ?: null
                    )
                    ->status('active')
                    ->stockLevel(0) // Default stock, pricing handled in separate action
                    ->assignFromPool() // Auto-assign barcode from pool per requirements
                    ->execute();

                $createdVariants[] = $createdVariant;
            }

            // Invoke barcode assignment job for all variants
            // Per ProductWizard.md: "auto assign is done via a job"
            if (! empty($createdVariants)) {
                $variantIds = collect($createdVariants)->pluck('id')->toArray();
                AssignBarcodesJob::dispatch('product_variants', $variantIds);
            }

            return [
                'success' => true,
                'variants' => $createdVariants,
                'message' => count($createdVariants).' variants created successfully',
            ];
        } catch (\Exception $e) {
            throw ProductSaveException::variantCreationFailed($e);
        }
    }

    /**
     * Generate sequential SKUs based on parent SKU
     * Per ProductWizard.md: "000-001 sequentially based off amount of variants"
     */
    public function generateVariantSkus(string $parentSku, array $variantData): array
    {
        $skus = [];
        foreach ($variantData as $index => $variant) {
            $skus[] = $parentSku.'-'.str_pad($index + 1, 3, '0', STR_PAD_LEFT);
        }

        return $skus;
    }
}
