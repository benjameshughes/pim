<?php

namespace App\StackedList\Actions;

class BulkAction extends Action
{
    /**
     * Create a new bulk action instance.
     */
    public static function make(string $name): static
    {
        $instance = parent::make($name);
        $instance->key = $name; // Ensure key is set for bulk actions
        return $instance;
    }

    /**
     * Create a bulk delete action with confirmation.
     */
    public static function delete(): static
    {
        return static::make('delete')
            ->label('Delete Selected')
            ->icon('trash-2')
            ->danger()
            ->requiresConfirmation(
                'Delete Selected Items',
                'Are you sure you want to delete the selected items? This action cannot be undone.'
            );
    }

    /**
     * Create a bulk export action.
     */
    public static function export(): static
    {
        return static::make('export')
            ->label('Export Selected')
            ->icon('download')
            ->outline();
    }

    /**
     * Create a bulk activate action.
     */
    public static function activate(): static
    {
        return static::make('activate')
            ->label('Activate Selected')
            ->icon('check-circle')
            ->outline();
    }

    /**
     * Create a bulk deactivate action.
     */
    public static function deactivate(): static
    {
        return static::make('deactivate')
            ->label('Deactivate Selected')
            ->icon('circle-x')
            ->outline();
    }
}