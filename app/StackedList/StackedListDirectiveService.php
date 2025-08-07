<?php

namespace App\StackedList;

use Livewire\Livewire;

/**
 * Service for rendering StackedList via Blade directives (FilamentPHP approach).
 */
class StackedListDirectiveService
{
    /**
     * Component mapping for StackedList types.
     */
    protected static array $componentMap = [
        'products' => \App\Livewire\Pim\Products\Management\ProductIndex::class,
        'barcodes' => \App\Livewire\Pim\Barcodes\BarcodeIndex::class,
    ];

    /**
     * Get available types.
     */
    public static function getAvailableTypes(): array
    {
        return static::$componentMap;
    }

    /**
     * Get the component class for a given type.
     */
    public static function getComponentClass(string $type): string
    {
        $componentClass = static::$componentMap[$type] ?? null;
        
        if (!$componentClass) {
            throw new \InvalidArgumentException("Unknown StackedList type: {$type}. Available types: " . implode(', ', array_keys(static::$componentMap)));
        }

        return $componentClass;
    }

    /**
     * Render a StackedList by delegating to the appropriate Livewire component.
     */
    public static function render(string $type, array $parameters = []): string
    {
        $componentClass = static::getComponentClass($type);

        // Get the Livewire component name
        $componentName = str_replace(['App\\Livewire\\', '\\'], ['', '.'], $componentClass);
        $componentName = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', str_replace('.', '-', $componentName)));
        
        // Return raw @livewire directive that will be processed by Blade
        $parametersJson = empty($parameters) ? '' : ', ' . json_encode($parameters);
        return "@livewire('{$componentClass}'{$parametersJson})";
    }
}