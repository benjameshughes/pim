<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Configuration
    |--------------------------------------------------------------------------
    |
    | Default settings for auto-generated stacked lists
    |
    */

    'column_limit' => env('STACKED_LIST_COLUMN_LIMIT', 6),
    'cache_ttl' => env('STACKED_LIST_CACHE_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | Hidden Columns
    |--------------------------------------------------------------------------
    |
    | Columns to automatically hide from auto-generated lists
    |
    */

    'hidden_columns' => [
        'id', 'password', 'remember_token', 'email_verified_at', 
        'created_at', 'updated_at', 'deleted_at'
    ],

    /*
    |--------------------------------------------------------------------------
    | Badge Columns
    |--------------------------------------------------------------------------
    |
    | Columns to automatically treat as badge columns
    |
    */

    'badge_columns' => [
        'status', 'is_active', 'is_featured', 'is_published', 
        'active', 'featured', 'published', 'enabled'
    ],

    /*
    |--------------------------------------------------------------------------
    | Searchable Column Types
    |--------------------------------------------------------------------------
    |
    | Database column types that should be searchable by default
    |
    */

    'searchable_types' => [
        'string', 'text', 'varchar'
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for data export functionality
    |
    */

    'export' => [
        'chunk_size' => env('STACKED_LIST_EXPORT_CHUNK_SIZE', 1000),
        'max_records' => env('STACKED_LIST_EXPORT_MAX_RECORDS', 10000),
        'formats' => ['csv', 'xlsx'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    |
    | Performance-related configuration
    |
    */

    'performance' => [
        'schema_cache_ttl' => env('STACKED_LIST_SCHEMA_CACHE_TTL', 3600),
        'default_per_page' => env('STACKED_LIST_DEFAULT_PER_PAGE', 15),
        'max_per_page' => env('STACKED_LIST_MAX_PER_PAGE', 100),
    ],
];