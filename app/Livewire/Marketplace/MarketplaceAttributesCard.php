<?php

namespace App\Livewire\Marketplace;

use App\Models\Product;
use App\Models\SyncAccount;
use App\Services\Marketplace\MarketplaceAttributeService;
use App\Services\Marketplace\MarketplaceTaxonomyService;
use Exception;
use Livewire\Component;

/**
 * 🏷️ MARKETPLACE ATTRIBUTES CARD
 *
 * Shows marketplace attribute assignments for a product.
 * Allows adding, editing, and removing marketplace-specific attributes.
 *
 * Integrates with marketplace taxonomy cache and validation system.
 */
class MarketplaceAttributesCard extends Component
{
    public Product $product;

    public ?int $selectedMarketplaceId = null;

    public array $marketplaces = [];

    public array $productAttributes = [];

    public array $availableAttributes = [];

    public array $missingRequired = [];

    public int $completionPercentage = 0;

    public ?array $readinessReport = null;

    // Form fields for adding new attributes
    public ?string $newAttributeKey = null;

    public string $newAttributeValue = '';

    public string $newAttributeDisplayValue = '';

    public bool $showAddForm = false;

    // Edit mode
    public ?int $editingAttributeId = null;

    public string $editValue = '';

    public string $editDisplayValue = '';

    protected ?MarketplaceAttributeService $attributeService = null;

    protected ?MarketplaceTaxonomyService $taxonomyService = null;

    public function mount(Product $product)
    {
        $this->product = $product;
        $this->initializeServices();

        $this->loadMarketplaces();

        // Auto-select first marketplace if available
        if (! empty($this->marketplaces)) {
            $this->selectedMarketplaceId = $this->marketplaces[0]['id'];
            $this->loadMarketplaceData();
        }
    }

    /**
     * 🔧 Initialize services
     */
    protected function initializeServices(): void
    {
        $this->attributeService = new MarketplaceAttributeService;
        $this->taxonomyService = new MarketplaceTaxonomyService;
    }

    /**
     * 🔧 Get attribute service (with lazy initialization)
     */
    public function getAttributeService(): MarketplaceAttributeService
    {
        if (! $this->attributeService) {
            $this->initializeServices();
        }

        return $this->attributeService;
    }

    /**
     * 🔧 Get taxonomy service (with lazy initialization)
     */
    public function getTaxonomyService(): MarketplaceTaxonomyService
    {
        if (! $this->taxonomyService) {
            $this->initializeServices();
        }

        return $this->taxonomyService;
    }

    /**
     * 🔧 Get selected marketplace object
     */
    public function getSelectedMarketplace(): ?SyncAccount
    {
        if (! $this->selectedMarketplaceId) {
            return null;
        }

        return SyncAccount::find($this->selectedMarketplaceId);
    }

    /**
     * 🔄 Load available marketplaces
     */
    public function loadMarketplaces(): void
    {
        $accounts = SyncAccount::where('is_active', true)
            ->select('id', 'name', 'channel')
            ->get();

        $this->marketplaces = $accounts->map(function ($account) {
            return [
                'id' => $account->id,
                'name' => $account->name,
                'channel' => $account->channel,
            ];
        })->toArray();

        \Log::info('MarketplaceAttributesCard: Loaded marketplaces', [
            'count' => count($this->marketplaces),
            'marketplaces' => $this->marketplaces,
        ]);
    }

    /**
     * 🔄 Marketplace selection changed
     */
    public function updatedSelectedMarketplaceId(): void
    {
        \Log::info('MarketplaceAttributesCard: Marketplace selection changed', [
            'product_id' => $this->product->id,
            'old_marketplace_id' => $this->selectedMarketplaceId ?? 'null',
            'new_marketplace_id' => $this->selectedMarketplaceId,
        ]);

        if ($this->selectedMarketplaceId) {
            $this->loadMarketplaceData();
        } else {
            $this->resetMarketplaceData();
        }
    }

    /**
     * 📊 Load marketplace-specific data
     */
    public function loadMarketplaceData(): void
    {
        $selectedMarketplace = $this->getSelectedMarketplace();
        if (! $selectedMarketplace) {
            \Log::warning('MarketplaceAttributesCard: No marketplace selected for data loading');

            return;
        }

        \Log::info('MarketplaceAttributesCard: Loading data for marketplace', [
            'marketplace_id' => $selectedMarketplace->id,
            'marketplace_name' => $selectedMarketplace->name,
            'product_id' => $this->product->id,
        ]);

        try {
            // Load product attributes for this marketplace
            $attributes = $this->getAttributeService()->getProductAttributes($this->product, $selectedMarketplace);
            $this->productAttributes = $attributes->map(function ($attr) {
                return [
                    'id' => $attr->id,
                    'key' => $attr->attribute_key,
                    'name' => $attr->attribute_name,
                    'value' => $attr->attribute_value,
                    'display_value' => $attr->getDisplayValue(),
                    'data_type' => $attr->data_type,
                    'is_required' => $attr->is_required,
                    'is_valid' => $attr->is_valid,
                    'assigned_at' => $attr->assigned_at->format('M j, Y'),
                ];
            })->toArray();

            // Load available attributes
            $available = $this->getTaxonomyService()->getAttributes($selectedMarketplace);
            $this->availableAttributes = $available->map(function ($attr) {
                return [
                    'key' => $attr->key,
                    'name' => $attr->name,
                    'description' => $attr->description,
                    'data_type' => $attr->data_type,
                    'is_required' => $attr->is_required,
                    'choices' => $attr->getChoices(),
                ];
            })->toArray();

            // Load missing required attributes
            $missing = $this->getAttributeService()->getMissingRequiredAttributes($this->product, $selectedMarketplace);
            $this->missingRequired = $missing->map(function ($attr) {
                return [
                    'key' => $attr->key,
                    'name' => $attr->name,
                    'description' => $attr->description,
                ];
            })->toArray();

            // Get completion percentage
            $this->completionPercentage = $this->getAttributeService()->getCompletionPercentage($this->product, $selectedMarketplace);

            // Get readiness report
            $this->readinessReport = $this->getAttributeService()->getMarketplaceReadinessReport($this->product, $selectedMarketplace);

            \Log::info('MarketplaceAttributesCard: Data loaded successfully', [
                'marketplace_name' => $selectedMarketplace->name,
                'product_attributes_count' => count($this->productAttributes),
                'available_attributes_count' => count($this->availableAttributes),
                'missing_required_count' => count($this->missingRequired),
                'completion_percentage' => $this->completionPercentage,
                'readiness_score' => $this->readinessReport['readiness_score'] ?? 'null',
                'readiness_status' => $this->readinessReport['status'] ?? 'null',
            ]);

        } catch (Exception $e) {
            $this->dispatch('error', 'Failed to load marketplace data: '.$e->getMessage());
        }
    }

    /**
     * 🔄 Reset marketplace data
     */
    public function resetMarketplaceData(): void
    {
        \Log::info('MarketplaceAttributesCard: Resetting marketplace data');

        $this->productAttributes = [];
        $this->availableAttributes = [];
        $this->missingRequired = [];
        $this->completionPercentage = 0;
        $this->readinessReport = null;
    }

    /**
     * 🔄 Debug: Force reload marketplace data
     */
    public function debugReloadData(): void
    {
        \Log::info('MarketplaceAttributesCard: Manual debug reload triggered', [
            'selectedMarketplaceId' => $this->selectedMarketplaceId,
        ]);

        // Clear taxonomy cache for this marketplace
        $selectedMarketplace = $this->getSelectedMarketplace();
        if ($selectedMarketplace) {
            $this->getTaxonomyService()->clearTaxonomyCache($selectedMarketplace);
        }

        $this->loadMarketplaceData();
        $this->dispatch('success', 'Data reloaded! Cache cleared.');
    }

    /**
     * ➕ Show add attribute form
     */
    public function showAddAttributeForm(): void
    {
        $this->showAddForm = true;
        $this->newAttributeKey = null;
        $this->newAttributeValue = '';
        $this->newAttributeDisplayValue = '';
    }

    /**
     * ❌ Cancel add attribute
     */
    public function cancelAddAttribute(): void
    {
        $this->showAddForm = false;
        $this->newAttributeKey = null;
        $this->newAttributeValue = '';
        $this->newAttributeDisplayValue = '';
    }

    /**
     * ✅ Add new attribute
     */
    public function addAttribute(): void
    {
        $selectedMarketplace = $this->getSelectedMarketplace();
        if (! $selectedMarketplace || ! $this->newAttributeKey) {
            $this->dispatch('error', 'Please select an attribute');

            return;
        }

        try {
            $this->getAttributeService()->assignAttribute(
                $this->product,
                $selectedMarketplace,
                $this->newAttributeKey,
                $this->newAttributeValue,
                [
                    'display_value' => $this->newAttributeDisplayValue ?: null,
                    'assigned_via' => 'manual',
                    'assigned_by' => auth()->id(),
                ]
            );

            $this->dispatch('success', 'Attribute added successfully! ✅');
            $this->cancelAddAttribute();
            $this->loadMarketplaceData();

        } catch (Exception $e) {
            $this->dispatch('error', 'Failed to add attribute: '.$e->getMessage());
        }
    }

    /**
     * ✏️ Start editing attribute
     */
    public function editAttribute(int $attributeId): void
    {
        $attribute = collect($this->productAttributes)->firstWhere('id', $attributeId);

        if ($attribute) {
            $this->editingAttributeId = $attributeId;
            $this->editValue = $attribute['value'];
            $this->editDisplayValue = $attribute['display_value'];
        }
    }

    /**
     * ❌ Cancel editing
     */
    public function cancelEdit(): void
    {
        $this->editingAttributeId = null;
        $this->editValue = '';
        $this->editDisplayValue = '';
    }

    /**
     * 💾 Save attribute edit
     */
    public function saveAttribute(): void
    {
        if (! $this->editingAttributeId) {
            return;
        }

        try {
            $attribute = \App\Models\MarketplaceProductAttribute::find($this->editingAttributeId);

            if ($attribute) {
                $this->getAttributeService()->updateAttribute(
                    $attribute,
                    $this->editValue,
                    [
                        'display_value' => $this->editDisplayValue ?: null,
                        'updated_via' => 'manual',
                        'updated_by' => auth()->id(),
                    ]
                );

                $this->dispatch('success', 'Attribute updated successfully! ✅');
                $this->cancelEdit();
                $this->loadMarketplaceData();
            }

        } catch (Exception $e) {
            $this->dispatch('error', 'Failed to update attribute: '.$e->getMessage());
        }
    }

    /**
     * 🗑️ Remove attribute
     */
    public function removeAttribute(int $attributeId): void
    {
        try {
            $attribute = \App\Models\MarketplaceProductAttribute::find($attributeId);

            if ($attribute) {
                $this->getAttributeService()->removeAttribute($attribute);
                $this->dispatch('success', 'Attribute removed successfully! 🗑️');
                $this->loadMarketplaceData();
            }

        } catch (Exception $e) {
            $this->dispatch('error', 'Failed to remove attribute: '.$e->getMessage());
        }
    }

    /**
     * 🤖 Auto-assign attributes
     */
    public function autoAssignAttributes(): void
    {
        $selectedMarketplace = $this->getSelectedMarketplace();
        if (! $selectedMarketplace) {
            $this->dispatch('error', 'Please select a marketplace');

            return;
        }

        try {
            $result = $this->getAttributeService()->autoAssignAttributes($this->product, $selectedMarketplace);

            $message = "Auto-assigned {$result['attributes_assigned']} attributes! 🤖";
            if (! empty($result['skipped'])) {
                $message .= " ({$result['skipped']} skipped)";
            }

            $this->dispatch('success', $message);
            $this->loadMarketplaceData();

        } catch (Exception $e) {
            $this->dispatch('error', 'Auto-assignment failed: '.$e->getMessage());
        }
    }

    /**
     * 🎨 Get readiness status color
     */
    public function getReadinessColor(): string
    {
        if (! $this->readinessReport) {
            return 'gray';
        }

        return match ($this->readinessReport['status']) {
            'ready' => 'green',
            'nearly_ready' => 'yellow',
            'needs_improvement' => 'orange',
            'not_ready' => 'red',
            default => 'gray',
        };
    }

    /**
     * 📋 Get available attributes for dropdown
     */
    public function getAvailableAttributesForSelect(): array
    {
        $assigned = collect($this->productAttributes)->pluck('key')->toArray();

        return collect($this->availableAttributes)
            ->filter(fn ($attr) => ! in_array($attr['key'], $assigned))
            ->map(fn ($attr) => [
                'value' => $attr['key'],
                'label' => $attr['name'].($attr['is_required'] ? ' *' : ''),
                'description' => $attr['description'],
            ])
            ->values()
            ->toArray();
    }

    public function render()
    {
        return view('livewire.marketplace.marketplace-attributes-card');
    }
}
