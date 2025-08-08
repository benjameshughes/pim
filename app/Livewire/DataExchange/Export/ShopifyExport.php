<?php

namespace App\Livewire\DataExchange\Export;

use App\Models\Category;
use App\Models\Product;
use App\Services\ShopifyExportService;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ShopifyExport extends Component
{
    public $selectedProducts = [];

    public $selectedCategories = [];

    public $includeInactive = false;

    public $exportFormat = 'csv';

    public $isProcessing = false;

    public $lastExportInfo = null;

    protected $shopifyService;

    public function boot()
    {
        $this->shopifyService = app(ShopifyExportService::class);
    }

    public function toggleProduct($productId)
    {
        if (in_array($productId, $this->selectedProducts)) {
            $this->selectedProducts = array_diff($this->selectedProducts, [$productId]);
        } else {
            $this->selectedProducts[] = $productId;
        }
    }

    public function toggleCategory($categoryId)
    {
        if (in_array($categoryId, $this->selectedCategories)) {
            $this->selectedCategories = array_diff($this->selectedCategories, [$categoryId]);
        } else {
            $this->selectedCategories[] = $categoryId;
        }
    }

    public function selectAllProducts()
    {
        $products = $this->getFilteredProducts();
        $this->selectedProducts = $products->pluck('id')->toArray();
    }

    public function deselectAllProducts()
    {
        $this->selectedProducts = [];
    }

    public function exportToShopify()
    {
        $this->validate([
            'selectedProducts' => 'required|array|min:1',
        ], [
            'selectedProducts.required' => 'Please select at least one product to export.',
            'selectedProducts.min' => 'Please select at least one product to export.',
        ]);

        $this->isProcessing = true;

        try {
            // Get selected products with relationships
            $products = Product::with(['variants', 'categories', 'productImages'])
                ->whereIn('id', $this->selectedProducts)
                ->get();

            // Export to Shopify format
            $shopifyProducts = $this->shopifyService->exportProducts($products);

            // Generate CSV
            $csvContent = $this->shopifyService->generateCSV($shopifyProducts);

            // Save to storage
            $filename = 'shopify-export-'.now()->format('Y-m-d-H-i-s').'.csv';
            Storage::disk('public')->put("exports/{$filename}", $csvContent);

            // Store export info for download
            $this->lastExportInfo = [
                'filename' => $filename,
                'path' => "exports/{$filename}",
                'size' => strlen($csvContent),
                'products_count' => $products->count(),
                'shopify_products_count' => count($shopifyProducts),
                'created_at' => now()->format('Y-m-d H:i:s'),
            ];

            session()->flash('message', 'Shopify export completed successfully!');

        } catch (\Exception $e) {
            session()->flash('error', 'Export failed: '.$e->getMessage());
        } finally {
            $this->isProcessing = false;
        }
    }

    public function downloadExport()
    {
        if (! $this->lastExportInfo) {
            session()->flash('error', 'No export file available for download.');

            return;
        }

        $path = $this->lastExportInfo['path'];

        if (! Storage::disk('public')->exists($path)) {
            session()->flash('error', 'Export file not found.');

            return;
        }

        return Storage::disk('public')->download($path, $this->lastExportInfo['filename']);
    }

    public function previewExport()
    {
        if (empty($this->selectedProducts)) {
            session()->flash('error', 'Please select at least one product to preview.');

            return;
        }

        // Get first selected product for preview
        $product = Product::with(['variants', 'categories'])
            ->where('id', $this->selectedProducts[0])
            ->first();

        if ($product) {
            $shopifyProducts = $this->shopifyService->exportProducts(collect([$product]));
            session()->flash('preview', $shopifyProducts[0] ?? []);
        }
    }

    private function getFilteredProducts()
    {
        $query = Product::with(['variants', 'categories']);

        // Filter by categories if selected
        if (! empty($this->selectedCategories)) {
            $query->whereHas('categories', function ($q) {
                $q->whereIn('categories.id', $this->selectedCategories);
            });
        }

        // Filter by status
        if (! $this->includeInactive) {
            $query->where('status', 'active');
        }

        return $query->orderBy('name')->get();
    }

    public function getProductsProperty()
    {
        return $this->getFilteredProducts();
    }

    public function getCategoriesProperty()
    {
        return Category::with('children')
            ->roots()
            ->ordered()
            ->get();
    }

    public function getSelectedProductsCountProperty()
    {
        return count($this->selectedProducts);
    }

    public function getEstimatedShopifyProductsProperty()
    {
        if (empty($this->selectedProducts)) {
            return 0;
        }

        // Estimate based on unique colors in selected products
        $products = Product::with('variants')
            ->whereIn('id', $this->selectedProducts)
            ->get();

        $totalShopifyProducts = 0;
        foreach ($products as $product) {
            $colors = $product->variants->pluck('color')->filter()->unique();
            $totalShopifyProducts += max(1, $colors->count()); // At least 1 even if no colors
        }

        return $totalShopifyProducts;
    }

    public function render()
    {
        return view('livewire.data-exchange.export.shopify-export');
    }
}
