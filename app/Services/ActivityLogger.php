<?php

namespace App\Services;

use App\Actions\Activity\ExtractModelDataAction;
use App\Actions\Activity\LogActivityAction;
use App\Models\ActivityLog;
use Illuminate\Support\Collection;

class ActivityLogger
{
    protected array $data = [];

    public function __construct(
        private readonly LogActivityAction $logAction,
        private readonly ExtractModelDataAction $extractAction,
    ) {}

    public function log(): self
    {
        return new static($this->logAction, $this->extractAction);
    }

    public function by(?int $userId): self
    {
        $this->data['user_id'] = $userId;
        return $this;
    }

    public function event($model, string $action): self
    {
        $this->data['event'] = $this->makeEvent($model, $action);
        $this->data['subject'] = $this->extractAction->extractData($model);
        return $this;
    }

    public function customEvent(string $eventName, $model = null): self
    {
        $this->data['event'] = $eventName;
        if ($model) {
            $this->data['subject'] = $this->extractAction->extractData($model);
        }
        return $this;
    }

    public function with(array $additionalData): self
    {
        $this->data = array_merge($this->data, $additionalData);
        return $this;
    }

    public function description(string $description): self
    {
        $this->data['description'] = $description;
        return $this;
    }

    public function changes(array $changes): self
    {
        $this->data['changes'] = $changes;
        return $this;
    }

    public function batch(?string $batchId): self
    {
        $this->data['batch_id'] = $batchId;
        return $this;
    }

    public function created($model): ActivityLog
    {
        return $this->event($model, 'created')->save();
    }

    public function updated($model, array $changes = []): ActivityLog
    {
        $logger = $this->event($model, 'updated');
        
        if (!empty($changes)) {
            $logger->changes($changes);
        }
        
        return $logger->save();
    }

    public function deleted($model): ActivityLog
    {
        return $this->event($model, 'deleted')->save();
    }

    public function imported($model, array $details = []): ActivityLog
    {
        $logger = $this->event($model, 'imported');
        
        if (!empty($details)) {
            $logger->with($details);
        }
        
        return $logger->save();
    }

    public function synced($model, string $channel, array $details = []): ActivityLog
    {
        return $this->event($model, 'synced')
            ->with(array_merge(['channel' => $channel], $details))
            ->save();
    }

    public function save(): ActivityLog
    {
        $this->addContext();

        return $this->logAction->createLog(
            event: $this->data['event'],
            data: collect($this->data)->except(['event', 'user_id'])->toArray(),
            userId: $this->data['user_id'] ?? null,
        );
    }

    protected function makeEvent($model, string $action): string
    {
        return match(true) {
            $model instanceof \App\Models\Product => "product.{$action}",
            $model instanceof \App\Models\ProductVariant => "variant.{$action}", 
            $model instanceof \App\Models\User => "user.{$action}",
            default => strtolower(class_basename($model)) . ".{$action}"
        };
    }

    protected function addContext(): void
    {
        $this->data = array_merge($this->data, [
            'ip' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'route' => request()?->route()?->getName(),
        ]);
    }

    public static function search(array $filters = []): Collection
    {
        return ActivityLog::search($filters);
    }

    public static function recent(int $hours = 24): Collection
    {
        return ActivityLog::with('user')
            ->recent($hours)
            ->latest('occurred_at')
            ->get();
    }

    public static function forSubject($model): Collection
    {
        return ActivityLog::forSubject(
            class_basename($model),
            $model->id
        )->get();
    }
}