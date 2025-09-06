<?php

namespace App\Livewire\Components;

use App\Models\AttributeDefinition;
use App\Models\Product;
use App\Services\Attributes\Facades\Attributes;
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
     * Load current attribute values using Attributes facade
     */
    protected function loadAttributeValues(): void
    {
        // Use the new Attributes facade to get all attributes
        $allAttributes = Attributes::for($this->model)->all();
        
        $this->attributeValues = [];
        
        foreach ($allAttributes as $key => $value) {
            // Get additional metadata by accessing the raw attribute if needed
            $rawAttribute = $this->model->validAttributes()
                ->whereHas('attributeDefinition', fn($q) => $q->where('key', $key))
                ->first();
                
            $this->attributeValues[$key] = [
                'value' => $value,
                'display_value' => $rawAttribute?->display_value ?? $value,
                'is_inherited' => $rawAttribute?->is_inherited ?? false,
                'is_override' => $rawAttribute?->is_override ?? false,
                'source' => $rawAttribute?->source ?? 'manual',
            ];
        }
    }

    /**
     * Get attributes formatted for display using the Attributes facade
     */
    protected function getAttributesForDisplay(): array
    {
        // Use the new facade's byGroup method for organized display
        $groupedAttributes = Attributes::for($this->model)->byGroup();
        $attributes = [];

        // Also get all active definitions for any missing attributes
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
                'group' => $definition->group ?? 'general',
            ];
        }

        return $attributes;
    }

    /**
     * Get attributes organized by groups using Attributes facade
     */
    public function getAttributesByGroup(): array
    {
        return Attributes::for($this->model)->byGroup();
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

        // For JSON attributes, format for editing
        $editValue = $currentValue;
        if ($definition->data_type === 'json' && $currentValue !== null) {
            // If it's already an array/object, encode it as pretty JSON
            if (is_array($currentValue) || is_object($currentValue)) {
                $editValue = json_encode($currentValue, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            } elseif (is_string($currentValue)) {
                // If it's a JSON string, try to decode and re-encode as pretty JSON
                $decoded = json_decode($currentValue, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $editValue = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                }
            }
        }

        $this->editingAttribute[$key] = [
            'value' => $editValue,
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
     * Save attribute value using Attributes facade
     */
    public function saveAttribute(string $key): void
    {
        if (! isset($this->editingAttribute[$key])) {
            return;
        }

        $newValue = $this->editingAttribute[$key]['value'];
        $definition = $this->editingAttribute[$key]['definition'];

        try {
            // Handle JSON attributes - validate and parse
            if ($definition->data_type === 'json' && !empty($newValue)) {
                $decoded = json_decode($newValue, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    session()->flash('error', 'Invalid JSON format: ' . json_last_error_msg());
                    return;
                }
                // Use the decoded value for storage
                $newValue = $decoded;
            }

            // Use the new Attributes facade with activity logging
            Attributes::for($this->model)
                ->source('manual_edit')
                ->log(['component' => 'AttributesCard'])
                ->key($key)
                ->value($newValue);

            // Success - reload data
            $this->loadAttributeValues();
            unset($this->editingAttribute[$key]);

            $this->dispatch('attribute-updated', [
                'key' => $key,
                'value' => $newValue,
                'model_type' => $this->modelType,
                'model_id' => $this->model->id,
            ]);

            session()->flash('success', 'Attribute updated successfully');
            
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

        // Start with the attribute's default value using the facade
        $defaultValue = $definition->default_value;
        
        // Add it immediately with the default value
        try {
            Attributes::for($this->model)
                ->source('manual_add')
                ->log(['component' => 'AttributesCard'])
                ->key($key)
                ->value($defaultValue);
                
            // Reload data and start editing
            $this->loadAttributeValues();
            
            $this->editingAttribute[$key] = [
                'value' => $defaultValue,
                'original_value' => null,
                'definition' => $definition,
                'ui_config' => $definition->getUIConfig(),
            ];
            
        } catch (\Exception $e) {
            session()->flash('error', 'Error adding attribute: '.$e->getMessage());
        }
    }

    /**
     * Delete an attribute using Attributes facade
     */
    public function deleteAttribute(string $key): void
    {
        if (! $this->allowEditing) {
            return;
        }

        try {
            Attributes::for($this->model)
                ->source('manual_delete')
                ->log(['component' => 'AttributesCard'])
                ->key($key)
                ->unset();

            // Success - reload data
            $this->loadAttributeValues();
            unset($this->editingAttribute[$key]);

            $this->dispatch('attribute-deleted', [
                'key' => $key,
                'model_type' => $this->modelType,
                'model_id' => $this->model->id,
            ]);

            session()->flash('success', 'Attribute deleted successfully');
            
        } catch (\Exception $e) {
            session()->flash('error', 'Error deleting attribute: '.$e->getMessage());
        }
    }

    /**
     * Bulk update multiple attributes using Attributes facade
     */
    public function bulkUpdateAttributes(array $attributes): void
    {
        if (! $this->allowEditing) {
            return;
        }

        try {
            Attributes::for($this->model)
                ->source('bulk_update')
                ->log([
                    'component' => 'AttributesCard',
                    'count' => count($attributes),
                ])
                ->keys(array_keys($attributes))
                ->value(array_values($attributes));

            // Success - reload data
            $this->loadAttributeValues();
            
            $this->dispatch('attributes-bulk-updated', [
                'keys' => array_keys($attributes),
                'count' => count($attributes),
                'model_type' => $this->modelType,
                'model_id' => $this->model->id,
            ]);

            $count = count($attributes);
            session()->flash('success', "Successfully updated {$count} attributes");
            
        } catch (\Exception $e) {
            session()->flash('error', 'Error updating attributes: '.$e->getMessage());
        }
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
