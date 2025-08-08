<?php

namespace App\Livewire\Operations;

use App\Models\Marketplace;
use App\Models\ProductVariant;
use App\Traits\HasRouteTabs;
use App\Traits\SharesBulkOperationsState;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class BulkOperationsTemplates extends Component
{
    use HasRouteTabs, SharesBulkOperationsState;

    // URL-tracked state
    #[Url(except: [], as: 'marketplaces')]
    public $selectedMarketplaces = [];

    #[Url(except: null, as: 'preview')]
    public $previewVariant = null;

    // Template state
    public $titleTemplate = '';

    public $descriptionTemplate = '';

    public $showTitlePreview = false;

    protected $baseRoute = 'operations.bulk';

    protected $tabConfig = [
        'tabs' => [
            [
                'key' => 'overview',
                'label' => 'Overview',
                'icon' => 'chart-bar',
            ],
            [
                'key' => 'templates',
                'label' => 'Title Templates',
                'icon' => 'layout-grid',
            ],
            [
                'key' => 'attributes',
                'label' => 'Bulk Attributes',
                'icon' => 'tag',
            ],
            [
                'key' => 'quality',
                'label' => 'Data Quality',
                'icon' => 'shield-check',
            ],
            [
                'key' => 'recommendations',
                'label' => 'Smart Recommendations',
                'icon' => 'lightbulb',
            ],
            [
                'key' => 'ai',
                'label' => 'AI Assistant',
                'icon' => 'zap',
            ],
        ],
    ];

    protected $queryString = [
        'selectedMarketplaces' => ['except' => [], 'as' => 'marketplaces'],
        'previewVariant' => ['except' => null, 'as' => 'preview'],
    ];

    public function mount()
    {
        // Initialize default templates
        $this->titleTemplate = '[Brand] [ProductName] - [Color] [Size] | Premium Quality [Material]';
        $this->descriptionTemplate = 'High-quality [Material] [ProductName] in [Color]. Perfect for [RoomType]. [Features]';

        // Load default marketplaces
        if (empty($this->selectedMarketplaces)) {
            $this->selectedMarketplaces = Marketplace::active()->pluck('id')->toArray();
        }
    }

    public function previewTitle($variantId)
    {
        $this->previewVariant = $variantId;
        $this->showTitlePreview = true;
    }

    public function clearPreview()
    {
        $this->previewVariant = null;
        $this->showTitlePreview = false;
    }

    public function generateTitles()
    {
        $selectedVariants = $this->getSelectedVariants();

        if (empty($selectedVariants)) {
            session()->flash('error', 'Please select variants from the Overview tab first.');

            return;
        }

        // In a real implementation, this would generate titles for selected variants
        session()->flash('message', 'Generated titles for '.count($selectedVariants).' variants.');
    }

    public function render()
    {
        $selectedVariants = $this->getSelectedVariants();
        $marketplaces = Marketplace::active()->get();

        $previewVariantModel = null;
        if ($this->previewVariant) {
            $previewVariantModel = ProductVariant::with(['product', 'attributes'])->find($this->previewVariant);
        }

        return view('livewire.operations.bulk-operations-templates', [
            'tabs' => $this->getTabsForNavigation(),
            'selectedVariants' => $selectedVariants,
            'selectedVariantsCount' => count($selectedVariants),
            'marketplaces' => $marketplaces,
            'previewVariantModel' => $previewVariantModel,
        ]);
    }
}
