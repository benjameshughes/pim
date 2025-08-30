<?php

namespace App\Livewire\Products;

use App\Models\Product;
use App\Services\Marketplace\Facades\Sync;
use Livewire\Component;

class ProductOverview extends Component
{
    public Product $product;
    public ?array $shopifyPushResult = null;

    public function mount(Product $product)
    {
        $this->authorize('view-product-details');
        
        $this->product = $product->load(['variants']);
    }

    public function pushToShopify()
    {
        $this->authorize('manage-products');
        
        try {
            // Simple fluent API call - let the facade handle everything
            $result = Sync::marketplace('shopify')
                ->create($this->product->id)
                ->push();
            
            $this->shopifyPushResult = [
                'success' => $result->isSuccess(),
                'message' => $result->getMessage(),
                'data' => $result->getData(),
            ];
            
            if ($result->isSuccess()) {
                $this->dispatch('toast', [
                    'type' => 'success',
                    'message' => 'Successfully pushed to Shopify! ' . $result->getMessage(),
                ]);
            } else {
                $this->dispatch('toast', [
                    'type' => 'error', 
                    'message' => 'Failed to push to Shopify: ' . $result->getMessage(),
                ]);
            }
            
        } catch (\Exception $e) {
            $this->shopifyPushResult = [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ];
            
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Push failed: ' . $e->getMessage(),
            ]);
        }
    }

    public function updateShopifyTitle()
    {
        $this->authorize('manage-products');
        
        try {
            // KISS fluent API for partial update
            $result = Sync::marketplace('shopify')
                ->update($this->product->id)
                ->title($this->product->name . ' - UPDATED')
                ->push();
            
            $this->shopifyPushResult = [
                'success' => $result->isSuccess(),
                'message' => $result->getMessage(),
            ];
            
            if ($result->isSuccess()) {
                $this->dispatch('toast', [
                    'type' => 'success',
                    'message' => 'Title updated in Shopify! ' . $result->getMessage(),
                ]);
            } else {
                $this->dispatch('toast', [
                    'type' => 'error',
                    'message' => 'Title update failed: ' . $result->getMessage(),
                ]);
            }
            
        } catch (\Exception $e) {
            $this->shopifyPushResult = [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ];
            
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Title update failed: ' . $e->getMessage(),
            ]);
        }
    }

    public function updateShopifyPricing()
    {
        $this->authorize('manage-products');
        
        try {
            // KISS fluent API for pricing update
            $result = Sync::marketplace('shopify')
                ->update($this->product->id)
                ->pricing()
                ->push();
            
            $this->shopifyPushResult = [
                'success' => $result->isSuccess(),
                'message' => $result->getMessage(),
            ];
            
            if ($result->isSuccess()) {
                $this->dispatch('toast', [
                    'type' => 'success',
                    'message' => 'Pricing updated in Shopify! ' . $result->getMessage(),
                ]);
            } else {
                $this->dispatch('toast', [
                    'type' => 'error',
                    'message' => 'Pricing update failed: ' . $result->getMessage(),
                ]);
            }
            
        } catch (\Exception $e) {
            $this->shopifyPushResult = [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ];
            
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Pricing update failed: ' . $e->getMessage(),
            ]);
        }
    }

    public function render()
    {
        return view('livewire.products.product-overview');
    }
}
