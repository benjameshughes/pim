<?php

namespace App\Jobs\StackedList;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BulkUpdateStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $modelClass,
        public array $ids,
        public string $status,
        public ?int $userId = null
    ) {
        // Set queue connection and queue name based on operation size
        $this->onQueue(count($ids) > 100 ? 'bulk-operations' : 'default');
    }

    public function handle(): void
    {
        $chunks = array_chunk($this->ids, 100); // Process in chunks of 100

        foreach ($chunks as $chunk) {
            $updated = $this->modelClass::whereIn('id', $chunk)
                ->update(['status' => $this->status]);

            Log::info("Bulk status update", [
                'model' => $this->modelClass,
                'ids_count' => count($chunk),
                'updated_count' => $updated,
                'status' => $this->status,
                'user_id' => $this->userId
            ]);
        }

        // Optional: Send notification to user when job completes
        if ($this->userId) {
            $this->notifyUserOfCompletion();
        }
    }

    protected function notifyUserOfCompletion(): void
    {
        // Could implement user notification here
        // e.g., using Laravel's notification system or broadcasting
    }
}