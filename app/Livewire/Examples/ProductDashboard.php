<?php

namespace App\Livewire\Examples;

use App\Atom\Core\Support\Concerns\HasResourceTable;
use App\Atom\Resources\ProductResource;
use Livewire\Component;

/**
 * Example: Your Custom Livewire Component with Atom Table
 * 
 * This shows how to add Atom framework table functionality
 * to your existing Livewire components using the HasResourceTable trait.
 */
class ProductDashboard extends Component
{
    use HasResourceTable;
    
    /**
     * The resource to use for the table.
     * This is all you need to add {{ $this->table }} to your view!
     */
    public string $resource = ProductResource::class;
    
    // Your custom component properties
    public string $customTitle = 'Product Management Dashboard';
    public string $customDescription = 'Manage your products with custom functionality and Atom table power.';
    
    /**
     * Your custom component methods.
     */
    public function refreshData(): void
    {
        // Your custom refresh logic
        $this->dispatch('$refresh');
        session()->flash('success', 'Data refreshed successfully!');
    }
    
    public function exportData(): void
    {
        // Your custom export logic
        session()->flash('success', 'Data export initiated!');
    }
    
    /**
     * Custom computed properties for your dashboard.
     */
    public function getTotalRecordsProperty(): int
    {
        return \App\Models\Product::count();
    }
    
    public function getActiveRecordsProperty(): int
    {
        return \App\Models\Product::where('active', true)->count();
    }
    
    public function getRecentRecordsProperty(): int
    {
        return \App\Models\Product::where('created_at', '>=', now()->subWeek())->count();
    }
    
    /**
     * Override to handle custom table actions.
     */
    protected function handleCustomTableAction(string $action, $record): void
    {
        match ($action) {
            'duplicate' => $this->duplicateProduct($record),
            'archive' => $this->archiveProduct($record),
            default => null,
        };
    }
    
    /**
     * Custom action: Duplicate product.
     */
    protected function duplicateProduct($product): void
    {
        $duplicate = $product->replicate();
        $duplicate->name = $product->name . ' (Copy)';
        $duplicate->save();
        
        session()->flash('success', "Product duplicated successfully!");
        $this->dispatch('$refresh');
    }
    
    /**
     * Custom action: Archive product.
     */
    protected function archiveProduct($product): void
    {
        $product->update(['archived' => true]);
        
        session()->flash('success', "Product archived successfully!");
        $this->dispatch('$refresh');
    }
    
    /**
     * Your component render method.
     */
    public function render()
    {
        return view('examples.custom-livewire-component')
            ->layout('layouts.app');
    }
}