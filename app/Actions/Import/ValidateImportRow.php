<?php

namespace App\Actions\Import;

class ValidateImportRow
{
    public function execute(array $rowData, int $rowNumber): ValidationRowResult
    {
        $result = new ValidationRowResult;

        // Validate required fields
        if (empty($rowData['variant_sku']) && empty($rowData['product_name'])) {
            $result->addError("Row {$rowNumber}: Either 'Variant SKU' or 'Product Name' is required");
        }

        // Validate SKU format if provided
        if (! empty($rowData['variant_sku'])) {
            if (! preg_match('/^[A-Za-z0-9\-_]+$/', $rowData['variant_sku'])) {
                $result->addError("Row {$rowNumber}: Invalid SKU format. Only letters, numbers, hyphens and underscores allowed");
            }
        }

        // Validate price format if provided
        if (! empty($rowData['retail_price'])) {
            if (! is_numeric($rowData['retail_price']) || $rowData['retail_price'] < 0) {
                $result->addError("Row {$rowNumber}: Invalid price format. Must be a positive number");
            }
        }

        // Validate barcode format if provided
        if (! empty($rowData['barcode'])) {
            if (! preg_match('/^[0-9]{8,13}$/', $rowData['barcode'])) {
                $result->addWarning("Row {$rowNumber}: Barcode should be 8-13 digits. Current value may not be a valid barcode");
            }
        }

        // Validate image URLs if provided
        if (! empty($rowData['image_urls'])) {
            $urls = explode(',', $rowData['image_urls']);
            foreach ($urls as $url) {
                $url = trim($url);
                if (! empty($url) && ! filter_var($url, FILTER_VALIDATE_URL)) {
                    $result->addWarning("Row {$rowNumber}: Invalid image URL format: {$url}");
                }
            }
        }

        return $result;
    }
}

class ValidationRowResult
{
    private array $errors = [];

    private array $warnings = [];

    public function addError(string $error): void
    {
        $this->errors[] = $error;
    }

    public function addWarning(string $warning): void
    {
        $this->warnings[] = $warning;
    }

    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    public function hasWarnings(): bool
    {
        return ! empty($this->warnings);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }
}
