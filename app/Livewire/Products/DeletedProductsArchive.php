<?php

namespace App\Livewire\Products;

use App\Models\DeletedProductVariant;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class DeletedProductsArchive extends Component
{
    use WithPagination;

    public string $search = '';
    public string $reasonFilter = '';
    public string $sortBy = 'deleted_at';
    public string $sortDirection = 'desc';

    protected $queryString = ['search', 'reasonFilter', 'sortBy', 'sortDirection'];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingReasonFilter()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->reasonFilter = '';
        $this->sortBy = 'deleted_at';
        $this->sortDirection = 'desc';
        $this->resetPage();
    }

    public function render()
    {
        $query = DeletedProductVariant::query()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('product_name', 'like', '%' . $this->search . '%')
                      ->orWhere('variant_sku', 'like', '%' . $this->search . '%')
                      ->orWhere('primary_barcode', 'like', '%' . $this->search . '%')
                      ->orWhere('color', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->reasonFilter, function ($query) {
                $query->where('deletion_reason', $this->reasonFilter);
            })
            ->orderBy($this->sortBy, $this->sortDirection);

        $deletedVariants = $query->paginate(25);

        // Get statistics
        $stats = [
            'total_deleted' => DeletedProductVariant::count(),
            'unique_products' => DeletedProductVariant::distinct('original_product_id')->count(),
            'barcodes_freed' => DeletedProductVariant::whereNotNull('primary_barcode')->count(),
            'deletion_reasons' => DeletedProductVariant::selectRaw('deletion_reason, COUNT(*) as count')
                ->groupBy('deletion_reason')
                ->pluck('count', 'deletion_reason')
                ->toArray()
        ];

        return view('livewire.products.deleted-products-archive', [
            'deletedVariants' => $deletedVariants,
            'availableReasons' => DeletedProductVariant::getAvailableReasons(),
            'stats' => $stats
        ]);
    }
}