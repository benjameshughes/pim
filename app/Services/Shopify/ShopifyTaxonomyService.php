<?php

namespace App\Services\Shopify;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 🏷️ SHOPIFY TAXONOMY SERVICE
 *
 * Fetches and caches Shopify's Standard Product Taxonomy
 * Provides intelligent category mapping and suggestions
 */
class ShopifyTaxonomyService
{
    private const TAXONOMY_URL = 'https://shopify.github.io/product-taxonomy/releases/2024-10/taxonomies/en/product_taxonomy.json';

    private const CACHE_KEY = 'shopify_taxonomy_2024_10';

    private const CACHE_TTL = 60 * 60 * 24 * 7; // 1 week

    /**
     * 🌳 Get the complete taxonomy tree
     */
    public function getTaxonomyTree(): Collection
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            try {
                $response = Http::timeout(30)->get(self::TAXONOMY_URL);

                if ($response->successful()) {
                    $data = $response->json();
                    Log::info('Shopify taxonomy fetched successfully', [
                        'categories_count' => count($data['categories'] ?? []),
                        'attributes_count' => count($data['attributes'] ?? []),
                    ]);

                    return collect($data);
                }

                Log::error('Failed to fetch Shopify taxonomy', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return collect();
            } catch (\Exception $e) {
                Log::error('Exception fetching Shopify taxonomy', [
                    'error' => $e->getMessage(),
                ]);

                return collect();
            }
        });
    }

    /**
     * 🔍 Search categories by name or path
     */
    public function searchCategories(string $query): Collection
    {
        $taxonomy = $this->getTaxonomyTree();
        $categories = $taxonomy->get('categories', []);

        return collect($categories)->filter(function ($category) use ($query) {
            $name = strtolower($category['name'] ?? '');
            $fullName = strtolower($category['full_name'] ?? '');
            $searchQuery = strtolower($query);

            return str_contains($name, $searchQuery) || str_contains($fullName, $searchQuery);
        })->take(20);
    }

    /**
     * 🎯 Get category by ID
     */
    public function getCategoryById(string $categoryId): ?array
    {
        $taxonomy = $this->getTaxonomyTree();
        $categories = $taxonomy->get('categories', []);

        return collect($categories)->firstWhere('id', $categoryId);
    }

    /**
     * 🏷️ Get attributes for a category
     */
    public function getCategoryAttributes(string $categoryId): Collection
    {
        $taxonomy = $this->getTaxonomyTree();
        $attributes = $taxonomy->get('attributes', []);

        return collect($attributes)->filter(function ($attribute) use ($categoryId) {
            $categories = $attribute['applicable_categories'] ?? [];

            return in_array($categoryId, $categories);
        });
    }

    /**
     * 🤖 AI-powered category suggestions based on product data
     */
    public function suggestCategories(string $productName, ?string $description = null, array $tags = []): Collection
    {
        $searchTerms = $this->extractSearchTerms($productName, $description, $tags);
        $suggestions = collect();

        foreach ($searchTerms as $term) {
            $matches = $this->searchCategories($term);
            $suggestions = $suggestions->merge($matches);
        }

        // Score and rank suggestions
        return $suggestions->unique('id')->map(function ($category) use ($searchTerms) {
            $category['relevance_score'] = $this->calculateRelevanceScore($category, $searchTerms);

            return $category;
        })->sortByDesc('relevance_score')->take(5);
    }

    /**
     * 🎨 Get categories relevant to window treatments/blinds
     */
    public function getWindowTreatmentCategories(): Collection
    {
        return $this->searchCategories('window')
            ->merge($this->searchCategories('blind'))
            ->merge($this->searchCategories('shade'))
            ->merge($this->searchCategories('curtain'))
            ->unique('id');
    }

    /**
     * 📊 Get taxonomy statistics
     */
    public function getTaxonomyStats(): array
    {
        $taxonomy = $this->getTaxonomyTree();

        if ($taxonomy->isEmpty()) {
            return [
                'categories_count' => 0,
                'attributes_count' => 0,
                'cache_status' => 'empty',
                'last_updated' => null,
            ];
        }

        return [
            'categories_count' => count($taxonomy->get('categories', [])),
            'attributes_count' => count($taxonomy->get('attributes', [])),
            'cache_status' => 'loaded',
            'last_updated' => Cache::get(self::CACHE_KEY.'_timestamp', now()),
        ];
    }

    /**
     * 🔄 Force refresh taxonomy cache
     */
    public function refreshTaxonomy(): bool
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CACHE_KEY.'_timestamp');

        $taxonomy = $this->getTaxonomyTree();

        if ($taxonomy->isNotEmpty()) {
            Cache::put(self::CACHE_KEY.'_timestamp', now(), self::CACHE_TTL);

            return true;
        }

        return false;
    }

    /**
     * 🔍 Extract search terms from product data
     */
    private function extractSearchTerms(string $productName, ?string $description, array $tags): array
    {
        $terms = [];

        // Extract from product name
        $nameWords = str_word_count(strtolower($productName), 1);
        $terms = array_merge($terms, $nameWords);

        // Extract from description
        if ($description) {
            $descWords = str_word_count(strtolower($description), 1);
            $terms = array_merge($terms, array_slice($descWords, 0, 10)); // Limit desc words
        }

        // Add tags
        $terms = array_merge($terms, array_map('strtolower', $tags));

        // Filter common words and keep meaningful terms
        $commonWords = ['the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'a', 'an'];
        $terms = array_filter($terms, fn ($term) => ! in_array($term, $commonWords) && strlen($term) > 2);

        return array_unique($terms);
    }

    /**
     * 📊 Calculate relevance score for category suggestions
     */
    private function calculateRelevanceScore(array $category, array $searchTerms): int
    {
        $score = 0;
        $categoryText = strtolower($category['name'].' '.($category['full_name'] ?? ''));

        foreach ($searchTerms as $term) {
            if (str_contains($categoryText, $term)) {
                // Exact matches in name get higher score
                if (str_contains(strtolower($category['name']), $term)) {
                    $score += 10;
                } else {
                    $score += 5;
                }
            }
        }

        return $score;
    }

    /**
     * 🌐 Get category breadcrumb path
     */
    public function getCategoryPath(string $categoryId): string
    {
        $category = $this->getCategoryById($categoryId);

        return $category['full_name'] ?? $category['name'] ?? 'Unknown Category';
    }

    /**
     * 📋 Get all root categories
     */
    public function getRootCategories(): Collection
    {
        $taxonomy = $this->getTaxonomyTree();
        $categories = $taxonomy->get('categories', []);

        return collect($categories)->filter(function ($category) {
            $fullName = $category['full_name'] ?? '';

            return substr_count($fullName, ' > ') === 0; // No separators = root level
        });
    }

    /**
     * 🎯 Map PIM product type to Shopify category
     */
    public function mapProductTypeToCategory(string $productType): ?array
    {
        $mappings = [
            'blind' => 'gid://shopify/TaxonomyCategory/aa-2-1-7', // Window Treatments > Blinds
            'roller_blind' => 'gid://shopify/TaxonomyCategory/aa-2-1-7-1', // Roller Blinds
            'venetian_blind' => 'gid://shopify/TaxonomyCategory/aa-2-1-7-2', // Venetian Blinds
            'vertical_blind' => 'gid://shopify/TaxonomyCategory/aa-2-1-7-3', // Vertical Blinds
            'roman_blind' => 'gid://shopify/TaxonomyCategory/aa-2-1-7-4', // Roman Blinds
            'curtain' => 'gid://shopify/TaxonomyCategory/aa-2-1-1', // Window Treatments > Curtains
            'shade' => 'gid://shopify/TaxonomyCategory/aa-2-1-8', // Window Treatments > Shades
        ];

        $categoryId = $mappings[strtolower($productType)] ?? null;

        if ($categoryId) {
            return $this->getCategoryById(str_replace('gid://shopify/TaxonomyCategory/', '', $categoryId));
        }

        return null;
    }
}
