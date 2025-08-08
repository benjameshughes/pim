<?php

namespace App\Livewire\Operations;

use App\Models\MarketplaceBarcode;
use App\Models\MarketplaceVariant;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Traits\HasRouteTabs;
use App\Traits\SharesBulkOperationsState;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class BulkOperationsQuality extends Component
{
    use HasRouteTabs, SharesBulkOperationsState;

    // Local state
    public $qualityResults = [];

    public $qualityScanning = false;

    protected $baseRoute = 'operations.bulk';

    protected $tabConfig = [
        'tabs' => [
            [
                'key' => 'overview',
                'label' => 'Overview',
                'icon' => 'chart-bar',
            ],
            [
                'key' => 'templates',
                'label' => 'Title Templates',
                'icon' => 'layout-grid',
            ],
            [
                'key' => 'attributes',
                'label' => 'Bulk Attributes',
                'icon' => 'tag',
            ],
            [
                'key' => 'quality',
                'label' => 'Data Quality',
                'icon' => 'shield-check',
            ],
            [
                'key' => 'recommendations',
                'label' => 'Smart Recommendations',
                'icon' => 'lightbulb',
            ],
            [
                'key' => 'ai',
                'label' => 'AI Assistant',
                'icon' => 'zap',
            ],
        ],
    ];

    public function mount()
    {
        // Run initial scan
        $this->scanDataQuality();
    }

    public function scanDataQuality()
    {
        $this->qualityScanning = true;
        $this->qualityResults = [];

        try {
            // Check missing marketplace variants
            $variantsWithoutMarketplace = ProductVariant::whereDoesntHave('marketplaceVariants')->count();

            // Check missing attributes
            $productsWithoutAttributes = Product::whereDoesntHave('attributes')->count();
            $variantsWithoutAttributes = ProductVariant::whereDoesntHave('attributes')->count();

            // Check missing marketplace identifiers
            $variantsWithoutASIN = ProductVariant::whereDoesntHave('marketplaceBarcodes', function ($query) {
                $query->where('identifier_type', 'asin');
            })->count();

            // Check duplicate ASINs
            $duplicateASINs = MarketplaceBarcode::where('identifier_type', 'asin')
                ->select('identifier_value')
                ->groupBy('identifier_value')
                ->havingRaw('COUNT(*) > 1')
                ->count();

            // Check incomplete titles
            $incompleteTitles = MarketplaceVariant::where('title', 'LIKE', '%[%]%')->count();

            // Check missing colors/sizes
            $variantsWithoutColor = ProductVariant::whereNull('color')->orWhere('color', '')->count();
            $variantsWithoutSize = ProductVariant::whereNull('size')->orWhere('size', '')->count();

            // Check missing barcodes
            $variantsWithoutBarcodes = ProductVariant::whereDoesntHave('barcodes')->count();

            // Check missing pricing
            $variantsWithoutPricing = ProductVariant::whereDoesntHave('pricing')->count();

            // Check empty product names
            $productsWithoutNames = Product::whereNull('name')->orWhere('name', '')->count();

            // Check missing parent SKUs
            $productsWithoutSKUs = Product::whereNull('parent_sku')->orWhere('parent_sku', '')->count();

            $this->qualityResults = [
                'missing_marketplace_variants' => $variantsWithoutMarketplace,
                'products_without_attributes' => $productsWithoutAttributes,
                'variants_without_attributes' => $variantsWithoutAttributes,
                'variants_without_asin' => $variantsWithoutASIN,
                'duplicate_asins' => $duplicateASINs,
                'incomplete_titles' => $incompleteTitles,
                'variants_without_color' => $variantsWithoutColor,
                'variants_without_size' => $variantsWithoutSize,
                'variants_without_barcodes' => $variantsWithoutBarcodes,
                'variants_without_pricing' => $variantsWithoutPricing,
                'products_without_names' => $productsWithoutNames,
                'products_without_skus' => $productsWithoutSKUs,
                'total_variants' => ProductVariant::count(),
                'total_products' => Product::count(),
            ];

        } catch (\Exception $e) {
            Log::error('Data quality scan failed', ['error' => $e->getMessage()]);
            session()->flash('error', 'Quality scan failed: '.$e->getMessage());
        }

        $this->qualityScanning = false;
    }

    public function getQualityScore()
    {
        if (empty($this->qualityResults)) {
            return 0;
        }

        $totalVariants = $this->qualityResults['total_variants'];
        $totalProducts = $this->qualityResults['total_products'];

        if ($totalVariants === 0 || $totalProducts === 0) {
            return 0;
        }

        // Calculate quality metrics as percentages
        $metrics = [
            'marketplace_coverage' => (($totalVariants - $this->qualityResults['missing_marketplace_variants']) / $totalVariants) * 100,
            'product_attributes' => (($totalProducts - $this->qualityResults['products_without_attributes']) / $totalProducts) * 100,
            'variant_attributes' => (($totalVariants - $this->qualityResults['variants_without_attributes']) / $totalVariants) * 100,
            'asin_coverage' => (($totalVariants - $this->qualityResults['variants_without_asin']) / $totalVariants) * 100,
            'title_completion' => (($totalVariants - $this->qualityResults['incomplete_titles']) / max($totalVariants, 1)) * 100,
            'color_completion' => (($totalVariants - $this->qualityResults['variants_without_color']) / $totalVariants) * 100,
            'size_completion' => (($totalVariants - $this->qualityResults['variants_without_size']) / $totalVariants) * 100,
            'barcode_coverage' => (($totalVariants - $this->qualityResults['variants_without_barcodes']) / $totalVariants) * 100,
            'pricing_coverage' => (($totalVariants - $this->qualityResults['variants_without_pricing']) / $totalVariants) * 100,
            'product_names' => (($totalProducts - $this->qualityResults['products_without_names']) / $totalProducts) * 100,
            'product_skus' => (($totalProducts - $this->qualityResults['products_without_skus']) / $totalProducts) * 100,
        ];

        // Calculate weighted average (some metrics are more important)
        $weights = [
            'marketplace_coverage' => 15,
            'product_attributes' => 10,
            'variant_attributes' => 10,
            'asin_coverage' => 15,
            'title_completion' => 15,
            'color_completion' => 8,
            'size_completion' => 8,
            'barcode_coverage' => 10,
            'pricing_coverage' => 15,
            'product_names' => 12,
            'product_skus' => 12,
        ];

        $weightedSum = 0;
        $totalWeight = 0;

        foreach ($metrics as $key => $value) {
            $weight = $weights[$key] ?? 1;
            $weightedSum += $value * $weight;
            $totalWeight += $weight;
        }

        return round($weightedSum / $totalWeight, 1);
    }

    public function render()
    {
        $selectedVariants = $this->getSelectedVariants();
        $selectedVariantsCount = count($selectedVariants);
        $qualityScore = $this->getQualityScore();

        return view('livewire.operations.bulk-operations-quality', [
            'tabs' => $this->getTabsForNavigation(),
            'selectedVariants' => $selectedVariants,
            'selectedVariantsCount' => $selectedVariantsCount,
            'qualityResults' => $this->qualityResults,
            'qualityScore' => $qualityScore,
            'qualityScanning' => $this->qualityScanning,
        ]);
    }
}
