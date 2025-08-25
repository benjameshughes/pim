<?php

namespace App\Rules;

use App\Models\Product;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ParentSkuRule implements ValidationRule
{
    public function __construct(
        private ?int $excludeProductId = null
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Check format: must be exactly 3 digits
        if (! preg_match('/^[0-9]{3}$/', $value)) {
            $fail('The :attribute must be exactly 3 digits (e.g., 001, 123, 999).');

            return;
        }

        // Check uniqueness
        $query = Product::where('parent_sku', $value);

        if ($this->excludeProductId) {
            $query->where('id', '!=', $this->excludeProductId);
        }

        if ($query->exists()) {
            $existingProduct = $query->first();
            $fail("The parent SKU '{$value}' is already used by product: {$existingProduct->name}");
        }
    }
}
