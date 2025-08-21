<?php

namespace App\Livewire\BulkOperations;

use Illuminate\Database\Eloquent\Model;

/**
 * 🚀 BULK ATTRIBUTE OPERATION
 *
 * Dedicated full-page component for bulk attribute management operations.
 * Supports bulk attribute updates, tag management, and metadata operations.
 * Handles both products and variants with appropriate attribute mapping.
 */
class BulkAttributeOperation extends BaseBulkOperation
{
    // Form data for attribute operations
    /** @var array<string, mixed> */
    public array $attributeData = [
        'operation_type' => 'update_attributes',
        'attribute_field' => '',
        'attribute_value' => '',
        'update_mode' => 'replace',
    ];

    // Predefined attribute options
    /** @var array<string, array<string, list<string>>> */
    public array $availableAttributes = [
        'products' => [
            'status' => ['active', 'draft', 'archived', 'inactive'], // ProductStatus enum values
            'category' => [],  // Will be populated dynamically
            'brand' => [],     // Will be populated dynamically
            'material' => ['Cotton', 'Polyester', 'Wool', 'Silk', 'Linen', 'Bamboo'],
            'tags' => [],      // Will be populated dynamically
        ],
        'variants' => [
            'status' => ['active', 'inactive'], // ProductVariant status values
            'color' => ['Red', 'Blue', 'Green', 'Yellow', 'Black', 'White', 'Gray', 'Pink', 'Purple', 'Orange', 'Brown', 'Beige'],
            'material' => ['Cotton', 'Polyester', 'Wool', 'Silk', 'Linen', 'Bamboo'],
            'finish' => ['Matte', 'Glossy', 'Satin', 'Textured', 'Smooth'],
        ],
    ];

    // Dynamic options loaded from database
    /** @var array<string, array<string>> */
    public array $dynamicOptions = [];

    /**
     * 🎯 Initialize attribute operation
     */
    public function mount(string $targetType, mixed $selectedItems): void
    {
        parent::mount($targetType, $selectedItems);
        $this->loadDynamicOptions();
    }

    /**
     * 🏷️ Apply bulk attribute operation
     */
    public function applyBulkAttributes(): void
    {
        if (empty($this->selectedItems)) {
            return;
        }

        $this->validate([
            'attributeData.operation_type' => 'required|in:update_attributes,clear_attributes,add_tags,remove_tags',
            'attributeData.attribute_field' => 'required_if:attributeData.operation_type,update_attributes,clear_attributes',
            'attributeData.attribute_value' => 'required_if:attributeData.operation_type,update_attributes,add_tags,remove_tags',
            'attributeData.update_mode' => 'required_if:attributeData.operation_type,update_attributes|in:replace,append,prepend',
        ]);

        $this->executeBulkOperation(
            operation: fn (Model $item) => $this->processItemAttributes($item),
            operationType: 'updated attributes for'
        );
    }

    /**
     * 🎨 Process attributes for individual item
     *
     * @param  \App\Models\Product|\App\Models\ProductVariant  $item
     */
    private function processItemAttributes(Model $item): void
    {
        switch ($this->attributeData['operation_type']) {
            case 'update_attributes':
                $this->updateItemAttribute($item);
                break;

            case 'clear_attributes':
                $this->clearItemAttribute($item);
                break;

            case 'add_tags':
                $this->addTagsToItem($item);
                break;

            case 'remove_tags':
                $this->removeTagsFromItem($item);
                break;
        }
    }

    /**
     * ✏️ Update individual item attribute
     *
     * @param  \App\Models\Product|\App\Models\ProductVariant  $item
     */
    private function updateItemAttribute(Model $item): void
    {
        $field = $this->attributeData['attribute_field'];
        $newValue = $this->attributeData['attribute_value'];
        $updateMode = $this->attributeData['update_mode'];

        // Validate field exists on model
        if (! $item->isFillable($field)) {
            return;
        }

        switch ($updateMode) {
            case 'replace':
                $item->update([$field => $newValue]);
                break;

            case 'append':
                $currentValue = $item->{$field} ?? '';
                $item->update([$field => $currentValue.' '.$newValue]);
                break;

            case 'prepend':
                $currentValue = $item->{$field} ?? '';
                $item->update([$field => $newValue.' '.$currentValue]);
                break;
        }
    }

    /**
     * 🗑️ Clear individual item attribute
     *
     * @param  \App\Models\Product|\App\Models\ProductVariant  $item
     */
    private function clearItemAttribute(Model $item): void
    {
        $field = $this->attributeData['attribute_field'];

        if ($item->isFillable($field)) {
            $item->update([$field => null]);
        }
    }

    /**
     * 🏷️ Add tags to item (products only)
     *
     * @param  \App\Models\Product|\App\Models\ProductVariant  $item
     */
    private function addTagsToItem(Model $item): void
    {
        if ($this->targetType !== 'products') {
            return;
        }

        $tags = explode(',', $this->attributeData['attribute_value']);
        $tags = array_map('trim', $tags);
        $tags = array_filter($tags);

        // Assuming a tags relationship exists
        if (method_exists($item, 'tags')) {
            foreach ($tags as $tagName) {
                // Create or find tag and attach
                $tag = \App\Models\Tag::firstOrCreate(['name' => $tagName]);
                $item->tags()->syncWithoutDetaching([$tag->id]);
            }
        }
    }

    /**
     * 🗑️ Remove tags from item (products only)
     *
     * @param  \App\Models\Product|\App\Models\ProductVariant  $item
     */
    private function removeTagsFromItem(Model $item): void
    {
        if ($this->targetType !== 'products') {
            return;
        }

        $tags = explode(',', $this->attributeData['attribute_value']);
        $tags = array_map('trim', $tags);
        $tags = array_filter($tags);

        // Assuming a tags relationship exists
        if (method_exists($item, 'tags')) {
            foreach ($tags as $tagName) {
                $tag = \App\Models\Tag::where('name', $tagName)->first();
                if ($tag) {
                    $item->tags()->detach($tag->id);
                }
            }
        }
    }

    /**
     * 📊 Load dynamic options from database
     */
    private function loadDynamicOptions(): void
    {
        // Load categories
        if (class_exists('App\\Models\\Category')) {
            $this->dynamicOptions['categories'] = \App\Models\Category::pluck('name')->toArray();
        }

        // Load existing tags
        if (class_exists('App\\Models\\Tag')) {
            $this->dynamicOptions['tags'] = \App\Models\Tag::pluck('name')->toArray();
        }

        // Load brands (if exists in products table)
        if ($this->targetType === 'products') {
            $this->dynamicOptions['brands'] = $this->getSelectedItemsCollection()
                ->whereNotNull('brand')
                ->pluck('brand')
                ->unique()
                ->values()
                ->toArray();
        }
    }

    /**
     * 🎯 Get available options for selected attribute field
     *
     * @return array<string>
     */
    public function getAvailableOptionsProperty(): array
    {
        $field = $this->attributeData['attribute_field'];

        // Return predefined options if available
        if (isset($this->availableAttributes[$this->targetType][$field])) {
            return $this->availableAttributes[$this->targetType][$field];
        }

        // Return dynamic options for special fields
        switch ($field) {
            case 'category':
            case 'category_id':
                return $this->dynamicOptions['categories'] ?? [];
            case 'brand':
                return $this->dynamicOptions['brands'] ?? [];
            case 'tags':
                return $this->dynamicOptions['tags'] ?? [];
            default:
                return [];
        }
    }

    /**
     * 📋 Get available fields for current target type
     *
     * @return array<string>
     */
    public function getAvailableFieldsProperty(): array
    {
        return array_keys($this->availableAttributes[$this->targetType]);
    }

    /**
     * 👁️ Update available options when field changes
     */
    public function updatedAttributeDataAttributeField(): void
    {
        $this->attributeData['attribute_value'] = '';
    }

    /**
     * 🧮 Get preview of what will be updated
     */
    public function getUpdatePreviewProperty(): string
    {
        $operationType = $this->attributeData['operation_type'] ?? '';
        $field = $this->attributeData['attribute_field'] ?? '';
        $value = $this->attributeData['attribute_value'] ?? '';

        // For clear operations, we only need the field, not the value
        if ($operationType === 'clear_attributes' && empty($field)) {
            return '';
        }

        // For other operations, we need both field and value
        if ($operationType !== 'clear_attributes' && (empty($field) || empty($value))) {
            return '';
        }

        $mode = $this->attributeData['update_mode'] ?? 'replace';

        switch ($this->attributeData['operation_type']) {
            case 'update_attributes':
                $modeText = match ($mode) {
                    'replace' => 'Set to',
                    'append' => 'Add to end:',
                    'prepend' => 'Add to beginning:',
                    default => 'Update to'
                };

                return "{$modeText} \"{$value}\"";

            case 'clear_attributes':
                return "Clear {$field} field";

            case 'add_tags':
                return "Add tags: {$value}";

            case 'remove_tags':
                return "Remove tags: {$value}";

            default:
                return '';
        }
    }

    /**
     * 🎨 Render the bulk attribute operation component
     */
    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.bulk-operations.bulk-attribute-operation');
    }
}
