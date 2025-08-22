<?php

namespace App\Rules;

use App\Models\ProductVariant;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class VariantSkuRule implements ValidationRule
{
    public function __construct(
        private ?int $excludeProductId = null
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Check format: must be 000-000 (3 digits, dash, 3 digits)
        if (!preg_match('/^[0-9]{3}-[0-9]{3}$/', $value)) {
            $fail('The :attribute must follow format 000-000 (e.g., 123-001, 456-002).');
            return;
        }

        // Check uniqueness
        $query = ProductVariant::where('sku', $value);
        
        if ($this->excludeProductId) {
            $query->whereHas('product', function($q) {
                $q->where('id', '!=', $this->excludeProductId);
            });
        }

        if ($query->exists()) {
            $existingVariant = $query->with('product')->first();
            $productName = $existingVariant->product->name ?? 'Unknown Product';
            $fail("The variant SKU '{$value}' is already used by product: {$productName}");
        }
    }
}