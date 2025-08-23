<?php

namespace App\Exceptions\ProductWizard;

use Exception;

/**
 * 📝 WIZARD VALIDATION EXCEPTION
 * 
 * Thrown when wizard validation fails during step transitions
 * or when required data is missing for specific operations.
 */
class WizardValidationException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $step,
        public readonly array $errors = [],
        public readonly array $data = []
    ) {
        parent::__construct($message);
    }
    
    /**
     * Create exception for missing variants in pricing step
     */
    public static function missingVariantsForPricing(): self
    {
        return new self(
            message: 'No variants found for pricing. Cannot proceed to pricing step.',
            step: 'pricing',
            errors: ['variants' => 'No variants found. Please go back to step 2.'],
            data: ['required_step' => 2, 'current_step' => 4]
        );
    }
    
    /**
     * Create exception for missing variant attributes
     */
    public static function missingVariantAttributes(): self
    {
        return new self(
            message: 'At least one variant attribute is required.',
            step: 'variants',
            errors: ['variants' => 'Please add at least one color, width, or drop to generate variants.'],
            data: ['required_attributes' => ['colors', 'widths', 'drops']]
        );
    }
    
    /**
     * Get user-friendly message
     */
    public function getUserMessage(): string
    {
        return match ($this->step) {
            'variants' => 'Please add variant attributes (colors, widths, or drops) to continue.',
            'pricing' => 'No variants available for pricing. Please go back and create variants first.',
            default => $this->getMessage()
        };
    }
    
    /**
     * Get the step where the error occurred
     */
    public function getStep(): string
    {
        return $this->step;
    }
    
    /**
     * Get validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}