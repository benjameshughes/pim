<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Traits\HasAttributesTrait;

/**
 * 🔥✨ IMAGE MODEL - SIMPLE R2 STORAGE ✨🔥
 *
 * Clean, focused image model for R2 cloud storage
 * Polymorphic relationships with background processing
 */
class Image extends Model
{
    use HasFactory, HasAttributesTrait;

    protected $fillable = [
        // Core file data
        'filename',
        'original_filename',
        'url',
        'size',
        'width',
        'height',
        'mime_type',
        'is_primary',
        'sort_order',
        // Metadata
        'title',
        'alt_text',
        'description',
        'folder',
        'tags',
    ];

    protected $casts = [
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'is_primary' => 'boolean',
        'sort_order' => 'integer',
        'tags' => 'array',
    ];

    /**
     * Get the UUID from filename (filename is UUID.extension)
     */
    public function getUuidAttribute(): string
    {
        return pathinfo($this->filename, PATHINFO_FILENAME);
    }

    // REMOVED: Polymorphic relationship - using pivot tables instead
    // /**
    //  * 🔗 Polymorphic relationship to any model
    //  */
    // public function imageable(): MorphTo
    // {
    //     return $this->morphTo();
    // }

    /**
     * 🔍 Scope for primary images
     */
    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('images.is_primary', true);
    }

    /**
     * 📊 Scope ordered by sort order
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('images.sort_order')->orderBy('images.created_at');
    }

    /**
     * 📦 PRODUCTS RELATIONSHIP
     *
     * Many-to-many relationship with products
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Product::class, 'image_product');
    }

    /**
     * 💎 VARIANTS RELATIONSHIP
     *
     * Many-to-many relationship with product variants
     */
    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\ProductVariant::class, 'image_variant');
    }

    /**
     * 🎨 COLOR GROUPS RELATIONSHIP
     *
     * Many-to-many relationship with products for color grouping
     */
    public function colorGroups(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Product::class, 'image_color_group')
            ->withPivot('color', 'is_primary', 'sort_order')
            ->withTimestamps()
            ->orderBy('image_color_group.sort_order')
            ->orderBy('images.created_at');
    }

    /**
     * 🏷️ ATTRIBUTES - Image-level flexible attributes
     */
    public function attributes(): HasMany
    {
        return $this->hasMany(ImageAttribute::class);
    }

    /**
     * ✅ VALID ATTRIBUTES
     */
    public function validAttributes(): HasMany
    {
        return $this->attributes()->valid();
    }

    /**
     * Tell HasAttributesTrait which attribute model to use for this entity
     */
    public function getAttributeModelClass(): string
    {
        return ImageAttribute::class;
    }

    /**
     * 🎯 DAM SCOPES
     */

    /**
     * 🆓 Unattached images - not linked to any model
     */
    public function scopeUnattached(Builder $query): Builder
    {
        return $query->whereDoesntHave('products')->whereDoesntHave('variants');
    }

    /**
     * 🔗 Attached images - linked to models
     */
    public function scopeAttached(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereHas('products')->orWhereHas('variants');
        });
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
     * @param  string[]  $tags
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
     * 🎯 DAM HELPER METHODS
     */

    /**
     * 🏷️ Add tag to image
     */
    public function addTag(string $tag): self
    {
        $tags = $this->tags ?? [];
        if (! in_array($tag, $tags)) {
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
        $tags = array_values(array_filter($tags, fn ($t) => $t !== $tag));
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
     * 📊 Check if image is attached to a model
     */
    public function isAttached(): bool
    {
        return $this->products()->exists() || $this->variants()->exists();
    }

    /**
     * 📊 Get display title (with fallback to original filename)
     */
    public function getDisplayTitleAttribute(): string
    {
        return $this->title ?: pathinfo($this->original_filename, PATHINFO_FILENAME);
    }

    /**
     * 📊 Get original filename (with extension)
     */
    public function getOriginalFilenameAttribute(): string
    {
        // Use the stored original_filename if available
        if (!empty($this->attributes['original_filename'])) {
            return $this->attributes['original_filename'];
        }

        // Fallback: If title contains an extension, use it as original filename
        if ($this->title && str_contains($this->title, '.')) {
            return $this->title;
        }

        // Final fallback: use the stored filename or empty string
        return $this->filename ?? '';
    }

    /**
     * 📊 Get formatted file size
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->size;
        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 2).' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2).' KB';
        }

        return $bytes.' bytes';
    }

    /**
     * 🔗 PIVOT HELPER METHODS
     */

    /**
     * 🔗 Attach image to a model (Product or ProductVariant)
     */
    public function attachTo(Model $model, array $pivotData = []): void
    {
        $defaultPivotData = [
            'is_primary' => false,
            'sort_order' => $this->getNextSortOrder($model),
        ];

        $mergedPivotData = array_merge($defaultPivotData, $pivotData);

        if ($model instanceof \App\Models\Product) {
            // Check if already attached to avoid duplicates
            if (! $model->images()->where('image_id', $this->id)->exists()) {
                $model->images()->attach($this->id, $mergedPivotData);
            }
        } elseif ($model instanceof \App\Models\ProductVariant) {
            // Check if already attached to avoid duplicates
            if (! $model->images()->where('image_id', $this->id)->exists()) {
                $model->images()->attach($this->id, $mergedPivotData);
            }
        } else {
            throw new \InvalidArgumentException('Model must be Product or ProductVariant');
        }
    }

    /**
     * 🔗 Detach image from a model (Product or ProductVariant)
     */
    public function detachFrom(Model $model): void
    {
        if ($model instanceof \App\Models\Product) {
            $model->images()->detach($this->id);
        } elseif ($model instanceof \App\Models\ProductVariant) {
            $model->images()->detach($this->id);
        } else {
            throw new \InvalidArgumentException('Model must be Product or ProductVariant');
        }
    }

    /**
     * 📊 Get next sort order for attachment
     */
    protected function getNextSortOrder(Model $model): int
    {
        if ($model instanceof \App\Models\Product) {
            $maxOrder = $model->images()->max('image_product.sort_order');
        } elseif ($model instanceof \App\Models\ProductVariant) {
            $maxOrder = $model->images()->max('image_variant.sort_order');
        } else {
            return 0;
        }

        return ($maxOrder ?? 0) + 1;
    }

    /**
     * 🎯 Set image as primary for a model
     */
    public function setPrimaryFor(Model $model): void
    {
        if ($model instanceof \App\Models\Product) {
            // Remove primary flag from other images
            $model->images()->updateExistingPivot(
                $model->images()->pluck('image_id')->toArray(),
                ['is_primary' => false]
            );
            // Set this image as primary
            $model->images()->updateExistingPivot($this->id, ['is_primary' => true]);
        } elseif ($model instanceof \App\Models\ProductVariant) {
            // Remove primary flag from other images
            $model->images()->updateExistingPivot(
                $model->images()->pluck('image_id')->toArray(),
                ['is_primary' => false]
            );
            // Set this image as primary
            $model->images()->updateExistingPivot($this->id, ['is_primary' => true]);
        } else {
            throw new \InvalidArgumentException('Model must be Product or ProductVariant');
        }
    }

    /**
     * 📊 Check if image is attached to a specific model
     */
    public function isAttachedTo(Model $model): bool
    {
        if ($model instanceof \App\Models\Product) {
            return $model->images()->where('image_id', $this->id)->exists();
        } elseif ($model instanceof \App\Models\ProductVariant) {
            return $model->images()->where('image_id', $this->id)->exists();
        }

        return false;
    }

    /**
     * 📊 Check if image is primary for a specific model
     */
    public function isPrimaryFor(Model $model): bool
    {
        if ($model instanceof \App\Models\Product) {
            return $model->images()->where('image_id', $this->id)->wherePivot('is_primary', true)->exists();
        } elseif ($model instanceof \App\Models\ProductVariant) {
            return $model->images()->where('image_id', $this->id)->wherePivot('is_primary', true)->exists();
        }

        return false;
    }

    /**
     * 🎨 COLOR GROUP HELPER METHODS
     */

    /**
     * 🎨 ATTACH TO COLOR GROUP
     *
     * Attach this image to a product color group
     */
    public function attachToColorGroup(\App\Models\Product $product, string $color, array $pivotData = []): void
    {
        $defaultPivotData = [
            'is_primary' => false,
            'sort_order' => $this->getNextColorGroupSortOrder($product, $color),
        ];

        $mergedPivotData = array_merge($defaultPivotData, $pivotData);

        // Check if already attached to this color group to avoid duplicates
        if (!$product->colorGroupImages()->where('image_id', $this->id)->wherePivot('color', $color)->exists()) {
            $product->colorGroupImages()->attach($this->id, array_merge($mergedPivotData, ['color' => $color]));
        }
    }

    /**
     * 🎨 DETACH FROM COLOR GROUP
     *
     * Detach this image from a product color group
     */
    public function detachFromColorGroup(\App\Models\Product $product, string $color): void
    {
        $product->colorGroupImages()->wherePivot('color', $color)->detach($this->id);
    }

    /**
     * ⭐ SET PRIMARY FOR COLOR
     *
     * Set this image as primary for a specific color group
     */
    public function setPrimaryForColor(\App\Models\Product $product, string $color): void
    {
        // Remove primary flag from other images in this color group
        $product->colorGroupImages()
            ->wherePivot('color', $color)
            ->get()
            ->each(function ($image) use ($product, $color) {
                $product->colorGroupImages()->updateExistingPivot(
                    $image->id,
                    ['is_primary' => false]
                );
            });

        // Set this image as primary for the color group
        $product->colorGroupImages()->updateExistingPivot($this->id, ['is_primary' => true]);
    }

    /**
     * 📊 CHECK IF ATTACHED TO COLOR GROUP
     *
     * Check if image is attached to a specific product color group
     */
    public function isAttachedToColorGroup(\App\Models\Product $product, string $color): bool
    {
        return $product->colorGroupImages()->where('image_id', $this->id)->wherePivot('color', $color)->exists();
    }

    /**
     * ⭐ CHECK IF PRIMARY FOR COLOR
     *
     * Check if image is primary for a specific color group
     */
    public function isPrimaryForColor(\App\Models\Product $product, string $color): bool
    {
        return $product->colorGroupImages()
            ->where('image_id', $this->id)
            ->wherePivot('color', $color)
            ->wherePivot('is_primary', true)
            ->exists();
    }

    /**
     * 📊 GET NEXT COLOR GROUP SORT ORDER
     *
     * Get the next sort order for a color group
     */
    protected function getNextColorGroupSortOrder(\App\Models\Product $product, string $color): int
    {
        $maxOrder = $product->colorGroupImages()
            ->wherePivot('color', $color)
            ->max('image_color_group.sort_order');

        return ($maxOrder ?? 0) + 1;
    }

    /**
     * 🎨 VARIANT HELPERS USING DAM SYSTEM
     */

    /**
     * Check if this image is a variant (has 'variant' tag)
     */
    public function isVariant(): bool
    {
        return in_array('variant', $this->tags ?? []);
    }

    /**
     * Check if this is an original image (not a variant)
     */
    public function isOriginal(): bool
    {
        return ! $this->isVariant();
    }

    /**
     * Get the variant type from tags
     */
    public function getVariantType(): ?string
    {
        $variantTags = ['thumb', 'small', 'medium', 'large', 'extra-large'];

        foreach ($this->tags ?? [] as $tag) {
            if (in_array($tag, $variantTags)) {
                return $tag;
            }
        }

        return null;
    }

    /**
     * Get the original image ID from tags
     */
    public function getOriginalImageId(): ?int
    {
        foreach ($this->tags ?? [] as $tag) {
            if (str_starts_with($tag, 'original-')) {
                return (int) str_replace('original-', '', $tag);
            }
        }

        return null;
    }

    /**
     * Scope for original images only (not variants)
     */
    public function scopeOriginals(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereJsonDoesntContain('tags', 'variant')
                ->orWhereNull('tags');
        });
    }

    /**
     * Scope for variant images only
     */
    public function scopeVariants(Builder $query): Builder
    {
        return $query->whereJsonContains('tags', 'variant');
    }

    /**
     * Scope for images in variants folder
     */
    public function scopeInVariantsFolder(Builder $query): Builder
    {
        return $query->where('folder', 'variants');
    }
}
