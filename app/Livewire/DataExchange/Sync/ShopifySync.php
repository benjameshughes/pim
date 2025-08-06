<?php

namespace App\Livewire\DataExchange\Sync;

use App\Models\Product;
use App\Services\ShopifyConnectService;
use App\Actions\API\Shopify\PushProductToShopify;
use App\Actions\API\Shopify\PushMultipleProductsToShopify;
use App\Actions\API\Shopify\ImportMultipleShopifyProducts;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Log;
use Exception;

#[Layout('components.layouts.app')]
class ShopifySync extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = '';
    public $syncResults = [];
    public $isSyncing = false;
    public $connectionStatus = null;
    public $selectedProducts = [];
    public $selectAll = false;
    
    // Import properties
    public $importResults = [];
    public $isImporting = false;
    public $importMode = 'preview'; // 'preview' or 'import'
    public $importLimit = 20;
    public $activeTab = 'export'; // 'export' or 'import'

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
            $service = new ShopifyConnectService();
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
        $this->importResults = [];
        session()->forget(['message', 'error']);
    }

    public function importFromShopify()
    {
        $this->isImporting = true;
        $this->importResults = [];

        try {
            $importAction = app(ImportMultipleShopifyProducts::class);
            
            $options = [
                'limit' => $this->importLimit,
                'dry_run' => $this->importMode === 'preview',
                'status' => 'active' // Only import active products
            ];

            Log::info('Starting Shopify import via UI', $options);

            $results = $importAction->execute($options);
            $this->importResults = $results;

            if ($results['success']) {
                $summary = $results['summary'];
                $action = $this->importMode === 'preview' ? 'analyzed' : 'imported';
                
                session()->flash('message', 
                    "Shopify import {$action}! {$summary['created']} products would be created, {$summary['skipped']} skipped, {$summary['total_variants']} total variants."
                );
            } else {
                session()->flash('error', 'Import failed: ' . $results['error']);
            }

        } catch (Exception $e) {
            Log::error('Shopify UI import failed', ['error' => $e->getMessage()]);
            session()->flash('error', 'Import failed: ' . $e->getMessage());
        } finally {
            $this->isImporting = false;
        }
    }

    private function syncProducts(array $productIds)
    {
        $this->isSyncing = true;
        $this->syncResults = [];

        try {
            $products = Product::with(['variants.attributes', 'variants.barcodes', 'variants.pricing'])
                ->whereIn('id', $productIds)
                ->get();
                
            // Debug logging
            Log::info('Shopify UI Sync starting', [
                'product_ids' => $productIds,
                'found_products' => $products->count(),
                'product_names' => $products->pluck('name')->toArray()
            ]);

            $pushMultipleAction = app(PushMultipleProductsToShopify::class);
            $results = $pushMultipleAction->execute($products);
            $this->syncResults = $results;

            // Calculate totals for summary
            $totalShopifyProducts = collect($results)->sum('color_groups');
            $successfulShopifyProducts = collect($results)->sum('summary.successful');
            
            // Debug the results
            Log::info('Shopify UI Sync completed', [
                'total_shopify_products' => $totalShopifyProducts,
                'successful_shopify_products' => $successfulShopifyProducts,
                'results_summary' => collect($results)->map(function($result) {
                    return [
                        'product' => $result['product_name'],
                        'color_groups' => $result['color_groups'],
                        'success_rate' => $result['summary']['success_rate']
                    ];
                })->toArray()
            ]);

            session()->flash('message', 
                "Shopify sync completed! {$successfulShopifyProducts}/{$totalShopifyProducts} color-based products successfully created in Shopify."
            );

        } catch (Exception $e) {
            Log::error('Shopify UI Sync failed', ['error' => $e->getMessage()]);
            session()->flash('error', 'Sync failed: ' . $e->getMessage());
        } finally {
            $this->isSyncing = false;
            $this->dispatch('sync-completed');
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

        return view('livewire.data-exchange.sync.shopify-sync', [
            'products' => $products
        ]);
    }
}