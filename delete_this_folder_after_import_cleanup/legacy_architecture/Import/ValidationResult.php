<?php

namespace App\DTOs\Import;

class ValidationResult
{
    public int $validRows = 0;

    public int $errorRows = 0;

    public array $errors = [];

    public array $warnings = [];

    public int $productsToCreate = 0;

    public int $productsToUpdate = 0;

    public int $productsToSkip = 0;

    public int $variantsToCreate = 0;

    public int $variantsToUpdate = 0;

    public int $variantsToSkip = 0;

    public int $barcodesNeeded = 0;

    public function addError(string $error): void
    {
        $this->errors[] = $error;
    }

    public function addErrors(array $errors): void
    {
        $this->errors = array_merge($this->errors, $errors);
    }

    public function addWarning(string $warning): void
    {
        $this->warnings[] = $warning;
    }

    public function addWarnings(array $warnings): void
    {
        $this->warnings = array_merge($this->warnings, $warnings);
    }

    public function incrementValidRows(): void
    {
        $this->validRows++;
    }

    public function incrementErrorRows(): void
    {
        $this->errorRows++;
    }

    public function incrementProductsToCreate(): void
    {
        $this->productsToCreate++;
    }

    public function incrementProductsToUpdate(): void
    {
        $this->productsToUpdate++;
    }

    public function incrementProductsToSkip(): void
    {
        $this->productsToSkip++;
    }

    public function incrementVariantsToCreate(): void
    {
        $this->variantsToCreate++;
    }

    public function incrementVariantsToUpdate(): void
    {
        $this->variantsToUpdate++;
    }

    public function incrementVariantsToSkip(): void
    {
        $this->variantsToSkip++;
    }

    public function setBarcodesNeeded(int $count): void
    {
        $this->barcodesNeeded = $count;
    }

    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    public function hasWarnings(): bool
    {
        return ! empty($this->warnings);
    }

    public function finalize(): void
    {
        // Any final calculations or cleanup
    }

    public function toArray(): array
    {
        return [
            'valid_rows' => $this->validRows,
            'error_rows' => $this->errorRows,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'products_to_create' => $this->productsToCreate,
            'products_to_update' => $this->productsToUpdate,
            'products_to_skip' => $this->productsToSkip,
            'variants_to_create' => $this->variantsToCreate,
            'variants_to_update' => $this->variantsToUpdate,
            'variants_to_skip' => $this->variantsToSkip,
            'barcodes_needed' => $this->barcodesNeeded,
        ];
    }
}
