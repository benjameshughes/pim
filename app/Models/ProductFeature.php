<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductFeature extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'type',
        'title',
        'content',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /**
     * The product that owns the feature
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scope for features only
     */
    public function scopeFeatures($query)
    {
        return $query->where('type', 'feature');
    }

    /**
     * Scope for details only
     */
    public function scopeDetails($query)
    {
        return $query->where('type', 'detail');
    }

    /**
     * Scope for ordered results
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Get formatted content for display
     */
    public function getFormattedContentAttribute(): string
    {
        if ($this->title) {
            return $this->title . ': ' . $this->content;
        }
        
        return $this->content;
    }
}