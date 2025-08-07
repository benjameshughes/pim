<?php

namespace App\Atom\Tables;

use Closure;

/**
 * Bulk Action Class
 * 
 * Specialized action for operating on multiple selected records.
 * Handles collections of records instead of single records.
 */
class BulkAction extends Action
{
    protected bool $deselectRecordsAfterCompletion = true;
    
    public function __construct(string $key)
    {
        parent::__construct($key);
        $this->size = 'sm';
    }
    
    /**
     * Set whether to deselect records after completion
     */
    public function deselectRecordsAfterCompletion(bool $deselect = true): static
    {
        $this->deselectRecordsAfterCompletion = $deselect;
        return $this;
    }
    
    /**
     * Create a common "Delete" bulk action
     */
    public static function delete(): static
    {
        return static::make('delete')
            ->label('Delete Selected')
            ->icon('trash')
            ->color('red')
            ->requiresConfirmation(
                'Delete Selected Items',
                'Are you sure you want to delete the selected items? This action cannot be undone.'
            )
            ->action(function ($records) {
                $records->each->delete();
            });
    }
    
    /**
     * Create a common "Export" bulk action
     */
    public static function export(): static
    {
        return static::make('export')
            ->label('Export Selected')
            ->icon('download')
            ->outline()
            ->deselectRecordsAfterCompletion(false);
    }
    
    /**
     * Create a common "Archive" bulk action
     */
    public static function archive(): static
    {
        return static::make('archive')
            ->label('Archive Selected')
            ->icon('archive')
            ->outline()
            ->requiresConfirmation(
                'Archive Selected Items',
                'Are you sure you want to archive the selected items?'
            );
    }
    
    /**
     * Get whether to deselect records after completion
     */
    public function shouldDeselectRecordsAfterCompletion(): bool
    {
        return $this->deselectRecordsAfterCompletion;
    }
    
    /**
     * Convert bulk action to array for rendering
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'deselectRecordsAfterCompletion' => $this->deselectRecordsAfterCompletion,
        ]);
    }
}