<?php

namespace App\Exceptions;

use Exception;

/**
 * Exception thrown when trying to create a variant with an existing SKU
 */
class DuplicateSkuException extends Exception
{
    protected string $sku;

    public function __construct(string $sku, ?string $message = null)
    {
        $this->sku = $sku;
        $this->message = $message ?? "A variant with SKU '{$sku}' already exists. SKUs must be unique across all variants.";
        parent::__construct($this->message);
    }

    /**
     * Get the duplicate SKU
     */
    public function getSku(): string
    {
        return $this->sku;
    }

    /**
     * Get user-friendly error message with suggestions
     */
    public function getUserMessage(): string
    {
        return "The SKU '{$this->sku}' is already in use. Please choose a different SKU for this variant.";
    }

    /**
     * Get suggested SKU alternatives
     */
    public function getSuggestedSkus(): array
    {
        $baseSku = $this->sku;
        $suggestions = [];

        // Remove any trailing numbers and add new suffixes
        $cleanBase = preg_replace('/\d+$/', '', $baseSku);

        for ($i = 1; $i <= 5; $i++) {
            $suggestions[] = $cleanBase.sprintf('%03d', $i);
        }

        return $suggestions;
    }
}
