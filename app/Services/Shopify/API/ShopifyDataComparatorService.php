<?php

namespace App\Services\Shopify\API;

use App\Models\Product;
use App\Models\ProductVariant;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * 🕵️ SHOPIFY DATA COMPARATOR SERVICE 🕵️
 * 
 * Detects data drift between our app and Shopify like a FORENSIC DETECTIVE!
 * Compares every detail and spots even the tiniest discrepancies.
 * 
 * *adjusts detective hat with sass* 💅
 */
class ShopifyDataComparatorService
{
    /**
     * Compare local product data with Shopify data and detect ALL the differences
     */
    public function compareProductData(Product $product, array $shopifyData): array
    {
        Log::info("🔍 Comparing product data for: {$product->name}");

        $differences = [];
        $driftScore = 0;

        // Compare basic product information
        $basicDifferences = $this->compareBasicProductInfo($product, $shopifyData);
        if (!empty($basicDifferences)) {
            $differences['basic_info'] = $basicDifferences;
            $driftScore += count($basicDifferences);
        }

        // Compare pricing information
        $pricingDifferences = $this->comparePricing($product, $shopifyData);
        if (!empty($pricingDifferences)) {
            $differences['pricing'] = $pricingDifferences;
            $driftScore += count($pricingDifferences) * 2; // Pricing is more important
        }

        // Compare variant information
        $variantDifferences = $this->compareVariants($product, $shopifyData);
        if (!empty($variantDifferences)) {
            $differences['variants'] = $variantDifferences;
            $driftScore += count($variantDifferences);
        }

        // Compare inventory levels
        $inventoryDifferences = $this->compareInventory($product, $shopifyData);
        if (!empty($inventoryDifferences)) {
            $differences['inventory'] = $inventoryDifferences;
            $driftScore += count($inventoryDifferences);
        }

        // Compare status and availability
        $statusDifferences = $this->compareStatus($product, $shopifyData);
        if (!empty($statusDifferences)) {
            $differences['status'] = $statusDifferences;
            $driftScore += count($statusDifferences);
        }

        // Compare images (if applicable)
        $imageDifferences = $this->compareImages($product, $shopifyData);
        if (!empty($imageDifferences)) {
            $differences['images'] = $imageDifferences;
            $driftScore += count($imageDifferences) * 0.5; // Images are less critical
        }

        return [
            'needs_sync' => !empty($differences),
            'drift_score' => $driftScore,
            'drift_severity' => $this->calculateDriftSeverity($driftScore),
            'differences' => $differences,
            'comparison_timestamp' => now()->toISOString(),
            'recommendation' => $this->generateSyncRecommendation($driftScore, $differences)
        ];
    }

    /**
     * Compare multiple products in bulk for efficient drift detection
     */
    public function bulkCompareProducts(Collection $products, array $shopifyDataCollection): array
    {
        Log::info("📊 Bulk comparing " . $products->count() . " products for data drift");

        $results = [];
        $summary = [
            'total_compared' => 0,
            'products_with_drift' => 0,
            'total_drift_score' => 0,
            'high_priority_drifts' => 0
        ];

        foreach ($products as $product) {
            $shopifyData = $this->findShopifyDataForProduct($product, $shopifyDataCollection);
            
            if ($shopifyData) {
                $comparison = $this->compareProductData($product, $shopifyData);
                
                $results[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'comparison' => $comparison
                ];

                // Update summary
                $summary['total_compared']++;
                if ($comparison['needs_sync']) {
                    $summary['products_with_drift']++;
                }
                $summary['total_drift_score'] += $comparison['drift_score'];
                if ($comparison['drift_severity'] === 'high') {
                    $summary['high_priority_drifts']++;
                }
            }
        }

        $summary['average_drift_score'] = $summary['total_compared'] > 0 
            ? round($summary['total_drift_score'] / $summary['total_compared'], 2)
            : 0;

        return [
            'summary' => $summary,
            'products' => $results,
            'drift_alerts' => $this->generateDriftAlerts($results)
        ];
    }

    /**
     * Generate a detailed drift report with actionable insights
     */
    public function generateDriftReport(Product $product, array $shopifyData): array
    {
        $comparison = $this->compareProductData($product, $shopifyData);
        
        return [
            'product_info' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->variants->first()?->sku,
                'shopify_id' => $this->extractShopifyProductId($shopifyData)
            ],
            'comparison_details' => $comparison,
            'sync_priority' => $this->calculateSyncPriority($comparison),
            'recommended_actions' => $this->getRecommendedActions($comparison),
            'risk_assessment' => $this->assessSyncRisk($comparison),
            'time_sensitivity' => $this->calculateTimeSensitivity($comparison)
        ];
    }

    // ===== PRIVATE COMPARISON METHODS ===== //

    private function compareBasicProductInfo(Product $product, array $shopifyData): array
    {
        $differences = [];

        // Compare title/name
        $localTitle = $product->name;
        $shopifyTitle = $shopifyData['title'] ?? '';
        if ($localTitle !== $shopifyTitle) {
            $differences['title'] = [
                'local' => $localTitle,
                'shopify' => $shopifyTitle,
                'severity' => 'medium'
            ];
        }

        // Compare description
        $localDescription = $product->description ?? '';
        $shopifyDescription = strip_tags($shopifyData['bodyHtml'] ?? '');
        if ($localDescription !== $shopifyDescription) {
            $differences['description'] = [
                'local' => $localDescription,
                'shopify' => $shopifyDescription,
                'severity' => 'low'
            ];
        }

        // Compare vendor/brand
        $shopifyVendor = $shopifyData['vendor'] ?? '';
        if (!empty($shopifyVendor) && $shopifyVendor !== 'BLINDS_OUTLET') {
            $differences['vendor'] = [
                'local' => 'BLINDS_OUTLET',
                'shopify' => $shopifyVendor,
                'severity' => 'low'
            ];
        }

        return $differences;
    }

    private function comparePricing(Product $product, array $shopifyData): array
    {
        $differences = [];
        $shopifyVariants = $shopifyData['variants'] ?? [];

        foreach ($product->variants as $localVariant) {
            $matchingShopifyVariant = $this->findMatchingShopifyVariant($localVariant, $shopifyVariants);
            
            if ($matchingShopifyVariant) {
                $priceDiff = $this->compareVariantPricing($localVariant, $matchingShopifyVariant);
                if (!empty($priceDiff)) {
                    $differences[$localVariant->sku] = $priceDiff;
                }
            }
        }

        return $differences;
    }

    private function compareVariantPricing(ProductVariant $localVariant, array $shopifyVariant): array
    {
        $differences = [];
        $localPricing = $localVariant->pricing()->first();
        
        if (!$localPricing) {
            return [];
        }

        $localPrice = (float) $localPricing->retail_price;
        $shopifyPrice = (float) ($shopifyVariant['price'] ?? 0);

        if (abs($localPrice - $shopifyPrice) > 0.01) { // Account for floating point precision
            $differences['price'] = [
                'local' => $localPrice,
                'shopify' => $shopifyPrice,
                'difference' => round($localPrice - $shopifyPrice, 2),
                'severity' => 'high'
            ];
        }

        // Compare compare at price if available
        if (isset($shopifyVariant['compareAtPrice']) && $shopifyVariant['compareAtPrice']) {
            $shopifyComparePrice = (float) $shopifyVariant['compareAtPrice'];
            // We don't have compare_at_price in our local model, so this is informational
            $differences['compare_at_price'] = [
                'local' => null,
                'shopify' => $shopifyComparePrice,
                'severity' => 'low'
            ];
        }

        return $differences;
    }

    private function compareVariants(Product $product, array $shopifyData): array
    {
        $differences = [];
        $shopifyVariants = $shopifyData['variants'] ?? [];

        // Compare variant counts
        $localCount = $product->variants->count();
        $shopifyCount = count($shopifyVariants);
        
        if ($localCount !== $shopifyCount) {
            $differences['variant_count'] = [
                'local' => $localCount,
                'shopify' => $shopifyCount,
                'severity' => 'high'
            ];
        }

        // Compare individual variants
        foreach ($product->variants as $localVariant) {
            $matchingShopifyVariant = $this->findMatchingShopifyVariant($localVariant, $shopifyVariants);
            
            if (!$matchingShopifyVariant) {
                $differences['missing_variants'][] = [
                    'sku' => $localVariant->sku,
                    'local_id' => $localVariant->id,
                    'severity' => 'high'
                ];
            } else {
                $variantDiff = $this->compareVariantDetails($localVariant, $matchingShopifyVariant);
                if (!empty($variantDiff)) {
                    $differences['variant_details'][$localVariant->sku] = $variantDiff;
                }
            }
        }

        return $differences;
    }

    private function compareVariantDetails(ProductVariant $localVariant, array $shopifyVariant): array
    {
        $differences = [];

        // Compare SKU
        if ($localVariant->sku !== ($shopifyVariant['sku'] ?? '')) {
            $differences['sku'] = [
                'local' => $localVariant->sku,
                'shopify' => $shopifyVariant['sku'] ?? '',
                'severity' => 'high'
            ];
        }

        // Compare barcode
        $localBarcode = $localVariant->barcodes()->where('is_primary', true)->first()?->barcode;
        $shopifyBarcode = $shopifyVariant['barcode'] ?? '';
        
        if ($localBarcode && $localBarcode !== $shopifyBarcode) {
            $differences['barcode'] = [
                'local' => $localBarcode,
                'shopify' => $shopifyBarcode,
                'severity' => 'medium'
            ];
        }

        // Compare weight (if available)
        if (isset($shopifyVariant['weight']) && $shopifyVariant['weight']) {
            // We don't store weight locally, so this is informational
            $differences['weight'] = [
                'local' => null,
                'shopify' => $shopifyVariant['weight'],
                'severity' => 'low'
            ];
        }

        return $differences;
    }

    private function compareInventory(Product $product, array $shopifyData): array
    {
        $differences = [];
        $shopifyVariants = $shopifyData['variants'] ?? [];

        foreach ($product->variants as $localVariant) {
            $matchingShopifyVariant = $this->findMatchingShopifyVariant($localVariant, $shopifyVariants);
            
            if ($matchingShopifyVariant) {
                $localStock = $localVariant->stock_level ?? 0;
                $shopifyStock = $matchingShopifyVariant['inventoryQuantity'] ?? 0;
                
                if ($localStock !== $shopifyStock) {
                    $differences[$localVariant->sku] = [
                        'local' => $localStock,
                        'shopify' => $shopifyStock,
                        'difference' => $localStock - $shopifyStock,
                        'severity' => abs($localStock - $shopifyStock) > 10 ? 'high' : 'medium'
                    ];
                }
            }
        }

        return $differences;
    }

    private function compareStatus(Product $product, array $shopifyData): array
    {
        $differences = [];

        $localStatus = $product->status;
        $shopifyStatus = strtolower($shopifyData['status'] ?? '');

        // Map our statuses to Shopify's
        $statusMapping = [
            'active' => 'active',
            'inactive' => 'draft',
            'discontinued' => 'archived'
        ];

        $expectedShopifyStatus = $statusMapping[$localStatus] ?? 'draft';

        if ($expectedShopifyStatus !== $shopifyStatus) {
            $differences['status'] = [
                'local' => $localStatus,
                'shopify' => $shopifyStatus,
                'expected_shopify' => $expectedShopifyStatus,
                'severity' => 'medium'
            ];
        }

        return $differences;
    }

    private function compareImages(Product $product, array $shopifyData): array
    {
        $differences = [];

        $localImageCount = $product->productImages->count();
        $shopifyImageCount = count($shopifyData['images'] ?? []);

        if ($localImageCount !== $shopifyImageCount) {
            $differences['image_count'] = [
                'local' => $localImageCount,
                'shopify' => $shopifyImageCount,
                'severity' => 'low'
            ];
        }

        return $differences;
    }

    // ===== HELPER METHODS ===== //

    private function findMatchingShopifyVariant(ProductVariant $localVariant, array $shopifyVariants): ?array
    {
        foreach ($shopifyVariants as $shopifyVariant) {
            if (($shopifyVariant['sku'] ?? '') === $localVariant->sku) {
                return $shopifyVariant;
            }
        }
        
        return null;
    }

    private function findShopifyDataForProduct(Product $product, array $shopifyDataCollection): ?array
    {
        // This would match based on sync records or other identifiers
        // For now, returning null as placeholder
        return null;
    }

    private function calculateDriftSeverity(float $driftScore): string
    {
        if ($driftScore >= 8) {
            return 'critical';
        } elseif ($driftScore >= 5) {
            return 'high';
        } elseif ($driftScore >= 2) {
            return 'medium';
        } elseif ($driftScore > 0) {
            return 'low';
        }
        
        return 'none';
    }

    private function generateSyncRecommendation(float $driftScore, array $differences): string
    {
        if ($driftScore === 0) {
            return 'No sync required - data is perfectly aligned';
        }
        
        if ($driftScore >= 8) {
            return 'Urgent sync required - critical data differences detected';
        } elseif ($driftScore >= 5) {
            return 'High priority sync recommended - significant differences found';
        } elseif ($driftScore >= 2) {
            return 'Sync recommended - moderate differences detected';
        }
        
        return 'Low priority sync - minor differences detected';
    }

    private function generateDriftAlerts(array $results): array
    {
        $alerts = [];
        
        foreach ($results as $result) {
            $comparison = $result['comparison'];
            
            if ($comparison['drift_severity'] === 'critical') {
                $alerts[] = [
                    'type' => 'critical',
                    'product_name' => $result['product_name'],
                    'message' => 'Critical data drift detected - immediate sync required',
                    'drift_score' => $comparison['drift_score']
                ];
            } elseif ($comparison['drift_severity'] === 'high') {
                $alerts[] = [
                    'type' => 'warning',
                    'product_name' => $result['product_name'],
                    'message' => 'Significant data drift detected',
                    'drift_score' => $comparison['drift_score']
                ];
            }
        }
        
        return $alerts;
    }

    private function extractShopifyProductId(array $shopifyData): ?string
    {
        return $shopifyData['id'] ?? null;
    }

    private function calculateSyncPriority(array $comparison): string
    {
        return match($comparison['drift_severity']) {
            'critical' => 'urgent',
            'high' => 'high',
            'medium' => 'normal',
            'low' => 'low',
            default => 'none'
        };
    }

    private function getRecommendedActions(array $comparison): array
    {
        $actions = [];
        
        if (!empty($comparison['differences']['pricing'])) {
            $actions[] = 'Update pricing information in Shopify';
        }
        
        if (!empty($comparison['differences']['inventory'])) {
            $actions[] = 'Sync inventory levels';
        }
        
        if (!empty($comparison['differences']['variants'])) {
            $actions[] = 'Review and sync variant information';
        }
        
        if (!empty($comparison['differences']['basic_info'])) {
            $actions[] = 'Update product details (title, description)';
        }
        
        return $actions;
    }

    private function assessSyncRisk(array $comparison): string
    {
        $driftScore = $comparison['drift_score'];
        
        if ($driftScore >= 8) {
            return 'high'; // Risk of customer confusion, lost sales
        } elseif ($driftScore >= 5) {
            return 'medium'; // Risk of inconsistent data
        } elseif ($driftScore >= 2) {
            return 'low'; // Minor inconsistencies
        }
        
        return 'minimal';
    }

    private function calculateTimeSensitivity(array $comparison): string
    {
        // Check for pricing differences (time-sensitive)
        if (!empty($comparison['differences']['pricing'])) {
            return 'immediate';
        }
        
        // Check for inventory differences (time-sensitive)
        if (!empty($comparison['differences']['inventory'])) {
            return 'within_hours';
        }
        
        // Other differences can wait
        if (!empty($comparison['differences'])) {
            return 'within_days';
        }
        
        return 'no_urgency';
    }
}