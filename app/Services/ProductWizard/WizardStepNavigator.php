<?php

namespace App\Services\ProductWizard;

use App\Actions\Products\Wizard\ValidateProductWizardStepAction;

class WizardStepNavigator
{
    public const STEP_PRODUCT_INFO = 1;

    public const STEP_VARIANTS = 2;

    public const STEP_IMAGES = 3;

    public const STEP_PRICING = 4;

    public const MAX_STEPS = 4;

    public const STEP_KEYS = [
        self::STEP_PRODUCT_INFO => 'product_info',
        self::STEP_VARIANTS => 'variants',
        self::STEP_IMAGES => 'images',
        self::STEP_PRICING => 'pricing',
    ];

    public const STEP_NAMES = [
        self::STEP_PRODUCT_INFO => 'Product Info',
        self::STEP_VARIANTS => 'Variants',
        self::STEP_IMAGES => 'Images',
        self::STEP_PRICING => 'Pricing & Stock',
    ];

    public const STEP_COMPONENTS = [
        self::STEP_PRODUCT_INFO => 'products.wizard.product-info-step',
        self::STEP_VARIANTS => 'products.wizard.variant-generation-step',
        self::STEP_IMAGES => 'products.wizard.image-upload-step',
        self::STEP_PRICING => 'products.wizard.pricing-stock-step',
    ];

    public function canProceedToStep(int $targetStep, int $currentStep, array $completedSteps): bool
    {
        if ($targetStep < 1 || $targetStep > self::MAX_STEPS) {
            return false;
        }

        if ($targetStep <= $currentStep) {
            return true;
        }

        // Must complete previous steps in order
        for ($i = 1; $i < $targetStep; $i++) {
            if (! in_array($i, $completedSteps)) {
                return false;
            }
        }

        return true;
    }

    public function validateStep(int $step, array $wizardData): array
    {
        $stepKey = self::STEP_KEYS[$step] ?? null;

        if (! $stepKey || ! isset($wizardData[$stepKey])) {
            // Allow proceeding if no data exists for images step (optional)
            if ($step === self::STEP_IMAGES) {
                return ['success' => true];
            }

            return [
                'success' => false,
                'message' => 'Please complete all required fields before continuing.',
            ];
        }

        $validateAction = new ValidateProductWizardStepAction;

        return $validateAction->execute($step, $wizardData);
    }

    public function getStepKey(int $step): ?string
    {
        return self::STEP_KEYS[$step] ?? null;
    }

    public function getStepName(int $step): ?string
    {
        return self::STEP_NAMES[$step] ?? null;
    }

    public function getStepComponent(int $step): string
    {
        return self::STEP_COMPONENTS[$step] ?? self::STEP_COMPONENTS[self::STEP_PRODUCT_INFO];
    }

    public function calculateProgress(array $completedSteps): float
    {
        return (count($completedSteps) / self::MAX_STEPS) * 100;
    }
}
