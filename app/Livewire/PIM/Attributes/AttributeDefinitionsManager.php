<?php

namespace App\Livewire\Pim\Attributes;

use App\Models\AttributeDefinition;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class AttributeDefinitionsManager extends Component
{
    use WithPagination;

    public $search = '';

    public $categoryFilter = '';

    public $appliesFilter = '';

    public $activeFilter = 'active';

    // Modal properties
    public $showModal = false;

    public $editingAttribute = null;

    public $modalTitle = '';

    // Form properties
    public $key = '';

    public $label = '';

    public $data_type = 'string';

    public $category = '';

    public $applies_to = 'both';

    public $is_required = false;

    public $description = '';

    public $sort_order = 0;

    public $is_active = true;

    // Validation rules
    public $validationRules = [];

    public $min_value = '';

    public $max_value = '';

    public $options = '';

    protected $rules = [
        'key' => 'required|string|max:100',
        'label' => 'required|string|max:255',
        'data_type' => 'required|in:string,number,boolean,json',
        'category' => 'required|string|max:100',
        'applies_to' => 'required|in:product,variant,both',
        'is_required' => 'boolean',
        'description' => 'nullable|string|max:1000',
        'sort_order' => 'required|integer|min:0',
        'is_active' => 'boolean',
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingCategoryFilter()
    {
        $this->resetPage();
    }

    public function updatingAppliesFilter()
    {
        $this->resetPage();
    }

    public function updatingActiveFilter()
    {
        $this->resetPage();
    }

    public function createAttribute()
    {
        $this->resetForm();
        $this->modalTitle = 'Create Attribute Definition';
        $this->showModal = true;
    }

    public function editAttribute($attributeId)
    {
        $this->editingAttribute = AttributeDefinition::findOrFail($attributeId);
        $this->fillForm($this->editingAttribute);
        $this->modalTitle = 'Edit Attribute Definition';
        $this->showModal = true;
    }

    public function saveAttribute()
    {
        $this->validate();

        // Build validation rules array
        $validationRules = [];
        if ($this->data_type === 'number') {
            if ($this->min_value !== '') {
                $validationRules['min'] = (float) $this->min_value;
            }
            if ($this->max_value !== '') {
                $validationRules['max'] = (float) $this->max_value;
            }
        }
        if ($this->options !== '') {
            $validationRules['options'] = array_map('trim', explode(',', $this->options));
        }

        $data = [
            'key' => $this->key,
            'label' => $this->label,
            'data_type' => $this->data_type,
            'category' => $this->category,
            'applies_to' => $this->applies_to,
            'is_required' => $this->is_required,
            'validation_rules' => $validationRules ?: null,
            'description' => $this->description ?: null,
            'sort_order' => (int) $this->sort_order,
            'is_active' => $this->is_active,
        ];

        if ($this->editingAttribute) {
            $this->editingAttribute->update($data);
            session()->flash('success', 'Attribute definition updated successfully!');
        } else {
            // Check for unique key
            if (AttributeDefinition::where('key', $this->key)->exists()) {
                $this->addError('key', 'This key already exists.');

                return;
            }

            AttributeDefinition::create($data);
            session()->flash('success', 'Attribute definition created successfully!');
        }

        $this->closeModal();
    }

    public function deleteAttribute($attributeId)
    {
        $attribute = AttributeDefinition::findOrFail($attributeId);
        $attribute->delete();

        session()->flash('success', 'Attribute definition deleted successfully!');
    }

    public function toggleStatus($attributeId)
    {
        $attribute = AttributeDefinition::findOrFail($attributeId);
        $attribute->update(['is_active' => ! $attribute->is_active]);

        session()->flash('success', 'Attribute status updated successfully!');
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->editingAttribute = null;
        $this->resetForm();
        $this->resetErrorBag();
    }

    private function resetForm()
    {
        $this->key = '';
        $this->label = '';
        $this->data_type = 'string';
        $this->category = '';
        $this->applies_to = 'both';
        $this->is_required = false;
        $this->description = '';
        $this->sort_order = AttributeDefinition::max('sort_order') + 10 ?? 10;
        $this->is_active = true;
        $this->min_value = '';
        $this->max_value = '';
        $this->options = '';
    }

    private function fillForm(AttributeDefinition $attribute)
    {
        $this->key = $attribute->key;
        $this->label = $attribute->label;
        $this->data_type = $attribute->data_type;
        $this->category = $attribute->category;
        $this->applies_to = $attribute->applies_to;
        $this->is_required = $attribute->is_required;
        $this->description = $attribute->description ?? '';
        $this->sort_order = $attribute->sort_order;
        $this->is_active = $attribute->is_active;

        // Fill validation rules
        $rules = $attribute->validation_rules ?? [];
        $this->min_value = $rules['min'] ?? '';
        $this->max_value = $rules['max'] ?? '';
        $this->options = isset($rules['options']) ? implode(', ', $rules['options']) : '';
    }

    private function getAttributesQuery()
    {
        $query = AttributeDefinition::query();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('key', 'like', '%'.$this->search.'%')
                    ->orWhere('label', 'like', '%'.$this->search.'%')
                    ->orWhere('description', 'like', '%'.$this->search.'%');
            });
        }

        if ($this->categoryFilter) {
            $query->where('category', $this->categoryFilter);
        }

        if ($this->appliesFilter) {
            $query->where('applies_to', $this->appliesFilter);
        }

        if ($this->activeFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($this->activeFilter === 'inactive') {
            $query->where('is_active', false);
        }

        return $query->ordered();
    }

    public function render()
    {
        $attributes = $this->getAttributesQuery()->paginate(20);

        // Get categories for filter
        $categories = AttributeDefinition::distinct()->pluck('category')->filter()->sort();

        // Calculate statistics
        $stats = [
            'total' => AttributeDefinition::count(),
            'active' => AttributeDefinition::where('is_active', true)->count(),
            'product_only' => AttributeDefinition::where('applies_to', 'product')->count(),
            'variant_only' => AttributeDefinition::where('applies_to', 'variant')->count(),
            'both' => AttributeDefinition::where('applies_to', 'both')->count(),
        ];

        return view('livewire.pim.attributes.attribute-definitions-manager', [
            'attributes' => $attributes,
            'categories' => $categories,
            'stats' => $stats,
        ]);
    }
}
