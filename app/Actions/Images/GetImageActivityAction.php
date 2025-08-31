<?php

namespace App\Actions\Images;

use App\Actions\Base\BaseAction;
use App\Models\ActivityLog;
use App\Models\Image;

class GetImageActivityAction extends BaseAction
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 📜 GET IMAGE ACTIVITY HISTORY
     *
     * Retrieves all activity logs for an image family (original + variants)
     */
    protected function performAction(...$params): array
    {
        $image = $params[0] ?? null;

        if (! $image instanceof Image) {
            throw new \InvalidArgumentException('First parameter must be an Image instance');
        }

        // Get the original image if this is a variant
        $originalImage = $image->isVariant()
            ? Image::find($image->getOriginalImageId()) ?? $image
            : $image;

        // Get all variants of the original
        $variants = Image::where('folder', 'variants')
            ->whereJsonContains('tags', "original-{$originalImage->id}")
            ->get();

        // Collect all image IDs in the family
        $imageIds = collect([$originalImage->id])
            ->merge($variants->pluck('id'))
            ->unique()
            ->values();

        // Get activity logs for all images in the family
        $activities = ActivityLog::with('user')
            ->where(function ($query) use ($imageIds) {
                foreach ($imageIds as $imageId) {
                    $query->orWhereJsonContains('data->subject->id', $imageId);
                }
            })
            ->where('event', 'like', 'image.%')
            ->orderBy('occurred_at', 'desc')
            ->get();

        // Group activities by type for better organization
        $groupedActivities = $activities->groupBy(function ($activity) {
            return str_replace('image.', '', $activity->event);
        });

        return $this->success('Image activity retrieved successfully', [
            'activities' => $activities,
            'grouped_activities' => $groupedActivities,
            'original_image' => $originalImage,
            'variants' => $variants,
            'total_activities' => $activities->count(),
            'activity_types' => $groupedActivities->keys()->toArray(),
            'date_range' => [
                'earliest' => $activities->last()?->occurred_at,
                'latest' => $activities->first()?->occurred_at,
            ],
        ]);
    }
}
