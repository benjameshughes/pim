<?php

namespace App\Livewire\Products;

use App\Models\Product;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Product Index Livewire Component
 * 
 * Displays paginated list of products with search and filtering capabilities.
 * Clean implementation following Livewire best practices.
 * 
 * @package App\Livewire\Products
 */
#[Layout('components.layouts.app')]
#[Title('Products')]
class ProductIndex extends Component
{
    use WithPagination;
    
    /**
     * Search query
     * 
     * @var string
     */
    public string $search = '';
    
    /**
     * Status filter
     * 
     * @var string
     */
    public string $statusFilter = '';
    
    /**
     * Sort field
     * 
     * @var string
     */
    public string $sortField = 'created_at';
    
    /**
     * Sort direction
     * 
     * @var string
     */
    public string $sortDirection = 'desc';
    
    /**
     * Items per page
     * 
     * @var int
     */
    public int $perPage = 15;
    
    /**
     * Available status options
     * 
     * @var array
     */
    public array $statusOptions = [
        '' => 'All Status',
        'draft' => 'Draft',
        'active' => 'Active',
        'inactive' => 'Inactive',
        'archived' => 'Archived',
    ];
    
    /**
     * Available sort options
     * 
     * @var array
     */
    public array $sortOptions = [
        'name' => 'Name',
        'parent_sku' => 'SKU',
        'status' => 'Status',
        'created_at' => 'Created Date',
        'updated_at' => 'Updated Date',
    ];
    
    /**
     * Reset pagination when search changes
     * 
     * @param string $value
     * @return void
     */
    public function updatedSearch($value): void
    {
        $this->resetPage();
    }
    
    /**
     * Reset pagination when status filter changes
     * 
     * @param string $value
     * @return void
     */
    public function updatedStatusFilter($value): void
    {
        $this->resetPage();
    }
    
    /**
     * Sort by field
     * 
     * @param string $field
     * @return void
     */
    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
        
        $this->resetPage();
    }
    
    /**
     * Clear all filters and search
     * 
     * @return void
     */
    public function clearFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->sortField = 'created_at';
        $this->sortDirection = 'desc';
        $this->resetPage();
    }
    
    /**
     * Delete product
     * 
     * @param int $productId
     * @return void
     */
    public function deleteProduct(int $productId): void
    {
        $product = Product::findOrFail($productId);
        
        try {
            $productName = $product->name;
            
            // Use the action for consistency
            app(\App\Actions\Products\DeleteProductAction::class)->execute($product);
            
            $this->dispatch('product-deleted', productName: $productName);
            
            // Flash success message
            session()->flash('success', "Product '{$productName}' deleted successfully.");
            
        } catch (\Exception $e) {
            $this->dispatch('product-delete-failed', error: $e->getMessage());
            
            // Flash error message
            session()->flash('error', 'Failed to delete product: ' . $e->getMessage());
        }
    }
    
    /**
     * Get products query with filters applied
     * 
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function getProductsQuery()
    {
        $query = Product::query()
            ->with(['variants', 'productImages'])
            ->withCount('variants');
        
        // Apply search filter
        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('parent_sku', 'like', "%{$this->search}%")
                  ->orWhere('description', 'like', "%{$this->search}%");
            });
        }
        
        // Apply status filter
        if (!empty($this->statusFilter)) {
            $query->where('status', $this->statusFilter);
        }
        
        // Apply sorting
        $query->orderBy($this->sortField, $this->sortDirection);
        
        return $query;
    }
    
    /**
     * Get the status badge class for display
     * 
     * @param string $status
     * @return string
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
     * Get sort direction icon
     * 
     * @param string $field
     * @return string
     */
    public function getSortIcon(string $field): string
    {
        if ($this->sortField !== $field) {
            return 'heroicon-m-arrows-up-down';
        }
        
        return $this->sortDirection === 'asc' 
            ? 'heroicon-m-arrow-up' 
            : 'heroicon-m-arrow-down';
    }
    
    /**
     * Render the component
     * 
     * @return \Illuminate\View\View
     */
    public function render()
    {
        $products = $this->getProductsQuery()->paginate($this->perPage);
        
        return view('livewire.products.product-index', [
            'products' => $products,
        ]);
    }
}