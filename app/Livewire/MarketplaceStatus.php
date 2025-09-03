<?php

namespace App\Livewire;

use App\Models\Product;
use Carbon\Carbon;
use Livewire\Component;

/**
 * 🏷️ MARKETPLACE STATUS COMPONENT
 * 
 * Generic status badge for any marketplace channel
 * Works with Sync facade architecture (shopify, ebay, amazon, etc.)
 */
class MarketplaceStatus extends Component
{
    public Product $product;
    public string $channel;
    public bool $showLabel = true;

    public function mount(Product $product, string $channel, bool $showLabel = true)
    {
        $this->product = $product;
        $this->channel = $channel;
        $this->showLabel = $showLabel;
    }

    /**
     * Get the current marketplace status
     */
    public function getStatusProperty(): array
    {
        // Check product attributes for this channel
        $status = $this->product->getSmartAttributeValue("{$this->channel}_status");
        $productIds = $this->product->getSmartAttributeValue("{$this->channel}_product_ids");
        $syncedAt = $this->product->getSmartAttributeValue("{$this->channel}_synced_at");
        
        // Simple attribute-based status logic
        return match($status) {
            'processing' => $this->getProcessingStatus(),
            'failed' => $this->getFailedStatus(),
            'synced' => $this->getActiveStatus($syncedAt),
            default => $this->getNotSyncedStatus(),
        };
    }

    /**
     * Get processing status
     */
    protected function getProcessingStatus(): array
    {
        return [
            'status' => 'processing',
            'label' => 'Processing...',
            'color' => 'blue',
            'icon' => 'refresh',
            'animated' => true,
        ];
    }

    /**
     * Get failed status
     */
    protected function getFailedStatus(): array
    {
        return [
            'status' => 'failed',
            'label' => 'Sync Failed',
            'color' => 'red',
            'icon' => 'x-circle',
            'animated' => false,
        ];
    }

    /**
     * Get active/synced status
     */
    protected function getActiveStatus(?string $syncedAt): array
    {
        $channelName = ucfirst($this->channel);
        
        if ($syncedAt) {
            $lastSync = Carbon::parse($syncedAt);
            $label = "Active (synced {$lastSync->diffForHumans()})";
        } else {
            $label = "Active";
        }
        
        return [
            'status' => 'active',
            'label' => $label,
            'color' => 'green',
            'icon' => 'check-circle',
            'animated' => false,
        ];
    }

    /**
     * Get not synced status
     */
    protected function getNotSyncedStatus(): array
    {
        $channelName = ucfirst($this->channel);
        
        return [
            'status' => 'not_synced',
            'label' => "Not on {$channelName}",
            'color' => 'gray',
            'icon' => 'circle',
            'animated' => false,
        ];
    }

    /**
     * Get CSS classes for the status dot
     */
    public function getDotClasses(): string
    {
        $status = $this->status;
        
        return match($status['color']) {
            'blue' => 'w-2.5 h-2.5 rounded-full bg-blue-500',
            'green' => 'w-2.5 h-2.5 rounded-full bg-green-500',
            'red' => 'w-2.5 h-2.5 rounded-full bg-red-500',
            'yellow' => 'w-2.5 h-2.5 rounded-full bg-yellow-500',
            default => 'w-2.5 h-2.5 rounded-full bg-gray-400',
        };
    }
    
    /**
     * Get text color for the status label
     */
    public function getTextColor(): string
    {
        $status = $this->status;
        
        return match($status['color']) {
            'blue' => 'text-blue-700 dark:text-blue-300',
            'green' => 'text-green-700 dark:text-green-300',
            'red' => 'text-red-700 dark:text-red-300',
            'yellow' => 'text-yellow-700 dark:text-yellow-300',
            default => 'text-gray-700 dark:text-gray-300',
        };
    }
    
    /**
     * Get clean status text without time/user info
     */
    public function getStatusText(): string
    {
        $status = $this->status;
        
        return match($status['status']) {
            'processing' => 'Processing...',
            'failed' => 'Sync Failed',
            'synced' => 'Active',
            default => 'Not Synced',
        };
    }
    
    /**
     * Should we show sync details?
     */
    public function shouldShowSyncDetails(): bool
    {
        return $this->status['status'] === 'synced' && $this->getLastSyncTime();
    }
    
    /**
     * Get last sync time
     */
    public function getLastSyncTime(): ?string
    {
        $syncedAt = $this->product->getSmartAttributeValue("{$this->channel}_synced_at");
        
        if (!$syncedAt) {
            return null;
        }
        
        return Carbon::parse($syncedAt)->diffForHumans();
    }
    
    /**
     * Get user who performed the sync (from attribute metadata)
     */
    public function getSyncUser(): ?string
    {
        // Could be stored in attribute metadata if needed
        // For now, return null - can be enhanced later
        return null;
    }

    /**
     * Refresh status (called via wire:poll or events)
     */
    public function refreshStatus()
    {
        // Refresh the product to get latest attributes
        $this->product->refresh();
    }

    public function render()
    {
        return view('livewire.marketplace-status');
    }
}
