<?php

namespace App\Services\Attributes\Concerns;

use App\Facades\Activity;

trait LogsActivity
{
    /**
     * Log activity using Activity facade with sensible defaults
     */
    protected function logActivity(string $eventType, array $eventData = []): void
    {
        if (!$this->shouldLog) {
            return;
        }

        $modelClass = class_basename($this->model);
        $modelName = method_exists($this->model, 'name') ? $this->model->name : "#{$this->model->id}";
        
        // Generate description if not provided
        $description = $this->logDescription ?? $this->generateLogDescription($eventType, $eventData);

        // Merge event data with configured log data
        $logWith = array_merge($this->logData, $eventData, [
            'model_type' => $modelClass,
            'model_id' => $this->model->id,
            'model_name' => $modelName,
            'source' => $this->source ?? 'manual',
        ]);

        Activity::log()
            ->by(auth()->id())
            ->customEvent($eventType, $this->model)
            ->description($description)
            ->with($logWith)
            ->save();
    }

    /**
     * Generate a default description for the activity log
     */
    protected function generateLogDescription(string $eventType, array $eventData): string
    {
        $modelClass = class_basename($this->model);
        $userName = auth()->user()?->name ?? 'System';
        
        return match($eventType) {
            'attribute_set' => "Attribute '{$eventData['attribute_key']}' set on {$modelClass} by {$userName}",
            'attributes_set_many' => "{$eventData['attributes_count']} attributes updated on {$modelClass} by {$userName}",
            'attribute_unset' => "Attribute '{$eventData['attribute_key']}' removed from {$modelClass} by {$userName}",
            default => "{$eventType} on {$modelClass} by {$userName}",
        };
    }
}