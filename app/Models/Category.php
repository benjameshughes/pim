<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'parent_id',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // Relationships
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_categories')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // Helper methods
    public function getFullNameAttribute(): string
    {
        $names = [];
        $category = $this;

        while ($category) {
            array_unshift($names, $category->name);
            $category = $category->parent;
        }

        return implode(' > ', $names);
    }

    public function getDepthAttribute(): int
    {
        $depth = 0;
        $category = $this->parent;

        while ($category) {
            $depth++;
            $category = $category->parent;
        }

        return $depth;
    }

    public function isParentOf(Category $category): bool
    {
        return $category->parent_id === $this->id;
    }

    public function isChildOf(Category $category): bool
    {
        return $this->parent_id === $category->id;
    }

    public function hasProducts(): bool
    {
        return $this->products()->exists();
    }

    // Auto-generate slug when name is set
    public function setNameAttribute($value)
    {
        $this->attributes['name'] = $value;

        if (empty($this->attributes['slug'])) {
            $this->attributes['slug'] = $this->generateUniqueSlug($value);
        }
    }

    private function generateUniqueSlug($name): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (static::where('slug', $slug)->where('id', '!=', $this->id ?? 0)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    // Get all descendants (children, grandchildren, etc.)
    public function getAllDescendants(): \Illuminate\Database\Eloquent\Collection
    {
        $descendants = collect();

        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->getAllDescendants());
        }

        return $descendants;
    }

    // Get all ancestors (parent, grandparent, etc.)
    public function getAllAncestors(): \Illuminate\Database\Eloquent\Collection
    {
        $ancestors = collect();
        $category = $this->parent;

        while ($category) {
            $ancestors->push($category);
            $category = $category->parent;
        }

        return $ancestors->reverse();
    }
}
