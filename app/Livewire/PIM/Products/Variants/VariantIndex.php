<?php

namespace App\Livewire\Pim\Products\Variants;

use App\Models\ProductVariant;
use App\Models\Product;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class VariantIndex extends Component
{
    use WithPagination;
    
    public string $search = '';
    public string $statusFilter = '';
    public int $perPage = 25;
    
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
     * Get variants query with filters
     */
    public function getVariants()
    {
        $query = ProductVariant::query()
            ->with(['product:id,name,parent_sku', 'barcodes', 'pricing']);
        
        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('sku', 'like', "%{$this->search}%")
                  ->orWhereHas('product', function ($productQuery) {
                      $productQuery->where('name', 'like', "%{$this->search}%");
                  });
            });
        }
        
        if (!empty($this->statusFilter)) {
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
     * Render the component
     */
    public function render()
    {
        return view('livewire.pim.products.variants.variant-index', [
            'variants' => $this->getVariants(),
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