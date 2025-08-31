<?php

namespace App\Actions\Activity;

use App\Actions\Base\BaseAction;
use App\Models\ActivityLog;
use Carbon\Carbon;

class LogActivityAction extends BaseAction
{
    protected bool $useTransactions = false; // Activity logs don't need transactions
    
    protected function performAction(...$params): array
    {
        $event = $params[0] ?? throw new \InvalidArgumentException('Event is required');
        $data = $params[1] ?? [];
        $userId = $params[2] ?? auth()->id();
        $occurredAt = $params[3] ?? now();

        $activityLog = ActivityLog::create([
            'event' => $event,
            'user_id' => $userId,
            'occurred_at' => $occurredAt,
            'data' => $data,
        ]);

        return $this->success('Activity logged successfully', [
            'activity_log' => $activityLog,
        ]);
    }

    // Convenience method that returns the ActivityLog directly
    public function createLog(
        string $event,
        array $data,
        ?int $userId = null,
        ?Carbon $occurredAt = null
    ): ActivityLog {
        $result = $this->execute($event, $data, $userId, $occurredAt);
        return $result['data']['activity_log'];
    }
}