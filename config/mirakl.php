<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mirakl Universal System Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Universal Mirakl Integration System supporting
    | hybrid mode with official SDK integration capabilities.
    |
    */

    /**
     * Default client mode for all Mirakl operations
     *
     * Options:
     * - 'custom': Use our proven HTTP client implementation
     * - 'sdk': Use official Mirakl SDK (requires mirakl/sdk-php-shop package)
     * - 'hybrid': Intelligent selection between SDK and custom (recommended)
     */
    'default_mode' => env('MIRAKL_DEFAULT_MODE', 'hybrid'),

    /**
     * Performance monitoring and metrics collection
     */
    'performance' => [
        'enable_monitoring' => env('MIRAKL_PERFORMANCE_MONITORING', true),
        'metrics_retention_count' => 100,
        'enable_comparison' => true,
    ],

    /**
     * Fallback and retry configuration
     */
    'fallback' => [
        'enable_automatic_fallback' => true,
        'retry_attempts' => 3,
        'retry_delay_seconds' => 30,
        'timeout_multiplier' => 2,
    ],

    /**
     * Caching configuration for API responses
     */
    'caching' => [
        'enable_caching' => env('MIRAKL_ENABLE_CACHING', true),
        'attributes_cache_ttl' => 3600, // 1 hour
        'value_lists_cache_ttl' => 3600, // 1 hour
        'operator_discovery_ttl' => 3600, // 1 hour
    ],

    /**
     * SDK Integration Settings
     */
    'sdk' => [
        'auto_detect_installation' => true,
        'prefer_sdk_for_endpoints' => [
            '/api/offers',
            '/api/products',
            '/api/orders',
        ],
        'fallback_on_sdk_failure' => true,
    ],

    /**
     * Intelligent Features Configuration
     */
    'intelligent_features' => [
        'dynamic_field_discovery' => true,
        'automatic_value_validation' => true,
        'variant_grouping' => true,
        'category_auto_detection' => true,
        'smart_ean_generation' => true,
    ],

    /**
     * CSV Generation Settings
     */
    'csv' => [
        'include_operator_format' => true,
        'validate_list_values' => true,
        'use_fallback_values' => true,
        'generate_valid_eans' => true,
        'cleanup_temp_files' => true,
        'temp_directory' => 'temp/mirakl_imports',
    ],

    /**
     * Logging and Debugging
     */
    'logging' => [
        'log_performance_metrics' => env('MIRAKL_LOG_PERFORMANCE', false),
        'log_csv_generation' => env('MIRAKL_LOG_CSV_GENERATION', false),
        'log_api_requests' => env('MIRAKL_LOG_API_REQUESTS', false),
        'log_fallback_attempts' => true,
    ],

    /**
     * Default categories for operators
     */
    'default_categories' => [
        'freemans' => 'H02',
        'debenhams' => 'home-curtains',
        'bq' => 'home-garden',
    ],

    /**
     * Error handling configuration
     */
    'error_handling' => [
        'classify_recoverable_errors' => true,
        'suggest_recovery_actions' => true,
        'include_context_in_errors' => true,
        'rate_limit_retry_after' => 60,
    ],

    /**
     * Migration and compatibility settings
     */
    'migration' => [
        'enable_gradual_sdk_migration' => false,
        'migration_percentage' => 0, // 0-100, percentage of requests to route to SDK
        'track_migration_performance' => true,
    ],

    /**
     * Operator-specific overrides
     *
     * You can override any of the above settings on a per-operator basis
     */
    'operator_overrides' => [
        'freemans' => [
            'default_mode' => 'custom', // Freemans works best with custom implementation
            'fallback' => [
                'retry_attempts' => 5, // Freemans needs more retries
            ],
        ],
        'debenhams' => [
            'default_mode' => 'hybrid', // Debenhams can use hybrid mode
        ],
        'bq' => [
            'default_mode' => 'custom', // B&Q specific settings
            'caching' => [
                'attributes_cache_ttl' => 7200, // Cache longer for B&Q
            ],
        ],
    ],

    /**
     * Development and testing settings
     */
    'development' => [
        'enable_debug_mode' => env('MIRAKL_DEBUG_MODE', false),
        'mock_sdk_responses' => env('MIRAKL_MOCK_SDK', false),
        'simulate_sdk_failures' => false,
        'dry_run_uploads' => env('MIRAKL_DRY_RUN', false),
    ],
];
