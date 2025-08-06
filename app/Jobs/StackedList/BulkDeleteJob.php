<?php

namespace App\Jobs\StackedList;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BulkDeleteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $modelClass,
        public array $ids,
        public ?int $userId = null
    ) {
        // Set queue for bulk operations
        $this->onQueue(count($ids) > 50 ? 'bulk-operations' : 'default');
    }

    public function handle(): void
    {
        $chunks = array_chunk($this->ids, 50); // Process in smaller chunks for deletions

        $totalDeleted = 0;
        foreach ($chunks as $chunk) {
            $deleted = $this->modelClass::whereIn('id', $chunk)->delete();
            $totalDeleted += $deleted;

            Log::info("Bulk delete operation", [
                'model' => $this->modelClass,
                'ids_count' => count($chunk),
                'deleted_count' => $deleted,
                'user_id' => $this->userId
            ]);
        }

        // Optional: Send notification to user when job completes
        if ($this->userId) {
            $this->notifyUserOfCompletion($totalDeleted);
        }
    }

    protected function notifyUserOfCompletion(int $deletedCount): void
    {
        // Could implement user notification here
        // e.g., using Laravel's notification system or broadcasting
    }
}