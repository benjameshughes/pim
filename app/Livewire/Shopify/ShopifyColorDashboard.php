<?php

namespace App\Livewire\Shopify;

use App\Models\Product;
use App\Services\Marketplace\Facades\Sync;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class ShopifyColorDashboard extends Component
{
    use WithPagination;

    public $selectedProductId = null;

    public $selectedProduct = null;

    public $selectedColors = [];

    public $previewData = null;

    public $syncResults = [];

    public $isLoading = false;

    public $currentTab = 'overview';

    // Stats
    public $taxonomyStats = [];

    public $systemStats = [];

    public function mount()
    {
        // Simple initialization - no complex service loading needed!
    }

    /**
     * 🏠 Render the dashboard
     */
    public function render()
    {
        return view('livewire.shopify.shopify-color-dashboard', [
            'products' => $this->getProducts(),
            'colorGroups' => $this->getColorGroups(),
        ]);
    }

    /**
     * 🎯 Select product for management
     */
    public function selectProduct($productId)
    {
        $this->selectedProductId = $productId;
        $this->selectedProduct = Product::with(['variants.pricing', 'variants.barcodes'])->find($productId);
        $this->selectedColors = [];
        $this->previewData = null;
        $this->syncResults = [];

        if ($this->selectedProduct) {
            $this->generatePreview();
            $this->loadSyncStatus();
        }
    }

    /**
     * 🎨 Toggle color selection
     */
    public function toggleColor($color)
    {
        if (in_array($color, $this->selectedColors)) {
            $this->selectedColors = array_values(array_diff($this->selectedColors, [$color]));
        } else {
            $this->selectedColors[] = $color;
        }
    }

    /**
     * 🎨 Select all colors
     */
    public function selectAllColors()
    {
        if (! $this->previewData) {
            return;
        }

        $this->selectedColors = collect($this->previewData['shopify_products_to_create'])
            ->pluck('color')
            ->toArray();
    }

    /**
     * 🎨 Clear color selection
     */
    public function clearColorSelection()
    {
        $this->selectedColors = [];
    }

    /**
     * 👀 Generate preview of what will be created (ULTRA-SIMPLE!)
     */
    public function generatePreview()
    {
        if (! $this->selectedProduct) {
            return;
        }

        $this->isLoading = true;

        try {
            // ULTRA-SIMPLE: Just group by colors - no complex service needed!
            $colorGroups = $this->selectedProduct->variants->groupBy('color')->filter(function ($variants, $color) {
                return ! empty($color);
            });

            $this->previewData = [
                'shopify_products_to_create' => $colorGroups->map(function ($variants, $color) {
                    return [
                        'color' => $color,
                        'title' => "{$this->selectedProduct->name} - {$color}",
                        'variant_count' => $variants->count(),
                        'variants' => $variants->toArray(),
                    ];
                })->values()->toArray(),
                'total_colors' => $colorGroups->count(),
                'total_variants' => $this->selectedProduct->variants->count(),
            ];

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to generate preview: '.$e->getMessage());
            $this->previewData = null;
        }

        $this->isLoading = false;
    }

    /**
     * 🚀 Create products in Shopify (ULTRA-SIMPLE with your API!)
     */
    public function createInShopify()
    {
        if (! $this->selectedProduct || empty($this->selectedColors)) {
            session()->flash('error', 'Please select a product and at least one color.');

            return;
        }

        $this->isLoading = true;
        $this->syncResults = [];

        try {
            // ULTRA-SIMPLE: Just use your beautiful new API! 🎉
            $results = Sync::shopify()->pushWithColors([$this->selectedProduct]);

            $successCount = collect($results)->where('success', true)->count();
            $this->syncResults = $results;

            if ($successCount > 0) {
                session()->flash('success',
                    "Successfully created {$successCount} color products in Shopify!"
                );
            } else {
                session()->flash('error', 'Failed to create products. Check results below.');
            }

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to create products: '.$e->getMessage());
            $this->syncResults = ['error' => $e->getMessage()];
        }

        $this->isLoading = false;
    }

    /**
     * 🔄 Pull updates from Shopify (ULTRA-SIMPLE!)
     */
    public function pullFromShopify($shopifyProductIds = [])
    {
        $this->isLoading = true;

        try {
            // ULTRA-SIMPLE: Just pull products from Shopify!
            $products = Sync::shopify()->pull(['limit' => 50]);

            $this->syncResults = [
                'success' => true,
                'products_found' => $products->count(),
                'message' => "Found {$products->count()} products on Shopify",
            ];

            session()->flash('success', "Successfully pulled {$products->count()} products from Shopify!");

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to pull updates: '.$e->getMessage());
            $this->syncResults = ['error' => $e->getMessage()];
        }

        $this->isLoading = false;
    }

    /**
     * 🔄 Test Shopify connection (Simple health check)
     */
    public function testConnection()
    {
        $this->isLoading = true;

        try {
            // ULTRA-SIMPLE: Just test the connection!
            $result = Sync::shopify()->testConnection();

            if ($result['success']) {
                session()->flash('success', 'Shopify connection successful! '.$result['shop_name']);
            } else {
                session()->flash('error', 'Connection failed: '.$result['message']);
            }

        } catch (\Exception $e) {
            session()->flash('error', 'Connection test failed: '.$e->getMessage());
        }

        $this->isLoading = false;
    }

    /**
     * 📊 Switch dashboard tab
     */
    public function setTab($tab)
    {
        $this->currentTab = $tab;

        if ($tab === 'overview') {
            $this->loadSystemStats();
        }
    }

    /**
     * 📊 Load system statistics (ULTRA-SIMPLE!)
     */
    private function loadSystemStats()
    {
        // ULTRA-SIMPLE: Just test connection and get basic stats
        try {
            $connectionTest = Sync::shopify()->testConnection();

            $this->systemStats = [
                'connection_status' => $connectionTest['success'] ? 'Connected' : 'Failed',
                'shop_name' => $connectionTest['shop_name'] ?? 'Unknown',
                'last_checked' => now()->toDateTimeString(),
            ];

            $this->taxonomyStats = [
                'status' => 'Simple mode - no complex taxonomy needed!',
                'api_ready' => true,
            ];

        } catch (\Exception $e) {
            $this->systemStats = ['error' => $e->getMessage()];
            $this->taxonomyStats = ['error' => $e->getMessage()];
        }
    }

    /**
     * 📦 Get paginated products
     */
    private function getProducts()
    {
        return Product::with(['variants' => function ($query) {
            $query->select('id', 'product_id', 'color', 'width', 'stock_level', 'price');
        }])
            ->whereHas('variants')
            ->latest()
            ->paginate(10);
    }

    /**
     * 🎨 Get color groups for selected product (ULTRA-SIMPLE!)
     */
    private function getColorGroups()
    {
        if (! $this->selectedProduct) {
            return collect();
        }

        // ULTRA-SIMPLE: Just group by color - no complex service needed!
        return $this->selectedProduct->variants->groupBy('color')->filter(function ($variants, $color) {
            return ! empty($color);
        });
    }

    /**
     * 📊 Load sync status for selected product (ULTRA-SIMPLE!)
     */
    private function loadSyncStatus()
    {
        if (! $this->selectedProduct) {
            return;
        }

        // ULTRA-SIMPLE: Just check if product has Shopify ID
        $this->syncResults = [
            'sync_status' => $this->selectedProduct->shopify_product_id ? 'synced' : 'not_synced',
            'shopify_id' => $this->selectedProduct->shopify_product_id,
            'last_loaded' => now()->toISOString(),
            'message' => $this->selectedProduct->shopify_product_id
                ? 'Product is synced with Shopify'
                : 'Product not yet synced to Shopify',
        ];
    }

    /**
     * 🔍 Search products
     */
    public function searchProducts($query)
    {
        // This would be implemented with a search input
        // For now, just refresh the products
        $this->resetPage();
    }

    /**
     * 📈 Get dashboard metrics
     */
    public function getDashboardMetrics()
    {
        return [
            'total_products' => Product::count(),
            'products_with_variants' => Product::whereHas('variants')->count(),
            'total_colors' => Product::whereHas('variants')
                ->with('variants:id,product_id,color')
                ->get()
                ->flatMap(fn ($p) => $p->variants->pluck('color'))
                ->unique()
                ->count(),
            'shopify_ready_products' => Product::whereHas('variants', function ($q) {
                $q->whereNotNull('color')->whereNotNull('width');
            })->count(),
        ];
    }

    /**
     * 🎯 Get color statistics for a product (ULTRA-SIMPLE!)
     */
    public function getProductColorStats($productId)
    {
        $product = Product::with('variants')->find($productId);
        if (! $product) {
            return [];
        }

        // ULTRA-SIMPLE: Just count colors directly!
        $colorGroups = $product->variants->groupBy('color')->filter(function ($variants, $color) {
            return ! empty($color);
        });

        return [
            'total_colors' => $colorGroups->count(),
            'colors' => $colorGroups->keys()->toArray(),
            'variants_per_color' => $colorGroups->map->count()->toArray(),
            'total_variants' => $product->variants->count(),
        ];
    }
}
