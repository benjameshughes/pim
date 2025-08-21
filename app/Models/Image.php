<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * 🔥✨ IMAGE MODEL - SIMPLE R2 STORAGE ✨🔥
 *
 * Clean, focused image model for R2 cloud storage
 * Polymorphic relationships with background processing
 */
class Image extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename',
        'path',
        'url',
        'size',
        'mime_type',
        'is_primary',
        'sort_order',
        // DAM metadata
        'title',
        'alt_text',
        'description',
        'folder',
        'tags',
        'created_by_user_id',
    ];

    protected $casts = [
        'size' => 'integer',
        'is_primary' => 'boolean',
        'sort_order' => 'integer',
        'tags' => 'array',
        'created_by_user_id' => 'integer',
    ];

    /**
     * 🔗 Polymorphic relationship to any model
     */
    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * 🔍 Scope for primary images
     */
    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    /**
     * 📊 Scope ordered by sort order
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('created_at');
    }

    /**
     * 👤 USER RELATIONSHIP
     * 
     * Track who uploaded this image
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_user_id');
    }

    /**
     * 🎯 DAM SCOPES
     */
    
    /**
     * 🆓 Unattached images - not linked to any model
     */
    public function scopeUnattached(Builder $query): Builder
    {
        return $query->whereNull('imageable_type')->whereNull('imageable_id');
    }

    /**
     * 🔗 Attached images - linked to models
     */
    public function scopeAttached(Builder $query): Builder
    {
        return $query->whereNotNull('imageable_type')->whereNotNull('imageable_id');
    }

    /**
     * 📁 Images in specific folder
     */
    public function scopeInFolder(Builder $query, string $folder): Builder
    {
        return $query->where('folder', $folder);
    }

    /**
     * 🏷️ Images with specific tag
     */
    public function scopeWithTag(Builder $query, string $tag): Builder
    {
        return $query->whereJsonContains('tags', $tag);
    }

    /**
     * 🏷️ Images with any of the specified tags
     */
    /**
     * @param string[] $tags
     */
    public function scopeWithAnyTag(Builder $query, array $tags): Builder
    {
        return $query->where(function ($q) use ($tags) {
            foreach ($tags as $tag) {
                $q->orWhereJsonContains('tags', $tag);
            }
        });
    }

    /**
     * 🔍 Search images by title, filename, or description
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('filename', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%")
              ->orWhere('alt_text', 'like', "%{$search}%");
        });
    }

    /**
     * 👤 Images created by specific user
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('created_by_user_id', $userId);
    }

    /**
     * 🎯 DAM HELPER METHODS
     */

    /**
     * 🏷️ Add tag to image
     */
    public function addTag(string $tag): self
    {
        $tags = $this->tags ?? [];
        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            $this->update(['tags' => $tags]);
        }
        return $this;
    }

    /**
     * 🏷️ Remove tag from image
     */
    public function removeTag(string $tag): self
    {
        $tags = $this->tags ?? [];
        $tags = array_values(array_filter($tags, fn($t) => $t !== $tag));
        $this->update(['tags' => $tags]);
        return $this;
    }

    /**
     * 🏷️ Check if image has tag
     */
    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags ?? []);
    }

    /**
     * 📁 Move image to folder
     */
    public function moveToFolder(string $folder): self
    {
        $this->update(['folder' => $folder]);
        return $this;
    }

    /**
     * 🔗 Attach to model (link image to product/variant)
     */
    public function attachTo(Model $model): self
    {
        $this->update([
            'imageable_type' => get_class($model),
            'imageable_id' => $model->id,
        ]);
        return $this;
    }

    /**
     * 🔓 Detach from model (unlink image)
     */
    public function detach(): self
    {
        $this->update([
            'imageable_type' => null,
            'imageable_id' => null,
            'is_primary' => false,
        ]);
        return $this;
    }

    /**
     * 📊 Check if image is attached to a model
     */
    public function isAttached(): bool
    {
        return $this->imageable_type !== null && $this->imageable_id !== null;
    }

    /**
     * 📊 Get display title (with fallback to filename)
     */
    public function getDisplayTitleAttribute(): string
    {
        return $this->title ?: pathinfo($this->filename, PATHINFO_FILENAME);
    }

    /**
     * 📊 Get formatted file size
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->size;
        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }
}
