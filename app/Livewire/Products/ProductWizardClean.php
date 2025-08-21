<?php

namespace App\Livewire\Products;

use App\Actions\Products\Wizard\SaveProductWizardDataAction;
use App\Actions\Products\Wizard\SaveWizardDraftAction;
use App\Actions\Products\Wizard\ValidateProductWizardStepAction;
use App\Models\Product;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * CLEAN PRODUCT WIZARD - ACTION-POWERED & COMPONENT-DRIVEN
 *
 * Simplified, focused product creation wizard using:
 * - Action pattern for business logic
 * - Child components for UI sections
 * - Session-based draft system
 * - Event-driven data flow
 */
#[Title('Product Creation Wizard')]
class ProductWizardClean extends Component
{
    // Core state
    public int $currentStep = 1;

    public array $completedSteps = [];

    public ?Product $product = null;

    public bool $isEditMode = false;

    // Wizard data (simple arrays, no Collections)
    public array $wizardData = [
        'product_info' => [],
        'variants' => [],
        'images' => [],
        'pricing' => [],
    ];

    // UI state
    public bool $isSaving = false;

    public ?string $lastSaveTime = null;

    /**
     * 🎪 MOUNT - Simple initialization
     */
    public function mount(?Product $product = null): void
    {
        // Check if product was passed and exists in database
        $this->product = ($product && $product->exists) ? $product : null;
        $this->isEditMode = ($this->product !== null);

        if ($this->isEditMode) {
            $this->loadExistingProduct($this->product);
        } else {
            $this->loadDraftIfAvailable();
        }
    }

    /**
     * 🔄 LOAD EXISTING PRODUCT FOR EDITING
     */
    protected function loadExistingProduct(Product $product): void
    {
        // Load product info
        $this->wizardData['product_info'] = [
            'id' => $product->id,
            'name' => $product->name,
            'parent_sku' => $product->parent_sku,
            'description' => $product->description,
            'status' => $product->status->value,
            'image_url' => $product->image_url,
        ];

        // Load variants
        $variants = $product->variants()->get();
        if ($variants->isNotEmpty()) {
            $this->wizardData['variants'] = [
                'generated_variants' => $variants->map(function ($variant) {
                    return [
                        'id' => $variant->id,
                        'sku' => $variant->sku,
                        'color' => $variant->color,
                        'width' => $variant->width,
                        'drop' => $variant->drop,
                        'price' => $variant->price, // Use correct price field
                        'stock' => $variant->stock_level,
                        'existing' => true,
                    ];
                })->toArray(),
            ];
        }

        // Mark steps as completed based on available data
        $this->completedSteps = [1]; // Product info always completed
        if (! empty($this->wizardData['variants']['generated_variants'])) {
            $this->completedSteps[] = 2;
        }
    }

    /**
     * 📂 LOAD DRAFT IF AVAILABLE
     */
    protected function loadDraftIfAvailable(): void
    {
        if (! auth()->check()) {
            return;
        }

        $draftAction = new SaveWizardDraftAction;
        $result = $draftAction->loadDraft(auth()->id());

        if ($result['data']['exists']) {
            $this->wizardData = array_merge($this->wizardData, $result['data']['data']);
            $this->completedSteps = $result['data']['steps'] ?? [];
            $this->currentStep = ! empty($this->completedSteps) ? max($this->completedSteps) + 1 : 1;
            $this->lastSaveTime = $result['data']['saved_at'] ?? null;

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => '📂 Draft loaded successfully!',
            ]);
        }
    }

    /**
     * STEP NAVIGATION
     */
    public function goToStep(int $step): void
    {
        if ($step >= 1 && $step <= 4 && $this->canProceedToStep($step)) {
            $this->currentStep = $step;
        }
    }

    public function nextStep(): void
    {
        if ($this->currentStep >= 4) {
            return;
        }

        // Validate current step before proceeding
        $validateAction = new ValidateProductWizardStepAction;
        $stepKey = match ($this->currentStep) {
            1 => 'product_info',
            2 => 'variants',
            3 => 'images',
            4 => 'pricing',
            default => null,
        };

        if ($stepKey && isset($this->wizardData[$stepKey])) {
            $result = $validateAction->execute($this->currentStep, $this->wizardData);

            if ($result['success']) {
                // Mark current step as completed
                if (! in_array($this->currentStep, $this->completedSteps)) {
                    $this->completedSteps[] = $this->currentStep;
                }

                // Move to next step
                $this->currentStep++;

                // Auto-save progress if authenticated
                if (auth()->check()) {
                    $this->saveDraft();
                }
            } else {
                // Show validation errors
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => $result['message'] ?? 'Please complete all required fields before continuing.',
                ]);
            }
        } else {
            // Allow proceeding if no data exists yet (for empty steps like images)
            if ($this->currentStep == 3) { // Images step can be skipped
                if (! in_array($this->currentStep, $this->completedSteps)) {
                    $this->completedSteps[] = $this->currentStep;
                }
                $this->currentStep++;
            }
        }
    }

    public function previousStep(): void
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    /**
     * HANDLE STEP COMPLETION FROM CHILD COMPONENTS
     */
    #[On('step-completed')]
    public function handleStepCompleted(int $step, array $data = []): void
    {
        // Store step data
        $stepKey = match ($step) {
            1 => 'product_info',
            2 => 'variants',
            3 => 'images',
            4 => 'pricing',
            default => null,
        };

        if ($stepKey) {
            $this->wizardData[$stepKey] = $data;
        }

        // Mark step as completed
        if (! in_array($step, $this->completedSteps)) {
            $this->completedSteps[] = $step;
        }

        // Auto-save draft (for new products only)
        if (! $this->isEditMode && auth()->check()) {
            $this->saveDraft();
        }

        // Auto-advance or complete wizard
        if ($step < 4) {
            $this->currentStep = $step + 1;
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Step completed!',
            ]);
        } else {
            $this->dispatch('wizard-ready-to-save');
        }
    }

    /**
     * 💾 SAVE DRAFT
     */
    public function saveDraft(): void
    {
        if (! auth()->check()) {
            return;
        }

        $this->isSaving = true;

        try {
            $draftAction = new SaveWizardDraftAction;
            $result = $draftAction->execute(auth()->id(), $this->wizardData);

            if ($result['success']) {
                $this->lastSaveTime = now()->format('H:i:s');
            }
        } catch (\Exception $e) {
            \Log::error('Draft save failed: '.$e->getMessage());
        } finally {
            $this->isSaving = false;
        }
    }

    /**
     * CLEAR DRAFT
     */
    public function clearDraft(): void
    {
        if (! auth()->check()) {
            return;
        }

        $draftAction = new SaveWizardDraftAction;
        $result = $draftAction->clearDraft(auth()->id());

        if ($result['success']) {
            $this->lastSaveTime = null;
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Draft cleared successfully!',
            ]);
        }
    }

    /**
     * SAVE PRODUCT USING ACTION
     */
    #[On('wizard-ready-to-save')]
    public function saveProduct(): void
    {
        $this->isSaving = true;

        try {
            // Validate all steps first
            $validateAction = new ValidateProductWizardStepAction;
            $validationResult = $validateAction->validateAllSteps($this->wizardData);

            if (! $validationResult['data']['overall_valid']) {
                $errors = collect($validationResult['data']['errors'])
                    ->flatten()
                    ->implode(', ');

                throw new \Exception('Validation failed: '.$errors);
            }

            // Save the product
            $saveAction = new SaveProductWizardDataAction;
            $result = $saveAction->execute($this->wizardData, $this->product);

            if (! $result['success']) {
                throw new \Exception($result['message']);
            }

            $product = $result['data']['product'];

            // Clear draft for new products
            if (! $this->isEditMode && auth()->check()) {
                $draftAction = new SaveWizardDraftAction;
                $draftAction->clearDraft(auth()->id());
            }

            $message = $this->isEditMode ? 'Product updated successfully!' : 'Product created successfully!';

            session()->flash('success', $message);
            $this->redirectRoute('products.show', ['product' => $product]);

        } catch (\Exception $e) {
            \Log::error('ProductWizard save failed', [
                'error' => $e->getMessage(),
                'wizard_data' => $this->wizardData,
            ]);

            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to save product: '.$e->getMessage(),
            ]);
        } finally {
            $this->isSaving = false;
        }
    }

    /**
     * CAN PROCEED TO STEP
     */
    public function canProceedToStep(int $step): bool
    {
        if ($step <= $this->currentStep) {
            return true;
        }

        // Must complete previous steps in order
        for ($i = 1; $i < $step; $i++) {
            if (! in_array($i, $this->completedSteps)) {
                return false;
            }
        }

        return true;
    }

    /**
     * COMPUTED PROPERTIES
     */
    #[Computed]
    public function stepNames(): array
    {
        return [
            1 => 'Product Info',
            2 => 'Variants',
            3 => 'Images',
            4 => 'Pricing & Stock',
        ];
    }

    #[Computed]
    public function progressPercentage(): float
    {
        return (count($this->completedSteps) / 4) * 100;
    }

    #[Computed]
    public function currentStepComponent(): string
    {
        return match ($this->currentStep) {
            1 => 'products.wizard.product-info-step',
            2 => 'products.wizard.variant-generation-step',
            3 => 'products.wizard.image-upload-step',
            4 => 'products.wizard.pricing-stock-form', // Use the comprehensive form instead of the placeholder
            default => 'products.wizard.product-info-step',
        };
    }

    #[Computed]
    public function draftStatus(): array
    {
        if ($this->isEditMode) {
            return [
                'exists' => false,
                'message' => 'Editing existing product',
            ];
        }

        if (! auth()->check()) {
            return [
                'exists' => false,
                'message' => 'Login to use drafts',
            ];
        }

        $draftAction = new SaveWizardDraftAction;

        return $draftAction->getDraftInfo(auth()->id());
    }

    #[Computed]
    public function autoSaveStatus(): string
    {
        if ($this->isEditMode) {
            return 'Changes saved directly to product';
        }

        if (! auth()->check()) {
            return 'Login required for auto-save';
        }

        if ($this->isSaving) {
            return 'Saving draft...';
        }

        if ($this->lastSaveTime) {
            return "Last saved at {$this->lastSaveTime}";
        }

        return 'Auto-save ready';
    }

    /**
     * RENDER
     */
    public function render()
    {
        return view('livewire.products.product-wizard-clean');
    }
}
