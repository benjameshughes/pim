<?php

namespace App\Services;

use App\Actions\Import\BuildMappingIndex;
use App\Actions\Import\MapRowToFields;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ColumnMappingService
{
    public function __construct(
        private BuildMappingIndex $buildMappingIndex,
        private MapRowToFields $mapRowToFields
    ) {}

    /**
     * Map all rows of data using column mapping configuration
     */
    public function mapAllRows(array $worksheetData, array $columnMapping, array $originalHeaders): array
    {
        // Build the header-to-field mapping index once
        $headerToFieldMapping = $this->buildMappingIndex->execute($originalHeaders, $columnMapping);

        $mappedData = [];

        foreach ($worksheetData as $rowIndex => $rowWithHeaders) {
            $mappedRow = $this->mapRowToFields->execute(
                $rowWithHeaders['data'],
                $rowWithHeaders['headers'],
                $headerToFieldMapping
            );

            // Only include rows with meaningful data
            if (! empty($mappedRow['variant_sku']) || ! empty($mappedRow['product_name'])) {
                $mappedData[] = $mappedRow;
            }
        }

        Log::info('Row mapping completed', [
            'total_input_rows' => count($worksheetData),
            'mapped_rows' => count($mappedData),
        ]);

        return $mappedData;
    }

    /**
     * Guess field mapping based on header names
     */
    public function guessFieldMapping(array $headers): array
    {
        $mapping = [];
        $fieldPatterns = $this->getFieldPatterns();

        foreach ($headers as $index => $header) {
            $normalizedHeader = strtolower(trim($header));

            foreach ($fieldPatterns as $fieldName => $patterns) {
                foreach ($patterns as $pattern) {
                    if (strpos($normalizedHeader, $pattern) !== false) {
                        $mapping[$index] = $fieldName;
                        break 2; // Break both loops
                    }
                }
            }
        }

        Log::info('Auto-mapped fields', [
            'total_headers' => count($headers),
            'mapped_fields' => count(array_filter($mapping)),
        ]);

        return $mapping;
    }

    /**
     * Save mapping configuration to cache
     */
    public function saveMappingConfiguration(array $columnMapping, array $headers): void
    {
        $mappingData = [
            'column_mapping' => $columnMapping,
            'headers' => $headers,
            'created_at' => now(),
            'fingerprint' => $this->generateMappingFingerprint($headers),
        ];

        Cache::put('import_column_mapping', $mappingData, now()->addDays(30));

        Log::info('Column mapping saved to cache', [
            'mapped_fields' => count(array_filter($columnMapping)),
            'cache_expires' => now()->addDays(30),
        ]);
    }

    /**
     * Load saved mapping configuration from cache
     */
    public function loadSavedMappingConfiguration(): ?array
    {
        $mappingData = Cache::get('import_column_mapping');

        if ($mappingData) {
            Log::info('Loaded saved column mapping', [
                'created_at' => $mappingData['created_at'],
                'mapped_fields' => count(array_filter($mappingData['column_mapping'])),
            ]);
        }

        return $mappingData;
    }

    /**
     * Get mapping statistics for UI display
     */
    public function getMappingStatistics(): array
    {
        $savedMapping = $this->loadSavedMappingConfiguration();

        if (! $savedMapping) {
            return [
                'has_saved_mapping' => false,
                'total_mappings' => 0,
                'created_at' => null,
            ];
        }

        return [
            'has_saved_mapping' => true,
            'total_mappings' => count(array_filter($savedMapping['column_mapping'])),
            'created_at' => $savedMapping['created_at'],
            'fingerprint' => $savedMapping['fingerprint'],
        ];
    }

    /**
     * Clear saved mapping configuration
     */
    public function clearSavedMappingConfiguration(): void
    {
        Cache::forget('import_column_mapping');
        Log::info('Cleared saved column mapping');
    }

    /**
     * Generate fingerprint for mapping to detect similar files
     */
    private function generateMappingFingerprint(array $headers): string
    {
        return md5(implode('|', array_map('strtolower', $headers)));
    }

    /**
     * Guess column mappings based on headers - used by ImportManagerService
     */
    public function guessColumnMappings(array $headers): array
    {
        $mapping = [];
        $fieldPatterns = $this->getFieldPatterns();

        foreach ($headers as $header) {
            $normalizedHeader = strtolower(trim($header));

            foreach ($fieldPatterns as $fieldName => $patterns) {
                foreach ($patterns as $pattern) {
                    if (strpos($normalizedHeader, $pattern) !== false) {
                        $mapping[$header] = $fieldName;
                        break 2; // Break both loops
                    }
                }
            }
        }

        Log::info('Auto-guessed column mappings', [
            'total_headers' => count($headers),
            'mapped_columns' => count($mapping),
        ]);

        return $mapping;
    }

    /**
     * Get field patterns for auto-mapping
     */
    private function getFieldPatterns(): array
    {
        return [
            'variant_sku' => ['sku', 'variant sku', 'product sku', 'caecus sku', 'linnworks sku', 'item code'],
            'product_name' => ['name', 'title', 'product name', 'item title', 'product title'],
            'description' => ['description', 'desc', 'full description', 'product description'],
            'is_parent' => ['is parent', 'parent', 'type'],
            'parent_name' => ['parent name', 'parent product'],
            'variant_color' => ['color', 'colour', 'variant color'],
            'variant_size' => ['size', 'variant size'],
            'retail_price' => ['price', 'retail price', 'selling price', 'cost'],
            'barcode' => ['barcode', 'ean', 'upc', 'gtin', 'caecus barcode'],
            'image_urls' => ['image', 'images', 'photo', 'picture', 'parent image'],
            'stock_quantity' => ['stock', 'quantity', 'qty', 'inventory'],
            'weight' => ['weight', 'wt'],
            'length' => ['length', 'l', 'parcel length'],
            'width' => ['width', 'w', 'parcel width'],
            'height' => ['height', 'h', 'depth', 'parcel depth'],
            'brand' => ['brand', 'manufacturer', 'make'],
            'category' => ['category', 'type', 'classification'],
        ];
    }
}
