<?php

namespace App\Livewire\Components;

use App\Models\AttributeDefinition;
use App\Models\Product;
use App\Services\AttributeInheritanceService;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * 🏷️ ATTRIBUTES CARD COMPONENT
 *
 * Displays and manages attributes for products and variants.
 * Shows inheritance relationships and allows editing attribute values.
 */
class AttributesCard extends Component
{
    public Model $model;

    public string $modelType;

    public bool $showInheritance = true;

    public bool $allowEditing = true;

    public array $editingAttribute = [];

    public array $attributeValues = [];

    protected AttributeInheritanceService $inheritanceService;

    public function boot(AttributeInheritanceService $inheritanceService)
    {
        $this->inheritanceService = $inheritanceService;
    }

    public function mount(Model $model, array $options = [])
    {
        $this->model = $model;
        $this->modelType = $model instanceof Product ? 'product' : 'variant';
        $this->showInheritance = $options['show_inheritance'] ?? true;
        $this->allowEditing = $options['allow_editing'] ?? true;

        $this->loadAttributeValues();
    }

    public function render()
    {
        $attributes = $this->getAttributesForDisplay();
        $availableDefinitions = $this->getAvailableAttributeDefinitions();

        return view('livewire.components.attributes-card', [
            'attributes' => $attributes,
            'availableDefinitions' => $availableDefinitions,
            'inheritanceSummary' => $this->getInheritanceSummary(),
        ]);
    }

    /**
     * Load current attribute values into component state
     */
    protected function loadAttributeValues(): void
    {
        $attributes = $this->model->validAttributes()->with('attributeDefinition')->get();

        foreach ($attributes as $attribute) {
            $key = $attribute->getAttributeKey();
            $this->attributeValues[$key] = [
                'value' => $attribute->getTypedValue(),
                'display_value' => $attribute->display_value,
                'is_inherited' => $attribute->is_inherited ?? false,
                'is_override' => $attribute->is_override ?? false,
                'source' => $attribute->source,
            ];
        }
    }

    /**
     * Get attributes formatted for display
     */
    protected function getAttributesForDisplay(): array
    {
        $attributes = [];
        $definitions = AttributeDefinition::active()->orderedForDisplay()->get();

        foreach ($definitions as $definition) {
            $key = $definition->key;
            $currentValue = $this->attributeValues[$key] ?? null;

            // For variants, check inheritance
            $inheritanceInfo = null;
            if ($this->modelType === 'variant' && $this->showInheritance) {
                $inheritanceInfo = $this->getInheritanceInfoForAttribute($key);
            }

            $attributes[] = [
                'definition' => $definition,
                'current_value' => $currentValue,
                'inheritance_info' => $inheritanceInfo,
                'is_editing' => isset($this->editingAttribute[$key]),
            ];
        }

        return $attributes;
    }

    /**
     * Get inheritance info for a specific attribute
     */
    protected function getInheritanceInfoForAttribute(string $key): ?array
    {
        if ($this->modelType !== 'variant') {
            return null;
        }

        $variant = $this->model;
        if (! $variant->product) {
            return null;
        }

        $attributeDefinition = AttributeDefinition::findByKey($key);
        if (! $attributeDefinition || ! $attributeDefinition->supportsInheritance()) {
            return null;
        }

        $productAttribute = $variant->product->attributes()
            ->where('attribute_definition_id', $attributeDefinition->id)
            ->first();

        return [
            'can_inherit' => true,
            'has_parent_value' => (bool) $productAttribute,
            'parent_value' => $productAttribute?->getTypedValue(),
            'parent_display_value' => $productAttribute?->display_value,
            'inheritance_strategy' => $attributeDefinition->getInheritanceStrategy(),
        ];
    }

    /**
     * Get available attribute definitions that aren't set yet
     */
    protected function getAvailableAttributeDefinitions(): array
    {
        $currentKeys = array_keys($this->attributeValues);

        return AttributeDefinition::active()
            ->whereNotIn('key', $currentKeys)
            ->orderedForDisplay()
            ->get()
            ->map(function ($definition) {
                return [
                    'definition' => $definition,
                    'ui_config' => $definition->getUIConfig(),
                ];
            })
            ->toArray();
    }

    /**
     * Get inheritance summary for variant
     */
    protected function getInheritanceSummary(): ?array
    {
        if ($this->modelType !== 'variant' || ! $this->showInheritance) {
            return null;
        }

        return $this->model->getInheritanceSummary();
    }

    /**
     * Start editing an attribute
     */
    public function editAttribute(string $key): void
    {
        if (! $this->allowEditing) {
            return;
        }

        $definition = AttributeDefinition::findByKey($key);
        if (! $definition) {
            return;
        }

        $currentValue = $this->attributeValues[$key]['value'] ?? null;

        $this->editingAttribute[$key] = [
            'value' => $currentValue,
            'original_value' => $currentValue,
            'definition' => $definition,
            'ui_config' => $definition->getUIConfig(),
        ];
    }

    /**
     * Cancel editing an attribute
     */
    public function cancelEditAttribute(string $key): void
    {
        unset($this->editingAttribute[$key]);
    }

    /**
     * Save attribute value
     */
    public function saveAttribute(string $key): void
    {
        if (! isset($this->editingAttribute[$key])) {
            return;
        }

        $newValue = $this->editingAttribute[$key]['value'];

        try {
            if (! method_exists($this->model, 'setAttributeValue')) {
                throw new \Exception('Model does not support attributes');
            }

            $result = $this->model->setAttributeValue($key, $newValue);

            if ($result !== null) {
                $this->loadAttributeValues();
                unset($this->editingAttribute[$key]);

                $this->dispatch('attribute-updated', [
                    'key' => $key,
                    'value' => $newValue,
                    'model_type' => $this->modelType,
                    'model_id' => $this->model->id,
                ]);

                session()->flash('success', 'Attribute updated successfully');
            } else {
                session()->flash('error', 'Failed to update attribute');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Error updating attribute: '.$e->getMessage());
        }
    }

    /**
     * Add a new attribute
     */
    public function addAttribute(string $key): void
    {
        if (! $this->allowEditing) {
            return;
        }

        $definition = AttributeDefinition::findByKey($key);
        if (! $definition) {
            return;
        }

        $this->editingAttribute[$key] = [
            'value' => $definition->default_value,
            'original_value' => null,
            'definition' => $definition,
            'ui_config' => $definition->getUIConfig(),
        ];
    }

    /**
     * Inherit attribute from parent product (variants only)
     */
    public function inheritAttribute(string $key): void
    {
        if ($this->modelType !== 'variant') {
            return;
        }

        try {
            $result = $this->inheritanceService->inheritAttributesForVariant($this->model, [
                'attributes' => [$key],
                'force' => true,
            ]);

            if (! empty($result['inherited'])) {
                $this->loadAttributeValues();

                $this->dispatch('attribute-inherited', [
                    'key' => $key,
                    'model_id' => $this->model->id,
                ]);

                session()->flash('success', 'Attribute inherited successfully');
            } else {
                $error = $result['errors'][$key] ?? 'Unable to inherit attribute';
                session()->flash('error', $error);
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Error inheriting attribute: '.$e->getMessage());
        }
    }

    /**
     * Override inherited attribute (variants only)
     */
    public function overrideAttribute(string $key): void
    {
        if ($this->modelType !== 'variant') {
            return;
        }

        $currentValue = $this->attributeValues[$key]['value'] ?? null;
        $this->editAttribute($key);
    }

    /**
     * Clear attribute override and revert to inherited value
     */
    public function clearOverride(string $key): void
    {
        if ($this->modelType !== 'variant') {
            return;
        }

        try {
            $success = $this->model->clearAttributeOverride($key);

            if ($success) {
                $this->loadAttributeValues();

                $this->dispatch('attribute-override-cleared', [
                    'key' => $key,
                    'model_id' => $this->model->id,
                ]);

                session()->flash('success', 'Override cleared successfully');
            } else {
                session()->flash('error', 'Failed to clear override');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Error clearing override: '.$e->getMessage());
        }
    }

    /**
     * Refresh inheritance for all attributes (variants only)
     */
    public function refreshInheritance(): void
    {
        if ($this->modelType !== 'variant') {
            return;
        }

        try {
            $result = $this->inheritanceService->refreshInheritanceForVariant($this->model);

            $refreshedCount = count($result['refreshed']);
            $errorCount = count($result['errors']);

            if ($refreshedCount > 0) {
                $this->loadAttributeValues();

                $this->dispatch('inheritance-refreshed', [
                    'model_id' => $this->model->id,
                    'refreshed_count' => $refreshedCount,
                ]);

                session()->flash('success', "Refreshed {$refreshedCount} inherited attributes");
            } elseif ($errorCount > 0) {
                session()->flash('error', "Failed to refresh {$errorCount} attributes");
            } else {
                session()->flash('info', 'All inherited attributes are up to date');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Error refreshing inheritance: '.$e->getMessage());
        }
    }

    /**
     * Listen for external attribute updates
     */
    #[On('product-updated')]
    #[On('variant-updated')]
    public function handleModelUpdated(): void
    {
        $this->loadAttributeValues();
    }
}
