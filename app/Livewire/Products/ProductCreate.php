<?php

namespace App\Livewire\Products;

use App\Builders\Products\ProductBuilder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Product Create Livewire Component
 * 
 * Form for creating new products using the ProductBuilder pattern.
 * Demonstrates clean validation and fluent API usage.
 * 
 * @package App\Livewire\Products
 */
#[Layout('components.layouts.app')]
#[Title('Create Product')]
class ProductCreate extends Component
{
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
    #[Validate('nullable|string|max:255|unique:products,slug')]
    public string $slug = '';
    
    /**
     * Product parent SKU
     * 
     * @var string
     */
    #[Validate('nullable|string|max:100|unique:products,parent_sku')]
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
     * Auto-generate slug from name
     * 
     * @param string $value
     * @return void
     */
    public function updatedName(string $value): void
    {
        if (empty($this->slug)) {
            $this->slug = \Str::slug($value);
        }
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
    }
    
    /**
     * Save as draft
     * 
     * @return void
     */
    public function saveAsDraft(): void
    {
        $this->status = 'draft';
        $this->save();
    }
    
    /**
     * Save and publish
     * 
     * @return void
     */
    public function saveAndPublish(): void
    {
        $this->status = 'active';
        $this->save();
    }
    
    /**
     * Create the product
     * 
     * @return void
     */
    public function save(): void
    {
        $this->processing = true;
        
        try {
            $this->validate();
            
            $builder = ProductBuilder::create()
                ->name($this->name)
                ->status($this->status);
            
            // Optional fields
            if (!empty($this->slug)) {
                $builder->slug($this->slug);
            }
            
            if (!empty($this->parent_sku)) {
                $builder->sku($this->parent_sku);
            }
            
            if (!empty($this->description)) {
                $builder->description($this->description);
            }
            
            // Filter out empty features and details
            $filteredFeatures = array_filter($this->features, fn($feature) => !empty(trim($feature)));
            if (!empty($filteredFeatures)) {
                $builder->features($filteredFeatures);
            }
            
            $filteredDetails = array_filter($this->details, fn($detail) => !empty(trim($detail)));
            if (!empty($filteredDetails)) {
                $builder->details($filteredDetails);
            }
            
            $product = $builder->execute();
            
            // Dispatch success event
            $this->dispatch('product-created', productId: $product->id, productName: $product->name);
            
            // Redirect to product show page
            $this->redirect(route('products.show', $product), navigate: true);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->processing = false;
            throw $e;
            
        } catch (\Exception $e) {
            $this->processing = false;
            
            $this->dispatch('product-create-failed', error: $e->getMessage());
            
            // Add error to the session
            session()->flash('error', 'Failed to create product: ' . $e->getMessage());
        }
    }
    
    /**
     * Cancel and go back
     * 
     * @return void
     */
    public function cancel(): void
    {
        $this->redirect(route('products.index'), navigate: true);
    }
    
    /**
     * Reset form
     * 
     * @return void
     */
    public function resetForm(): void
    {
        $this->reset([
            'name',
            'slug',
            'parent_sku',
            'description',
            'status',
            'features',
            'details',
            'processing'
        ]);
        
        $this->features = ['', '', '', '', ''];
        $this->details = ['', '', '', '', ''];
        $this->status = 'draft';
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
        return view('livewire.products.product-create');
    }
}