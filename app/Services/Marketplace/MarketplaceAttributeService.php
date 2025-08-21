<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceProductAttribute;
use App\Models\MarketplaceTaxonomy;
use App\Models\Product;
use App\Models\SyncAccount;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 🏷️ MARKETPLACE ATTRIBUTE SERVICE
 *
 * Manages assignment and validation of marketplace-specific attributes to products.
 * Provides bulk operations, quality scoring, and marketplace readiness analysis.
 *
 * Integrates with MarketplaceTaxonomyService for cached taxonomy data.
 */
class MarketplaceAttributeService
{
    protected MarketplaceTaxonomyService $taxonomyService;

    public function __construct(?MarketplaceTaxonomyService $taxonomyService = null)
    {
        $this->taxonomyService = $taxonomyService ?? new MarketplaceTaxonomyService;
    }

    /**
     * 🏷️ Assign attribute to a product
     */
    public function assignAttribute(
        Product $product,
        SyncAccount $syncAccount,
        string $attributeKey,
        $value,
        array $options = []
    ): MarketplaceProductAttribute {
        // Get taxonomy definition
        $taxonomy = MarketplaceTaxonomy::where([
            'sync_account_id' => $syncAccount->id,
            'taxonomy_type' => 'attribute',
            'key' => $attributeKey,
            'is_active' => true,
        ])->first();

        if (! $taxonomy) {
            throw new Exception("Attribute '{$attributeKey}' not found for marketplace {$syncAccount->name}");
        }

        // Validate value against taxonomy rules
        $validation = $this->taxonomyService->validateAttributeValue($syncAccount, $attributeKey, $value);

        if (! $validation['valid'] && ! ($options['skip_validation'] ?? false)) {
            throw new Exception('Invalid attribute value: '.implode(', ', $validation['errors']));
        }

        // Prepare attribute data
        $attributeData = [
            'product_id' => $product->id,
            'sync_account_id' => $syncAccount->id,
            'marketplace_taxonomy_id' => $taxonomy->id,
            'attribute_key' => $attributeKey,
            'attribute_name' => $taxonomy->name,
            'attribute_value' => $this->serializeValue($value, $taxonomy->data_type),
            'display_value' => $options['display_value'] ?? $this->formatDisplayValue($value, $taxonomy->data_type),
            'data_type' => $taxonomy->data_type,
            'is_required' => $taxonomy->is_required,
            'value_metadata' => $options['metadata'] ?? null,
            'sync_metadata' => [
                'assigned_via' => $options['assigned_via'] ?? 'manual',
                'source' => $options['source'] ?? 'user',
                'confidence' => $options['confidence'] ?? 100,
            ],
            'assigned_at' => now(),
            'assigned_by' => $options['assigned_by'] ?? auth()->id(),
            'last_validated_at' => now(),
            'is_valid' => $validation['valid'],
        ];

        // Create or update attribute assignment
        $attribute = MarketplaceProductAttribute::updateOrCreate(
            [
                'product_id' => $product->id,
                'sync_account_id' => $syncAccount->id,
                'attribute_key' => $attributeKey,
            ],
            $attributeData
        );

        Log::info('✅ Assigned marketplace attribute', [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'marketplace' => $syncAccount->name,
            'attribute' => $attributeKey,
            'value' => $value,
            'is_valid' => $validation['valid'],
        ]);

        return $attribute;
    }

    /**
     * 🔄 Update attribute value
     */
    public function updateAttribute(
        MarketplaceProductAttribute $attribute,
        $newValue,
        array $options = []
    ): MarketplaceProductAttribute {
        // Validate new value
        $validation = $this->taxonomyService->validateAttributeValue(
            $attribute->syncAccount,
            $attribute->attribute_key,
            $newValue
        );

        if (! $validation['valid'] && ! ($options['skip_validation'] ?? false)) {
            throw new Exception('Invalid attribute value: '.implode(', ', $validation['errors']));
        }

        // Update attribute
        $attribute->update([
            'attribute_value' => $this->serializeValue($newValue, $attribute->data_type),
            'display_value' => $options['display_value'] ?? $this->formatDisplayValue($newValue, $attribute->data_type),
            'value_metadata' => array_merge($attribute->value_metadata ?? [], $options['metadata'] ?? []),
            'sync_metadata' => array_merge($attribute->sync_metadata ?? [], [
                'last_updated_via' => $options['updated_via'] ?? 'manual',
                'last_updated_by' => $options['updated_by'] ?? auth()->id(),
                'updated_at' => now()->toISOString(),
            ]),
            'last_validated_at' => now(),
            'is_valid' => $validation['valid'],
        ]);

        return $attribute->fresh();
    }

    /**
     * ❌ Remove attribute assignment
     */
    public function removeAttribute(MarketplaceProductAttribute $attribute): bool
    {
        Log::info('🗑️ Removing marketplace attribute', [
            'product_id' => $attribute->product_id,
            'marketplace' => $attribute->syncAccount->name,
            'attribute' => $attribute->attribute_key,
        ]);

        return $attribute->delete();
    }

    /**
     * 📋 Get all attributes for a product in a marketplace
     */
    public function getProductAttributes(Product $product, SyncAccount $syncAccount): Collection
    {
        return MarketplaceProductAttribute::getForProductInMarketplace($product, $syncAccount);
    }

    /**
     * ⚠️ Get missing required attributes for a product
     */
    public function getMissingRequiredAttributes(Product $product, SyncAccount $syncAccount): Collection
    {
        return MarketplaceProductAttribute::getMissingRequiredAttributes($product, $syncAccount);
    }

    /**
     * 📊 Get completion percentage for a product in a marketplace
     */
    public function getCompletionPercentage(Product $product, SyncAccount $syncAccount): int
    {
        return MarketplaceProductAttribute::getCompletionPercentage($product, $syncAccount);
    }

    /**
     * 📋 Bulk assign attributes to multiple products
     */
    public function bulkAssignAttributes(
        Collection $products,
        SyncAccount $syncAccount,
        array $attributeAssignments,
        array $options = []
    ): array {
        $results = [
            'success_count' => 0,
            'error_count' => 0,
            'errors' => [],
            'processed_products' => [],
        ];

        DB::beginTransaction();

        try {
            foreach ($products as $product) {
                $productResults = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'attributes_assigned' => 0,
                    'errors' => [],
                ];

                foreach ($attributeAssignments as $attributeKey => $value) {
                    try {
                        $this->assignAttribute($product, $syncAccount, $attributeKey, $value, $options);
                        $productResults['attributes_assigned']++;
                    } catch (Exception $e) {
                        $productResults['errors'][] = [
                            'attribute' => $attributeKey,
                            'error' => $e->getMessage(),
                        ];
                        $results['error_count']++;
                    }
                }

                if (empty($productResults['errors'])) {
                    $results['success_count']++;
                }

                $results['processed_products'][] = $productResults;
            }

            DB::commit();

            Log::info('✅ Completed bulk attribute assignment', [
                'marketplace' => $syncAccount->name,
                'products_processed' => $products->count(),
                'attributes_per_product' => count($attributeAssignments),
                'success_count' => $results['success_count'],
                'error_count' => $results['error_count'],
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $results;
    }

    /**
     * ✅ Bulk validate attributes for multiple products
     */
    public function bulkValidateAttributes(Collection $products, SyncAccount $syncAccount): array
    {
        $results = [
            'total_products' => $products->count(),
            'valid_products' => 0,
            'invalid_products' => 0,
            'validation_details' => [],
        ];

        foreach ($products as $product) {
            $attributes = $this->getProductAttributes($product, $syncAccount);
            $productValidation = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'completion_percentage' => $this->getCompletionPercentage($product, $syncAccount),
                'total_attributes' => $attributes->count(),
                'valid_attributes' => $attributes->where('is_valid', true)->count(),
                'invalid_attributes' => [],
                'missing_required' => [],
            ];

            // Check invalid attributes
            foreach ($attributes->where('is_valid', false) as $attribute) {
                $productValidation['invalid_attributes'][] = [
                    'attribute' => $attribute->attribute_key,
                    'name' => $attribute->attribute_name,
                    'value' => $attribute->attribute_value,
                    'errors' => $this->getAttributeValidationErrors($attribute),
                ];
            }

            // Check missing required attributes
            $missing = $this->getMissingRequiredAttributes($product, $syncAccount);
            foreach ($missing as $taxonomy) {
                $productValidation['missing_required'][] = [
                    'attribute' => $taxonomy->key,
                    'name' => $taxonomy->name,
                    'description' => $taxonomy->description,
                ];
            }

            // Determine if product is valid
            $isProductValid = empty($productValidation['invalid_attributes']) &&
                             empty($productValidation['missing_required']);

            if ($isProductValid) {
                $results['valid_products']++;
            } else {
                $results['invalid_products']++;
            }

            $productValidation['is_valid'] = $isProductValid;
            $results['validation_details'][] = $productValidation;
        }

        return $results;
    }

    /**
     * 🎯 Auto-assign attributes based on product data
     */
    public function autoAssignAttributes(Product $product, SyncAccount $syncAccount, array $options = []): array
    {
        $results = [
            'attributes_assigned' => 0,
            'assignments' => [],
            'skipped' => [],
        ];

        // Get available attributes
        $attributes = $this->taxonomyService->getAttributes($syncAccount);

        foreach ($attributes as $taxonomy) {
            try {
                $value = $this->extractAttributeFromProduct($product, $taxonomy);

                if ($value !== null) {
                    $attribute = $this->assignAttribute(
                        $product,
                        $syncAccount,
                        $taxonomy->key,
                        $value,
                        array_merge($options, [
                            'assigned_via' => 'auto',
                            'source' => 'product_data',
                            'confidence' => $this->calculateConfidence($product, $taxonomy, $value),
                        ])
                    );

                    $results['attributes_assigned']++;
                    $results['assignments'][] = [
                        'attribute' => $taxonomy->key,
                        'name' => $taxonomy->name,
                        'value' => $value,
                        'confidence' => $attribute->sync_metadata['confidence'] ?? 0,
                    ];
                } else {
                    $results['skipped'][] = [
                        'attribute' => $taxonomy->key,
                        'name' => $taxonomy->name,
                        'reason' => 'No suitable value found in product data',
                    ];
                }
            } catch (Exception $e) {
                $results['skipped'][] = [
                    'attribute' => $taxonomy->key,
                    'name' => $taxonomy->name,
                    'reason' => $e->getMessage(),
                ];
            }
        }

        Log::info('🤖 Auto-assigned marketplace attributes', [
            'product_id' => $product->id,
            'marketplace' => $syncAccount->name,
            'attributes_assigned' => $results['attributes_assigned'],
            'total_attempted' => $attributes->count(),
        ]);

        return $results;
    }

    /**
     * 📊 Get marketplace readiness report for a product
     */
    public function getMarketplaceReadinessReport(Product $product, SyncAccount $syncAccount): array
    {
        $attributes = $this->getProductAttributes($product, $syncAccount);
        $missing = $this->getMissingRequiredAttributes($product, $syncAccount);
        $completionPercentage = $this->getCompletionPercentage($product, $syncAccount);

        $readinessScore = $this->calculateReadinessScore($product, $syncAccount, $attributes, $missing);

        return [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
            ],
            'marketplace' => [
                'id' => $syncAccount->id,
                'name' => $syncAccount->name,
                'channel' => $syncAccount->channel,
            ],
            'readiness_score' => $readinessScore,
            'status' => $this->getReadinessStatus($readinessScore),
            'completion_percentage' => $completionPercentage,
            'attributes' => [
                'total_assigned' => $attributes->count(),
                'valid' => $attributes->where('is_valid', true)->count(),
                'invalid' => $attributes->where('is_valid', false)->count(),
                'required_missing' => $missing->count(),
            ],
            'quality_indicators' => $this->getQualityIndicators($attributes),
            'recommendations' => $this->getMarketplaceRecommendations($product, $syncAccount, $attributes, $missing),
            'generated_at' => now()->toISOString(),
        ];
    }

    // ==================== HELPER METHODS ====================

    /**
     * 💾 Serialize value based on data type
     */
    protected function serializeValue($value, string $dataType): string
    {
        return match ($dataType) {
            'list', 'dimension' => is_array($value) ? json_encode($value) : $value,
            'boolean' => is_bool($value) ? ($value ? 'true' : 'false') : $value,
            default => (string) $value,
        };
    }

    /**
     * 🎨 Format display value
     */
    protected function formatDisplayValue($value, string $dataType): string
    {
        return match ($dataType) {
            'boolean' => is_bool($value) ? ($value ? 'Yes' : 'No') : $value,
            'list' => is_array($value) ? implode(', ', $value) : $value,
            'dimension' => is_array($value) ? "{$value['width']}x{$value['height']} {$value['unit']}" : $value,
            default => (string) $value,
        };
    }

    /**
     * 🔍 Extract attribute value from product data
     */
    protected function extractAttributeFromProduct(Product $product, MarketplaceTaxonomy $taxonomy): mixed
    {
        // This is a smart extraction system that maps product fields to marketplace attributes
        return match ($taxonomy->key) {
            'brand' => $product->brand ?? null,
            'color' => $product->color ?? null,
            'material' => $product->material ?? null,
            'size' => $product->size ?? null,
            'weight' => $product->weight ?? null,
            'dimensions' => $product->dimensions ?? null,
            'description' => $product->description ?? null,
            'title', 'name' => $product->name ?? null,
            default => null,
        };
    }

    /**
     * 🎯 Calculate confidence score for auto-assignment
     */
    protected function calculateConfidence(Product $product, MarketplaceTaxonomy $taxonomy, $value): int
    {
        $confidence = 50; // Base confidence

        // Increase confidence for exact field matches
        if (in_array($taxonomy->key, ['brand', 'color', 'material', 'size'])) {
            $confidence += 30;
        }

        // Increase confidence for required attributes
        if ($taxonomy->is_required) {
            $confidence += 20;
        }

        // Decrease confidence for empty or generic values
        if (empty($value) || in_array(strtolower($value), ['unknown', 'n/a', 'none'])) {
            $confidence -= 40;
        }

        return min(100, max(0, $confidence));
    }

    /**
     * 📊 Calculate marketplace readiness score
     */
    protected function calculateReadinessScore(Product $product, SyncAccount $syncAccount, Collection $attributes, Collection $missing): int
    {
        $score = 100;

        // Penalize missing required attributes heavily
        $score -= $missing->count() * 20;

        // Penalize invalid attributes
        $invalidCount = $attributes->where('is_valid', false)->count();
        $score -= $invalidCount * 10;

        // Penalize poor data quality
        $qualityScore = $this->calculateQualityScore($attributes);
        $score = ($score + $qualityScore) / 2;

        return max(0, min(100, $score));
    }

    /**
     * 🏆 Calculate quality score from attributes
     */
    protected function calculateQualityScore(Collection $attributes): int
    {
        if ($attributes->isEmpty()) {
            return 0;
        }

        $totalScore = 0;

        foreach ($attributes as $attribute) {
            $totalScore += $attribute->getCompletenessScore();
        }

        return round($totalScore / $attributes->count());
    }

    /**
     * 🚦 Get readiness status from score
     */
    protected function getReadinessStatus(int $score): string
    {
        return match (true) {
            $score >= 90 => 'ready',
            $score >= 70 => 'nearly_ready',
            $score >= 50 => 'needs_improvement',
            default => 'not_ready',
        };
    }

    /**
     * 🎯 Get quality indicators
     */
    protected function getQualityIndicators(Collection $attributes): array
    {
        return [
            'has_display_values' => $attributes->where('display_value', '!=', null)->count(),
            'recently_validated' => $attributes->filter->isRecentlyValidated()->count(),
            'auto_assigned' => $attributes->where('sync_metadata.assigned_via', 'auto')->count(),
            'high_confidence' => $attributes->filter(function ($attr) {
                return ($attr->sync_metadata['confidence'] ?? 0) >= 80;
            })->count(),
        ];
    }

    /**
     * 💡 Get marketplace recommendations
     */
    protected function getMarketplaceRecommendations(Product $product, SyncAccount $syncAccount, Collection $attributes, Collection $missing): array
    {
        $recommendations = [];

        if ($missing->isNotEmpty()) {
            $recommendations[] = [
                'type' => 'missing_attributes',
                'priority' => 'high',
                'message' => "Complete {$missing->count()} required attributes",
                'action' => 'assign_required_attributes',
                'details' => $missing->pluck('name')->toArray(),
            ];
        }

        $invalidAttributes = $attributes->where('is_valid', false);
        if ($invalidAttributes->isNotEmpty()) {
            $recommendations[] = [
                'type' => 'invalid_attributes',
                'priority' => 'medium',
                'message' => "Fix {$invalidAttributes->count()} invalid attributes",
                'action' => 'validate_attributes',
                'details' => $invalidAttributes->pluck('attribute_name')->toArray(),
            ];
        }

        return $recommendations;
    }

    /**
     * ❌ Get validation errors for an attribute
     */
    protected function getAttributeValidationErrors(MarketplaceProductAttribute $attribute): array
    {
        $validation = $this->taxonomyService->validateAttributeValue(
            $attribute->syncAccount,
            $attribute->attribute_key,
            $attribute->getParsedValue()
        );

        return $validation['errors'] ?? [];
    }
}
