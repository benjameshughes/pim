<?php

namespace App\Livewire\Shopify;

use App\Models\ShopifySyncStatus;
use App\Services\Marketplace\Facades\Sync;
use Livewire\Component;
use Livewire\WithPagination;

class ShopifySync extends Component
{
    use WithPagination;

    public $status = 'all';

    public function mount()
    {
        // Authorize syncing products to marketplaces
        $this->authorize('sync-to-marketplace');
        
        // Simple initialization - no complex services needed!
    }

    public function render()
    {
        $syncRecords = ShopifySyncStatus::query()
            ->with(['product', 'productVariant'])
            ->when($this->status !== 'all', fn ($query) => $query->where('sync_status', $this->status))
            ->orderBy('updated_at', 'desc')
            ->paginate(20);

        // Get summary stats
        $stats = [
            'total' => ShopifySyncStatus::count(),
            'synced' => ShopifySyncStatus::where('sync_status', 'synced')->count(),
            'pending' => ShopifySyncStatus::where('sync_status', 'pending')->count(),
            'failed' => ShopifySyncStatus::where('sync_status', 'failed')->count(),
        ];

        return view('livewire.shopify.shopify-sync', compact('syncRecords', 'stats'));
    }

    public function updatingStatus()
    {
        $this->resetPage();
    }

    public function syncAll()
    {
        // Authorize syncing products to marketplaces
        $this->authorize('sync-to-marketplace');
        
        try {
            // Get products that need syncing
            $products = \App\Models\Product::with('variants')->where('status', 'active')->get();

            if ($products->isEmpty()) {
                $this->dispatch('toast', [
                    'type' => 'warning',
                    'message' => 'No active products found to sync',
                ]);

                return;
            }

            // ULTRA-SIMPLE: Use the beautiful new API with color splitting! 🎉
            $result = Sync::shopify()->pushWithColors($products->toArray());

            $successCount = collect($result)->where('success', true)->count();
            $failCount = collect($result)->where('success', false)->count();

            $message = "Sync completed: {$successCount} products synced successfully";
            if ($failCount > 0) {
                $message .= ", {$failCount} failed";
            }

            $this->dispatch('toast', [
                'type' => $failCount === 0 ? 'success' : 'warning',
                'message' => $message,
            ]);

        } catch (\Exception $e) {
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Sync failed: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Test Shopify connection
     */
    public function testConnection()
    {
        // Authorize testing marketplace connections
        $this->authorize('test-marketplace-connection');
        
        try {
            $result = Sync::shopify()->testConnection();

            $this->dispatch('toast', [
                'type' => $result['success'] ? 'success' : 'error',
                'message' => $result['message'],
            ]);

        } catch (\Exception $e) {
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Connection test failed: '.$e->getMessage(),
            ]);
        }
    }
}
