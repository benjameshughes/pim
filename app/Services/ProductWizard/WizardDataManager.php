<?php

namespace App\Services\ProductWizard;

use App\Models\Product;

class WizardDataManager
{
    public function getInitialWizardData(): array
    {
        return [
            'product_info' => [],
            'variants' => [],
            'images' => [],
            'pricing' => [],
        ];
    }

    public function loadExistingProductData(Product $product): array
    {
        $wizardData = $this->getInitialWizardData();

        // Load product info
        $wizardData['product_info'] = [
            'id' => $product->id,
            'name' => $product->name,
            'parent_sku' => $product->parent_sku,
            'description' => $product->description,
            'status' => $product->status->value,
            'image_url' => $product->image_url,
        ];

        // Load variants
        $variants = $product->variants()->get();
        if ($variants->isNotEmpty()) {
            $wizardData['variants'] = [
                'generated_variants' => $variants->map(function ($variant) {
                    return [
                        'id' => $variant->id,
                        'sku' => $variant->sku,
                        'color' => $variant->color,
                        'width' => $variant->width,
                        'drop' => $variant->drop,
                        'price' => $variant->price,
                        'stock' => $variant->stock_level,
                        'existing' => true,
                    ];
                })->toArray(),
            ];
        }

        return $wizardData;
    }

    public function calculateCompletedStepsFromData(array $wizardData): array
    {
        $completedSteps = [];

        // Product info step
        if (! empty($wizardData['product_info'])) {
            $completedSteps[] = WizardStepNavigator::STEP_PRODUCT_INFO;
        }

        // Variants step
        if (! empty($wizardData['variants']['generated_variants'])) {
            $completedSteps[] = WizardStepNavigator::STEP_VARIANTS;
        }

        // Images step (optional, so we check for any image data)
        if (! empty($wizardData['images'])) {
            $completedSteps[] = WizardStepNavigator::STEP_IMAGES;
        }

        // Pricing step
        if (! empty($wizardData['pricing'])) {
            $completedSteps[] = WizardStepNavigator::STEP_PRICING;
        }

        return $completedSteps;
    }

    public function updateStepData(array $wizardData, int $step, array $data): array
    {
        $stepKey = WizardStepNavigator::STEP_KEYS[$step] ?? null;

        if ($stepKey) {
            $wizardData[$stepKey] = $data;
        }

        return $wizardData;
    }

    public function mergeWizardData(array $existing, array $new): array
    {
        return array_merge($existing, $new);
    }
}
