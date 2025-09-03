<?php

namespace App\Livewire\Products;

use App\Models\Product;
use App\Services\Marketplace\Facades\Sync;
use Livewire\Component;

class ProductOverview extends Component
{
    public Product $product;

    public ?array $shopifyPushResult = null;

    // Inline editing states
    public bool $editingName = false;
    public bool $editingDescription = false;
    public string $tempName = '';
    public string $tempDescription = '';

    public function mount(Product $product)
    {
        $this->authorize('view-product-details');

        $this->product = $product->load(['variants', 'images']);
    }

    // Inline editing methods for Name
    public function startEditingName()
    {
        $this->authorize('edit-products');
        $this->editingName = true;
        $this->tempName = $this->product->name;
    }

    public function saveName()
    {
        $this->authorize('edit-products');
        
        $this->validate([
            'tempName' => 'required|string|max:255'
        ]);

        $this->product->update(['name' => $this->tempName]);
        $this->editingName = false;

        $this->dispatch('toast', [
            'type' => 'success',
            'message' => 'Product name updated successfully! ✨'
        ]);
    }

    public function cancelEditingName()
    {
        $this->editingName = false;
        $this->tempName = $this->product->name;
        $this->resetErrorBag('tempName');
    }

    // Inline editing methods for Description
    public function startEditingDescription()
    {
        $this->authorize('edit-products');
        $this->editingDescription = true;
        $this->tempDescription = $this->product->description ?? '';
    }

    public function saveDescription()
    {
        $this->authorize('edit-products');
        
        $this->validate([
            'tempDescription' => 'nullable|string|max:1000'
        ]);

        $this->product->update(['description' => $this->tempDescription ?: null]);
        $this->editingDescription = false;

        $this->dispatch('toast', [
            'type' => 'success',
            'message' => 'Product description updated successfully! ✨'
        ]);
    }

    public function cancelEditingDescription()
    {
        $this->editingDescription = false;
        $this->tempDescription = $this->product->description ?? '';
        $this->resetErrorBag('tempDescription');
    }

    public function pushToShopify()
    {
        $this->authorize('manage-products');

        try {
            // 🎯 KISS API - Use create() or fullUpdate() based on current status
            $shopifyStatus = $this->product->getSmartAttributeValue('shopify_status');

            if ($shopifyStatus === 'synced') {
                // Products already exist - perform full update (preserves Shopify IDs)
                $result = Sync::marketplace('shopify')
                    ->fullUpdate($this->product->id)
                    ->push();

                $actionMessage = 'fully updated';
            } else {
                // No existing products - create new ones
                $result = Sync::marketplace('shopify')
                    ->create($this->product->id)
                    ->push();

                $actionMessage = 'created';
            }

            $this->shopifyPushResult = [
                'success' => $result->isSuccess(),
                'message' => $result->getMessage(),
                'data' => $result->getData(),
            ];

            if ($result->isSuccess()) {
                $this->dispatch('toast', [
                    'type' => 'success',
                    'message' => "Successfully {$actionMessage} in Shopify! ".$result->getMessage(),
                ]);

                // Refresh the product to show updated attributes
                $this->product->refresh();
            } else {
                $this->dispatch('toast', [
                    'type' => 'error',
                    'message' => "Failed to {$actionMessage} in Shopify: ".$result->getMessage(),
                ]);
            }

        } catch (\Exception $e) {
            $this->shopifyPushResult = [
                'success' => false,
                'message' => 'Error: '.$e->getMessage(),
            ];

            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Push failed: '.$e->getMessage(),
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
                ->title($this->product->name.' - UPDATED')
                ->push();

            $this->shopifyPushResult = [
                'success' => $result->isSuccess(),
                'message' => $result->getMessage(),
            ];

            if ($result->isSuccess()) {
                $this->dispatch('toast', [
                    'type' => 'success',
                    'message' => 'Title updated in Shopify! '.$result->getMessage(),
                ]);

                // Refresh to show any status changes
                $this->product->refresh();
            } else {
                $this->dispatch('toast', [
                    'type' => 'error',
                    'message' => 'Title update failed: '.$result->getMessage(),
                ]);
            }

        } catch (\Exception $e) {
            $this->shopifyPushResult = [
                'success' => false,
                'message' => 'Error: '.$e->getMessage(),
            ];

            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Title update failed: '.$e->getMessage(),
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
                    'message' => 'Pricing updated in Shopify! '.$result->getMessage(),
                ]);

                // Refresh to show any status changes
                $this->product->refresh();
            } else {
                $this->dispatch('toast', [
                    'type' => 'error',
                    'message' => 'Pricing update failed: '.$result->getMessage(),
                ]);
            }

        } catch (\Exception $e) {
            $this->shopifyPushResult = [
                'success' => false,
                'message' => 'Error: '.$e->getMessage(),
            ];

            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Pricing update failed: '.$e->getMessage(),
            ]);
        }
    }

    public function deleteFromShopify()
    {
        $this->authorize('manage-products');

        try {
            // KISS fluent API for delete operation
            $result = Sync::marketplace('shopify')
                ->delete($this->product->id)
                ->push();

            $this->shopifyPushResult = [
                'success' => $result->isSuccess(),
                'message' => $result->getMessage(),
                'data' => $result->getData(),
            ];

            if ($result->isSuccess()) {
                $this->dispatch('toast', [
                    'type' => 'success',
                    'message' => 'Successfully deleted from Shopify! '.$result->getMessage(),
                ]);

                // Refresh to show cleared attributes
                $this->product->refresh();
            } else {
                $this->dispatch('toast', [
                    'type' => 'error',
                    'message' => 'Delete failed: '.$result->getMessage(),
                ]);
            }

        } catch (\Exception $e) {
            $this->shopifyPushResult = [
                'success' => false,
                'message' => 'Error: '.$e->getMessage(),
            ];

            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Delete failed: '.$e->getMessage(),
            ]);
        }
    }

    public function linkToShopify()
    {
        $this->authorize('manage-products');

        try {
            // KISS fluent API for link operation
            $result = Sync::marketplace('shopify')
                ->link($this->product->id)
                ->push();

            $this->shopifyPushResult = [
                'success' => $result->isSuccess(),
                'message' => $result->getMessage(),
                'data' => $result->getData(),
            ];

            if ($result->isSuccess()) {
                $data = $result->getData();
                $colorGroups = $data['color_groups_count'] ?? 0;
                $coverage = $data['coverage_percent'] ?? 0;

                $this->dispatch('toast', [
                    'type' => 'success',
                    'message' => "Successfully linked to Shopify! Found {$colorGroups} color groups ({$coverage}% coverage)",
                ]);

                // Refresh to show new sync status
                $this->product->refresh();
            } else {
                $this->dispatch('toast', [
                    'type' => 'error',
                    'message' => 'Link failed: '.$result->getMessage(),
                ]);
            }

        } catch (\Exception $e) {
            $this->shopifyPushResult = [
                'success' => false,
                'message' => 'Error: '.$e->getMessage(),
            ];

            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Link failed: '.$e->getMessage(),
            ]);
        }
    }

    public function render()
    {
        return view('livewire.products.product-overview');
    }
}
