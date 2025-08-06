<?php

namespace App\Livewire\Operations;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Traits\HasRouteTabs;
use App\Traits\SharesBulkOperationsState;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class BulkOperationsOverview extends Component
{
    use WithPagination, HasRouteTabs, SharesBulkOperationsState;

    // URL-tracked state
    #[Url(except: '', as: 'q')]
    public $search = '';
    
    #[Url(except: 'all', as: 'filter')]
    public $searchFilter = 'all';

    #[Url(except: [], as: 'selected')]
    public $selectedVariants = [];

    #[Url(except: [], as: 'expanded')]
    public $expandedProducts = [];

    // Local state
    public $selectAll = false;
    public $selectedProducts = [];

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

    protected $queryString = [
        'search' => ['except' => '', 'as' => 'q'],
        'searchFilter' => ['except' => 'all', 'as' => 'filter'],
        'selectedVariants' => ['except' => [], 'as' => 'selected'],
        'expandedProducts' => ['except' => [], 'as' => 'expanded'],
        'page' => ['except' => 1],
    ];

    public function mount()
    {
        // Load state from session/URL
        $searchState = $this->getSearchState();
        
        // Prefer URL params over session
        $this->search = request('q', $searchState['search']);
        $this->searchFilter = request('filter', $searchState['searchFilter']);
        
        // Load selected variants from URL or session
        if (empty($this->selectedVariants)) {
            $this->selectedVariants = $this->getSelectedVariants();
        }
        
        // Load expanded products
        if (empty($this->expandedProducts)) {
            $this->expandedProducts = $this->getExpandedProducts();
        }
    }

    public function updatedSearch()
    {
        $this->resetPage();
        $this->setSearchState($this->search, $this->searchFilter);
    }

    public function updatedSearchFilter()
    {
        $this->resetPage();
        $this->setSearchState($this->search, $this->searchFilter);
    }

    public function updatedSelectedVariants()
    {
        $this->setSelectedVariants($this->selectedVariants);
        $this->updateSelectAll();
        $this->updateSelectedProducts();
    }

    public function updatedSelectedProducts()
    {
        $this->updateVariantsFromSelectedProducts();
    }

    public function updatedSelectAll()
    {
        if ($this->selectAll) {
            $this->selectedVariants = ProductVariant::pluck('id')->toArray();
        } else {
            $this->selectedVariants = [];
        }
        $this->updatedSelectedVariants();
    }

    public function updatedExpandedProducts()
    {
        $this->setExpandedProducts($this->expandedProducts);
    }

    private function updateSelectAll()
    {
        $totalVariants = ProductVariant::count();
        $this->selectAll = count($this->selectedVariants) === $totalVariants;
    }

    private function updateSelectedProducts()
    {
        // Update selectedProducts based on selected variants
        $this->selectedProducts = [];
        $products = Product::with('variants')->get();
        
        foreach ($products as $product) {
            $variantIds = $product->variants->pluck('id')->toArray();
            $selectedProductVariantIds = array_intersect($this->selectedVariants, $variantIds);
            
            // If all variants of this product are selected, add product to selectedProducts
            if (count($selectedProductVariantIds) === count($variantIds) && count($variantIds) > 0) {
                $this->selectedProducts[] = $product->id;
            }
        }
    }

    private function updateVariantsFromSelectedProducts()
    {
        // When products are selected/deselected, update variants accordingly
        $products = Product::with('variants')->whereIn('id', $this->selectedProducts)->get();
        
        // Get all variant IDs from selected products
        $productVariantIds = [];
        foreach ($products as $product) {
            $productVariantIds = array_merge($productVariantIds, $product->variants->pluck('id')->toArray());
        }
        
        // Remove variants from products that are no longer selected
        $allSelectedProductIds = $this->selectedProducts;
        $allProducts = Product::with('variants')->get();
        
        foreach ($allProducts as $product) {
            if (!in_array($product->id, $allSelectedProductIds)) {
                // Remove this product's variants from selected variants
                $variantIds = $product->variants->pluck('id')->toArray();
                $this->selectedVariants = array_diff($this->selectedVariants, $variantIds);
            }
        }
        
        // Add variants from newly selected products
        $this->selectedVariants = array_unique(array_merge($this->selectedVariants, $productVariantIds));
        
        // Update session state
        $this->setSelectedVariants($this->selectedVariants);
        $this->updateSelectAll();
    }

    public function toggleProductExpansion($productId)
    {
        if (in_array($productId, $this->expandedProducts)) {
            $this->expandedProducts = array_diff($this->expandedProducts, [$productId]);
        } else {
            $this->expandedProducts[] = $productId;
        }
        $this->updatedExpandedProducts();
    }


    public function render()
    {
        $query = Product::with(['variants.attributes', 'variants.pricing', 'variants.barcodes']);

        // Apply search filters
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('parent_sku', 'like', '%' . $this->search . '%')
                  ->orWhereHas('variants', function ($vq) {
                      $vq->where('sku', 'like', '%' . $this->search . '%');
                  });
            });
        }

        // Apply search filter
        if ($this->searchFilter && $this->searchFilter !== 'all') {
            switch ($this->searchFilter) {
                case 'parent_sku':
                    if ($this->search) {
                        $query->where('parent_sku', 'like', '%' . $this->search . '%');
                    }
                    break;
                case 'variant_sku':
                    if ($this->search) {
                        $query->whereHas('variants', function ($q) {
                            $q->where('sku', 'like', '%' . $this->search . '%');
                        });
                    }
                    break;
                case 'barcode':
                    if ($this->search) {
                        $query->whereHas('variants.barcodes', function ($q) {
                            $q->where('barcode', 'like', '%' . $this->search . '%');
                        });
                    }
                    break;
            }
        }

        $products = $query->latest()->paginate(20);

        return view('livewire.operations.bulk-operations-overview', [
            'products' => $products,
            'tabs' => $this->getTabsForNavigation(),
        ]);
    }
}