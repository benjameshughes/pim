<?php

namespace App\Services\Marketplace\Shopify;

/**
 * 🏷️ SHOPIFY TAXONOMY HELPER
 *
 * Smart category detection for Shopify products based on title, type, and keywords.
 * Maps common product attributes to Shopify's Standard Product Taxonomy IDs.
 */
class ShopifyTaxonomyHelper
{
    /**
     * Common category mappings based on keywords and product types
     * 
     * Note: These are common Shopify taxonomy IDs - should be updated 
     * based on actual taxonomy data when available
     */
    protected static array $categoryMappings = [
        // Window Treatments & Blinds (most specific first)
        'roller blind' => 'gid://shopify/TaxonomyCategory/ho-1-1-6-1',
        'vertical blind' => 'gid://shopify/TaxonomyCategory/ho-1-1-6-2', 
        'venetian blind' => 'gid://shopify/TaxonomyCategory/ho-1-1-6-3',
        'window blind' => 'gid://shopify/TaxonomyCategory/ho-1-1-6',
        'blinds' => 'gid://shopify/TaxonomyCategory/ho-1-1-6',
        'curtains' => 'gid://shopify/TaxonomyCategory/ho-1-1-5',
        'shutters' => 'gid://shopify/TaxonomyCategory/ho-1-1-7',
        'drapes' => 'gid://shopify/TaxonomyCategory/ho-1-1-5',
        'window treatments' => 'gid://shopify/TaxonomyCategory/ho-1-1',
        'window coverings' => 'gid://shopify/TaxonomyCategory/ho-1-1',
        
        // Home & Garden fallbacks (least specific)
        'home decor' => 'gid://shopify/TaxonomyCategory/ho-1',
        'home furnishings' => 'gid://shopify/TaxonomyCategory/ho-1-2',
        'furniture' => 'gid://shopify/TaxonomyCategory/ho-1-2',
    ];

    /**
     * Keyword patterns for category detection
     */
    protected static array $keywordPatterns = [
        'blinds' => ['blind', 'shade', 'roller', 'venetian', 'vertical', 'blackout'],
        'curtains' => ['curtain', 'drape', 'panel', 'valance', 'tier'],
        'window_treatments' => ['window', 'covering', 'treatment', 'drapery'],
        'home_decor' => ['decor', 'decoration', 'decorative', 'ornament'],
    ];

    /**
     * Detect appropriate Shopify taxonomy category based on product data
     */
    public static function detectCategory(array $productData): ?string
    {
        $title = strtolower($productData['title'] ?? '');
        $productType = strtolower($productData['productType'] ?? '');
        $description = strtolower($productData['descriptionHtml'] ?? '');
        
        $searchText = $title . ' ' . $productType . ' ' . $description;
        
        // Direct category mappings (most specific first)
        foreach (self::$categoryMappings as $keyword => $categoryId) {
            if (str_contains($searchText, $keyword)) {
                return $categoryId;
            }
        }
        
        // Pattern-based detection
        foreach (self::$keywordPatterns as $category => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($searchText, $pattern)) {
                    return self::getCategoryForPattern($category);
                }
            }
        }
        
        // Default fallback
        return null;
    }

    /**
     * Get category ID for detected pattern
     */
    protected static function getCategoryForPattern(string $pattern): ?string
    {
        return match ($pattern) {
            'blinds' => self::$categoryMappings['blinds'],
            'curtains' => self::$categoryMappings['curtains'], 
            'window_treatments' => self::$categoryMappings['window treatments'],
            'home_decor' => self::$categoryMappings['home decor'],
            default => null,
        };
    }

    /**
     * Get human-readable category name from ID
     */
    public static function getCategoryName(string $categoryId): string
    {
        $reverseMappings = array_flip(self::$categoryMappings);
        return $reverseMappings[$categoryId] ?? 'Unknown Category';
    }

    /**
     * Validate if a category ID is properly formatted
     */
    public static function isValidCategoryId(string $categoryId): bool
    {
        return str_starts_with($categoryId, 'gid://shopify/TaxonomyCategory/');
    }

    /**
     * Get all available category mappings
     */
    public static function getAvailableCategories(): array
    {
        return self::$categoryMappings;
    }

    /**
     * Smart category detection specifically for blinds products
     */
    public static function detectBlindsCategory(array $productData): ?string
    {
        $title = strtolower($productData['title'] ?? '');
        
        // Specific blind types
        if (str_contains($title, 'roller')) {
            return self::$categoryMappings['roller blind'];
        }
        
        if (str_contains($title, 'vertical')) {
            return self::$categoryMappings['vertical blind'];
        }
        
        if (str_contains($title, 'venetian')) {
            return self::$categoryMappings['venetian blind'];
        }
        
        // Generic blinds
        if (str_contains($title, 'blind')) {
            return self::$categoryMappings['blinds'];
        }
        
        return null;
    }
}