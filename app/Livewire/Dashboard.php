<?php

namespace App\Livewire;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\BarcodePool;
use App\Models\Pricing;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Dashboard extends Component
{
    public $refreshInterval = 30000; // 30 seconds
    
    public function getPimMetricsProperty()
    {
        return [
            'catalog_health' => [
                'completeness_score' => $this->getCatalogCompletenessScore(),
                'products_with_images' => $this->getProductsWithImages(),
                'variants_with_complete_data' => $this->getVariantsWithCompleteData(),
                'data_quality_trend' => $this->getDataQualityTrend(),
            ],
            'content_efficiency' => [
                'time_to_market' => $this->getAverageTimeToMarket(),
                'content_reuse_rate' => $this->getContentReuseRate(),
                'automation_rate' => $this->getAutomationRate(),
                'weekly_throughput' => $this->getWeeklyThroughput(),
            ],
            'operational_kpis' => [
                'products_pending_approval' => $this->getProductsPendingApproval(),
                'missing_barcodes' => $this->getMissingBarcodes(),
                'pricing_gaps' => $this->getPricingGaps(),
                'image_coverage' => $this->getImageCoverage(),
            ],
            'channel_readiness' => [
                'ready_for_export' => $this->getReadyForExport(),
                'channel_sync_status' => $this->getChannelSyncStatus(),
                'localization_progress' => $this->getLocalizationProgress(),
                'compliance_score' => $this->getComplianceScore(),
            ]
        ];
    }

    public function getPimWorkflowProperty()
    {
        return [
            'recent_imports' => $this->getRecentImports(),
            'data_quality_alerts' => $this->getDataQualityAlerts(),
            'pending_enrichment' => $this->getPendingEnrichment(),
            'workflow_bottlenecks' => $this->getWorkflowBottlenecks(),
        ];
    }

    public function getPimChartsProperty()
    {
        return [
            'catalog_completeness_trend' => $this->getCatalogCompletenessTrend(),
            'content_velocity' => $this->getContentVelocity(),
            'channel_distribution' => $this->getChannelDistribution(),
            'data_quality_score' => $this->getDataQualityScoreHistory(),
        ];
    }

    // PIM Catalog Health Metrics
    private function getCatalogCompletenessScore(): float
    {
        $totalVariants = ProductVariant::count();
        if ($totalVariants === 0) return 100.0;
        
        $completeVariants = ProductVariant::whereNotNull('color')
            ->whereNotNull('size')
            ->whereHas('barcodes')
            ->whereHas('pricing')
            ->count();
            
        return round(($completeVariants / $totalVariants) * 100, 1);
    }

    private function getProductsWithImages(): int
    {
        return Product::whereNotNull('images')
            ->where('images', '!=', '[]')
            ->count();
    }

    private function getVariantsWithCompleteData(): int
    {
        return ProductVariant::whereNotNull('color')
            ->whereNotNull('size')
            ->whereNotNull('stock_level')
            ->whereHas('barcodes')
            ->whereHas('pricing')
            ->count();
    }

    private function getDataQualityTrend(): string
    {
        $thisWeek = $this->getCatalogCompletenessScore();
        $lastWeek = 85.0; // Mock historical data
        return $thisWeek > $lastWeek ? 'improving' : ($thisWeek < $lastWeek ? 'declining' : 'stable');
    }

    // PIM Content Efficiency Metrics
    private function getAverageTimeToMarket(): float
    {
        $recentProducts = Product::where('created_at', '>=', now()->subMonth())
            ->where('status', 'active')
            ->get();
            
        if ($recentProducts->isEmpty()) return 0.0;
        
        $avgDays = $recentProducts->avg(function ($product) {
            return $product->created_at->diffInDays($product->updated_at ?? $product->created_at);
        });
        
        return round($avgDays ?? 3.5, 1);
    }

    private function getContentReuseRate(): float
    {
        $totalProducts = Product::count();
        $productsWithVariants = Product::has('variants', '>', 1)->count();
        
        return $totalProducts > 0 ? round(($productsWithVariants / $totalProducts) * 100, 1) : 0.0;
    }

    private function getAutomationRate(): float
    {
        $totalVariants = ProductVariant::count();
        $autoAssignedBarcodes = ProductVariant::whereHas('barcodes')->count();
        
        return $totalVariants > 0 ? round(($autoAssignedBarcodes / $totalVariants) * 100, 1) : 0.0;
    }

    private function getWeeklyThroughput(): int
    {
        return ProductVariant::where('created_at', '>=', now()->subWeek())->count();
    }

    // PIM Operational KPIs
    private function getProductsPendingApproval(): int
    {
        return Product::where('status', 'draft')->count();
    }

    private function getMissingBarcodes(): int
    {
        return ProductVariant::doesntHave('barcodes')->count();
    }

    private function getPricingGaps(): int
    {
        return ProductVariant::doesntHave('pricing')->count();
    }

    private function getImageCoverage(): float
    {
        $totalProducts = Product::count();
        if ($totalProducts === 0) return 100.0;
        
        $productsWithImages = $this->getProductsWithImages();
        return round(($productsWithImages / $totalProducts) * 100, 1);
    }

    // PIM Channel Readiness Metrics
    private function getReadyForExport(): int
    {
        return ProductVariant::whereNotNull('color')
            ->whereNotNull('size')
            ->whereHas('barcodes')
            ->whereHas('pricing')
            ->whereHas('product', function($query) {
                $query->whereNotNull('images')->where('images', '!=', '[]');
            })
            ->count();
    }

    private function getChannelSyncStatus(): string
    {
        $readyProducts = $this->getReadyForExport();
        $totalProducts = ProductVariant::count();
        
        if ($totalProducts === 0) return 'synced';
        
        $percentage = ($readyProducts / $totalProducts) * 100;
        
        if ($percentage >= 95) return 'synced';
        if ($percentage >= 80) return 'partial';
        return 'pending';
    }

    private function getLocalizationProgress(): float
    {
        // Mock localization data - could be extended for multi-language support
        return 78.5;
    }

    private function getComplianceScore(): float
    {
        $variantsWithBarcodes = ProductVariant::whereHas('barcodes')->count();
        $variantsWithPricing = ProductVariant::whereHas('pricing')->count();
        $totalVariants = ProductVariant::count();
        
        if ($totalVariants === 0) return 100.0;
        
        $barcodeCompliance = ($variantsWithBarcodes / $totalVariants) * 50;
        $pricingCompliance = ($variantsWithPricing / $totalVariants) * 50;
        
        return round($barcodeCompliance + $pricingCompliance, 1);
    }

    // PIM Workflow Methods
    private function getRecentImports(): array
    {
        // Mock recent import data - could be enhanced with actual import tracking
        return [
            ['name' => 'Electronics Catalog', 'status' => 'completed', 'items' => 145, 'time' => '2 hours ago'],
            ['name' => 'Fashion Collection', 'status' => 'processing', 'items' => 89, 'time' => '1 day ago'],
            ['name' => 'Home & Garden', 'status' => 'completed', 'items' => 234, 'time' => '3 days ago'],
        ];
    }

    private function getDataQualityAlerts(): array
    {
        $alerts = [];
        
        $missingBarcodes = $this->getMissingBarcodes();
        if ($missingBarcodes > 0) {
            $alerts[] = ['type' => 'warning', 'message' => "{$missingBarcodes} variants missing barcodes", 'action' => 'assign_barcodes'];
        }
        
        $pricingGaps = $this->getPricingGaps();
        if ($pricingGaps > 0) {
            $alerts[] = ['type' => 'error', 'message' => "{$pricingGaps} variants missing pricing", 'action' => 'add_pricing'];
        }
        
        $completenessScore = $this->getCatalogCompletenessScore();
        if ($completenessScore < 80) {
            $alerts[] = ['type' => 'info', 'message' => "Catalog completeness below 80%", 'action' => 'enrich_data'];
        }
        
        return $alerts;
    }

    private function getPendingEnrichment(): int
    {
        return ProductVariant::where(function($query) {
            $query->whereNull('color')
                  ->orWhereNull('size')
                  ->orWhereNull('package_weight')
                  ->orWhereNull('package_length');
        })->count();
    }

    private function getWorkflowBottlenecks(): array
    {
        return [
            ['stage' => 'Image Processing', 'count' => 23, 'avg_time' => '2.3 hours'],
            ['stage' => 'Price Validation', 'count' => 12, 'avg_time' => '45 minutes'],
            ['stage' => 'Barcode Assignment', 'count' => 8, 'avg_time' => '15 minutes'],
        ];
    }

    // PIM Chart Data Methods
    private function getCatalogCompletenessTrend(): array
    {
        // Mock trend data for last 7 days
        return [
            '2025-01-27' => 82.5,
            '2025-01-28' => 84.1,
            '2025-01-29' => 85.3,
            '2025-01-30' => 87.2,
            '2025-01-31' => 88.9,
            '2025-02-01' => 90.1,
            '2025-02-02' => $this->getCatalogCompletenessScore(),
        ];
    }

    private function getContentVelocity(): array
    {
        $velocity = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $count = ProductVariant::whereDate('created_at', $date)->count();
            $velocity[$date] = $count;
        }
        return $velocity;
    }

    private function getChannelDistribution(): array
    {
        return [
            'ready' => $this->getReadyForExport(),
            'pending_images' => ProductVariant::whereHas('barcodes')
                ->whereHas('pricing')
                ->whereDoesntHave('product', function($query) {
                    $query->whereNotNull('images')->where('images', '!=', '[]');
                })->count(),
            'pending_data' => $this->getPendingEnrichment(),
            'draft' => Product::where('status', 'draft')->count(),
        ];
    }

    private function getDataQualityScoreHistory(): array
    {
        // Mock quality score history
        return [
            'completeness' => $this->getCatalogCompletenessScore(),
            'accuracy' => 94.2,
            'consistency' => 91.7,
            'timeliness' => 88.5,
        ];
    }

    public function refreshData()
    {
        // Trigger reactivity by accessing computed properties
        $this->getPimMetricsProperty();
        $this->getPimWorkflowProperty();
        $this->getPimChartsProperty();
        
        // Show success toast using helper function
        toast_success('Dashboard Refreshed', 'All metrics have been updated successfully.')
            ->duration(3000)
            ->send();
        
        $this->dispatch('data-refreshed');
    }

    public function render()
    {
        return view('livewire.dashboard');
    }
}