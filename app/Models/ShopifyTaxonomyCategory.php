<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopifyTaxonomyCategory extends Model
{
    protected $fillable = [
        'shopify_id',
        'name',
        'full_name',
        'level',
        'is_leaf',
        'is_root',
        'parent_id',
        'children_ids',
        'ancestor_ids',
        'attributes'
    ];

    protected $casts = [
        'is_leaf' => 'boolean',
        'is_root' => 'boolean',
        'children_ids' => 'array',
        'ancestor_ids' => 'array',
        'attributes' => 'array'
    ];

    /**
     * Get the parent category
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ShopifyTaxonomyCategory::class, 'parent_id', 'shopify_id');
    }

    /**
     * Get the child categories
     */
    public function children(): HasMany
    {
        return $this->hasMany(ShopifyTaxonomyCategory::class, 'parent_id', 'shopify_id');
    }

    /**
     * Scope for root categories
     */
    public function scopeRoots($query)
    {
        return $query->where('is_root', true);
    }

    /**
     * Scope for leaf categories (no children)
     */
    public function scopeLeaves($query)
    {
        return $query->where('is_leaf', true);
    }

    /**
     * Search categories by full name
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where('full_name', 'LIKE', "%{$term}%")
                    ->orWhere('name', 'LIKE', "%{$term}%");
    }

    /**
     * Find categories by keywords (blinds, window treatments, etc.)
     */
    public static function findByKeywords(array $keywords): \Illuminate\Database\Eloquent\Collection
    {
        $query = static::query();
        
        foreach ($keywords as $keyword) {
            $query->orWhere('full_name', 'LIKE', "%{$keyword}%")
                  ->orWhere('name', 'LIKE', "%{$keyword}%");
        }
        
        return $query->get();
    }

    /**
     * Get the best matching category for a product
     */
    public static function getBestMatchForProduct(string $productName): ?self
    {
        $productName = strtolower($productName);
        
        // Define keyword priority (more specific first)
        $keywordMap = [
            'blackout blind' => ['blackout', 'blind'],
            'roller blind' => ['roller', 'blind'],
            'vertical blind' => ['vertical', 'blind'],
            'venetian blind' => ['venetian', 'blind'],
            'roman blind' => ['roman', 'blind'],
            'day night blind' => ['day night', 'blind'],
            'blind' => ['blind', 'shade', 'window treatment'],
        ];

        foreach ($keywordMap as $pattern => $keywords) {
            if (str_contains($productName, $pattern)) {
                $categories = static::findByKeywords($keywords);
                
                // Prefer leaf categories (most specific)
                $leafCategory = $categories->where('is_leaf', true)->first();
                if ($leafCategory) {
                    return $leafCategory;
                }
                
                // Fall back to any matching category
                return $categories->first();
            }
        }

        // Default fallback - find any window treatment category
        return static::findByKeywords(['blind', 'shade', 'window'])->first();
    }

    /**
     * Get category-specific metafields based on attributes
     */
    public function getCategoryMetafields(): array
    {
        $metafields = [];
        
        if (!empty($this->attributes)) {
            foreach ($this->attributes as $attribute) {
                $metafields[] = [
                    'namespace' => 'taxonomy',
                    'key' => strtolower(str_replace(' ', '_', $attribute['name'] ?? 'attribute')),
                    'value' => '',
                    'type' => $this->inferMetafieldType($attribute)
                ];
            }
        }

        return $metafields;
    }

    /**
     * Infer metafield type from attribute structure
     */
    private function inferMetafieldType(array $attribute): string
    {
        if (isset($attribute['values']) && is_array($attribute['values'])) {
            return count($attribute['values']) > 5 ? 'multi_line_text_field' : 'single_line_text_field';
        }
        
        return 'single_line_text_field';
    }
}
