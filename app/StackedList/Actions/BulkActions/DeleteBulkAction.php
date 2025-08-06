<?php

namespace App\StackedList\Actions\BulkActions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class DeleteBulkAction
{
    public function __construct(
        private string $modelClass
    ) {}

    /**
     * Execute the bulk delete action.
     */
    public function execute(array $selectedIds): array
    {
        $deletedCount = 0;
        $errors = [];

        try {
            // Use chunking for large datasets
            collect($selectedIds)
                ->chunk(config('stacked-list.performance.chunk_size', 100))
                ->each(function ($chunk) use (&$deletedCount) {
                    $deletedCount += $this->modelClass::whereIn('id', $chunk->toArray())->delete();
                });

            return [
                'success' => true,
                'message' => "{$deletedCount} items deleted successfully.",
                'count' => $deletedCount,
            ];
        } catch (\Exception $e) {
            logger()->error('Bulk delete failed', [
                'model' => $this->modelClass,
                'ids' => $selectedIds,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to delete items. Please try again.',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if the action can be performed on the given items.
     */
    public function canExecute(Collection $items): bool
    {
        // Add business logic here - e.g., check permissions, status constraints
        return $items->isNotEmpty() && $items->count() <= config('stacked-list.performance.max_bulk_operations', 1000);
    }

    /**
     * Get confirmation message for the action.
     */
    public function getConfirmationMessage(int $count): string
    {
        return "Are you sure you want to delete {$count} " . 
               str($count === 1 ? 'item' : 'items') . 
               "? This action cannot be undone.";
    }
}