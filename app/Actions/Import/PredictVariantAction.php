<?php

namespace App\Actions\Import;

use App\Models\Product;
use App\Repositories\ProductRepository;
use App\Repositories\ProductVariantRepository;
use Illuminate\Support\Facades\Log;

class PredictVariantAction
{
    public function __construct(
        private ProductVariantRepository $variantRepository,
        private ProductRepository $productRepository
    ) {}

    public function execute(
        ?string $variantSku,
        string $productName,
        ?string $extractedColor,
        ?string $extractedSize,
        string $importMode
    ): string {
        // Check for existing variant by SKU
        if (! empty($variantSku)) {
            $existingVariant = $this->variantRepository->findBySku($variantSku);
            if ($existingVariant) {
                return $this->getActionForMode($importMode, true);
            }
        }

        // Check for existing variant by product name + color + size combination
        if ($extractedColor || $extractedSize) {
            $parentProduct = $this->productRepository->findParentByNameLike('%'.$productName.'%');

            if ($parentProduct) {
                $existingVariant = $this->variantRepository->findByProductColorAndSize(
                    $parentProduct,
                    $extractedColor,
                    $extractedSize
                );

                if ($existingVariant) {
                    Log::info('Found existing variant by color/size', [
                        'parent_product' => $parentProduct->name,
                        'color' => $extractedColor,
                        'size' => $extractedSize,
                        'existing_sku' => $existingVariant->sku,
                    ]);

                    return $this->getActionForMode($importMode, true);
                }
            }
        }

        // No existing variant found
        return $this->getActionForMode($importMode, false);
    }

    private function getActionForMode(string $importMode, bool $exists): string
    {
        return match ([$importMode, $exists]) {
            ['create_only', true] => 'skip',
            ['create_only', false] => 'create',
            ['update_existing', true] => 'update',
            ['update_existing', false] => 'skip',
            ['create_or_update', true] => 'update',
            ['create_or_update', false] => 'create',
            default => 'skip'
        };
    }
}
