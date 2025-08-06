<?php

namespace App\Actions\Import;

class BuildMappingIndex
{
    public function execute(array $originalHeaders, array $columnMapping): array
    {
        $headerToFieldMapping = [];
        
        \Log::info('Building mapping index', [
            'original_headers' => $originalHeaders,
            'column_mapping' => $columnMapping
        ]);
        
        foreach ($columnMapping as $originalColumnIndex => $fieldName) {
            if (!empty($fieldName)) {
                // Check if this is position-based (numeric) or header-based (string)
                if (is_numeric($originalColumnIndex) && isset($originalHeaders[$originalColumnIndex])) {
                    // Position-based mapping
                    $originalHeaderName = $originalHeaders[$originalColumnIndex];
                    $headerToFieldMapping[$originalHeaderName] = $fieldName;
                } elseif (is_string($originalColumnIndex)) {
                    // Header-based mapping
                    $headerToFieldMapping[$originalColumnIndex] = $fieldName;
                } else {
                    \Log::warning('Invalid column mapping entry', [
                        'index' => $originalColumnIndex,
                        'field' => $fieldName,
                        'headers_count' => count($originalHeaders)
                    ]);
                }
            }
        }
        
        \Log::info('Mapping index built', [
            'header_to_field_mapping' => $headerToFieldMapping
        ]);
        
        return $headerToFieldMapping;
    }
}