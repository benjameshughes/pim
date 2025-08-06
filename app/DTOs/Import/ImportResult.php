<?php

namespace App\DTOs\Import;

class ImportResult
{
    public int $productsCreated = 0;
    public int $productsUpdated = 0;
    public int $variantsCreated = 0;
    public int $variantsUpdated = 0;
    public array $errors = [];
    public array $warnings = [];

    public function incrementProductsCreated(): void
    {
        $this->productsCreated++;
    }

    public function incrementProductsUpdated(): void
    {
        $this->productsUpdated++;
    }

    public function incrementVariantsCreated(): void
    {
        $this->variantsCreated++;
    }

    public function incrementVariantsUpdated(): void
    {
        $this->variantsUpdated++;
    }

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
        return !empty($this->errors);
    }

    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    public function toArray(): array
    {
        return [
            'products_created' => $this->productsCreated,
            'products_updated' => $this->productsUpdated,
            'variants_created' => $this->variantsCreated,
            'variants_updated' => $this->variantsUpdated,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }
}