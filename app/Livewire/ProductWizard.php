<?php

namespace App\Livewire;

use App\Actions\Products\AttachImagesAction;
use App\Actions\Products\CreateVariantsAction;
use App\Actions\Products\SaveProductAction;
use App\Actions\Products\SaveVariantPricingAction;
use App\Actions\Products\SaveVariantStockAction;
use App\Exceptions\ProductWizard\NoVariantsException;
use App\Exceptions\ProductWizard\ProductSaveException;
use App\Exceptions\ProductWizard\WizardValidationException;
use App\Models\Pricing;
use App\Models\Product;
use App\Rules\ParentSkuRule;
use App\Services\ImageUploadService;
use App\Services\WizardDraftService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * 🚀 ENHANCED PRODUCT WIZARD - Clean & Integrated
 *
 * Features:
 * - Auto-save drafts
 * - Barcode assignment integration
 * - DAM/Image upload integration
 * - Proper database model saving
 */
class ProductWizard extends Component
{
    use WithFileUploads;

    // Step management
    public int $currentStep = 1;

    public array $completedSteps = [];

    // Product data
    public string $name = '';

    public string $parent_sku = '';

    public string $description = '';

    public string $status = 'active';

    public string $brand = '';

    // Variants
    public array $colors = [];

    public array $widths = [];

    public array $drops = [];

    public string $new_color = '';

    public string $new_width = '';

    public string $new_drop = '';

    public array $generated_variants = [];

    // Pricing (simplified)
    public array $variant_pricing = [];

    public array $variant_stock = [];

    // Images
    public array $uploaded_images = [];

    public array $image_files = [];

    // Draft system
    public bool $autoSave = true;

    public ?string $lastSaved = null;

    // State
    public bool $isEditMode = false;

    public ?Product $product = null;

    public bool $isSaving = false;

    // Services (not serialized)
    protected ?WizardDraftService $draftService = null;

    protected ?ImageUploadService $imageService = null;

    // Livewire event listeners
    protected $listeners = [
        'wizard-next-step' => 'nextStep',
        'wizard-previous-step' => 'previousStep',
        'wizard-save-product' => 'saveProduct',
        'wizard-clear-draft' => 'clearDraft',
    ];

    /**
     * 🎯 SIMPLE VALIDATION RULES
     */
    protected function rules(): array
    {
        $ignoreProduct = $this->isEditMode && $this->product ? $this->product : null;
        \Log::info('ProductWizard rules()', [
            'edit_mode' => $this->isEditMode,
            'product_id' => $this->product?->id ?? 'null',
            'ignoring_product' => $ignoreProduct?->id ?? 'null',
        ]);

        return [
            // Step 1
            'name' => 'required|min:3|max:255',
            'parent_sku' => [
                'required',
                'regex:/^[0-9]{3}$/',
                Rule::unique('products', 'parent_sku')->ignore($ignoreProduct),
            ],
            'status' => 'required|in:draft,active,inactive,archived',
            'description' => 'nullable|max:1000',
            'brand' => 'nullable|string|max:100',

            // Step 2 - at least one variant attribute
            'colors' => 'required_without_all:widths,drops|array',
            'widths' => 'required_without_all:colors,drops|array',
            'drops' => 'required_without_all:colors,widths|array',
        ];
    }

    /**
     * 🔥 GET SERVICES (LAZY LOADED)
     */
    protected function getDraftService(): WizardDraftService
    {
        if (! $this->draftService) {
            $this->draftService = app(WizardDraftService::class);
        }

        return $this->draftService;
    }

    protected function getImageService(): ImageUploadService
    {
        if (! $this->imageService) {
            $this->imageService = app(ImageUploadService::class);
        }

        return $this->imageService;
    }

    /**
     * 🎪 MOUNT
     */
    public function mount(?Product $product = null): void
    {
        // Authorize product creation/editing
        if ($product && $product->exists) {
            $this->authorize('edit-products');
        } else {
            $this->authorize('create-products');
        }

        $routeName = request()->route()?->getName() ?? 'unknown';

        \Log::info('🚀 ProductWizard mount() called', [
            'product_passed' => $product ? $product->id : 'null',
            'route' => $routeName,
            'product_exists' => $product && $product->exists,
        ]);

        // 🎯 ROUTE-BASED MODE DETECTION - Clean & Explicit
        $this->isEditMode = $routeName === 'products.edit' && $product && $product->exists;

        if ($this->isEditMode) {
            // Edit mode: Use provided product + load any draft changes
            $this->product = $product;
            $this->loadProduct();
            $this->loadDraft(); // Load draft changes on top of product data
        } else {
            // Create mode: Start completely blank - no draft loading yet
            $this->product = new Product;
            // User starts fresh - draft only gets created when they type
        }

        \Log::info('ProductWizard mount', [
            'route' => $routeName,
            'edit_mode' => $this->isEditMode,
            'product_id' => $this->product?->id ?? 'null',
        ]);
    }

    /**
     * 💾 LOAD DRAFT DATA
     */
    protected function loadDraft(): void
    {
        \Log::info('ProductWizard: loadDraft() called');

        if (! auth()->check()) {
            \Log::info('ProductWizard: No auth user, skipping draft load');

            return;
        }

        try {
            $draft = $this->getDraftService()->get(
                (string) auth()->id(),
                $this->product?->id
            );
            \Log::info('ProductWizard: Draft service call successful', ['draft_exists' => ! empty($draft)]);
        } catch (\Exception $e) {
            \Log::error('ProductWizard: Draft service failed', ['error' => $e->getMessage()]);

            return;
        }

        if ($draft && isset($draft['data'])) {
            $data = $draft['data'];

            // Load step 1 data
            if (isset($data['step_1'])) {
                $this->name = $data['step_1']['name'] ?? '';
                $this->parent_sku = $data['step_1']['parent_sku'] ?? '';
                $this->description = $data['step_1']['description'] ?? '';
                $this->status = $data['step_1']['status'] ?? 'active';
                $this->brand = $data['step_1']['brand'] ?? '';
            }

            // Load step 2 data
            if (isset($data['step_2'])) {
                $this->colors = $data['step_2']['colors'] ?? [];
                $this->widths = $data['step_2']['widths'] ?? [];
                $this->drops = $data['step_2']['drops'] ?? [];
                $this->generateVariants();
            }

            // Load step 4 data
            if (isset($data['step_4'])) {
                $this->variant_pricing = $data['step_4']['variant_pricing'] ?? [];
                $this->variant_stock = $data['step_4']['variant_stock'] ?? [];
            }

            $this->lastSaved = $draft['updated_at'];
        }
    }

    /**
     * 📥 LOAD EXISTING PRODUCT
     */
    protected function loadProduct(): void
    {
        $this->name = $this->product->name;
        $this->parent_sku = $this->product->parent_sku;
        $this->description = $this->product->description ?? '';
        $this->status = $this->product->status->value;

        // Load brand from attributes
        $this->brand = \App\Models\ProductAttribute::getValueFor($this->product, 'brand') ?? '';

        // Load variants
        if ($this->product->variants()->count() > 0) {
            // Extract colors, widths, drops from existing variants
            $variants = $this->product->variants;
            $this->colors = $variants->pluck('color')->filter()->unique()->values()->toArray();
            $this->widths = $variants->pluck('width')->filter()->unique()->sort()->values()->toArray();
            $this->drops = $variants->pluck('drop')->filter()->unique()->sort()->values()->toArray();
            $this->generateVariants();
        }
    }

    /**
     * ➡️ NEXT STEP
     */
    public function nextStep(): void
    {
        // Validate current step
        $this->validateCurrentStep();

        if ($this->getErrorBag()->isNotEmpty()) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Please fix the errors before continuing.',
            ]);

            return;
        }

        // Mark step as completed
        if (! in_array($this->currentStep, $this->completedSteps)) {
            $this->completedSteps[] = $this->currentStep;
        }

        // Auto-save current step
        $this->autoSaveDraft();

        // Generate variants after step 2
        if ($this->currentStep === 2) {
            $this->generateVariants();
        }

        // Move to next step
        if ($this->currentStep < 4) {
            $this->currentStep++;

            // Toast feedback for navigation
            $this->dispatch('notify',
                message: 'Next step',
                type: 'info'
            );
        }
    }

    /**
     * ⬅️ PREVIOUS STEP
     */
    public function previousStep(): void
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;

            // Toast feedback for navigation
            $this->dispatch('notify',
                message: 'Previous step',
                type: 'info'
            );
        }
    }

    /**
     * 🎯 VALIDATE CURRENT STEP
     */
    protected function validateCurrentStep(): void
    {
        $rules = [];

        switch ($this->currentStep) {
            case 1:
                $rules = [
                    'name' => 'required|min:3|max:255',
                    'parent_sku' => ['required', new ParentSkuRule($this->product?->id)],
                    'status' => 'required|in:draft,active,inactive,archived',
                    'brand' => 'nullable|string|max:100',
                ];
                break;

            case 2:
                // Must have at least one variant attribute
                if (empty($this->colors) && empty($this->widths) && empty($this->drops)) {
                    $exception = WizardValidationException::missingVariantAttributes();
                    $this->addError('variants', $exception->getUserMessage());

                    return;
                }
                break;

            case 3:
                // Images are optional
                break;

            case 4:
                // Validate pricing
                if (empty($this->generated_variants)) {
                    $exception = WizardValidationException::missingVariantsForPricing();
                    $this->addError('pricing', $exception->getUserMessage());

                    return;
                }
                break;
        }

        if (! empty($rules)) {
            $this->validate($rules);
        }
    }

    /**
     * 💾 AUTO-SAVE DRAFT
     */
    protected function autoSaveDraft(): void
    {
        if (! $this->autoSave || ! auth()->check()) {
            return;
        }

        $stepData = match ($this->currentStep) {
            1 => [
                'name' => $this->name,
                'parent_sku' => $this->parent_sku,
                'description' => $this->description,
                'status' => $this->status,
                'brand' => $this->brand,
            ],
            2 => [
                'colors' => $this->colors,
                'widths' => $this->widths,
                'drops' => $this->drops,
            ],
            3 => [
                'uploaded_images' => $this->uploaded_images,
            ],
            4 => [
                'variant_pricing' => $this->variant_pricing,
                'variant_stock' => $this->variant_stock,
            ],
            default => []
        };

        $this->getDraftService()->updateStep(
            (string) auth()->id(),
            $this->product?->id,
            $this->currentStep,
            $stepData
        );

        $this->lastSaved = now()->format('H:i:s');
    }

    /**
     * ➕ ADD COLOR
     */
    public function addColor(): void
    {
        if ($this->new_color && ! in_array(trim($this->new_color), $this->colors)) {
            $this->colors[] = trim($this->new_color);
            $this->new_color = '';
            $this->generateVariants();
            $this->autoSaveDraft();
        }
    }

    /**
     * ✏️ UPDATE COLOR
     */
    public function updateColor(int $index, string $newValue): void
    {
        $newValue = trim($newValue);
        if ($newValue && ! in_array($newValue, array_diff($this->colors, [$this->colors[$index]]))) {
            $this->colors[$index] = $newValue;
            $this->generateVariants();
            $this->autoSaveDraft();
        }
    }

    /**
     * 🗑️ REMOVE COLOR
     */
    public function removeColor(int $index): void
    {
        if (isset($this->colors[$index])) {
            array_splice($this->colors, $index, 1);
            $this->colors = array_values($this->colors); // Re-index
            $this->generateVariants();
            $this->autoSaveDraft();
        }
    }

    /**
     * ➕ ADD WIDTH
     */
    public function addWidth(): void
    {
        if ($this->new_width && ! in_array((int) $this->new_width, $this->widths)) {
            $this->widths[] = (int) $this->new_width;
            sort($this->widths);
            $this->new_width = '';
            $this->generateVariants();
            $this->autoSaveDraft();
        }
    }

    /**
     * ✏️ UPDATE WIDTH
     */
    public function updateWidth(int $index, string $newValue): void
    {
        $newValue = (int) trim($newValue);
        if ($newValue > 0 && ! in_array($newValue, array_diff($this->widths, [$this->widths[$index]]))) {
            $this->widths[$index] = $newValue;
            sort($this->widths);
            $this->generateVariants();
            $this->autoSaveDraft();
        }
    }

    /**
     * 🗑️ REMOVE WIDTH
     */
    public function removeWidth(int $index): void
    {
        if (isset($this->widths[$index])) {
            array_splice($this->widths, $index, 1);
            $this->widths = array_values($this->widths); // Re-index
            $this->generateVariants();
            $this->autoSaveDraft();
        }
    }

    /**
     * ➕ ADD DROP
     */
    public function addDrop(): void
    {
        if ($this->new_drop && ! in_array((int) $this->new_drop, $this->drops)) {
            $this->drops[] = (int) $this->new_drop;
            sort($this->drops);
            $this->new_drop = '';
            $this->generateVariants();
            $this->autoSaveDraft();
        }
    }

    /**
     * ✏️ UPDATE DROP
     */
    public function updateDrop(int $index, string $newValue): void
    {
        $newValue = (int) trim($newValue);
        if ($newValue > 0 && ! in_array($newValue, array_diff($this->drops, [$this->drops[$index]]))) {
            $this->drops[$index] = $newValue;
            sort($this->drops);
            $this->generateVariants();
            $this->autoSaveDraft();
        }
    }

    /**
     * 🗑️ REMOVE DROP
     */
    public function removeDrop(int $index): void
    {
        if (isset($this->drops[$index])) {
            array_splice($this->drops, $index, 1);
            $this->drops = array_values($this->drops); // Re-index
            $this->generateVariants();
            $this->autoSaveDraft();
        }
    }

    /**
     * 🏷️ GENERATE VARIANT TITLE
     */
    private function generateVariantTitle(array $variantData): string
    {
        $title = $this->name;

        $parts = [];
        if (! empty($variantData['color'])) {
            $parts[] = $variantData['color'];
        }
        if (! empty($variantData['width']) && $variantData['width'] > 0) {
            $parts[] = $variantData['width'].'cm';
        }
        if (! empty($variantData['drop']) && $variantData['drop'] > 0) {
            $parts[] = 'Drop '.$variantData['drop'].'cm';
        }

        if (! empty($parts)) {
            $title .= ' - '.implode(' ', $parts);
        }

        return $title;
    }

    /**
     * ⚡ GENERATE VARIANTS
     */
    public function generateVariants(): void
    {
        $this->generated_variants = [];

        $colors = empty($this->colors) ? [''] : $this->colors;
        $widths = empty($this->widths) ? [0] : $this->widths;
        $drops = empty($this->drops) ? [0] : $this->drops;

        $index = 1;
        foreach ($colors as $color) {
            foreach ($widths as $width) {
                foreach ($drops as $drop) {
                    $sku = $this->parent_sku.'-'.str_pad($index, 3, '0', STR_PAD_LEFT);

                    $this->generated_variants[] = [
                        'sku' => $sku,
                        'color' => $color ?: null,
                        'width' => $width ?: null,
                        'drop' => $drop ?: null,
                    ];

                    // Initialize pricing
                    if (! isset($this->variant_pricing[$index - 1])) {
                        $this->variant_pricing[$index - 1] = [
                            'retail_price' => 0,
                            'cost_price' => 0,
                        ];
                        $this->variant_stock[$index - 1] = [
                            'quantity' => 0,
                        ];
                    }

                    $index++;
                }
            }
        }
    }

    /**
     * 📋 UPLOAD IMAGES
     */
    public function uploadImages(): void
    {
        \Log::info('ProductWizard: uploadImages() method called');

        if (empty($this->image_files)) {
            \Log::info('ProductWizard: No image files to upload');
            $this->dispatch('notify', message: 'No files selected', type: 'error');

            return;
        }

        \Log::info('ProductWizard: Uploading '.count($this->image_files).' files');
        $this->dispatch('notify', message: 'Starting upload...', type: 'info');

        try {
            $uploadedImages = $this->getImageService()->uploadStandalone(
                $this->image_files,
                ['folder' => 'wizard_uploads']
            );

            \Log::info('ProductWizard: Successfully uploaded '.$uploadedImages->count().' images');

            foreach ($uploadedImages as $image) {
                $this->uploaded_images[] = [
                    'id' => $image->id,
                    'url' => $image->url,
                    'filename' => $image->filename,
                ];
            }

            $this->image_files = [];
            $this->autoSaveDraft();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Images uploaded successfully!',
            ]);

        } catch (\Exception $e) {
            \Log::error('ProductWizard upload error: '.$e->getMessage(), [
                'exception' => $e,
                'files_count' => count($this->image_files),
            ]);

            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to upload images: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * 🔗 ATTACH STANDALONE IMAGES TO PRODUCT
     *
     * Links unattached images from the image library to this product
     */
    private function attachImagesToProduct(): void
    {
        if (empty($this->uploaded_images) || ! $this->product) {
            return;
        }

        $imageIds = collect($this->uploaded_images)->pluck('id');

        foreach ($imageIds as $imageId) {
            $image = \App\Models\Image::find($imageId);
            if ($image && ! $image->isAttachedTo($this->product)) {
                $image->attachTo($this->product);
            }
        }
    }

    /**
     * 🚀 SAVE PRODUCT - REFACTORED WITH BUILDER PATTERN
     *
     * Clean separation: Livewire handles UI state, Actions handle business logic
     * Follows ProductWizard.md architecture specification
     */
    public function saveProduct(): void
    {
        // Authorize save operation
        if ($this->isEditMode) {
            $this->authorize('edit-products');
        } else {
            $this->authorize('create-products');
        }

        $this->isSaving = true;

        try {
            // Clear any previous errors and validate
            $this->resetErrorBag();
            $this->validateWizardData();

            if (empty($this->generated_variants)) {
                throw new NoVariantsException;
            }

            DB::transaction(function () {
                // Step 1: Save/Update Parent Product (ProductWizard.md Step 1)
                $productResult = (new SaveProductAction)->execute([
                    'name' => $this->name,
                    'parent_sku' => $this->parent_sku,
                    'description' => $this->description,
                    'status' => $this->status,
                    'brand' => $this->brand,
                ], $this->isEditMode ? $this->product : null);

                $this->product = $productResult['product'];

                // Step 2: Create Variants (ProductWizard.md Step 2) - get the created variants
                $createdVariants = null;
                if (! $this->isEditMode) {
                    $variantResult = (new CreateVariantsAction)->execute($this->product, $this->generated_variants);
                    $createdVariants = $variantResult['variants'] ?? collect();
                } else {
                    // For edit mode, get existing variants
                    $createdVariants = $this->product->variants;
                }

                // Step 3: Attach Images (ProductWizard.md Step 3)
                if (! empty($this->uploaded_images)) {
                    $imageIds = collect($this->uploaded_images)->pluck('id')->toArray();
                    (new AttachImagesAction)->execute($this->product, $imageIds);
                }

                // Step 4: Save Pricing & Stock (ProductWizard.md Step 4) - NEW ARCHITECTURE
                if ($createdVariants && $createdVariants->count() > 0) {
                    // Save pricing using new action
                    if (! empty($this->variant_pricing)) {
                        (new SaveVariantPricingAction)->execute($createdVariants, $this->variant_pricing);
                    }

                    // Save stock using new action
                    if (! empty($this->variant_stock)) {
                        (new SaveVariantStockAction)->execute($createdVariants, $this->variant_stock);
                    } else {
                        // Create initial stock records with 0 quantity
                        (new SaveVariantStockAction)->setInitialStock($createdVariants, 0);
                    }
                }

                // Clean up: Delete draft on successful save
                $this->deleteDraftAfterSave();
            });

            // Success feedback and redirect
            $this->handleSuccessfulSave();

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e; // Re-throw validation exceptions
        } catch (NoVariantsException|WizardValidationException|ProductSaveException $e) {
            $this->handleKnownException($e);
        } catch (\Exception $e) {
            $this->handleUnexpectedException($e);
        } finally {
            $this->isSaving = false;
        }
    }

    /**
     * Validate all wizard data
     */
    protected function validateWizardData(): void
    {
        \Log::info('ProductWizard validation', [
            'product_id' => $this->product?->id ?? 'null',
            'edit_mode' => $this->isEditMode,
        ]);

        $this->validate([
            'name' => 'required|min:3|max:255',
            'parent_sku' => [
                'required',
                'regex:/^[0-9]{3}$/',
                Rule::unique('products', 'parent_sku')->ignore($this->isEditMode && $this->product ? $this->product : null),
            ],
            'status' => 'required|in:draft,active,inactive,archived',
            'brand' => 'nullable|string|max:100',
        ]);
    }

    /**
     * Handle successful save
     */
    protected function handleSuccessfulSave(): void
    {
        session()->flash('success',
            'Product '.($this->isEditMode ? 'updated' : 'created').' successfully!'
        );

        // Don't redirect in tests - just mark success
        if (! app()->runningInConsole()) {
            $this->redirect(route('products.show', ['product' => $this->product->id]));
        }
    }

    /**
     * Delete draft after successful save
     */
    protected function deleteDraftAfterSave(): void
    {
        if (auth()->check()) {
            try {
                $draftDeleted = $this->getDraftService()->delete(
                    (string) auth()->id(),
                    $this->product?->id
                );
                \Log::info('ProductWizard: Draft deletion', [
                    'user_id' => auth()->id(),
                    'product_id' => $this->product?->id,
                    'result' => $draftDeleted ? 'success' : 'no_draft_found',
                ]);
            } catch (\Exception $e) {
                \Log::error('ProductWizard: Draft deletion failed', [
                    'error' => $e->getMessage(),
                ]);
                // Don't fail the save for draft deletion errors
            }
        }
    }

    /**
     * Handle known exceptions with user-friendly messages
     */
    protected function handleKnownException($e): void
    {
        $this->dispatch('notify', [
            'type' => 'error',
            'message' => $e->getUserMessage(),
        ]);
        throw $e;
    }

    /**
     * Handle unexpected exceptions
     */
    protected function handleUnexpectedException(\Exception $e): void
    {
        $this->dispatch('notify', [
            'type' => 'error',
            'message' => 'An unexpected error occurred: '.$e->getMessage(),
        ]);
        throw $e;
    }

    /**
     * 🗑️ CLEAR DRAFT
     */
    public function clearDraft(): void
    {
        if (! auth()->check()) {
            return;
        }

        $this->getDraftService()->delete(
            (string) auth()->id(),
            $this->product?->id
        );

        $this->lastSaved = null;

        $this->dispatch('notify', [
            'type' => 'info',
            'message' => 'Draft cleared successfully!',
        ]);
    }

    /**
     * 🎨 RENDER
     */
    public function render()
    {
        return view('livewire.product-wizard');
    }
}
