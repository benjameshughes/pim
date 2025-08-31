<?php

if (! function_exists('format_attribute_value')) {
    /**
     * KISS Helper: Format JSON attribute values for human display
     *
     * Takes raw attribute values and makes them readable for normies
     *
     * @param  mixed  $value  The attribute value (could be JSON string, array, or simple value)
     * @return string Human-readable formatted value
     */
    function format_attribute_value($value): string
    {
        if (empty($value)) {
            return 'No value';
        }

        // Handle JSON strings
        if (is_string($value) && str_starts_with($value, '{') && str_ends_with($value, '}')) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return format_json_for_display($decoded);
            }
        }

        // Handle arrays directly
        if (is_array($value)) {
            return format_json_for_display($value);
        }

        // Handle simple values
        return (string) $value;
    }
}

if (! function_exists('format_json_for_display')) {
    /**
     * KISS: Format JSON array for human display
     *
     * Converts things like:
     * {"Black":"gid://shopify/Product/123","White":"gid://shopify/Product/456"}
     * Into: "Black: 123, White: 456"
     */
    function format_json_for_display(array $data): string
    {
        $formatted = [];

        foreach ($data as $key => $value) {
            // Clean up Shopify GIDs
            if (is_string($value) && str_contains($value, 'gid://shopify/Product/')) {
                $cleanValue = str_replace('gid://shopify/Product/', '', $value);
                $formatted[] = "{$key}: {$cleanValue}";
            } elseif (is_array($value)) {
                // Nested arrays
                $formatted[] = "{$key}: ".format_json_for_display($value);
            } else {
                // Simple values
                $formatted[] = "{$key}: {$value}";
            }
        }

        return implode(', ', $formatted);
    }
}
