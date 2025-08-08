<?php

namespace App\Livewire\Products;

use App\Builders\Products\ProductBuilder;
use App\Models\Product;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Product Edit Livewire Component
 * 
 * Form for editing existing products using the ProductBuilder pattern.
 * Demonstrates clean validation and fluent API usage for updates.
 * 
 * @package App\Livewire\Products
 */
#[Layout('components.layouts.app')]
#[Title('Edit Product')]
class ProductEdit extends Component
{
    /**
     * Product being edited
     * 
     * @var Product
     */
    public Product $product;
    
    /**
     * Product name
     * 
     * @var string
     */
    #[Validate('required|string|max:255')]
    public string $name = '';
    
    /**
     * Product slug
     * 
     * @var string
     */
    public string $slug = '';
    
    /**
     * Product parent SKU
     * 
     * @var string
     */
    public string $parent_sku = '';
    
    /**
     * Product description
     * 
     * @var string
     */
    #[Validate('nullable|string')]
    public string $description = '';
    
    /**
     * Product status
     * 
     * @var string
     */
    #[Validate('required|in:draft,active,inactive,archived')]
    public string $status = 'draft';
    
    /**
     * Product features (up to 5)
     * 
     * @var array
     */
    #[Validate('nullable|array|max:5')]
    #[Validate('features.*', 'nullable|string|max:255')]
    public array $features = ['', '', '', '', ''];
    
    /**
     * Product details (up to 5)
     * 
     * @var array
     */
    #[Validate('nullable|array|max:5')]
    #[Validate('details.*', 'nullable|string|max:255')]
    public array $details = ['', '', '', '', ''];
    
    /**
     * Available status options
     * 
     * @var array
     */
    public array $statusOptions = [
        'draft' => 'Draft',
        'active' => 'Active',
        'inactive' => 'Inactive',
        'archived' => 'Archived',
    ];
    
    /**
     * Form processing state
     * 
     * @var bool
     */
    public bool $processing = false;
    
    /**
     * Track if the product has been modified
     * 
     * @var bool
     */
    public bool $hasChanges = false;
    
    /**
     * Mount the component with product data
     * 
     * @param Product $product
     * @return void
     */
    public function mount(Product $product): void
    {
        $this->product = $product;
        
        // Populate form with existing data
        $this->name = $product->name;
        $this->slug = $product->slug ?? '';
        $this->parent_sku = $product->parent_sku ?? '';
        $this->description = $product->description ?? '';
        $this->status = $product->status;
        
        // Populate features
        $this->features = [
            $product->product_features_1 ?? '',
            $product->product_features_2 ?? '',
            $product->product_features_3 ?? '',
            $product->product_features_4 ?? '',
            $product->product_features_5 ?? '',
        ];
        
        // Populate details
        $this->details = [
            $product->product_details_1 ?? '',
            $product->product_details_2 ?? '',
            $product->product_details_3 ?? '',
            $product->product_details_4 ?? '',
            $product->product_details_5 ?? '',
        ];
    }
    
    /**
     * Get dynamic validation rules
     * 
     * @return array
     */
    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:products,slug,' . $this->product->id,
            'parent_sku' => 'nullable|string|max:100|unique:products,parent_sku,' . $this->product->id,
            'description' => 'nullable|string',
            'status' => 'required|in:draft,active,inactive,archived',
            'features' => 'nullable|array|max:5',
            'features.*' => 'nullable|string|max:255',
            'details' => 'nullable|array|max:5',
            'details.*' => 'nullable|string|max:255',
        ];
    }
    
    /**
     * Auto-generate slug from name if slug is empty
     * 
     * @param string $value
     * @return void
     */
    public function updatedName(string $value): void
    {
        if (empty($this->slug)) {
            $this->slug = \Str::slug($value);
        }
        $this->hasChanges = true;
    }
    
    /**
     * Track changes for any field update
     * 
     * @param string $property
     * @param mixed $value
     * @return void
     */
    public function updated($property, $value): void
    {
        $this->hasChanges = true;
    }
    
    /**
     * Add feature field
     * 
     * @return void
     */
    public function addFeature(): void
    {
        if (count($this->features) < 5) {
            $this->features[] = '';
        }
        $this->hasChanges = true;
    }
    
    /**
     * Remove feature field
     * 
     * @param int $index
     * @return void
     */
    public function removeFeature(int $index): void
    {
        if (isset($this->features[$index])) {
            unset($this->features[$index]);
            $this->features = array_values($this->features); // Re-index array
        }
        $this->hasChanges = true;
    }
    
    /**
     * Add detail field
     * 
     * @return void
     */
    public function addDetail(): void
    {
        if (count($this->details) < 5) {
            $this->details[] = '';
        }
        $this->hasChanges = true;
    }
    
    /**
     * Remove detail field
     * 
     * @param int $index
     * @return void
     */
    public function removeDetail(int $index): void
    {
        if (isset($this->details[$index])) {
            unset($this->details[$index]);
            $this->details = array_values($this->details); // Re-index array
        }
        $this->hasChanges = true;
    }
    
    /**
     * Update the product
     * 
     * @return void
     */
    public function save(): void
    {
        $this->processing = true;
        
        try {
            $this->validate();
            
            $builder = ProductBuilder::update($this->product)
                ->name($this->name)
                ->status($this->status);
            
            // Optional fields - set to null if empty
            $builder->slug($this->slug ?: null);
            $builder->sku($this->parent_sku ?: null);
            $builder->description($this->description ?: null);
            
            // Filter out empty features and details
            $filteredFeatures = array_filter($this->features, fn($feature) => !empty(trim($feature)));
            $builder->features($filteredFeatures);
            
            $filteredDetails = array_filter($this->details, fn($detail) => !empty(trim($detail)));
            $builder->details($filteredDetails);
            
            $product = $builder->execute();
            
            $this->hasChanges = false;
            
            // Dispatch success event
            $this->dispatch('product-updated', productId: $product->id, productName: $product->name);
            
            // Flash success message
            session()->flash('success', "Product '{$product->name}' updated successfully.");
            
            // Redirect to product show page
            $this->redirect(route('products.show', $product), navigate: true);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->processing = false;
            throw $e;
            
        } catch (\Exception $e) {
            $this->processing = false;
            
            $this->dispatch('product-update-failed', error: $e->getMessage());
            
            // Add error to the session
            session()->flash('error', 'Failed to update product: ' . $e->getMessage());
        }
    }
    
    /**
     * Save and continue editing
     * 
     * @return void
     */
    public function saveAndContinue(): void
    {
        $this->processing = true;
        
        try {
            $this->validate();
            
            $builder = ProductBuilder::update($this->product)
                ->name($this->name)
                ->status($this->status);
            
            // Optional fields - set to null if empty
            $builder->slug($this->slug ?: null);
            $builder->sku($this->parent_sku ?: null);
            $builder->description($this->description ?: null);
            
            // Filter out empty features and details
            $filteredFeatures = array_filter($this->features, fn($feature) => !empty(trim($feature)));
            $builder->features($filteredFeatures);
            
            $filteredDetails = array_filter($this->details, fn($detail) => !empty(trim($detail)));
            $builder->details($filteredDetails);
            
            $this->product = $builder->execute();
            
            $this->hasChanges = false;
            $this->processing = false;
            
            // Dispatch success event
            $this->dispatch('product-updated', productId: $this->product->id, productName: $this->product->name);
            
            // Flash success message
            session()->flash('success', "Product '{$this->product->name}' updated successfully.");
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->processing = false;
            throw $e;
            
        } catch (\Exception $e) {
            $this->processing = false;
            
            $this->dispatch('product-update-failed', error: $e->getMessage());
            
            // Add error to the session
            session()->flash('error', 'Failed to update product: ' . $e->getMessage());
        }
    }
    
    /**
     * Cancel and go back
     * 
     * @return void
     */
    public function cancel(): void
    {
        if ($this->hasChanges) {
            $this->dispatch('confirm-navigation-with-changes');
        } else {
            $this->redirect(route('products.show', $this->product), navigate: true);
        }
    }
    
    /**
     * Force navigation (after confirmation)
     * 
     * @return void
     */
    public function forceCancel(): void
    {
        $this->redirect(route('products.show', $this->product), navigate: true);
    }
    
    /**
     * Get filtered features count
     * 
     * @return int
     */
    public function getFilteredFeaturesCount(): int
    {
        return count(array_filter($this->features, fn($feature) => !empty(trim($feature))));
    }
    
    /**
     * Get filtered details count
     * 
     * @return int
     */
    public function getFilteredDetailsCount(): int
    {
        return count(array_filter($this->details, fn($detail) => !empty(trim($detail))));
    }
    
    /**
     * Render the component
     * 
     * @return \Illuminate\View\View
     */
    public function render()
    {
        return view('livewire.products.product-edit');
    }
}