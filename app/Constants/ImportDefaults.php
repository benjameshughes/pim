<?php

namespace App\Constants;

/**
 * 📊✨ IMPORT SYSTEM DEFAULTS ✨📊
 *
 * Centralized constants for the import system, replacing hardcoded values
 * scattered throughout the codebase with maintainable defaults
 */
final class ImportDefaults
{
    /**
     * 📁 File Upload Constraints
     */
    public const MAX_FILE_SIZE_MB = 10;

    public const MAX_FILE_SIZE_BYTES = self::MAX_FILE_SIZE_MB * 1024 * 1024;

    public const ALLOWED_FILE_EXTENSIONS = ['csv', 'xlsx', 'xls'];

    public const ALLOWED_MIME_TYPES = [
        'text/csv',
        'text/plain',
        'application/csv',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    /**
     * 🔢 Processing Limits
     */
    public const DEFAULT_CHUNK_SIZE = 100;

    public const MAX_CHUNK_SIZE = 1000;

    public const MAX_ROWS_PER_IMPORT = 50000;

    public const SAMPLE_DATA_ROWS = 5;

    public const PREVIEW_DATA_ROWS = 10;

    /**
     * 🤖 AI Suggestion Thresholds
     */
    public const HIGH_CONFIDENCE_THRESHOLD = 0.8;

    public const MEDIUM_CONFIDENCE_THRESHOLD = 0.5;

    public const MIN_CONFIDENCE_THRESHOLD = 0.1;

    public const AUTO_MAPPING_CONFIDENCE = 0.8;

    /**
     * 📊 Data Quality Thresholds
     */
    public const MIN_DATA_COMPLETENESS = 0.7; // 70%

    public const GOOD_DATA_QUALITY_THRESHOLD = 0.8; // 80%

    public const EXCELLENT_DATA_QUALITY_THRESHOLD = 0.95; // 95%

    /**
     * ⏱️ Performance & Caching
     */
    public const TEMPLATE_CACHE_TTL = 3600; // 1 hour

    public const FIELD_SUGGESTION_CACHE_TTL = 1800; // 30 minutes

    public const IMPORT_SESSION_TTL = 7200; // 2 hours

    public const MAX_PROCESSING_TIME = 300; // 5 minutes

    /**
     * 🎯 Validation Rules
     */
    public const MIN_HEADER_COUNT = 1;

    public const MAX_HEADER_COUNT = 100;

    public const MIN_REQUIRED_FIELDS = 2; // name and parent_sku

    public const MAX_FIELD_NAME_LENGTH = 50;

    public const MAX_PRODUCT_NAME_LENGTH = 255;

    /**
     * 🏷️ Default Field Mappings (legacy compatibility)
     */
    public const DEFAULT_FIELD_MAPPINGS = [
        'item_title' => 'name',
        'caecus_sku' => 'parent_sku',
        'linnworks_sku' => 'linnworks_sku',
        'caecus_barcode' => 'barcode',
        'retail_price_with_carriage_at_495' => 'retail_price',
        'parcel_length' => 'length',
        'parcel_width' => 'width',
        'parcel_depth' => 'depth',
        'parcel_weightkg' => 'weight',
        'parent_image_1' => 'image_url',
        'full_description' => 'description',
    ];

    /**
     * 📋 Required Fields for Import
     */
    public const REQUIRED_FIELDS = [
        'name',
        'parent_sku',
    ];

    /**
     * 🎨 UI Display Defaults
     */
    public const ITEMS_PER_PAGE = 25;

    public const MAX_DISPLAY_ERRORS = 10;

    public const MAX_DISPLAY_WARNINGS = 5;

    public const DEBOUNCE_DELAY_MS = 300;

    /**
     * 📁 Template System
     */
    public const MAX_TEMPLATES_PER_USER = 20;

    public const DEFAULT_TEMPLATE_NAME = 'Default Product Import';

    public const TEMPLATE_NAME_MAX_LENGTH = 100;

    public const TEMPLATE_DESCRIPTION_MAX_LENGTH = 500;

    /**
     * 🔄 Error Handling
     */
    public const MAX_ERROR_DETAILS_LENGTH = 1000;

    public const MAX_VALIDATION_ERRORS_STORED = 100;

    public const DEFAULT_ERROR_MESSAGE = 'An unexpected error occurred during import';

    /**
     * 🎯 Field Types & Validation
     */
    public const NUMERIC_FIELDS = [
        'retail_price',
        'weight',
        'length',
        'width',
        'depth',
    ];

    public const STRING_FIELDS = [
        'name',
        'parent_sku',
        'linnworks_sku',
        'description',
        'barcode',
    ];

    public const URL_FIELDS = [
        'image_url',
    ];

    /**
     * 📊 Success Message Templates
     */
    public const SUCCESS_MESSAGES = [
        'all_success' => 'Successfully imported {created} products!',
        'partial_success' => 'Imported {created} products, skipped {skipped} rows.',
        'no_success' => 'No products imported. {skipped} rows were skipped.',
        'no_changes' => 'Import completed with no changes.',
    ];

    /**
     * 🛡️ Security Constraints
     */
    public const MAX_UPLOAD_ATTEMPTS = 5;

    public const UPLOAD_RATE_LIMIT_MINUTES = 15;

    public const MAX_CONCURRENT_IMPORTS = 3;

    /**
     * 🔍 Helper Methods for Dynamic Access
     */

    /**
     * Get file size limit in human readable format
     */
    public static function getMaxFileSizeFormatted(): string
    {
        return self::MAX_FILE_SIZE_MB.' MB';
    }

    /**
     * Get allowed file extensions as comma-separated string
     */
    public static function getAllowedExtensionsString(): string
    {
        return implode(', ', self::ALLOWED_FILE_EXTENSIONS);
    }

    /**
     * Check if field is numeric type
     */
    public static function isNumericField(string $fieldName): bool
    {
        return in_array($fieldName, self::NUMERIC_FIELDS);
    }

    /**
     * Check if field is string type
     */
    public static function isStringField(string $fieldName): bool
    {
        return in_array($fieldName, self::STRING_FIELDS);
    }

    /**
     * Check if field is URL type
     */
    public static function isUrlField(string $fieldName): bool
    {
        return in_array($fieldName, self::URL_FIELDS);
    }

    /**
     * Get confidence level description
     */
    public static function getConfidenceLevel(float $confidence): string
    {
        return match (true) {
            $confidence >= self::HIGH_CONFIDENCE_THRESHOLD => 'high',
            $confidence >= self::MEDIUM_CONFIDENCE_THRESHOLD => 'medium',
            $confidence >= self::MIN_CONFIDENCE_THRESHOLD => 'low',
            default => 'none'
        };
    }
}
