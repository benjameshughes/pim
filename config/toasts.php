<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Toast Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration defines the default settings for toast notifications.
    | These values can be overridden when creating individual toast instances.
    |
    */

    'defaults' => [
        'duration' => 4000, // 4 seconds in milliseconds
        'position' => 'top-right',
        'type' => 'info',
        'closable' => true,
        'persistent' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Toast Positions
    |--------------------------------------------------------------------------
    |
    | Available positions for displaying toast notifications.
    | Each position maps to CSS classes for proper positioning.
    |
    */

    'positions' => [
        'top-left' => [
            'container' => 'fixed top-4 left-4 z-50 max-w-sm',
            'alignment' => 'flex-col items-start',
        ],
        'top-right' => [
            'container' => 'fixed top-4 right-4 z-50 max-w-sm',
            'alignment' => 'flex-col items-end',
        ],
        'top-center' => [
            'container' => 'fixed top-4 left-1/2 -translate-x-1/2 z-50 max-w-sm',
            'alignment' => 'flex-col items-center',
        ],
        'bottom-left' => [
            'container' => 'fixed bottom-4 left-4 z-50 max-w-sm',
            'alignment' => 'flex-col-reverse items-start',
        ],
        'bottom-right' => [
            'container' => 'fixed bottom-4 right-4 z-50 max-w-sm',
            'alignment' => 'flex-col-reverse items-end',
        ],
        'bottom-center' => [
            'container' => 'fixed bottom-4 left-1/2 -translate-x-1/2 z-50 max-w-sm',
            'alignment' => 'flex-col-reverse items-center',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Toast Types and Styling
    |--------------------------------------------------------------------------
    |
    | Configuration for different toast types including their colors,
    | icons, and styling variations following Flux UI design patterns.
    |
    */

    'types' => [
        'success' => [
            'icon' => 'circle-check',
            'background' => 'bg-status-success-50 dark:bg-status-success-700 dark:bg-opacity-30',
            'border' => 'border border-status-success-100 dark:border-status-success-600',
            'text' => 'text-status-success-700 dark:text-status-success-100',
            'icon_color' => 'text-status-success-500 dark:text-status-success-100',
            'close_hover' => 'hover:bg-status-success-100 dark:hover:bg-status-success-600',
        ],
        'error' => [
            'icon' => 'circle-x',
            'background' => 'bg-status-error-50 dark:bg-status-error-700 dark:bg-opacity-30',
            'border' => 'border border-status-error-100 dark:border-status-error-600',
            'text' => 'text-status-error-700 dark:text-status-error-100',
            'icon_color' => 'text-status-error-500 dark:text-status-error-100',
            'close_hover' => 'hover:bg-status-error-100 dark:hover:bg-status-error-600',
        ],
        'warning' => [
            'icon' => 'triangle-alert',
            'background' => 'bg-status-warning-50 dark:bg-status-warning-700 dark:bg-opacity-30',
            'border' => 'border border-status-warning-100 dark:border-status-warning-600',
            'text' => 'text-status-warning-700 dark:text-status-warning-100',
            'icon_color' => 'text-status-warning-500 dark:text-status-warning-100',
            'close_hover' => 'hover:bg-status-warning-100 dark:hover:bg-status-warning-600',
        ],
        'info' => [
            'icon' => 'info',
            'background' => 'bg-status-info-50 dark:bg-status-info-700 dark:bg-opacity-30',
            'border' => 'border border-status-info-100 dark:border-status-info-600',
            'text' => 'text-status-info-700 dark:text-status-info-100',
            'icon_color' => 'text-status-info-500 dark:text-status-info-100',
            'close_hover' => 'hover:bg-status-info-100 dark:hover:bg-status-info-600',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Animation Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for toast entrance and exit animations using Alpine.js
    | and Tailwind CSS transitions.
    |
    */

    'animations' => [
        'enter' => [
            'duration' => 300, // milliseconds
            'from' => 'opacity-0 transform translate-x-full scale-95',
            'to' => 'opacity-100 transform translate-x-0 scale-100',
        ],
        'exit' => [
            'duration' => 200, // milliseconds
            'from' => 'opacity-100 transform translate-x-0 scale-100',
            'to' => 'opacity-0 transform translate-x-full scale-95',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Maximum Toast Limit
    |--------------------------------------------------------------------------
    |
    | The maximum number of toast notifications that can be displayed
    | simultaneously. Older toasts will be automatically dismissed.
    |
    */

    'max_toasts' => 5,

    /*
    |--------------------------------------------------------------------------
    | Session Key
    |--------------------------------------------------------------------------
    |
    | The session key used to store toast notifications between requests.
    | This allows toasts to persist across redirects.
    |
    */

    'session_key' => 'toasts',
];