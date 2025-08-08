<?php

namespace App\Services\StackedList;

use App\Exceptions\StackedList\UnauthorizedBulkActionException;
use App\Jobs\StackedList\BulkDeleteJob;
use App\Jobs\StackedList\BulkUpdateStatusJob;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BulkActionService
{
    protected int $asyncThreshold = 50; // Dispatch job if more than 50 items

    public function __construct(
        protected AuthorizationService $authService
    ) {}

    /**
     * Update model status for bulk operations.
     */
    public function updateStatus(string $modelClass, array $ids, string $status): int
    {
        if (! $this->authService->canPerformBulkAction('activate', $modelClass)) {
            throw UnauthorizedBulkActionException::forAction('activate', $modelClass);
        }

        if (count($ids) > $this->asyncThreshold) {
            BulkUpdateStatusJob::dispatch($modelClass, $ids, $status, auth()->id());

            return count($ids); // Return expected count for message
        }

        return $modelClass::whereIn('id', $ids)
            ->update(['status' => $status]);
    }

    /**
     * Delete models for bulk operations.
     */
    public function delete(string $modelClass, array $ids): int
    {
        if (! $this->authService->canPerformBulkAction('delete', $modelClass)) {
            throw UnauthorizedBulkActionException::forAction('delete', $modelClass);
        }

        if (count($ids) > $this->asyncThreshold) {
            BulkDeleteJob::dispatch($modelClass, $ids, auth()->id());

            return count($ids); // Return expected count for message
        }

        return $modelClass::whereIn('id', $ids)->delete();
    }

    /**
     * Get friendly model name for flash messages.
     */
    public function getModelDisplayName(string $modelClass): string
    {
        return Str::plural(Str::lower(class_basename($modelClass)));
    }

    /**
     * Create success flash message.
     */
    public function flashSuccess(string $message): void
    {
        session()->flash('message', $message);
    }

    /**
     * Create error flash message.
     */
    public function flashError(string $message): void
    {
        session()->flash('error', $message);
    }
}
