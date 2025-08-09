<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'attributes',
    ];

    protected $casts = [
        'is_leaf' => 'boolean',
        'is_root' => 'boolean',
        'children_ids' => 'array',
        'ancestor_ids' => 'array',
        'attributes' => 'array',
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
     * Get the best matching category for a product (ENHANCED SASSY VERSION)
     */
    public static function getBestMatchForProduct(string $productName): ?self
    {
        $productName = strtolower($productName);

        // PRIORITY 1: Check for specific Window Treatment categories we found!
        $windowTreatmentCategory = static::where('shopify_id', 'gid://shopify/TaxonomyCategory/hg-3-74')->first(); // Home & Garden > Decor > Window Treatments
        
        if ($windowTreatmentCategory) {
            // Any product with blind/shade/curtain/treatment should use this category
            $blindKeywords = ['blind', 'shade', 'curtain', 'window treatment', 'blackout', 'roller', 'venetian', 'roman', 'vertical'];
            
            foreach ($blindKeywords as $keyword) {
                if (str_contains($productName, $keyword)) {
                    return $windowTreatmentCategory; // Perfect match!
                }
            }
        }

        // PRIORITY 2: Define keyword priority (more specific first) for fallback matching
        $keywordMap = [
            'blackout blind' => ['blackout', 'blind', 'window treatment'],
            'roller blind' => ['roller', 'blind', 'window treatment'],  
            'vertical blind' => ['vertical', 'blind', 'window treatment'],
            'venetian blind' => ['venetian', 'blind', 'window treatment'],
            'roman blind' => ['roman', 'blind', 'window treatment'],
            'day night blind' => ['day night', 'blind', 'window treatment'],
            'blind' => ['blind', 'shade', 'window treatment'],
            'shade' => ['shade', 'blind', 'window treatment'],
            'curtain' => ['curtain', 'window treatment'],
        ];

        foreach ($keywordMap as $pattern => $keywords) {
            if (str_contains($productName, $pattern)) {
                // First try to find our specific window treatment categories
                $windowCategories = static::where('full_name', 'LIKE', '%Window Treatment%')->get();
                if ($windowCategories->isNotEmpty()) {
                    // Prefer leaf categories (most specific)
                    $leafCategory = $windowCategories->where('is_leaf', true)->first();
                    if ($leafCategory) {
                        return $leafCategory;
                    }
                    return $windowCategories->first();
                }

                // Fallback to general keyword search
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

        // PRIORITY 3: No blind/window keywords found - use generic fallback
        // Default to Home & Garden for non-window treatment products
        return static::where('shopify_id', 'gid://shopify/TaxonomyCategory/hg')->first();
    }

    /**
     * Get category-specific metafields based on attributes
     */
    public function getCategoryMetafields(): array
    {
        $metafields = [];

        if (! empty($this->attributes)) {
            foreach ($this->attributes as $attribute) {
                $metafields[] = [
                    'namespace' => 'taxonomy',
                    'key' => strtolower(str_replace(' ', '_', $attribute['name'] ?? 'attribute')),
                    'value' => '',
                    'type' => $this->inferMetafieldType($attribute),
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
