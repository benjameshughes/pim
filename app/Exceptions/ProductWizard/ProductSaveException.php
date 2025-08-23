<?php

namespace App\Exceptions\ProductWizard;

use Exception;
use Throwable;

/**
 * 💾 PRODUCT SAVE EXCEPTION
 * 
 * Thrown when product saving fails due to database errors,
 * constraint violations, or other persistence issues.
 */
class ProductSaveException extends Exception
{
    public function __construct(
        string $message,
        public readonly array $productData = [],
        public readonly ?Throwable $previousException = null,
        int $code = 0
    ) {
        parent::__construct($message, $code, $previousException);
    }
    
    /**
     * Create exception for database transaction failure
     */
    public static function transactionFailed(Throwable $previous, array $productData = []): self
    {
        return new self(
            message: 'Failed to save product due to database error: ' . $previous->getMessage(),
            productData: $productData,
            previousException: $previous
        );
    }
    
    /**
     * Create exception for attribute creation failure
     */
    public static function attributeCreationFailed(string $attributeKey, Throwable $previous): self
    {
        return new self(
            message: "Failed to create attribute '{$attributeKey}': " . $previous->getMessage(),
            productData: ['failed_attribute' => $attributeKey],
            previousException: $previous
        );
    }
    
    /**
     * Create exception for variant creation failure
     */
    public static function variantCreationFailed(array $variantData, Throwable $previous): self
    {
        return new self(
            message: 'Failed to create product variant: ' . $previous->getMessage(),
            productData: ['failed_variant' => $variantData],
            previousException: $previous
        );
    }
    
    /**
     * Get user-friendly message
     */
    public function getUserMessage(): string
    {
        if (str_contains($this->getMessage(), 'UNIQUE constraint failed')) {
            return 'This product SKU already exists. Please choose a different Parent SKU.';
        }
        
        if (str_contains($this->getMessage(), 'attribute')) {
            return 'Failed to save product attributes. Please try again.';
        }
        
        if (str_contains($this->getMessage(), 'variant')) {
            return 'Failed to create product variants. Please check your variant configuration.';
        }
        
        return 'Failed to save product. Please try again or contact support if the problem persists.';
    }
    
    /**
     * Get the product data that failed to save
     */
    public function getProductData(): array
    {
        return $this->productData;
    }
    
    /**
     * Get the underlying exception that caused the failure
     */
    public function getUnderlyingException(): ?Throwable
    {
        return $this->previousException;
    }
}