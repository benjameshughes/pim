<?php

namespace App\Livewire\Pim\Products\Management;

use App\Models\Product;
use App\Models\ProductVariant;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class ProductIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public int $perPage = 25;

    public array $expandedProducts = [];

    /**
     * Update search and reset pagination
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Update filter and reset pagination
     */
    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Update perPage and reset pagination
     */
    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    /**
     * Get products query with filters using Collections best practices
     */
    public function getProducts()
    {
        $query = Product::query()
            ->withCount([
                'variants',
                'variants as active_variants_count' => fn($q) => $q->where('status', 'active'),
                'variants as draft_variants_count' => fn($q) => $q->where('status', 'draft'),
            ])
            ->with([
                'productImages',
                'variants' => fn($q) => $q->limit(3)->select('id', 'product_id', 'sku', 'status') // Preview only
            ]);

        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('parent_sku', 'like', "%{$this->search}%")
                    ->orWhereHas('variants', function ($vq) {
                        $vq->where('sku', 'like', "%{$this->search}%");
                    });
            });
        }

        if (! empty($this->statusFilter)) {
            $query->where('status', $this->statusFilter);
        }

        return $query->latest()->paginate($this->perPage);
    }

    /**
     * Get status badge class
     */
    public function getStatusBadgeClass(string $status): string
    {
        return match ($status) {
            'active' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
            'inactive' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
            'draft' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
            'archived' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        };
    }

    /**
     * Load variants for a specific product when expanded
     */
    public function loadVariants($productId)
    {
        $variants = ProductVariant::query()
            ->where('product_id', $productId)
            ->withCommon()
            ->get();

        $this->expandedProducts[$productId] = $variants->map(function ($variant) {
            return [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'status' => $variant->status,
                'color' => $variant->color,
                'size' => $variant->size,
                'stock_level' => $variant->stock_level,
                'retail_price' => $variant->pricing->first()?->retail_price,
                'image_url' => $variant->image_url,
                'has_pricing' => $variant->hasPricing(),
                'has_barcode' => $variant->hasPrimaryBarcode(),
            ];
        })->toArray();
        
        $this->dispatch('variants-loaded', productId: $productId);
    }
    
    /**
     * Toggle expanded state for a product
     */
    public function toggleExpanded($productId)
    {
        if (isset($this->expandedProducts[$productId])) {
            unset($this->expandedProducts[$productId]);
        } else {
            $this->loadVariants($productId);
        }
    }

    /**
     * Render the component
     */
    public function render()
    {
        return view('livewire.pim.products.management.product-index', [
            'products' => $this->getProducts(),
            'statusOptions' => [
                '' => 'All Status',
                'active' => 'Active',
                'inactive' => 'Inactive',
                'draft' => 'Draft',
                'archived' => 'Archived',
            ],
        ]);
    }
}
