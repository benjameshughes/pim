<?php

namespace App\Actions\Products\Wizard;

use App\Actions\Base\BaseAction;
use InvalidArgumentException;

/**
 * Validate Product Wizard Step Action
 *
 * Handles validation for individual wizard steps.
 * Returns validation errors in a standardized format.
 */
class ValidateProductWizardStepAction extends BaseAction
{
    protected bool $useTransactions = false; // No database operations needed

    /**
     * Validate a specific wizard step
     *
     * @param  int  $step  Step number (1-4)
     * @param  array  $stepData  Step data to validate
     * @return array Action result with validation results
     */
    protected function performAction(...$params): array
    {
        $step = $params[0] ?? null;
        $stepData = $params[1] ?? [];

        if (! $step || $step < 1 || $step > 4) {
            throw new InvalidArgumentException('Invalid step number. Must be between 1 and 4.');
        }

        $errors = match ($step) {
            1 => $this->validateProductInfo($stepData),
            2 => $this->validateVariants($stepData),
            3 => $this->validateImages($stepData),
            4 => $this->validatePricing($stepData),
            default => [],
        };

        $isValid = empty($errors);

        return $this->success(
            $isValid ? 'Step validation passed' : 'Step validation failed',
            [
                'step' => $step,
                'valid' => $isValid,
                'errors' => $errors,
                'validated_fields' => array_keys($stepData),
            ]
        );
    }

    /**
     * Validate product info (Step 1)
     */
    protected function validateProductInfo(array $data): array
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors['name'] = 'Product name is required';
        } elseif (strlen($data['name']) < 3) {
            $errors['name'] = 'Product name must be at least 3 characters';
        }

        if (! empty($data['parent_sku']) && strlen($data['parent_sku']) < 2) {
            $errors['parent_sku'] = 'Parent SKU must be at least 2 characters';
        }

        if (! empty($data['status']) && ! in_array($data['status'], ['draft', 'active', 'inactive', 'archived'])) {
            $errors['status'] = 'Invalid product status';
        }

        if (! empty($data['image_url']) && ! filter_var($data['image_url'], FILTER_VALIDATE_URL)) {
            $errors['image_url'] = 'Image URL must be a valid URL';
        }

        return $errors;
    }

    /**
     * Validate variants (Step 2)
     */
    protected function validateVariants(array $data): array
    {
        $errors = [];

        if (empty($data['generated_variants'])) {
            $errors['variants'] = 'At least one variant is required';

            return $errors;
        }

        $skus = [];
        foreach ($data['generated_variants'] as $index => $variant) {
            $variantErrors = [];

            if (empty($variant['sku'])) {
                $variantErrors['sku'] = 'SKU is required';
            } elseif (in_array($variant['sku'], $skus)) {
                $variantErrors['sku'] = 'Duplicate SKU detected';
            } else {
                $skus[] = $variant['sku'];
            }

            if (isset($variant['price']) && (! is_numeric($variant['price']) || $variant['price'] < 0)) {
                $variantErrors['price'] = 'Price must be a positive number';
            }

            if (isset($variant['stock']) && (! is_numeric($variant['stock']) || $variant['stock'] < 0)) {
                $variantErrors['stock'] = 'Stock must be a positive number';
            }

            if (! empty($variantErrors)) {
                $errors["variant_{$index}"] = $variantErrors;
            }
        }

        return $errors;
    }

    /**
     * Validate images (Step 3)
     */
    protected function validateImages(array $data): array
    {
        $errors = [];

        // Images are optional, but if provided, validate them
        if (! empty($data['product_images'])) {
            foreach ($data['product_images'] as $index => $image) {
                if (empty($image['path']) && empty($image['url'])) {
                    $errors["image_{$index}"] = 'Image must have either path or URL';
                }
            }
        }

        return $errors;
    }

    /**
     * Validate pricing (Step 4)
     */
    protected function validatePricing(array $data): array
    {
        $errors = [];

        // Pricing validation will depend on your specific requirements
        // For now, we'll just check basic structure
        if (! empty($data['variant_pricing'])) {
            foreach ($data['variant_pricing'] as $variantId => $pricing) {
                if (isset($pricing['retail_price']) && (! is_numeric($pricing['retail_price']) || $pricing['retail_price'] < 0)) {
                    $errors["pricing_{$variantId}_retail"] = 'Retail price must be a positive number';
                }

                if (isset($pricing['cost_price']) && (! is_numeric($pricing['cost_price']) || $pricing['cost_price'] < 0)) {
                    $errors["pricing_{$variantId}_cost"] = 'Cost price must be a positive number';
                }
            }
        }

        return $errors;
    }

    /**
     * Validate all steps at once
     *
     * @param  array  $wizardData  Complete wizard data
     * @return array Validation results for all steps
     */
    public function validateAllSteps(array $wizardData): array
    {
        $allErrors = [];
        $stepsValid = [];

        for ($step = 1; $step <= 4; $step++) {
            $stepKey = match ($step) {
                1 => 'product_info',
                2 => 'variants',
                3 => 'images',
                4 => 'pricing',
            };

            $stepData = $wizardData[$stepKey] ?? [];
            $result = $this->execute($step, $stepData);

            $stepsValid[$step] = $result['data']['valid'];

            if (! $result['data']['valid']) {
                $allErrors[$stepKey] = $result['data']['errors'];
            }
        }

        $overallValid = ! in_array(false, $stepsValid, true);

        return $this->success(
            $overallValid ? 'All steps valid' : 'Some steps have validation errors',
            [
                'overall_valid' => $overallValid,
                'steps_valid' => $stepsValid,
                'errors' => $allErrors,
            ]
        );
    }
}
