<?php

namespace App\Livewire\PIM\Products\Management;

use App\Models\Product;
use App\Traits\HasRouteTabs;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ProductView extends Component
{
    use HasRouteTabs;

    public Product $product;

    protected $baseRoute = 'products.product';
    
    protected $tabConfig = [
        'tabs' => [
            [
                'key' => 'overview',
                'label' => 'Overview',
                'icon' => 'package',
            ],
            [
                'key' => 'variants',
                'label' => 'Variants',
                'icon' => 'layers',
            ],
            [
                'key' => 'images',
                'label' => 'Images',
                'icon' => 'image',
            ],
            [
                'key' => 'attributes',
                'label' => 'Attributes',
                'icon' => 'tag',
            ],
            [
                'key' => 'sync',
                'label' => 'Marketplace Sync',
                'icon' => 'globe',
            ],
        ],
    ];

    public function mount(Product $product)
    {
        $this->product = $product;
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
                'url' => route($routeName, ['product' => $this->product]),
            ];
        }
        
        return $tabs;
    }

    public function getActiveTabProperty(): string
    {
        $currentRoute = request()->route()->getName();
        
        // Extract tab from route name (e.g., 'products.product.overview' -> 'overview')
        $parts = explode('.', $currentRoute);
        $lastPart = end($parts);
        
        // Default to 'overview' for base product route
        return $lastPart === 'view' ? 'overview' : ($lastPart ?? 'overview');
    }


    public function render()
    {
        // Load relationships
        $this->product->load([
            'variants.pricing',
            'variants.barcodes', 
            'variants.marketplaceVariants.marketplace',
            'productImages',
            'attributes.attributeDefinition'
        ]);

        return view('livewire.pim.products.management.product-view', [
            'tabs' => $this->getTabsForNavigation(),
        ]);
    }
}