<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class ActivityLog extends Model
{
    protected $fillable = [
        'event',
        'user_id',
        'occurred_at',
        'data',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getUserNameAttribute(): ?string
    {
        return $this->user?->name;
    }

    public function getSubjectNameAttribute(): ?string
    {
        return collect($this->data)
            ->get('subject.name') ??
            collect($this->data)->get('subject.title') ??
            "#{$this->getSubjectId()}";
    }

    public function getSubjectId(): mixed
    {
        return $this->data['subject']['id'] ?? null;
    }

    public function getSubjectType(): ?string
    {
        return $this->data['subject']['type'] ?? null;
    }

    public function getContextData(): Collection
    {
        return collect($this->data)->except(['subject']);
    }

    public function getChangesAttribute(): ?array
    {
        return collect($this->data)->get('changes');
    }

    public function getDescriptionAttribute(): ?string
    {
        return collect($this->data)->get('description') ??
            $this->generateDescription();
    }

    public function scopeByEvent($query, string $event)
    {
        return $query->where('event', $event);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForSubject($query, string $type, int $id)
    {
        return $query->whereJsonContains('data->subject->type', $type)
            ->whereJsonContains('data->subject->id', $id);
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('occurred_at', '>=', now()->subHours($hours));
    }

    protected function generateDescription(): string
    {
        $action = str_replace('.', ' ', $this->event);
        $subject = $this->subject_name ?: $this->subject_type;

        return ucwords("{$action} {$subject}");
    }

    public static function search(array $filters = []): Collection
    {
        $query = static::with('user')->latest('occurred_at');

        if (! empty($filters['event'])) {
            $query->byEvent($filters['event']);
        }

        if (! empty($filters['user_id'])) {
            $query->byUser($filters['user_id']);
        }

        if (! empty($filters['hours'])) {
            $query->recent($filters['hours']);
        }

        if (! empty($filters['subject_type']) && ! empty($filters['subject_id'])) {
            $query->forSubject($filters['subject_type'], $filters['subject_id']);
        }

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('event', 'like', "%{$filters['search']}%")
                    ->orWhereJsonContains('data->description', $filters['search'])
                    ->orWhereJsonContains('data->subject->name', $filters['search']);
            });
        }

        if (! empty($filters['date_from'])) {
            $query->where('occurred_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('occurred_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['limit'])) {
            $query->limit($filters['limit']);
        }

        return $query->get();
    }

    public static function getActivityStats(int $hours = 24): array
    {
        $logs = static::recent($hours)->get();

        return [
            'total_activities' => $logs->count(),
            'unique_users' => $logs->pluck('user_id')->filter()->unique()->count(),
            'top_events' => $logs->groupBy('event')
                ->map(fn ($group) => $group->count())
                ->sortDesc()
                ->take(5)
                ->toArray(),
            'activity_by_hour' => $logs->groupBy(function ($log) {
                return $log->occurred_at->format('H');
            })->map(fn ($group) => $group->count())->toArray(),
        ];
    }

    public static function getRecentByUser(int $userId, int $limit = 20): Collection
    {
        return static::with('user')
            ->byUser($userId)
            ->latest('occurred_at')
            ->limit($limit)
            ->get();
    }
}
