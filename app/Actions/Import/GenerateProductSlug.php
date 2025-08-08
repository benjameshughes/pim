<?php

namespace App\Actions\Import;

use App\Models\Product;
use Illuminate\Support\Str;

class GenerateProductSlug
{
    public function execute(string $name, int $attempt = 0): string
    {
        $baseSlug = Str::slug($name);

        // If this is the first attempt, try the base slug
        if ($attempt === 0) {
            $slug = $baseSlug;
        } else {
            // Add a number suffix for subsequent attempts
            $slug = $baseSlug.'-'.$attempt;
        }

        // Check if this slug already exists
        if (Product::where('slug', $slug)->exists()) {
            // Recursively try with the next number
            return $this->execute($name, $attempt + 1);
        }

        return $slug;
    }
}
