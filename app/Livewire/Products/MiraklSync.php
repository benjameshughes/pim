<?php

namespace App\Livewire\Products;

use App\Models\Product;
use App\Services\MiraklConnectService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Log;
use Exception;

#[Layout('components.layouts.app')]
class MiraklSync extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = '';
    public $syncResults = [];
    public $isSyncing = false;
    public $connectionStatus = null;
    public $selectedProducts = [];
    public $selectAll = false;

    protected $queryString = ['search', 'statusFilter'];

    public function mount()
    {
        $this->testConnection();
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function testConnection()
    {
        try {
            $service = new MiraklConnectService();
            $this->connectionStatus = $service->testConnection();
        } catch (Exception $e) {
            $this->connectionStatus = [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage()
            ];
        }
    }

    public function toggleSelectAll()
    {
        if ($this->selectAll) {
            $this->selectedProducts = $this->getProducts()->pluck('id')->toArray();
        } else {
            $this->selectedProducts = [];
        }
    }

    public function syncSelected()
    {
        if (empty($this->selectedProducts)) {
            session()->flash('error', 'Please select at least one product to sync.');
            return;
        }

        $this->syncProducts($this->selectedProducts);
    }

    public function syncAll()
    {
        $productIds = $this->getProducts()->pluck('id')->toArray();
        $this->syncProducts($productIds);
    }

    public function syncSingle($productId)
    {
        $this->syncProducts([$productId]);
    }

    public function clearResults()
    {
        $this->syncResults = [];
        session()->forget(['message', 'error']);
    }

    private function syncProducts(array $productIds)
    {
        $this->isSyncing = true;
        $this->syncResults = [];

        try {
            $service = new MiraklConnectService();
            $products = Product::with(['variants.attributes', 'variants.barcodes', 'variants.pricing'])
                ->whereIn('id', $productIds)
                ->get();
                
            // Debug logging
            Log::info('UI Sync starting', [
                'product_ids' => $productIds,
                'found_products' => $products->count(),
                'product_names' => $products->pluck('name')->toArray()
            ]);

            $results = $service->pushProducts($products);
            $this->syncResults = $results;

            $totalVariants = collect($results)->sum(fn($result) => count($result['results']));
            $successfulVariants = collect($results)->sum(function($result) {
                return collect($result['results'])->where('success', true)->count();
            });
            
            // Debug the results
            Log::info('UI Sync completed', [
                'total_variants' => $totalVariants,
                'successful_variants' => $successfulVariants,
                'results_summary' => collect($results)->map(function($result) {
                    return [
                        'product' => $result['product_name'],
                        'success_count' => collect($result['results'])->where('success', true)->count(),
                        'total_count' => count($result['results'])
                    ];
                })->toArray()
            ]);

            session()->flash('message', 
                "Sync completed! {$successfulVariants}/{$totalVariants} variants successfully synced to Mirakl Connect."
            );

        } catch (Exception $e) {
            Log::error('UI Sync failed', ['error' => $e->getMessage()]);
            session()->flash('error', 'Sync failed: ' . $e->getMessage());
        } finally {
            $this->isSyncing = false;
            $this->dispatch('sync-completed'); // Add event to refresh UI
        }
    }

    private function getProducts()
    {
        return Product::withCount('variants')
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('description', 'like', '%' . $this->search . '%');
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->orderBy('name');
    }

    public function render()
    {
        $products = $this->getProducts()->paginate(10);

        return view('livewire.products.mirakl-sync', [
            'products' => $products
        ]);
    }
}