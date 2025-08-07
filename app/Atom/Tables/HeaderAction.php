<?php

namespace App\Atom\Tables;

/**
 * Header Action Class
 * 
 * Specialized action for table headers/toolbar.
 * Typically used for "Create New" type actions.
 */
class HeaderAction extends Action
{
    public function __construct(string $key)
    {
        parent::__construct($key);
        $this->variant = 'primary'; // Header actions are usually primary by default
        $this->size = 'base'; // Slightly larger than row actions
    }
    
    /**
     * Create a common "Create" header action
     */
    public static function create(): static
    {
        return static::make('create')
            ->label('Create New')
            ->icon('plus')
            ->button();
    }
    
    /**
     * Create a common "Export" header action
     */
    public static function export(): static
    {
        return static::make('export')
            ->label('Export')
            ->icon('download')
            ->outline();
    }
    
    /**
     * Create a common "Import" header action
     */
    public static function import(): static
    {
        return static::make('import')
            ->label('Import')
            ->icon('upload')
            ->outline();
    }
}