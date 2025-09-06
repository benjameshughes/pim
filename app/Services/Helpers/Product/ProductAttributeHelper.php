<?php

namespace App\Services\Helpers\Product;

use App\Models\Product;
use App\Services\Attributes\Facades\Attributes;

/**
 * ProductAttributeHelper
 *
 * Focused helper methods for product attribute workflows.
 */
class ProductAttributeHelper
{
    /**
     * Ensure a product has a basic set of attributes, filling missing values with defaults.
     * Returns the list of keys that were newly created.
     * @param array<string,mixed> $defaults
     * @return array<int,string>
     */
    public function fillMissing(Product $product, array $defaults): array
    {
        $created = [];
        foreach ($defaults as $key => $value) {
            $current = Attributes::for($product)->get($key);
            if ($current === null) {
                Attributes::for($product)->source('helper')->set($key, $value);
                $created[] = $key;
            }
        }
        return $created;
    }

    /**
     * Read-only effective attributes grouped for UI.
     */
    public function groupedForDisplay(Product $product): array
    {
        return Attributes::for($product)->byGroup();
    }
}

