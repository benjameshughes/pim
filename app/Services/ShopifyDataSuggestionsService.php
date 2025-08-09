<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ShopifyTaxonomyCategory;
use Illuminate\Support\Collection;

class ShopifyDataSuggestionsService
{
    /**
     * Generate comprehensive Shopify optimization suggestions for a product
     */
    public function generateSuggestions(Product $product): array
    {
        $suggestions = [];

        // Load required relationships
        $product->load(['variants.pricing', 'variants.attributes', 'productImages']);

        // Category optimization suggestions
        $suggestions['category'] = $this->analyzeCategorySuggestions($product);
        
        // SEO optimization suggestions
        $suggestions['seo'] = $this->analyzeSeoSuggestions($product);
        
        // Pricing optimization suggestions
        $suggestions['pricing'] = $this->analyzePricingSuggestions($product);
        
        // Product data completeness suggestions
        $suggestions['data_quality'] = $this->analyzeDataQualitySuggestions($product);
        
        // Variant optimization suggestions
        $suggestions['variants'] = $this->analyzeVariantSuggestions($product);

        // Image optimization suggestions
        $suggestions['images'] = $this->analyzeImageSuggestions($product);

        // Calculate overall optimization score
        $suggestions['optimization_score'] = $this->calculateOptimizationScore($suggestions);

        return $suggestions;
    }

    /**
     * Analyze category optimization suggestions
     */
    private function analyzeCategorySuggestions(Product $product): array
    {
        $suggestions = [];
        $recommendations = [];
        $warnings = [];

        // Get current best match
        $bestMatch = ShopifyTaxonomyCategory::getBestMatchForProduct($product->name);
        
        if ($bestMatch) {
            $suggestions['current_category'] = [
                'id' => $bestMatch->shopify_id,
                'name' => $bestMatch->name,
                'full_name' => $bestMatch->full_name,
                'is_leaf' => $bestMatch->is_leaf,
                'confidence' => $this->calculateCategoryConfidence($product, $bestMatch)
            ];

            if (!$bestMatch->is_leaf) {
                $warnings[] = 'Current category is not a leaf category - consider using a more specific subcategory for better visibility';
            }

            if ($bestMatch->full_name === 'Home & Garden') {
                $warnings[] = 'Product is in generic Home & Garden category - more specific blind categories may improve discoverability';
            }
        } else {
            $warnings[] = 'No specific category match found - product will use default Home & Garden category';
        }

        // Find alternative categories
        $alternatives = $this->findAlternativeCategories($product);
        if ($alternatives->isNotEmpty()) {
            $suggestions['alternative_categories'] = $alternatives->map(function ($category) use ($product) {
                return [
                    'id' => $category->shopify_id,
                    'name' => $category->name,
                    'full_name' => $category->full_name,
                    'confidence' => $this->calculateCategoryConfidence($product, $category),
                    'reason' => $this->explainCategoryMatch($product, $category)
                ];
            })->toArray();

            $recommendations[] = 'Consider alternative categories that might better represent your product';
        }

        return [
            'suggestions' => $suggestions,
            'recommendations' => $recommendations,
            'warnings' => $warnings,
            'status' => empty($warnings) ? 'good' : 'needs_attention'
        ];
    }

    /**
     * Analyze SEO optimization suggestions
     */
    private function analyzeSeoSuggestions(Product $product): array
    {
        $recommendations = [];
        $warnings = [];

        // Title analysis
        $titleLength = strlen($product->name);
        if ($titleLength < 20) {
            $warnings[] = "Product title is quite short ({$titleLength} characters) - consider adding descriptive keywords";
        } elseif ($titleLength > 70) {
            $warnings[] = "Product title is very long ({$titleLength} characters) - may be truncated in search results";
        } else {
            $recommendations[] = 'Product title length is optimal for SEO';
        }

        // Description analysis
        if (empty($product->description)) {
            $warnings[] = 'Missing product description - add detailed description for better SEO and customer understanding';
        } elseif (strlen($product->description) < 100) {
            $warnings[] = 'Product description is quite short - consider expanding with more detail, benefits, and keywords';
        }

        // Keyword suggestions
        $suggestedKeywords = $this->generateSeoKeywords($product);
        
        return [
            'title_analysis' => [
                'length' => $titleLength,
                'status' => ($titleLength >= 20 && $titleLength <= 70) ? 'good' : 'needs_improvement'
            ],
            'description_analysis' => [
                'exists' => !empty($product->description),
                'length' => strlen($product->description ?? ''),
                'status' => (!empty($product->description) && strlen($product->description) >= 100) ? 'good' : 'needs_improvement'
            ],
            'suggested_keywords' => $suggestedKeywords,
            'recommendations' => $recommendations,
            'warnings' => $warnings,
            'status' => empty($warnings) ? 'good' : 'needs_attention'
        ];
    }

    /**
     * Analyze pricing optimization suggestions
     */
    private function analyzePricingSuggestions(Product $product): array
    {
        $recommendations = [];
        $warnings = [];
        $pricingData = [];

        foreach ($product->variants as $variant) {
            $pricing = $variant->pricing()->first();
            if (!$pricing) {
                $warnings[] = "Variant {$variant->sku} has no pricing information";
                continue;
            }

            $pricingData[] = [
                'sku' => $variant->sku,
                'retail_price' => $pricing->retail_price,
                'cost_price' => $pricing->cost_price ?? 0,
                'margin' => $pricing->cost_price ? 
                    round((($pricing->retail_price - $pricing->cost_price) / $pricing->retail_price) * 100, 1) : null
            ];

            if ($pricing->retail_price < 10) {
                $warnings[] = "Variant {$variant->sku} has very low retail price (£{$pricing->retail_price}) - verify pricing is correct";
            }
        }

        if (!empty($pricingData)) {
            $avgPrice = collect($pricingData)->avg('retail_price');
            $minPrice = collect($pricingData)->min('retail_price');
            $maxPrice = collect($pricingData)->max('retail_price');
            
            $recommendations[] = "Price range: £{$minPrice} - £{$maxPrice} (avg: £" . round($avgPrice, 2) . ")";
            
            if ($maxPrice / $minPrice > 3) {
                $recommendations[] = 'Large price variation between variants - consider using Shopify product options for better organization';
            }
        }

        return [
            'pricing_data' => $pricingData,
            'recommendations' => $recommendations,
            'warnings' => $warnings,
            'status' => empty($warnings) ? 'good' : 'needs_attention'
        ];
    }

    /**
     * Analyze data quality suggestions
     */
    private function analyzeDataQualitySuggestions(Product $product): array
    {
        $score = 0;
        $maxScore = 8;
        $recommendations = [];
        $warnings = [];

        // Product name check
        if (!empty($product->name)) {
            $score++;
        } else {
            $warnings[] = 'Missing product name';
        }

        // Description check
        if (!empty($product->description) && strlen($product->description) >= 50) {
            $score++;
        } else {
            $recommendations[] = 'Add detailed product description (at least 50 characters)';
        }

        // Variants check
        if ($product->variants->count() > 0) {
            $score++;
        } else {
            $warnings[] = 'Product has no variants';
        }

        // Pricing check
        $variantsWithPricing = $product->variants->filter(fn($v) => $v->pricing()->exists())->count();
        if ($variantsWithPricing === $product->variants->count() && $product->variants->count() > 0) {
            $score++;
        } else {
            $warnings[] = 'Some variants are missing pricing information';
        }

        // Images check
        if ($product->productImages->count() >= 1) {
            $score++;
        } else {
            $recommendations[] = 'Add at least one product image';
        }

        // Multiple images bonus
        if ($product->productImages->count() >= 3) {
            $score++;
        } else {
            $recommendations[] = 'Add more product images (recommended: 3-5 images)';
        }

        // Attributes check
        $variantsWithAttributes = $product->variants->filter(fn($v) => $v->attributes->count() > 0)->count();
        if ($variantsWithAttributes > 0) {
            $score++;
        } else {
            $recommendations[] = 'Add product attributes (color, size, material) for better filtering';
        }

        // SEO-friendly slug check
        if (!empty($product->slug) && $product->slug !== $product->name) {
            $score++;
        } else {
            $recommendations[] = 'Optimize product slug for SEO';
        }

        $completionPercentage = round(($score / $maxScore) * 100);

        return [
            'completion_score' => $score,
            'max_score' => $maxScore,
            'completion_percentage' => $completionPercentage,
            'recommendations' => $recommendations,
            'warnings' => $warnings,
            'status' => $completionPercentage >= 80 ? 'excellent' : ($completionPercentage >= 60 ? 'good' : 'needs_improvement')
        ];
    }

    /**
     * Analyze variant optimization suggestions
     */
    private function analyzeVariantSuggestions(Product $product): array
    {
        $recommendations = [];
        $warnings = [];

        $variantCount = $product->variants->count();
        
        if ($variantCount === 0) {
            $warnings[] = 'Product has no variants - at least one variant is required for Shopify';
            return ['recommendations' => $recommendations, 'warnings' => $warnings, 'status' => 'critical'];
        }

        if ($variantCount > 100) {
            $warnings[] = 'Product has many variants (100+) - consider splitting into multiple products';
        }

        // Analyze variant attributes
        $attributeUsage = [];
        foreach ($product->variants as $variant) {
            foreach ($variant->attributes as $attribute) {
                $key = $attribute->attribute_key;
                $attributeUsage[$key] = ($attributeUsage[$key] ?? 0) + 1;
            }
        }

        if (empty($attributeUsage)) {
            $recommendations[] = 'Add variant attributes (color, size) for better product organization in Shopify';
        } else {
            $recommendations[] = 'Variant attributes found: ' . implode(', ', array_keys($attributeUsage));
        }

        // Check SKU patterns
        $skus = $product->variants->pluck('sku')->toArray();
        $emptySkus = collect($skus)->filter(fn($sku) => empty($sku))->count();
        
        if ($emptySkus > 0) {
            $warnings[] = "{$emptySkus} variants are missing SKUs";
        }

        return [
            'variant_count' => $variantCount,
            'attribute_usage' => $attributeUsage,
            'recommendations' => $recommendations,
            'warnings' => $warnings,
            'status' => empty($warnings) ? 'good' : 'needs_attention'
        ];
    }

    /**
     * Analyze image optimization suggestions
     */
    private function analyzeImageSuggestions(Product $product): array
    {
        $recommendations = [];
        $warnings = [];

        $imageCount = $product->productImages->count();

        if ($imageCount === 0) {
            $warnings[] = 'No product images - add at least 1 main product image';
        } elseif ($imageCount === 1) {
            $recommendations[] = 'Consider adding more product images (recommended: 3-5 images)';
        } elseif ($imageCount >= 10) {
            $recommendations[] = 'Many images found - ensure they\'re all necessary and high-quality';
        }

        // Check for main image
        $hasMainImage = $product->productImages->where('image_type', 'main')->first();
        if (!$hasMainImage && $imageCount > 0) {
            $recommendations[] = 'Set one image as the main product image';
        }

        return [
            'image_count' => $imageCount,
            'has_main_image' => (bool)$hasMainImage,
            'recommendations' => $recommendations,
            'warnings' => $warnings,
            'status' => $imageCount >= 1 ? 'good' : 'needs_improvement'
        ];
    }

    /**
     * Calculate overall optimization score
     */
    private function calculateOptimizationScore(array $suggestions): array
    {
        $scores = [];
        $totalWeight = 0;
        $weightedScore = 0;

        // Define weights for each category
        $weights = [
            'category' => 2.0,      // Most important for discoverability
            'data_quality' => 2.0,  // Critical for product completeness
            'seo' => 1.5,           // Important for search
            'variants' => 1.5,      // Important for Shopify structure
            'pricing' => 1.0,       // Important but less critical
            'images' => 1.0         // Important for conversions
        ];

        foreach ($suggestions as $category => $data) {
            if ($category === 'optimization_score') continue;
            
            $weight = $weights[$category] ?? 1.0;
            $totalWeight += $weight;
            
            // Convert status to numeric score
            $score = match($data['status'] ?? 'unknown') {
                'excellent' => 100,
                'good' => 80,
                'needs_attention', 'needs_improvement' => 50,
                'critical' => 20,
                default => 60
            };

            $scores[$category] = $score;
            $weightedScore += $score * $weight;
        }

        $overallScore = $totalWeight > 0 ? round($weightedScore / $totalWeight) : 0;

        return [
            'overall_score' => $overallScore,
            'category_scores' => $scores,
            'grade' => $this->getGradeFromScore($overallScore),
            'summary' => $this->getScoreSummary($overallScore)
        ];
    }

    /**
     * Helper methods
     */
    private function calculateCategoryConfidence(Product $product, ShopifyTaxonomyCategory $category): int
    {
        $confidence = 50; // Base confidence
        
        $productName = strtolower($product->name);
        $categoryName = strtolower($category->full_name);
        
        // Boost confidence for exact matches
        if (str_contains($productName, 'blind') && str_contains($categoryName, 'blind')) {
            $confidence += 30;
        }
        
        if (str_contains($productName, 'roller') && str_contains($categoryName, 'roller')) {
            $confidence += 20;
        }
        
        if (str_contains($productName, 'blackout') && str_contains($categoryName, 'blackout')) {
            $confidence += 20;
        }

        // Bonus for leaf categories (most specific)
        if ($category->is_leaf) {
            $confidence += 10;
        }

        return min(100, $confidence);
    }

    private function findAlternativeCategories(Product $product): Collection
    {
        $keywords = ['blind', 'shade', 'window', 'treatment'];
        return ShopifyTaxonomyCategory::findByKeywords($keywords)
            ->reject(function ($category) use ($product) {
                $current = ShopifyTaxonomyCategory::getBestMatchForProduct($product->name);
                return $current && $category->shopify_id === $current->shopify_id;
            })
            ->take(3);
    }

    private function explainCategoryMatch(Product $product, ShopifyTaxonomyCategory $category): string
    {
        $productName = strtolower($product->name);
        $categoryName = strtolower($category->full_name);
        
        if (str_contains($productName, 'roller') && str_contains($categoryName, 'roller')) {
            return 'Product name contains "roller" which matches this roller blind category';
        }
        
        if (str_contains($productName, 'blackout') && str_contains($categoryName, 'blackout')) {
            return 'Product name contains "blackout" which matches this blackout category';
        }
        
        return 'General blind/window treatment category match';
    }

    private function generateSeoKeywords(Product $product): array
    {
        $keywords = [];
        $productName = strtolower($product->name);
        
        // Base blind keywords
        $keywords[] = 'blinds';
        $keywords[] = 'window blinds';
        $keywords[] = 'window treatments';
        
        // Specific type keywords
        if (str_contains($productName, 'roller')) {
            $keywords[] = 'roller blinds';
            $keywords[] = 'roller shades';
        }
        
        if (str_contains($productName, 'blackout')) {
            $keywords[] = 'blackout blinds';
            $keywords[] = 'room darkening blinds';
        }
        
        if (str_contains($productName, 'vertical')) {
            $keywords[] = 'vertical blinds';
        }
        
        // Room/usage keywords
        $keywords[] = 'bedroom blinds';
        $keywords[] = 'living room blinds';
        $keywords[] = 'office blinds';
        
        return array_unique($keywords);
    }

    private function getGradeFromScore(int $score): string
    {
        return match(true) {
            $score >= 90 => 'A+',
            $score >= 80 => 'A',
            $score >= 70 => 'B',
            $score >= 60 => 'C',
            $score >= 50 => 'D',
            default => 'F'
        };
    }

    private function getScoreSummary(int $score): string
    {
        return match(true) {
            $score >= 90 => 'Excellent - Product is well optimized for Shopify',
            $score >= 80 => 'Good - Minor improvements could boost performance',
            $score >= 70 => 'Fair - Several areas need attention',
            $score >= 60 => 'Needs Work - Important optimizations required',
            default => 'Poor - Major improvements needed before syncing'
        };
    }
}