<?php

namespace App\Livewire\Products\Wizard;

use App\DTOs\Products\ProductDTO;
use App\Enums\ProductStatus;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * 🔥✨ PRODUCT INFO FORM - COLLECTION-POWERED STEP 1 ✨🔥
 *
 * Clean, focused product information form using our sexy ProductDTO
 * Replaces the complex FoundationStep with Collection-based validation
 */
class ProductInfoForm extends Component
{
    public bool $isActive = false;

    /** @var Collection<string, mixed> */
    public Collection $existingData;

    // Form properties using ProductDTO structure
    public ?int $product_id = null; // ✨ Store product ID for edit validation

    public string $name = '';

    public string $parent_sku = '';

    public string $description = '';

    public string $status = 'active';

    public string $image_url = '';

    // Validation errors using Collections
    /** @var Collection<int, string> */
    public Collection $validationErrors;

    /**
     * 🎪 MOUNT WITH EXISTING DATA
     */
    /**
     * @param  array<string, mixed>  $stepData
     */
    public function mount(bool $isActive = false, array $stepData = []): void
    {
        $this->isActive = $isActive;
        $this->existingData = collect($stepData);
        $this->validationErrors = collect();

        // Populate form with existing data using Collections
        if ($this->existingData->isNotEmpty()) {
            $this->fillFromCollection($this->existingData);
        }

        // Set default status using our sexy enum
        if (empty($this->status)) {
            $this->status = ProductStatus::ACTIVE->value;
        }
    }

    /**
     * 📝 FILL FORM FROM COLLECTION DATA
     *
     * @param  Collection<string, mixed>  $data
     */
    private function fillFromCollection(Collection $data): void
    {
        /** @var Collection<int, string> $fields */
        $fields = collect(['id', 'name', 'parent_sku', 'description', 'status', 'image_url']);
        $fields->each(function (string $field) use ($data) {
            if ($field === 'id') {
                $this->product_id = $data->get($field);
            } else {
                $this->{$field} = $data->get($field, $this->{$field}) ?? $this->{$field};
            }
        });
    }

    /**
     * 🎯 VALIDATE STEP USING PRODUCTDTO
     */
    #[On('validate-current-step')]
    public function validateStep(): void
    {
        if (! $this->isActive) {
            return;
        }

        try {
            // Create DTO from current form data
            $dto = $this->createProductDTO();

            // Use DTO's Collection-based validation
            $this->validationErrors = $dto->validate();

            if ($this->validationErrors->isEmpty()) {
                // Step is valid - notify parent and send data
                $this->completeStep();
            } else {
                // Handle validation errors using Collections
                $this->handleValidationErrors();
            }

        } catch (\Exception $e) {
            $this->validationErrors->push('Validation failed: '.$e->getMessage());
            $this->handleValidationErrors();
        }
    }

    /**
     * 🚀 CREATE PRODUCT DTO FROM FORM DATA
     */
    private function createProductDTO(): ProductDTO
    {
        return ProductDTO::fromArray([
            'id' => $this->product_id, // ✨ Pass product ID for edit validation
            'name' => $this->name,
            'parent_sku' => $this->parent_sku,
            'description' => $this->description ?: null,
            'status' => $this->status,
            'image_url' => $this->image_url ?: null,
        ]);
    }

    /**
     * ✅ COMPLETE STEP AND SEND DATA TO PARENT
     */
    private function completeStep(): void
    {
        $stepData = [
            'id' => $this->product_id, // ✨ Include product ID in step data
            'name' => $this->name,
            'parent_sku' => $this->parent_sku,
            'description' => $this->description,
            'status' => $this->status,
            'image_url' => $this->image_url,
        ];

        $this->dispatch('step-completed', step: 1, data: $stepData);
    }

    /**
     * ⚠️ HANDLE VALIDATION ERRORS USING COLLECTIONS
     */
    private function handleValidationErrors(): void
    {
        // Convert Collection errors to Livewire error bag
        $errorsByField = $this->validationErrors->mapToGroups(function ($error) {
            return match (true) {
                str_contains($error, 'Name') => ['name' => $error],
                str_contains($error, 'SKU') => ['parent_sku' => $error],
                str_contains($error, 'Status') => ['status' => $error],
                str_contains($error, 'Image') => ['image_url' => $error],
                str_contains($error, 'Description') => ['description' => $error],
                default => ['general' => $error]
            };
        });

        // Clear existing errors
        $this->resetErrorBag();

        // Add new errors using Collection operations
        $errorsByField->each(function (Collection $errors, string $field) {
            $this->addError($field, $errors->first());
        });
    }

    /**
     * 🎯 GET AVAILABLE STATUS OPTIONS USING COLLECTIONS
     */
    /**
     * @return Collection<string, string>
     */
    public function getStatusOptionsProperty(): Collection
    {
        return ProductStatus::options();
    }

    /**
     * 🎲 GENERATE UNIQUE SKU SUGGESTION
     */
    public function generateSkuSuggestion(): void
    {
        if (! empty($this->name)) {
            // Generate SKU from product name using Collection operations
            $suggestion = collect(explode(' ', $this->name))
                ->take(3) // First 3 words
                ->map(function ($word) {
                    $word = strtoupper(trim($word));
                    // Take up to 4 characters for better readability, minimum 2
                    $length = min(4, max(2, strlen($word)));

                    return substr($word, 0, $length);
                })
                ->implode('-');

            // Add number if exists
            $counter = 1;
            $baseSku = $suggestion;

            while (\App\Models\Product::where('parent_sku', $suggestion)->exists()) {
                $suggestion = $baseSku.'-'.str_pad((string) $counter, 2, '0', STR_PAD_LEFT);
                $counter++;
            }

            $this->parent_sku = $suggestion;
        }
    }

    /**
     * 🧹 RESET FORM TO DEFAULTS
     */
    public function resetForm(): void
    {
        $this->reset(['name', 'parent_sku', 'description', 'image_url']);
        $this->status = ProductStatus::ACTIVE->value;
        $this->validationErrors = collect();
        $this->resetErrorBag();
    }

    /**
     * 📊 GET FORM COMPLETION STATISTICS
     */
    /**
     * @return Collection<string, mixed>
     */
    public function getFormStatsProperty(): Collection
    {
        $fields = collect(['name', 'parent_sku', 'description', 'status', 'image_url']);
        $filledFields = $fields->filter(fn ($field) => ! empty($this->{$field}));
        $requiredFields = collect(['name', 'parent_sku', 'status']);
        $filledRequired = $requiredFields->filter(fn ($field) => ! empty($this->{$field}));

        return collect([
            'total_fields' => $fields->count(),
            'filled_fields' => $filledFields->count(),
            'completion_percentage' => round(($filledFields->count() / $fields->count()) * 100, 1),
            'required_complete' => $filledRequired->count() === $requiredFields->count(),
            'validation_errors' => $this->validationErrors->count(),
            'is_valid' => $this->validationErrors->isEmpty() && $filledRequired->count() === $requiredFields->count(),
        ]);
    }

    /**
     * 🎨 RENDER THE FORM
     */
    /**
     * @return \Illuminate\View\View
     */
    public function render()
    {
        return view('livewire.products.wizard.product-info-form');
    }
}
