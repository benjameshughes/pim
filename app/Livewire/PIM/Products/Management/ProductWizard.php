<?php

namespace App\Livewire\Pim\Products\Management;

use App\Livewire\Forms\ProductWizardForm;
use App\Models\AttributeDefinition;
use App\Models\Barcode;
use App\Models\BarcodePool;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
class ProductWizard extends Component
{
    use WithFileUploads;

    public ProductWizardForm $form;

    // Wizard state
    public int $currentStep = 1;

    public int $totalSteps = 7;

    public array $completedSteps = [];

    // Step-specific properties
    public $newImages = [];

    public $imageType = 'gallery';

    public array $variantMatrix = [];

    public array $selectedColors = [];

    public array $selectedSizes = [];

    public array $selectedWidths = [];

    public array $selectedDrops = [];

    public array $attributeValues = [];

    public bool $generateVariants = true;

    public array $customVariants = [];

    public string $skuGenerationMethod = 'sequential'; // 'sequential', 'random', 'manual'

    public string $parentSku = '';

    private int $sequentialCounter = 1;

    public ?string $skuConflictProduct = null;

    // Barcode assignment properties
    public bool $assignBarcodes = true;

    public string $barcodeAssignmentMethod = 'auto'; // 'auto', 'manual', 'skip'

    public string $barcodeType = 'EAN13';

    public array $variantBarcodes = []; // [variant_index => barcode]

    public int $availableBarcodesCount = 0;

    // Pre-defined options
    public array $commonColors = [
        'Black', 'White', 'Red', 'Blue', 'Green', 'Yellow', 'Orange',
        'Purple', 'Pink', 'Brown', 'Grey', 'Navy', 'Teal',
    ];

    public array $commonSizes = [
        '60cm', '80cm', '100cm', '120cm', '140cm', '160cm', '180cm', '200cm',
        '220cm', '240cm', '260cm', '280cm', '300cm', '320cm', '340cm', '360cm',
    ];

    public array $commonWidths = [
        '60cm', '80cm', '100cm', '120cm', '140cm', '160cm', '180cm', '200cm',
        '220cm', '240cm', '260cm', '280cm', '300cm', '320cm', '340cm', '360cm',
    ];

    public array $commonDrops = [
        '120cm', '140cm', '160cm', '180cm', '200cm', '220cm', '240cm',
        '260cm', '280cm', '300cm', '320cm', '340cm', '360cm', '380cm', '400cm',
    ];

    protected $listeners = [
        'imageUploaded' => 'handleImageUpload',
        'variantGenerated' => 'handleVariantGeneration',
    ];

    public function mount()
    {
        $this->form->status = 'active';
        $this->loadAttributeDefinitions();
        $this->generateParentSku();
        $this->loadBarcodeStats();
    }

    private function generateParentSku()
    {
        // Get the highest existing parent SKU number
        $highestSku = Product::whereNotNull('parent_sku')
            ->get()
            ->map(function ($product) {
                // Extract numeric part from parent_sku (e.g., "010" -> 10)
                return (int) preg_replace('/[^0-9]/', '', $product->parent_sku);
            })
            ->filter()
            ->max();

        $nextNumber = $highestSku ? $highestSku + 1 : 1;
        $this->parentSku = str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }

    public function loadAttributeDefinitions()
    {
        $attributes = AttributeDefinition::active()
            ->forProducts()
            ->ordered()
            ->get();

        foreach ($attributes as $attribute) {
            $this->attributeValues[$attribute->key] = '';
        }
    }

    public function updatedCurrentStep()
    {
        $this->validateCurrentStep();
    }

    public function updatedSelectedColors()
    {
        if ($this->generateVariants) {
            $this->generateVariantMatrix();
        }
    }

    public function updatedSelectedSizes()
    {
        if ($this->generateVariants) {
            $this->generateVariantMatrix();
        }
    }

    public function updatedSelectedWidths()
    {
        if ($this->generateVariants) {
            $this->generateVariantMatrix();
        }
    }

    public function updatedSelectedDrops()
    {
        if ($this->generateVariants) {
            $this->generateVariantMatrix();
        }
    }

    public function updatedSkuGenerationMethod()
    {
        if ($this->generateVariants && $this->hasVariantSelections()) {
            $this->generateVariantMatrix();
        }
    }

    private function hasVariantSelections(): bool
    {
        return ! empty($this->selectedColors) ||
               ! empty($this->selectedWidths) ||
               ! empty($this->selectedDrops);
    }

    public function updatedParentSku()
    {
        $this->validateParentSkuUniqueness();
    }

    public function updatedFormName()
    {
        // Auto-generate slug when name changes, but only if slug is empty
        if (empty($this->form->slug) && ! empty($this->form->name)) {
            $this->form->slug = $this->generateUniqueSlug($this->form->name);
        }
    }

    private function validateParentSkuUniqueness()
    {
        $this->skuConflictProduct = null;

        if (empty($this->parentSku)) {
            return;
        }

        $existingProduct = Product::where('parent_sku', $this->parentSku)->first();
        if ($existingProduct) {
            $this->skuConflictProduct = $existingProduct->name;
            $this->addError('parentSku', "Parent SKU '{$this->parentSku}' is already used by product: {$existingProduct->name}");
        } else {
            $this->resetErrorBag('parentSku');
        }
    }

    public function regenerateParentSku()
    {
        $this->generateParentSku();
        $this->validateParentSkuUniqueness();
    }

    public function nextStep()
    {
        if ($this->validateCurrentStep()) {
            $this->markStepCompleted($this->currentStep);

            if ($this->currentStep < $this->totalSteps) {
                $this->currentStep++;
                $this->dispatch('step-changed', step: $this->currentStep);
            }
        }
    }

    public function previousStep()
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
            $this->dispatch('step-changed', step: $this->currentStep);
        }
    }

    public function goToStep($step)
    {
        if ($step >= 1 && $step <= $this->totalSteps) {
            // Can only go to completed steps or next step
            if (in_array($step, $this->completedSteps) || $step == $this->currentStep + 1) {
                $this->currentStep = $step;
                $this->dispatch('step-changed', step: $this->currentStep);
            }
        }
    }

    private function validateCurrentStep(): bool
    {
        switch ($this->currentStep) {
            case 1: // Basic Info
                return $this->validateBasicInfo();
            case 2: // Images
                return true; // Images are optional
            case 3: // Features & Details
                return true; // Optional content
            case 4: // Attributes
                return $this->validateAttributes();
            case 5: // Variants
                return $this->validateVariants();
            case 6: // Barcodes
                return $this->validateBarcodes();
            case 7: // Review
                return true;
            default:
                return false;
        }
    }

    private function validateBasicInfo(): bool
    {
        $rules = [
            'form.name' => 'required|string|max:255',
            'form.description' => 'nullable|string',
            'form.status' => 'required|in:active,inactive,discontinued',
            'parentSku' => 'required|string|max:10',
        ];

        try {
            $this->validate($rules);

            // Validate Parent SKU uniqueness
            $this->validateParentSkuUniqueness();
            if ($this->skuConflictProduct) {
                return false;
            }

            // Auto-generate slug if empty
            if (empty($this->form->slug)) {
                $this->form->slug = $this->generateUniqueSlug($this->form->name);
            }

            return true;
        } catch (\Illuminate\Validation\ValidationException $e) {
            return false;
        }
    }

    private function validateAttributes(): bool
    {
        $attributes = AttributeDefinition::active()->forProducts()->where('is_required', true)->get();

        foreach ($attributes as $attribute) {
            if (empty($this->attributeValues[$attribute->key])) {
                $this->addError('attributeValues.'.$attribute->key, "The {$attribute->label} field is required.");

                return false;
            }
        }

        return true;
    }

    private function validateVariants(): bool
    {
        if ($this->generateVariants) {
            if (! $this->hasVariantSelections()) {
                $this->addError('variants', 'Please select at least one color, width, or drop option for variant generation.');

                return false;
            }
        } else {
            if (empty($this->customVariants)) {
                $this->addError('variants', 'Please add at least one custom variant.');

                return false;
            }
        }

        return true;
    }

    private function validateBarcodes(): bool
    {
        // If barcode assignment is disabled, skip validation
        if (! $this->assignBarcodes || $this->barcodeAssignmentMethod === 'skip') {
            return true;
        }

        // Check if we have variants to assign barcodes to
        if (empty($this->variantMatrix)) {
            $this->addError('barcodes', 'No variants available for barcode assignment. Please create variants first.');

            return false;
        }

        // Check if we have enough barcodes available
        if (count($this->variantMatrix) > $this->availableBarcodesCount) {
            $this->addError('barcodes', 'Insufficient barcodes available. Need '.count($this->variantMatrix).' but only '.$this->availableBarcodesCount.' available.');

            return false;
        }

        // For auto assignment, ensure barcodes are assigned
        if ($this->barcodeAssignmentMethod === 'auto') {
            $assignedCount = count(array_filter($this->variantBarcodes));
            if ($assignedCount < count($this->variantMatrix)) {
                $this->addError('barcodes', 'Not all variants have been assigned barcodes. Please assign barcodes or change assignment method.');

                return false;
            }
        }

        return true;
    }

    private function markStepCompleted($step)
    {
        if (! in_array($step, $this->completedSteps)) {
            $this->completedSteps[] = $step;
        }
    }

    public function addCustomVariant()
    {
        $this->customVariants[] = [
            'sku' => '',
            'color' => '',
            'width' => '',
            'drop' => '',
            'stock_level' => 0,
            'status' => 'active',
        ];
    }

    public function removeCustomVariant($index)
    {
        unset($this->customVariants[$index]);
        $this->customVariants = array_values($this->customVariants);
    }

    public function generateVariantMatrix()
    {
        $this->variantMatrix = [];

        if (! $this->hasVariantSelections()) {
            return;
        }

        // Prepare arrays - use empty string if no selections to ensure at least one iteration
        $colors = empty($this->selectedColors) ? [''] : $this->selectedColors;
        $widths = empty($this->selectedWidths) ? [''] : $this->selectedWidths;
        $drops = empty($this->selectedDrops) ? [''] : $this->selectedDrops;

        // Reset sequential counter for each matrix generation
        $this->resetSequentialCounter();

        foreach ($colors as $color) {
            foreach ($widths as $width) {
                foreach ($drops as $drop) {
                    $this->variantMatrix[] = [
                        'color' => ! empty($color) ? $color : null,
                        'size' => null, // Size is no longer used for window shades
                        'width' => $width ?: null,
                        'drop' => $drop ?: null,
                        'sku' => $this->generateVariantSku($color, null, $width, $drop),
                        'stock_level' => 0,
                        'status' => 'active',
                    ];
                }
            }
        }

        // Auto-assign barcodes after generating variants
        if ($this->barcodeAssignmentMethod === 'auto') {
            $this->assignBarcodesToVariants();
        }
    }

    private function generateVariantSku($color = '', $size = '', $width = '', $drop = ''): string
    {
        switch ($this->skuGenerationMethod) {
            case 'sequential':
                return $this->generateSequentialSku();
            case 'random':
                return $this->generateRandomSku();
            case 'manual':
            default:
                // Legacy manual generation for custom variants
                $baseSku = Str::upper(Str::slug($this->form->name, ''));
                $parts = array_filter([$color, $size, $width, $drop]);

                if (! empty($parts)) {
                    $suffix = Str::upper(implode('-', array_map(fn ($p) => Str::slug($p, ''), $parts)));

                    return $baseSku.'-'.$suffix;
                }

                return $baseSku;
        }
    }

    private function generateSequentialSku(): string
    {
        $sequentialNumber = str_pad($this->sequentialCounter, 3, '0', STR_PAD_LEFT);
        $this->sequentialCounter++;

        return $this->parentSku.'-'.$sequentialNumber;
    }

    private function resetSequentialCounter(): void
    {
        $this->sequentialCounter = 1;
    }

    private function generateRandomSku(): string
    {
        $randomNumber = str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);

        // Ensure uniqueness by checking existing SKUs
        $sku = $this->parentSku.'-'.$randomNumber;
        while (ProductVariant::where('sku', $sku)->exists() || $this->skuExistsInCurrentMatrix($sku)) {
            $randomNumber = str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            $sku = $this->parentSku.'-'.$randomNumber;
        }

        return $sku;
    }

    private function skuExistsInCurrentMatrix(string $sku): bool
    {
        foreach ($this->variantMatrix as $variant) {
            if ($variant['sku'] === $sku) {
                return true;
            }
        }

        return false;
    }

    private function generateUniqueSlug($name): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (Product::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    // Barcode Management Methods
    public function loadBarcodeStats()
    {
        $this->availableBarcodesCount = BarcodePool::where('status', 'available')
            ->where('barcode_type', $this->barcodeType)
            ->count();
    }

    public function updatedBarcodeType()
    {
        $this->loadBarcodeStats();
        if ($this->barcodeAssignmentMethod === 'auto') {
            $this->assignBarcodesToVariants();
        }
    }

    public function updatedBarcodeAssignmentMethod()
    {
        if ($this->barcodeAssignmentMethod === 'auto') {
            $this->assignBarcodesToVariants();
        } elseif ($this->barcodeAssignmentMethod === 'skip') {
            $this->variantBarcodes = [];
        }
    }

    public function assignBarcodesToVariants()
    {
        if (empty($this->variantMatrix)) {
            return;
        }

        $neededCount = count($this->variantMatrix);
        $availableBarcodes = BarcodePool::where('status', 'available')
            ->where('barcode_type', $this->barcodeType)
            ->where('is_legacy', false)
            ->limit($neededCount)
            ->get();

        $this->variantBarcodes = [];

        foreach ($this->variantMatrix as $index => $variant) {
            if (isset($availableBarcodes[$index])) {
                $this->variantBarcodes[$index] = $availableBarcodes[$index]->barcode;
            }
        }
    }

    public function refreshBarcodeAssignment()
    {
        $this->loadBarcodeStats();
        if ($this->barcodeAssignmentMethod === 'auto') {
            $this->assignBarcodesToVariants();
        }
    }

    public function assignSpecificBarcode($variantIndex)
    {
        $barcode = BarcodePool::where('status', 'available')
            ->where('barcode_type', $this->barcodeType)
            ->where('is_legacy', false)
            ->whereNotIn('barcode', array_values($this->variantBarcodes))
            ->first();

        if ($barcode) {
            $this->variantBarcodes[$variantIndex] = $barcode->barcode;
        } else {
            session()->flash('error', "No available {$this->barcodeType} barcodes in pool.");
        }
    }

    public function removeVariantBarcode($variantIndex)
    {
        unset($this->variantBarcodes[$variantIndex]);
    }

    public function removeNewImage($index)
    {
        unset($this->newImages[$index]);
        $this->newImages = array_values($this->newImages);
    }

    public function createProduct()
    {
        // Validate all steps
        for ($i = 1; $i <= $this->totalSteps; $i++) {
            $this->currentStep = $i;
            if (! $this->validateCurrentStep()) {
                session()->flash('error', "Please complete step {$i} before creating the product.");

                return;
            }
        }

        try {
            DB::transaction(function () {
                // Create the product using our beautiful ProductBuilder! 🚀
                $builder = Product::build()
                    ->name($this->form->name)
                    ->slug($this->form->slug)
                    ->description($this->form->description)
                    ->status($this->form->status);

                // Add parent SKU
                if ($this->parentSku) {
                    $builder->set('parent_sku', $this->parentSku);
                }

                // Add features using ProductBuilder's features method
                $features = [];
                for ($i = 1; $i <= 5; $i++) {
                    $feature = $this->form->{"product_features_{$i}"};
                    if (! empty($feature)) {
                        $features[] = $feature;
                    }
                }
                if (! empty($features)) {
                    $builder->features($features);
                }

                // Add details using ProductBuilder's details method
                $details = [];
                for ($i = 1; $i <= 5; $i++) {
                    $detail = $this->form->{"product_details_{$i}"};
                    if (! empty($detail)) {
                        $details[] = $detail;
                    }
                }
                if (! empty($details)) {
                    $builder->details($details);
                }

                // Add product attributes using Builder
                if (! empty($this->attributeValues)) {
                    $builder->attributes(array_filter($this->attributeValues));
                }

                // Execute the ProductBuilder to create the product
                $product = $builder->execute();

                // Handle images using existing method (still works!)
                $this->handleProductImages($product);

                // Handle variants using our new Builder pattern
                $this->handleProductVariants($product);

                session()->flash('message', 'Product created successfully with all variants using Builder patterns!');

                return redirect()->route('products.view', $product);
            });
        } catch (\Exception $e) {
            session()->flash('error', 'Error creating product: '.$e->getMessage());
            \Log::error('Product creation failed in wizard: '.$e->getMessage(), [
                'form_data' => $this->form->toArray(),
                'parent_sku' => $this->parentSku,
                'variants_count' => count($this->generateVariants ? $this->variantMatrix : $this->customVariants),
            ]);
        }
    }

    private function handleProductImages($product)
    {
        $sortOrder = 1;
        foreach ($this->newImages as $image) {
            $path = $image->store('product-images', 'public');

            ProductImage::create([
                'product_id' => $product->id,
                'variant_id' => null,
                'image_path' => $path,
                'image_type' => $this->imageType,
                'alt_text' => $product->name,
                'sort_order' => $sortOrder++,
            ]);
        }
    }

    private function handleProductVariants($product)
    {
        $variants = $this->generateVariants ? $this->variantMatrix : $this->customVariants;

        foreach ($variants as $index => $variantData) {
            // Create variant using our beautiful Builder pattern! 🚀
            $builder = ProductVariant::buildFor($product)
                ->sku($variantData['sku'])
                ->stockLevel($variantData['stock_level'] ?? 0)
                ->status($variantData['status'] ?? 'active');

            // Add variant attributes using Builder methods
            if (! empty($variantData['color'])) {
                $builder->color($variantData['color']);
            }

            if (! empty($variantData['width']) || ! empty($variantData['drop'])) {
                $builder->windowDimensions(
                    $variantData['width'] ?? '',
                    $variantData['drop'] ?? ''
                );
            }

            // Handle barcode assignment using Builder barcode methods
            if ($this->assignBarcodes && isset($this->variantBarcodes[$index])) {
                $barcode = $this->variantBarcodes[$index];
                $builder->primaryBarcode($barcode, $this->barcodeType);
            }

            // Execute the builder to create the variant with all integrations
            try {
                $variant = $builder->execute();
                \Log::info("Created variant {$variant->sku} with Builder pattern");
            } catch (\Exception $e) {
                \Log::error("Failed to create variant {$variantData['sku']}: ".$e->getMessage());

                // Fallback to basic creation if builder fails
                $variant = ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => $this->ensureUniqueSku($variantData['sku']),
                    'stock_level' => $variantData['stock_level'] ?? 0,
                    'status' => $variantData['status'] ?? 'active',
                ]);

                // Handle attributes manually for fallback
                $this->handleVariantAttributesFallback($variant, $variantData, $index);
            }
        }
    }

    /**
     * Ensure SKU is unique by appending counter if needed
     */
    private function ensureUniqueSku(string $originalSku): string
    {
        $sku = $originalSku;
        $counter = 1;

        while (ProductVariant::where('sku', $sku)->exists()) {
            $sku = $originalSku.'-'.$counter;
            $counter++;
        }

        return $sku;
    }

    /**
     * Fallback method for handling variant attributes if Builder fails
     */
    private function handleVariantAttributesFallback(ProductVariant $variant, array $variantData, int $index): void
    {
        // Set attributes using the attribute system
        if (! empty($variantData['color'])) {
            \App\Models\VariantAttribute::setValue($variant->id, 'color', $variantData['color'], 'string');
        }
        if (! empty($variantData['width'])) {
            \App\Models\VariantAttribute::setValue($variant->id, 'width', $variantData['width'], 'string');
        }
        if (! empty($variantData['drop'])) {
            \App\Models\VariantAttribute::setValue($variant->id, 'drop', $variantData['drop'], 'string');
        }

        // Handle barcode assignment if enabled
        if ($this->assignBarcodes && isset($this->variantBarcodes[$index])) {
            $this->assignBarcodeToVariant($variant, $this->variantBarcodes[$index]);
        }
    }

    private function assignBarcodeToVariant($variant, $barcodeValue)
    {
        // Find the barcode in the pool
        $barcodePool = BarcodePool::where('barcode', $barcodeValue)
            ->where('status', 'available')
            ->first();

        if ($barcodePool) {
            // Update the barcode pool to mark it as assigned
            $barcodePool->update([
                'status' => 'assigned',
                'assigned_to_variant_id' => $variant->id,
                'assigned_at' => now(),
                'date_first_used' => now(),
            ]);

            // Create a barcode record for the variant
            Barcode::create([
                'product_variant_id' => $variant->id,
                'barcode' => $barcodeValue,
                'barcode_type' => $this->barcodeType,
                'is_primary' => true, // Set as primary barcode for the variant
            ]);

            \Log::info("Barcode {$barcodeValue} assigned to variant {$variant->sku}");
        } else {
            \Log::warning("Barcode {$barcodeValue} not found in available pool for variant {$variant->sku}");
        }
    }

    public function getStepTitleProperty()
    {
        return match ($this->currentStep) {
            1 => 'Basic Information',
            2 => 'Product Images',
            3 => 'Features & Details',
            4 => 'Product Attributes',
            5 => 'Product Variants',
            6 => 'Barcode Assignment',
            7 => 'Review & Create',
            default => 'Unknown Step'
        };
    }

    public function getStepDescriptionProperty()
    {
        return match ($this->currentStep) {
            1 => 'Enter the basic product information like name, description, and status.',
            2 => 'Upload product images for your main product.',
            3 => 'Add detailed features and specifications for your product.',
            4 => 'Configure custom attributes specific to your product.',
            5 => 'Define product variants with different colors, widths, drops, or options.',
            6 => 'Assign barcodes to your product variants from the barcode pool.',
            7 => 'Review all information and create your product with variants.',
            default => ''
        };
    }

    public function render()
    {
        $attributeDefinitions = AttributeDefinition::active()
            ->forProducts()
            ->ordered()
            ->get();

        return view('livewire.pim.products.management.product-wizard', [
            'attributeDefinitions' => $attributeDefinitions,
        ]);
    }
}
