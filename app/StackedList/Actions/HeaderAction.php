<?php

namespace App\StackedList\Actions;

class HeaderAction extends Action
{
    /**
     * Create a new header action instance.
     */
    public static function make(string $name): static
    {
        $instance = parent::make($name);
        $instance->variant = 'primary'; // Default to primary for header actions
        return $instance;
    }

    /**
     * Create a primary action (main CTA).
     */
    public static function create(): static
    {
        return static::make('create')
            ->label('Create')
            ->icon('plus')
            ->primary();
    }

    /**
     * Create an import action.
     */
    public static function import(): static
    {
        return static::make('import')
            ->label('Import')
            ->icon('upload')
            ->outline();
    }

    /**
     * Create an export action.
     */
    public static function export(): static
    {
        return static::make('export')
            ->label('Export')
            ->icon('download')
            ->outline();
    }
}