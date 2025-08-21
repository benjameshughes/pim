<?php

namespace App\Livewire\Products\Wizard;

use App\Actions\Products\Wizard\ValidateProductWizardStepAction;
use App\Models\Product;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * 📋 PRODUCT INFO STEP - Clean & Focused
 *
 * Handles the first step of product creation: basic product information.
 * Uses simple validation and event-driven communication with parent wizard.
 */
class ProductInfoStep extends Component
{
    // Form fields with validation
    #[Validate('required|min:3')]
    public string $name = '';

    #[Validate('nullable|string|min:2')]
    public string $parent_sku = '';

    #[Validate('nullable|string')]
    public string $description = '';

    #[Validate('required|in:draft,active,inactive,archived')]
    public string $status = 'active';

    #[Validate('nullable|url')]
    public string $image_url = '';

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
        // Validate using our action
        $validateAction = new ValidateProductWizardStepAction;
        $result = $validateAction->execute(1, $this->getFormData());

        if (! $result['data']['valid']) {
            $this->errors = $result['data']['errors'];
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Please fix the validation errors below.',
            ]);

            return;
        }

        // Clear errors and emit step completed event
        $this->errors = [];
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
        $this->validateField('parent_sku');
        $this->emitDataUpdate();
    }

    public function updatedDescription(): void
    {
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
        try {
            $this->validateOnly($field);
            // Clear field error if validation passes
            unset($this->errors[$field]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Set field error from Livewire validation
            $this->errors[$field] = $e->validator->errors()->first($field);
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
