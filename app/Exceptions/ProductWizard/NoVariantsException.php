<?php

namespace App\Exceptions\ProductWizard;

use Exception;

/**
 * 🚫 NO VARIANTS EXCEPTION
 *
 * Thrown when attempting to save a product without any variants.
 * This ensures that products always have at least one variant
 * before being persisted to the database.
 */
class NoVariantsException extends Exception
{
    public function __construct(?string $message = null)
    {
        $defaultMessage = 'Cannot save product without variants. Please add at least one color, width, or drop to generate variants.';

        parent::__construct($message ?? $defaultMessage);
    }

    /**
     * Get user-friendly message for display
     */
    public function getUserMessage(): string
    {
        return 'Please add colors, widths, or drops to create product variants before saving.';
    }

    /**
     * Get suggested actions for the user
     */
    public function getSuggestedActions(): array
    {
        return [
            'Go back to Step 2: Variants',
            'Add at least one color, width, or drop',
            'Try saving again',
        ];
    }
}
