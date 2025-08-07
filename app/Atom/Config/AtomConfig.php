<?php

namespace App\Atom\Config;

/**
 * Atom Framework Configuration
 * 
 * Central configuration for the Atom framework.
 * Customize framework behavior here.
 */
class AtomConfig
{
    /**
     * Default layout paths to try in order of preference.
     */
    public static function getDefaultLayouts(): array
    {
        return [
            'components.layouts.app',     // Laravel 11+ component layout
            'layouts.app',                // Traditional Laravel layout  
            'app',                        // Simple app layout
            'components.layout',          // Alternative component layout
            'layout',                     // Minimal layout
        ];
    }
    
    /**
     * View prefixes to try for elements in order of preference.
     */
    public static function getViewPrefixes(): array
    {
        return [
            'atom.elements',              // User's custom elements
            'atom::elements',             // Framework default elements
        ];
    }
    
    /**
     * CSS frameworks to detect and their corresponding view paths.
     */
    public static function getCssFrameworks(): array
    {
        return [
            'tailwind' => 'atom::elements.tailwind',
            'bootstrap' => 'atom::elements.bootstrap', 
            'minimal' => 'atom::elements.minimal',
        ];
    }
    
    /**
     * Default element fallbacks when no views exist.
     */
    public static function getElementFallbacks(): array
    {
        return [
            'navigation.main' => '<nav class="navigation"><!-- Navigation --></nav>',
            'navigation.breadcrumbs' => '<div class="breadcrumbs"><!-- Breadcrumbs --></div>',
            'actions.buttons' => '<div class="actions"><!-- Actions --></div>',
            'table.filters' => '<div class="filters"><!-- Filters --></div>',
            'notifications.container' => '<div class="notifications"><!-- Notifications --></div>',
        ];
    }
    
    /**
     * Framework-wide settings.
     */
    public static function getSettings(): array
    {
        return [
            'auto_discovery' => true,
            'silent_discovery' => !config('app.debug'),
            'cache_discovery' => true,
            'css_detection' => true,
            'layout_detection' => true,
        ];
    }
}