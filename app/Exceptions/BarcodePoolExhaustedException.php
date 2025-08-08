<?php

namespace App\Exceptions;

use Exception;

/**
 * Exception thrown when trying to assign a barcode from an exhausted pool
 */
class BarcodePoolExhaustedException extends Exception
{
    protected $message = 'No barcodes available in pool';

    public function __construct(string $barcodeType = 'barcode', ?string $message = null)
    {
        $this->message = $message ?? "No available {$barcodeType} barcodes in pool. Please import more barcodes before continuing.";
        parent::__construct($this->message);
    }

    /**
     * Get user-friendly error message with suggestions
     */
    public function getUserMessage(): string
    {
        return "We've run out of barcodes! Please contact support or import more barcodes to continue creating variants.";
    }

    /**
     * Get suggested actions for the user
     */
    public function getSuggestedActions(): array
    {
        return [
            'Import more barcodes to the barcode pool',
            'Contact support for additional barcode allocation',
            'Create variant without barcode (manual assignment later)',
        ];
    }
}
