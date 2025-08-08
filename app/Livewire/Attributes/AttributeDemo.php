<?php

namespace App\Livewire\Attributes;

use App\Models\AttributeDefinition;
use App\Services\AttributeService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class AttributeDemo extends Component
{
    public $selectedCategory = '';
    public $selectedAttribute = '';
    public $attributeValue = '';
    public $validationResult = null;

    public function mount()
    {
        $this->selectedCategory = 'appearance';
    }

    public function updatedSelectedAttribute()
    {
        $this->attributeValue = '';
        $this->validationResult = null;
    }

    public function validateValue()
    {
        if (!$this->selectedAttribute || !$this->attributeValue) {
            $this->validationResult = null;
            return;
        }

        $attributeService = new AttributeService();
        $isValid = $attributeService->validateAttributeValue($this->selectedAttribute, $this->attributeValue);
        
        $this->validationResult = [
            'valid' => $isValid,
            'message' => $isValid ? 'Valid value!' : 'Invalid value for this attribute.',
            'formatted' => $attributeService->getFormattedValue($this->selectedAttribute, $this->attributeValue)
        ];
    }

    public function render()
    {
        $attributeService = new AttributeService();
        
        $categorizedAttributes = $attributeService->getAttributesByCategory();
        $categories = $categorizedAttributes->keys();
        
        $attributesInCategory = $this->selectedCategory 
            ? $categorizedAttributes->get($this->selectedCategory, collect())
            : collect();
            
        $currentAttribute = $this->selectedAttribute 
            ? AttributeDefinition::where('key', $this->selectedAttribute)->first()
            : null;
            
        $attributeOptions = $currentAttribute && isset($currentAttribute->validation_rules['options'])
            ? $currentAttribute->validation_rules['options']
            : [];

        $coreAttributes = $attributeService->getCoreWindowTreatmentAttributes();

        return view('livewire.attributes.attribute-demo', [
            'categories' => $categories,
            'attributesInCategory' => $attributesInCategory,
            'currentAttribute' => $currentAttribute,
            'attributeOptions' => $attributeOptions,
            'coreAttributes' => $coreAttributes,
        ]);
    }
}