<?php

namespace App\Actions\Import;

class IsParentRow
{
    public function execute(array $data): bool
    {
        // Check explicit parent indicator
        if (isset($data['is_parent'])) {
            $value = strtolower(trim($data['is_parent']));

            return in_array($value, ['true', '1', 'yes', 'parent']);
        }

        // If no variant_sku but has product_name, likely a parent
        if (empty($data['variant_sku']) && ! empty($data['product_name'])) {
            return true;
        }

        // If has parent-specific fields without variant fields
        $hasParentFields = ! empty($data['parent_name']) ||
                          ! empty($data['product_features_1']) ||
                          ! empty($data['product_details_1']);

        $hasVariantFields = ! empty($data['variant_color']) ||
                           ! empty($data['variant_size']) ||
                           ! empty($data['stock_level']);

        return $hasParentFields && ! $hasVariantFields;
    }
}
