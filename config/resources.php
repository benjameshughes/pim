<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Resource System Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the FilamentPHP-inspired resource system.
    | This system allows you to define pure PHP resource classes that
    | automatically work across Livewire tables, API endpoints, and more.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Auto Discovery
    |--------------------------------------------------------------------------
    |
    | Enable automatic discovery of resource classes. When enabled, the system
    | will scan configured directories for resource classes and register them
    | automatically. Disable in production for better performance.
    |
    */

    'auto_discovery' => env('RESOURCE_AUTO_DISCOVERY', true),

    /*
    |--------------------------------------------------------------------------
    | Discovery Paths
    |--------------------------------------------------------------------------
    |
    | Directories to scan for resource classes during auto-discovery.
    | Paths are relative to the application root directory.
    |
    */

    'discovery_paths' => [
        'app/Resources',
    ],

    /*
    |--------------------------------------------------------------------------
    | Resource Namespace
    |--------------------------------------------------------------------------
    |
    | The base namespace for resource classes. This is used during
    | auto-discovery and resource resolution.
    |
    */

    'namespace' => 'App\\Resources\\',

    /*
    |--------------------------------------------------------------------------
    | Web Routes Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for web routes generated for resources.
    |
    */

    'web' => [
        'enabled' => true,
        'prefix' => '',
        'middleware' => ['web'],
        'name_prefix' => 'resources.',
    ],

    /*
    |--------------------------------------------------------------------------
    | API Routes Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for API routes generated for resources.
    |
    */

    'api' => [
        'enabled' => env('RESOURCE_API_ENABLED', false),
        'prefix' => 'api/resources',
        'middleware' => ['api'],
        'name_prefix' => 'api.resources.',
        'version' => 'v1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for resource navigation generation.
    |
    */

    'navigation' => [
        'enabled' => true,
        'share_globally' => true,
        'cache_duration' => 3600, // 1 hour in seconds
        'sort_groups' => true,
        'default_group' => 'Other',
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for resource system caching. Caching improves performance
    | by storing resolved resource configurations and query results.
    |
    */

    'cache' => [
        'enabled' => env('RESOURCE_CACHE_ENABLED', true),
        'duration' => env('RESOURCE_CACHE_DURATION', 3600), // 1 hour
        'prefix' => 'resources.',
        'tags' => ['resources'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for resource authorization. The system will automatically
    | check model policies if they exist.
    |
    */

    'authorization' => [
        'enabled' => true,
        'default_guard' => null, // Uses default auth guard
        'policy_discovery' => true,
        'fallback_permissions' => [
            'viewAny' => true,
            'view' => true,
            'create' => true,
            'update' => true,
            'delete' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Table Configuration
    |--------------------------------------------------------------------------
    |
    | Default configuration for resource tables.
    |
    */

    'table' => [
        'default_per_page' => 15,
        'per_page_options' => [10, 15, 25, 50, 100],
        'max_per_page' => 100,
        'enable_search' => true,
        'enable_filters' => true,
        'enable_sorting' => true,
        'enable_bulk_actions' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Form Configuration
    |--------------------------------------------------------------------------
    |
    | Default configuration for resource forms (create/edit).
    |
    */

    'form' => [
        'enable_validation' => true,
        'auto_save_drafts' => false,
        'confirm_unsaved_changes' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Global Search Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for global search across resources.
    |
    */

    'global_search' => [
        'enabled' => true,
        'max_results' => 50,
        'min_query_length' => 2,
        'cache_results' => true,
        'cache_duration' => 300, // 5 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for performance optimizations.
    |
    */

    'performance' => [
        'eager_load_relations' => true,
        'optimize_queries' => true,
        'enable_query_cache' => true,
        'preload_navigation' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Development Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for development and debugging features.
    |
    */

    'development' => [
        'debug_mode' => env('RESOURCE_DEBUG', env('APP_DEBUG', false)),
        'log_queries' => env('RESOURCE_LOG_QUERIES', false),
        'show_resource_info' => env('RESOURCE_SHOW_INFO', false),
        'validation_errors_in_console' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Enable or disable specific features of the resource system.
    |
    */

    'features' => [
        'livewire_adapter' => true,
        'api_adapter' => env('RESOURCE_API_ADAPTER', false),
        'blade_adapter' => env('RESOURCE_BLADE_ADAPTER', true),
        'export_functionality' => true,
        'import_functionality' => true,
        'bulk_operations' => true,
        'real_time_updates' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Adapter Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for different resource adapters.
    |
    */

    'adapters' => [
        'livewire' => [
            'layout' => 'components.layouts.app',
            'theme' => 'default',
            'enable_spa' => true,
        ],
        
        'api' => [
            'serializer' => 'default',
            'include_meta' => true,
            'include_links' => true,
            'pagination_type' => 'cursor', // or 'page'
        ],
        
        'blade' => [
            'theme' => 'bootstrap', // or 'tailwind'
            'enable_ajax' => false,
        ],
    ],
];