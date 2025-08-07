<?php

namespace App\Livewire\Pim\Products\Variants;

use App\Models\ProductVariant;
use App\Traits\HasRouteTabs;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class VariantView extends Component
{
    use HasRouteTabs;

    public ProductVariant $variant;

    protected $baseRoute = 'products.variants';
    
    protected $tabConfig = [
        'tabs' => [
            [
                'key' => 'details',
                'label' => 'Details',
                'icon' => 'package',
            ],
            [
                'key' => 'inventory',
                'label' => 'Inventory',
                'icon' => 'warehouse',
            ],
            [
                'key' => 'attributes',
                'label' => 'Attributes',
                'icon' => 'tag',
            ],
            [
                'key' => 'data',
                'label' => 'Data & Pricing',
                'icon' => 'database',
            ],
            [
                'key' => 'images',
                'label' => 'Images',
                'icon' => 'image',
            ],
        ],
    ];
    
    public function mount(ProductVariant $variant)
    {
        $this->variant = $variant->load([
            'product:id,name,description,status',
            'product.attributes',
            'barcodes',
            'pricing.salesChannel:id,name',
            'marketplaceVariants.marketplace:id,name,platform',
            'marketplaceBarcodes.marketplace:id,name,platform',
            'attributes'
        ]);
    }

    public function getTabsForNavigation(): array
    {
        $config = $this->getTabConfig();
        $baseRoute = $this->getBaseRoute();
        $currentRoute = $this->getCurrentTab();
        
        $tabs = [];
        
        foreach ($config['tabs'] as $tab) {
            $routeName = "{$baseRoute}.{$tab['key']}";
            
            $tabs[] = [
                'key' => $tab['key'],
                'label' => $tab['label'],
                'icon' => $tab['icon'] ?? 'document',
                'route' => $routeName,
                'active' => $currentRoute === $routeName,
                'url' => route($routeName, ['variant' => $this->variant]),
            ];
        }
        
        return $tabs;
    }

    public function getActiveTabProperty(): string
    {
        $currentRoute = request()->route()->getName();
        
        // Extract tab from route name (e.g., 'products.variants.details' -> 'details')
        $parts = explode('.', $currentRoute);
        return end($parts) ?? 'details';
    }

    
    public function editVariant()
    {
        return $this->redirect(route('products.variants.edit', $this->variant));
    }
    
    public function duplicateVariant()
    {
        $newVariant = $this->variant->replicate();
        $newVariant->sku = $this->variant->sku . '-COPY';
        $newVariant->save();
        
        session()->flash('message', 'Variant duplicated successfully.');
        return $this->redirect(route('products.variants.view', $newVariant));
    }
    
    public function deleteVariant()
    {
        $productId = $this->variant->product_id;
        $this->variant->delete();
        
        session()->flash('message', 'Variant deleted successfully.');
        return $this->redirect(route('products.index'));
    }
    
    public function getStatusBadgeClass(): string
    {
        return match($this->variant->status) {
            'active' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
            'inactive' => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300',
            'out_of_stock' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300'
        };
    }
    
    public function getStockLevelClass(): string
    {
        $stock = $this->variant->stock_level ?? 0;
        
        if ($stock === 0) {
            return 'text-red-600 dark:text-red-400';
        } elseif ($stock <= 10) {
            return 'text-amber-600 dark:text-amber-400';
        } else {
            return 'text-green-600 dark:text-green-400';
        }
    }
    
    public function render()
    {
        return view('livewire.pim.products.variants.variant-view', [
            'tabs' => $this->getTabsForNavigation(),
        ]);
    }
}