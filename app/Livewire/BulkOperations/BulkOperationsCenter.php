<?php

namespace App\Livewire\BulkOperations;

use App\Models\Product;
use App\Models\ProductVariant;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * 🚀 BULK OPERATIONS CENTER
 *
 * Powerful bulk operations system for managing products and variants at scale.
 * Built with Livewire + Alpine.js for seamless reactivity.
 */
class BulkOperationsCenter extends Component
{
    use WithPagination;

    // Selection Management (Alpine.js bound)
    /** @var array<int> */
    public array $selectedItems = [];

    public bool $selectAll = false;

    public string $targetType = 'products'; // or 'variants'

    // Search & Filters
    public string $search = '';

    /** @var array<string, string|null> */
    public array $filters = [
        'status' => 'all',
        'has_variants' => null,
    ];

    // Success message state
    public ?string $successMessage = null;

    // Pagination
    public int $perPage = 15;

    /**
     * 🧮 Computed Properties for Alpine.js
     */
    #[Computed]
    public function selectedCount(): int
    {
        return count($this->selectedItems);
    }

    /**
     * @return array<int>
     */
    #[Computed]
    public function currentPageIds(): array
    {
        $paginated = $this->getPaginatedItems();

        return $paginated->items() ? collect($paginated->items())->pluck('id')->toArray() : [];
    }

    #[Computed]
    public function totalItemsCount(): int
    {
        return $this->getFilteredQuery()->count();
    }

    /**
     * 📊 Get filtered and paginated items
     */
    #[Computed]
    public function paginatedItems(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return $this->getPaginatedItems();
    }

    /**
     * 🎨 Render the component
     */
    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.bulk-operations.bulk-operations-center', [
            'paginatedItems' => $this->paginatedItems,
            'currentPageIds' => $this->currentPageIds,
            'totalItemsCount' => $this->totalItemsCount,
        ]);
    }

    /**
     * 🔍 Main data query based on target type
     *
     * @return \Illuminate\Database\Eloquent\Builder<Product>|\Illuminate\Database\Eloquent\Builder<ProductVariant>
     */
    private function getFilteredQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return $this->targetType === 'products'
            ? $this->getFilteredProducts()
            : $this->getFilteredVariants();
    }

    /**
     * 📦 Get paginated items
     */
    private function getPaginatedItems(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return $this->getFilteredQuery()
            ->orderBy('id', 'desc')
            ->paginate($this->perPage);
    }

    /**
     * 🔍 Filter products based on search and filters
     *
     * @return \Illuminate\Database\Eloquent\Builder<Product>
     */
    private function getFilteredProducts(): \Illuminate\Database\Eloquent\Builder
    {
        return Product::query()
            ->when($this->search, fn ($q) => $q->where(function ($query) {
                $query->where('name', 'like', "%{$this->search}%")
                    ->orWhere('parent_sku', 'like', "%{$this->search}%")
                    ->orWhere('description', 'like', "%{$this->search}%");
            }))
            ->when($this->filters['status'] !== 'all', fn ($q) => $q->where('status', $this->filters['status'])
            )
            ->when($this->filters['has_variants'] !== null, fn ($q) => $this->filters['has_variants'] ? $q->has('variants') : $q->doesntHave('variants')
            )
            ->with(['variants' => function ($query) {
                $query->orderBy('color')->orderBy('width');
            }])
            ->withCount('variants');
    }

    /**
     * 🎨 Filter variants based on search and filters
     *
     * @return \Illuminate\Database\Eloquent\Builder<ProductVariant>
     */
    private function getFilteredVariants(): \Illuminate\Database\Eloquent\Builder
    {
        return ProductVariant::query()
            ->when($this->search, fn ($q) => $q->where(function ($query) {
                $query->where('sku', 'like', "%{$this->search}%")
                    ->orWhere('title', 'like', "%{$this->search}%")
                    ->orWhere('color', 'like', "%{$this->search}%")
                    ->orWhereHas('product', function ($productQuery) {
                        $productQuery->where('name', 'like', "%{$this->search}%");
                    });
            }))
            ->when($this->filters['status'] !== 'all', fn ($q) => $q->where('status', $this->filters['status'])
            )
            ->with('product')
            ->orderBy('id', 'desc');
    }

    /**
     * 🎯 Selection Management Methods
     */
    public function toggleSelectAll(): void
    {
        if ($this->selectAll) {
            $this->selectedItems = collect($this->getPaginatedItems()->items())->pluck('id')->toArray();
        } else {
            $this->selectedItems = [];
        }
    }

    public function clearSelection(): void
    {
        $this->selectedItems = [];
        $this->selectAll = false;
    }

    public function selectAllMatching(): void
    {
        $this->selectedItems = $this->getFilteredQuery()
            ->pluck('id')
            ->toArray();
    }

    /**
     * 🛠️ Bulk Operation Navigation
     */
    public function openBulkPricing(): void
    {
        if (empty($this->selectedItems)) {
            return;
        }

        $this->redirectRoute('bulk.pricing', [
            'targetType' => $this->targetType,
            'selectedItems' => encrypt($this->selectedItems),
        ]);
    }

    public function openBulkImages(): void
    {
        if (empty($this->selectedItems)) {
            return;
        }

        $this->redirectRoute('bulk.images', [
            'targetType' => $this->targetType,
            'selectedItems' => encrypt($this->selectedItems),
        ]);
    }

    public function openBulkAttributes(): void
    {
        if (empty($this->selectedItems)) {
            return;
        }

        $this->redirectRoute('bulk.attributes', [
            'targetType' => $this->targetType,
            'selectedItems' => encrypt($this->selectedItems),
        ]);
    }

    /**
     * 📝 Handle success messages from operations
     */
    public function mount(): void
    {
        // Authorize bulk operations access
        $this->authorize('bulk-edit-products');

        if (session()->has('bulk_operation_success')) {
            $this->successMessage = session('bulk_operation_success');
            session()->forget('bulk_operation_success');
        }
    }

    /**
     * 🧹 Clear success message
     */
    public function clearSuccessMessage(): void
    {
        $this->successMessage = null;
    }

    /**
     * 🔄 Reset pagination when search/filters change
     */
    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'filters', 'targetType'])) {
            $this->resetPage();
            $this->clearSelection();
        }
    }
}
