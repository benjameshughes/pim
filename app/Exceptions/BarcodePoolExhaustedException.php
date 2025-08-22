<?php

namespace App\Exceptions;

use Exception;

/**
 * 🏊‍♂️ BARCODE POOL EXHAUSTED EXCEPTION
 *
 * Thrown when no suitable barcodes are available for assignment
 */
class BarcodePoolExhaustedException extends Exception
{
    /**
     * Create a new exception instance
     */
    public function __construct(string $message = "Barcode pool exhausted - no suitable barcodes available for assignment", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get user-friendly error message with suggested actions
     */
    public function getUserMessage(): string
    {
        return "No barcodes are currently available for assignment. " .
               "Please import more barcodes or adjust quality requirements.";
    }

    /**
     * Get suggested recovery actions
     */
    public function getRecoveryActions(): array
    {
        return [
            'Import more barcodes from your GS1 allocation',
            'Lower the minimum quality score requirement',
            'Release unused barcodes from deleted variants',
            'Check if legacy barcodes can be made available',
        ];
    }
}