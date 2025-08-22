<?php

namespace App\Livewire\Products;

use App\Actions\Products\Wizard\SaveProductWizardDataAction;
use App\Actions\Products\Wizard\ValidateProductWizardStepAction;
use App\Models\Product;
use App\Services\ProductWizard\WizardDataManager;
use App\Services\ProductWizard\WizardDraftService;
use App\Services\ProductWizard\WizardStepNavigator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * REFACTORED PRODUCT WIZARD - SERVICE-DRIVEN ARCHITECTURE
 *
 * Clean, maintainable product creation wizard using:
 * - Service classes for separation of concerns
 * - Action pattern for business logic
 * - Child components for UI sections
 * - Session-based draft system
 * - Event-driven data flow
 */
#[Title('Product Creation Wizard')]
class ProductWizardClean extends Component
{
    public int $currentStep = 1;

    public array $completedSteps = [];

    public ?Product $product = null;

    public bool $isEditMode = false;

    public array $wizardData = [];

    public bool $isSaving = false;

    public ?string $lastSaveTime = null;

    // Auto-save configuration
    public bool $autoSaveEnabled = true;
    
    public int $autoSaveInterval = 30; // seconds
    
    public bool $hasUnsavedChanges = false;

    protected function getStepNavigator(): WizardStepNavigator
    {
        return app(WizardStepNavigator::class);
    }

    protected function getDraftService(): WizardDraftService
    {
        return app(WizardDraftService::class);
    }

    /**
     * Get authenticated user ID (decoupled from Auth facade)
     */
    protected function getUserId(): ?string
    {
        return auth()->check() ? (string) auth()->id() : null;
    }

    protected function getDataManager(): WizardDataManager
    {
        return app(WizardDataManager::class);
    }

    public function mount(?Product $product = null): void
    {
        $this->wizardData = $this->getDataManager()->getInitialWizardData();
        $this->product = ($product && $product->exists) ? $product : null;
        $this->isEditMode = ($this->product !== null);
        
        // Configure auto-save
        $this->autoSaveEnabled = config('drafts.auto_save.enabled', true);
        $this->autoSaveInterval = config('drafts.auto_save.interval', 30);

        if ($this->isEditMode) {
            $this->loadExistingProduct($this->product);
            $this->checkForDraftConflict($this->product);
        } else {
            $this->loadDraftIfAvailable();
        }
    }

    protected function loadExistingProduct(Product $product): void
    {
        $this->wizardData = $this->getDataManager()->loadExistingProductData($product);
        $this->completedSteps = $this->getDataManager()->calculateCompletedStepsFromData($this->wizardData);
    }

    protected function loadDraftIfAvailable(): void
    {
        $productId = $this->product?->id;
        $draftResult = $this->getDraftService()->loadDraftIfAvailable($this->getUserId(), $productId);

        if ($draftResult['exists']) {
            $this->wizardData = $this->getDataManager()->mergeWizardData($this->wizardData, $draftResult['data']);
            $this->completedSteps = $draftResult['completedSteps'];
            $this->currentStep = ! empty($this->completedSteps) ? max(array_map('intval', $this->completedSteps)) + 1 : 1;
            $this->lastSaveTime = $draftResult['savedAt'];

            $stepCount = count($this->completedSteps);
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "📂 Draft loaded successfully! {$stepCount} step(s) restored.",
            ]);
        }
    }

    /**
     * Check for draft conflicts when editing existing product
     */
    protected function checkForDraftConflict(Product $product): void
    {
        $draftResult = $this->getDraftService()->loadDraftIfAvailable($this->getUserId(), $product->id);
        
        if ($draftResult['exists']) {
            $this->dispatch('draft-conflict-detected', [
                'productId' => $product->id,
                'productName' => $product->name,
                'draftData' => $draftResult,
            ]);
        }
    }

    public function goToStep(int $step): void
    {
        if ($this->getStepNavigator()->canProceedToStep($step, $this->currentStep, $this->completedSteps)) {
            $this->currentStep = $step;
        }
    }

    public function nextStep(): void
    {
        if ($this->currentStep >= WizardStepNavigator::MAX_STEPS) {
            return;
        }

        $validationResult = $this->getStepNavigator()->validateStep($this->currentStep, $this->wizardData);

        if ($validationResult['success']) {
            if (! in_array($this->currentStep, $this->completedSteps)) {
                $this->completedSteps[] = $this->currentStep;
            }

            $this->currentStep++;

            if (auth()->check()) {
                $this->saveDraft();
            }
        } else {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => $validationResult['message'] ?? 'Please complete all required fields before continuing.',
            ]);
        }
    }

    public function previousStep(): void
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    #[On('step-completed')]
    public function handleStepCompleted(int $step, array $data = []): void
    {
        $this->wizardData = $this->getDataManager()->updateStepData($this->wizardData, $step, $data);

        if (! in_array($step, $this->completedSteps)) {
            $this->completedSteps[] = $step;
        }

        if (! $this->isEditMode && auth()->check()) {
            $this->saveDraft();
        }

        if ($step < WizardStepNavigator::MAX_STEPS) {
            $this->currentStep = $step + 1;
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Step completed!',
            ]);
        } else {
            $this->dispatch('wizard-ready-to-save');
        }
    }

    public function saveDraft(): void
    {
        $this->isSaving = true;

        $productId = $this->product?->id;
        $result = $this->getDraftService()->saveDraft($this->getUserId(), $this->wizardData, $productId);

        if ($result['success']) {
            $this->lastSaveTime = $result['savedAt'];
            $this->hasUnsavedChanges = false;
        }

        $this->isSaving = false;
    }

    /**
     * Auto-save triggered by frontend timer (Livewire best practice)
     */
    public function autoSave(): void
    {
        if (!$this->autoSaveEnabled || !$this->hasUnsavedChanges || $this->isSaving) {
            return;
        }

        $this->saveDraft();
    }

    /**
     * Mark as having unsaved changes (called when data changes)
     */
    public function markUnsaved(): void
    {
        $this->hasUnsavedChanges = true;
    }

    /**
     * Event listeners for draft-related actions
     */
    #[On('step-data-updated')]
    public function handleStepDataUpdate(int $step, array $stepData): void
    {
        $this->wizardData[(string) $step] = $stepData;
        $this->markUnsaved();
    }

    #[On('restore-draft')]
    public function restoreDraft(): void
    {
        $this->loadDraftIfAvailable();
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => '📂 Draft restored successfully!',
        ]);
    }

    #[On('ignore-draft')]
    public function ignoreDraft(): void
    {
        $productId = $this->product?->id;
        $this->getDraftService()->clearDraft($this->getUserId(), $productId);
        
        $this->dispatch('notify', [
            'type' => 'info',
            'message' => '🗑️ Draft ignored and cleared.',
        ]);
    }

    public function clearDraft(): void
    {
        $productId = $this->product?->id;
        $result = $this->getDraftService()->clearDraft($this->getUserId(), $productId);

        if ($result['success']) {
            $this->wizardData = $this->getDataManager()->getInitialWizardData();
            $this->completedSteps = [];
            $this->currentStep = 1;
            $this->lastSaveTime = null;
            $this->hasUnsavedChanges = false;
            
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => '🗑️ Draft cleared successfully!',
            ]);
        }
    }

    #[On('wizard-ready-to-save')]
    public function saveProduct(): void
    {
        $this->isSaving = true;

        try {
            $validateAction = new ValidateProductWizardStepAction;
            $validationResult = $validateAction->validateAllSteps($this->wizardData);

            if (! $validationResult['data']['overall_valid']) {
                $errors = collect($validationResult['data']['errors'])
                    ->flatten()
                    ->implode(', ');

                throw new \Exception('Validation failed: '.$errors);
            }

            $saveAction = new SaveProductWizardDataAction;
            $result = $saveAction->execute($this->wizardData, $this->product);

            if (! $result['success']) {
                throw new \Exception($result['message']);
            }

            $product = $result['data']['product'];

            if (! $this->isEditMode && auth()->check()) {
                $this->getDraftService()->clearDraft($this->getUserId(), $this->product?->id);
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

    public function canProceedToStep(int $step): bool
    {
        return $this->getStepNavigator()->canProceedToStep($step, $this->currentStep, $this->completedSteps);
    }

    #[Computed]
    public function stepNames(): array
    {
        return WizardStepNavigator::STEP_NAMES;
    }

    #[Computed]
    public function progressPercentage(): float
    {
        return $this->getStepNavigator()->calculateProgress($this->completedSteps);
    }

    #[Computed]
    public function currentStepComponent(): string
    {
        return $this->getStepNavigator()->getStepComponent($this->currentStep);
    }


    /**
     * Computed properties for view
     */
    #[Computed]
    public function autoSaveStatus(): string
    {
        return $this->getDraftService()->getAutoSaveStatus(
            $this->isEditMode, 
            $this->isSaving, 
            $this->lastSaveTime, 
            $this->getUserId()
        );
    }

    #[Computed]
    public function draftStatus(): array
    {
        return $this->getDraftService()->getDraftStatus($this->getUserId());
    }

    public function render()
    {
        return view('livewire.products.product-wizard-clean');
    }
}
