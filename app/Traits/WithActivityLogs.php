<?php

namespace App\Traits;

use App\Facades\Activity;
use App\Models\ActivityLog;

trait WithActivityLogs
{
    protected function logActivity(): \App\Services\ActivityLogger
    {
        return Activity::log()->by(auth()->id());
    }

    protected function logCreated($model, array $details = []): ActivityLog
    {
        $logger = $this->logActivity()->created($model);
        
        if (!empty($details)) {
            $logger->with($details)->save();
        }
        
        return $logger;
    }

    protected function logUpdated($model, array $changes = [], string $description = null): ActivityLog
    {
        $logger = $this->logActivity()->updated($model, $changes);
        
        if ($description) {
            $logger->description($description)->save();
        }
        
        return $logger;
    }

    protected function logDeleted($model, string $reason = null): ActivityLog
    {
        $logger = $this->logActivity()->deleted($model);
        
        if ($reason) {
            $logger->with(['reason' => $reason])->save();
        }
        
        return $logger;
    }

    protected function logCustom(string $event, $model, array $data = [], string $description = null): ActivityLog
    {
        $logger = $this->logActivity()
            ->event($model, $event)
            ->with($data);
            
        if ($description) {
            $logger->description($description);
        }
        
        return $logger->save();
    }

    protected function logImported($model, array $importDetails = []): ActivityLog
    {
        return $this->logActivity()->imported($model, $importDetails);
    }

    protected function logSynced($model, string $channel, array $syncDetails = []): ActivityLog
    {
        return $this->logActivity()->synced($model, $channel, $syncDetails);
    }

    protected function logBulkOperation(string $operation, array $models, array $details = []): ActivityLog
    {
        $batchId = uniqid('bulk_');
        
        collect($models)->each(function ($model) use ($operation, $batchId, $details) {
            $this->logActivity()
                ->event($model, $operation)
                ->batch($batchId)
                ->with($details)
                ->save();
        });

        return $this->logActivity()
            ->event((object)['id' => null], "bulk_{$operation}")
            ->batch($batchId)
            ->with([
                'count' => count($models),
                'details' => $details,
            ])
            ->save();
    }
}