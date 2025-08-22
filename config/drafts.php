<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Draft Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how drafts are stored and managed in your application.
    |
    */

    'key_prefix' => env('DRAFT_KEY_PREFIX', 'draft'),
    
    'default_ttl' => env('DRAFT_DEFAULT_TTL', 86400), // 24 hours
    
    'wizard_ttl' => env('DRAFT_WIZARD_TTL', 604800), // 7 days for wizard drafts
    
    'storage_driver' => env('DRAFT_STORAGE_DRIVER', 'cache'),
    
    /*
    |--------------------------------------------------------------------------
    | Auto-save Configuration
    |--------------------------------------------------------------------------
    */
    
    'auto_save' => [
        'enabled' => env('DRAFT_AUTO_SAVE_ENABLED', true),
        'interval' => env('DRAFT_AUTO_SAVE_INTERVAL', 30), // seconds
        'max_retries' => env('DRAFT_AUTO_SAVE_MAX_RETRIES', 3),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Cleanup Configuration
    |--------------------------------------------------------------------------
    */
    
    'cleanup' => [
        'enabled' => env('DRAFT_CLEANUP_ENABLED', true),
        'schedule' => env('DRAFT_CLEANUP_SCHEDULE', 'daily'),
        'keep_days' => env('DRAFT_CLEANUP_KEEP_DAYS', 30),
    ],
];