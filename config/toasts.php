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
            'background' => 'bg-status-success-50/90 dark:bg-status-success-900/80 backdrop-blur-sm',
            'border' => 'border border-status-success-200/50 dark:border-status-success-500/30',
            'text' => 'text-status-success-800 dark:text-status-success-100',
            'icon_color' => 'text-status-success-600 dark:text-status-success-300',
            'icon_background' => 'bg-status-success-100 dark:bg-status-success-800/50',
            'close_hover' => 'hover:bg-status-success-200/50 dark:hover:bg-status-success-700/50 focus:bg-status-success-200/50',
            'action_hover' => 'hover:bg-status-success-100 dark:hover:bg-status-success-800/30 focus:bg-status-success-100',
            'progress_color' => 'rgb(34 197 94)',
            'progress_color_end' => 'rgb(21 128 61)',
            'accent_bar' => 'bg-gradient-to-b from-status-success-400 to-status-success-600',
        ],
        'error' => [
            'icon' => 'circle-x',
            'background' => 'bg-status-error-50/90 dark:bg-status-error-900/80 backdrop-blur-sm',
            'border' => 'border border-status-error-200/50 dark:border-status-error-500/30',
            'text' => 'text-status-error-800 dark:text-status-error-100',
            'icon_color' => 'text-status-error-600 dark:text-status-error-300',
            'icon_background' => 'bg-status-error-100 dark:bg-status-error-800/50',
            'close_hover' => 'hover:bg-status-error-200/50 dark:hover:bg-status-error-700/50 focus:bg-status-error-200/50',
            'action_hover' => 'hover:bg-status-error-100 dark:hover:bg-status-error-800/30 focus:bg-status-error-100',
            'progress_color' => 'rgb(239 68 68)',
            'progress_color_end' => 'rgb(185 28 28)',
            'accent_bar' => 'bg-gradient-to-b from-status-error-400 to-status-error-600',
        ],
        'warning' => [
            'icon' => 'triangle-alert',
            'background' => 'bg-status-warning-50/90 dark:bg-status-warning-900/80 backdrop-blur-sm',
            'border' => 'border border-status-warning-200/50 dark:border-status-warning-500/30',
            'text' => 'text-status-warning-800 dark:text-status-warning-100',
            'icon_color' => 'text-status-warning-600 dark:text-status-warning-300',
            'icon_background' => 'bg-status-warning-100 dark:bg-status-warning-800/50',
            'close_hover' => 'hover:bg-status-warning-200/50 dark:hover:bg-status-warning-700/50 focus:bg-status-warning-200/50',
            'action_hover' => 'hover:bg-status-warning-100 dark:hover:bg-status-warning-800/30 focus:bg-status-warning-100',
            'progress_color' => 'rgb(245 158 11)',
            'progress_color_end' => 'rgb(180 83 9)',
            'accent_bar' => 'bg-gradient-to-b from-status-warning-400 to-status-warning-600',
        ],
        'info' => [
            'icon' => 'info',
            'background' => 'bg-status-info-50/90 dark:bg-status-info-900/80 backdrop-blur-sm',
            'border' => 'border border-status-info-200/50 dark:border-status-info-500/30',
            'text' => 'text-status-info-800 dark:text-status-info-100',
            'icon_color' => 'text-status-info-600 dark:text-status-info-300',
            'icon_background' => 'bg-status-info-100 dark:bg-status-info-800/50',
            'close_hover' => 'hover:bg-status-info-200/50 dark:hover:bg-status-info-700/50 focus:bg-status-info-200/50',
            'action_hover' => 'hover:bg-status-info-100 dark:hover:bg-status-info-800/30 focus:bg-status-info-100',
            'progress_color' => 'rgb(59 130 246)',
            'progress_color_end' => 'rgb(29 78 216)',
            'accent_bar' => 'bg-gradient-to-b from-status-info-400 to-status-info-600',
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
            'duration' => 500, // milliseconds - increased for smoother entrance
            'from' => 'opacity-0 transform translate-x-full scale-95 rotate-1',
            'to' => 'opacity-100 transform translate-x-0 scale-100 rotate-0',
        ],
        'exit' => [
            'duration' => 300, // milliseconds - increased for better feedback
            'from' => 'opacity-100 transform translate-x-0 scale-100 rotate-0',
            'to' => 'opacity-0 transform translate-x-full scale-95 rotate-1',
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
