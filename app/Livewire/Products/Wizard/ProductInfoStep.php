<?php

namespace App\Livewire\Products\Wizard;

use App\Http\Requests\ProductInfoStepRequest;
use App\Models\Product;
use App\Services\SkuGeneratorService;
use Livewire\Component;

/**
 * 📋 PRODUCT INFO STEP - Clean & Focused
 *
 * Handles the first step of product creation: basic product information.
 * Uses simple validation and event-driven communication with parent wizard.
 */
class ProductInfoStep extends Component
{
    // Form fields
    public string $name = '';
    public string $parent_sku = '';
    public string $description = '';
    public string $status = 'active';
    public string $image_url = '';
    
    // Real-time validation state
    public array $skuSuggestions = [];
    public bool $isValidatingSku = false;

    // Component state
    public array $stepData = [];

    public bool $isActive = false;

    public int $currentStep = 1;

    public bool $isEditMode = false;

    public ?Product $product = null;

    // Validation state
    public array $errors = [];

    /**
     * 🎪 MOUNT - Initialize with existing data
     */
    public function mount(
        array $stepData = [],
        bool $isActive = false,
        int $currentStep = 1,
        bool $isEditMode = false,
        array $productData = []
    ): void {
        $this->stepData = $stepData;
        $this->isActive = $isActive;
        $this->currentStep = $currentStep;
        $this->isEditMode = $isEditMode;

        // Convert product data array to Product model if provided
        if (! empty($productData) && isset($productData['id'])) {
            $this->product = Product::find($productData['id']);
        }

        // Populate form with existing data
        if (! empty($stepData)) {
            $this->name = $stepData['name'] ?? '';
            $this->parent_sku = $stepData['parent_sku'] ?? '';
            $this->description = $stepData['description'] ?? '';
            $this->status = $stepData['status'] ?? 'active';
            $this->image_url = $stepData['image_url'] ?? '';
        }
    }

    /**
     * 🎯 VALIDATE AND COMPLETE STEP
     */
    public function completeStep(): void
    {
        // Prepare data for validation
        $data = $this->getFormData();
        if ($this->product) {
            $data['product_id'] = $this->product->id;
        }
        
        // Use Laravel Form Request validation
        $validator = validator($data, (new ProductInfoStepRequest())->rules());
        
        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }
            
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Please fix the validation errors below.',
            ]);

            return;
        }

        // Clear errors and emit step completed event
        $this->resetErrorBag();
        $this->dispatch('step-completed', 1, $this->getFormData());
    }

    /**
     * 🔄 AUTO-SAVE ON FIELD CHANGES
     */
    public function updatedName(): void
    {
        $this->validateField('name');
        $this->emitDataUpdate();
    }

    public function updatedParentSku(): void
    {
        $this->validateParentSkuRealtime();
        $this->emitDataUpdate();
    }
    
    /**
     * Real-time SKU validation with suggestions
     */
    public function validateParentSkuRealtime(): void
    {
        $this->isValidatingSku = true;
        $this->skuSuggestions = [];
        
        if (empty($this->parent_sku)) {
            $this->isValidatingSku = false;
            return;
        }
        
        // Use Laravel validation
        $validator = validator(['parent_sku' => $this->parent_sku], [
            'parent_sku' => ['required', new \App\Rules\ParentSkuRule($this->product?->id)]
        ]);
        
        if ($validator->fails()) {
            $this->addError('parent_sku', $validator->errors()->first('parent_sku'));
            
            // Generate suggestions if SKU is taken
            $skuService = app(SkuGeneratorService::class);
            $this->skuSuggestions = $skuService->suggestAlternativeParentSkus($this->parent_sku);
        } else {
            $this->resetErrorBag('parent_sku');
        }
        
        $this->isValidatingSku = false;
    }

    public function updatedDescription(): void
    {
        $this->emitDataUpdate();
    }
    
    /**
     * Use a suggested SKU
     */
    public function useSuggestedSku(string $sku): void
    {
        $this->parent_sku = $sku;
        $this->skuSuggestions = [];
        $this->resetErrorBag('parent_sku');
        $this->emitDataUpdate();
    }

    public function updatedStatus(): void
    {
        $this->validateField('status');
        $this->emitDataUpdate();
    }

    public function updatedImageUrl(): void
    {
        $this->validateField('image_url');
        $this->emitDataUpdate();
    }

    /**
     * ✅ VALIDATE INDIVIDUAL FIELD
     */
    protected function validateField(string $field): void
    {
        // Use our Form Request rules for validation
        $data = $this->getFormData();
        if ($this->product) {
            $data['product_id'] = $this->product->id;
        }
        
        $rules = (new ProductInfoStepRequest())->rules();
        
        if (!isset($rules[$field])) {
            return;
        }
        
        $validator = validator([$field => $data[$field]], [$field => $rules[$field]]);
        
        if ($validator->fails()) {
            $this->addError($field, $validator->errors()->first($field));
        } else {
            $this->resetErrorBag($field);
        }
    }

    /**
     * 📡 EMIT DATA UPDATE TO PARENT
     */
    protected function emitDataUpdate(): void
    {
        $this->dispatch('product-info-updated', $this->getFormData());
    }

    /**
     * 📊 GET FORM DATA
     */
    protected function getFormData(): array
    {
        return [
            'name' => $this->name,
            'parent_sku' => $this->parent_sku,
            'description' => $this->description,
            'status' => $this->status,
            'image_url' => $this->image_url,
        ];
    }

    /**
     * 🎯 GET STATUS OPTIONS
     */
    public function getStatusOptions(): array
    {
        return [
            'active' => '✅ Active - Ready for sale',
            'draft' => '📝 Draft - Work in progress',
            'inactive' => '⏸️ Inactive - Hidden from sale',
            'archived' => '📦 Archived - Discontinued',
        ];
    }

    /**
     * 🎨 RENDER
     */
    public function render()
    {
        return view('livewire.products.wizard.product-info-step');
    }
}
