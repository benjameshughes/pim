<?php

namespace App\Livewire\Products;

use App\DTOs\Products\ProductDTO;
use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * 🔥✨ COLLECTION-POWERED PRODUCT FORM ✨🔥
 *
 * Sexy Livewire form using Collection-based ProductDTO
 */
class ProductForm extends Component
{
    public ?Product $product = null;

    public ?ProductDTO $productDto = null;

    public $name = '';

    public $parent_sku = '';

    public $description = '';

    public $status = 'active';

    public $image_url = '';

    public $isEditing = false;

    /**
     * 🎪 Component initialization with Collection magic
     */
    public function mount(?Product $product = null)
    {
        if ($product && $product->exists) {
            // Authorize edit access
            $this->authorize('edit-products');

            $this->product = $product;
            $this->isEditing = true;

            // Create DTO from model using Collections
            $this->productDto = ProductDTO::fromModel($product);

            // Fill form properties using Collection operations
            $this->fillFromDTO($this->productDto);
        } else {
            // Authorize create access
            $this->authorize('create-products');

            // Create empty DTO with defaults
            $this->productDto = ProductDTO::fromArray([
                'name' => '',
                'parent_sku' => '',
                'description' => null,
                'status' => ProductStatus::ACTIVE->value,
                'image_url' => null,
            ]);
        }
    }

    /**
     * 📝 Fill form from DTO using Collections
     */
    private function fillFromDTO(ProductDTO $dto): void
    {
        $formData = collect([
            'name' => $dto->name,
            'parent_sku' => $dto->parentSku,
            'description' => $dto->description,
            'status' => $dto->status->value,
            'image_url' => $dto->imageUrl,
        ]);

        $this->fill($formData->toArray());
    }

    /**
     * 💾 Save with Collection-powered DTO validation
     */
    public function save()
    {
        // Authorize the save operation
        if ($this->isEditing) {
            $this->authorize('edit-products');
        } else {
            $this->authorize('create-products');
        }

        // Create DTO from current form data using Collections
        $formData = $this->getFormDataCollection();

        try {
            $dto = ProductDTO::fromArray($formData->toArray());

            // Use DTO's Collection-based validation
            $validationErrors = $dto->validate();

            if ($validationErrors->isNotEmpty()) {
                // Convert Collection validation errors to Livewire format
                $this->handleValidationErrors($validationErrors);

                return;
            }

            // Additional Laravel validation using DTO rules
            $this->validateWithDTORules($dto);

            // Perform save operation using Collection-optimized methods
            if ($this->isEditing) {
                $this->updateProduct($dto);
            } else {
                $this->createProduct($dto);
            }

            $this->dispatch('success', $this->getSuccessMessage());

            return $this->redirect(route('products.show', $this->product), navigate: true);

        } catch (\Exception $e) {
            $this->addError('save', 'An error occurred while saving: '.$e->getMessage());
        }
    }

    /**
     * 🗂️ Get form data as Collection
     */
    private function getFormDataCollection(): Collection
    {
        return collect([
            'name' => $this->name,
            'parent_sku' => $this->parent_sku,
            'description' => $this->description,
            'status' => $this->status,
            'image_url' => $this->image_url,
            'id' => $this->product?->id,
        ])->filter(fn ($value, $key) => $key !== 'id' || ! is_null($value) // Keep id only if it exists
        );
    }

    /**
     * ⚠️ Handle Collection validation errors
     */
    private function handleValidationErrors(Collection $validationErrors): void
    {
        $errorsByField = $validationErrors->mapToGroups(function ($error) {
            // Simple field mapping for common errors
            return match (true) {
                str_contains($error, 'Name') => ['name' => $error],
                str_contains($error, 'SKU') => ['parent_sku' => $error],
                str_contains($error, 'Status') => ['status' => $error],
                str_contains($error, 'Image') => ['image_url' => $error],
                default => ['general' => $error]
            };
        });

        // Add errors to Livewire using Collection operations
        $errorsByField->each(function (Collection $errors, string $field) {
            $this->addError($field, $errors->first());
        });
    }

    /**
     * 📋 Validate using DTO's Collection-based rules
     */
    private function validateWithDTORules(ProductDTO $dto): void
    {
        $rules = $dto->getValidationRules($this->product?->id);
        $this->validate($rules->toArray());
    }

    /**
     * ✏️ Update existing product using Collection operations
     */
    private function updateProduct(ProductDTO $dto): void
    {
        $modelData = $dto->toModelData();

        // Check what fields are actually changing using Collections
        $currentDto = ProductDTO::fromModel($this->product);
        $changedFields = $dto->getChangedFields($currentDto);

        if ($changedFields->isEmpty()) {
            $this->dispatch('info', 'No changes detected.');

            return;
        }

        // Update only changed fields for efficiency
        $updateData = $modelData->only($changedFields->toArray());
        $this->product->update($updateData->toArray());

        $this->productDto = $dto->with(['id' => $this->product->id]);
    }

    /**
     * ✨ Create new product using Collection operations
     */
    private function createProduct(ProductDTO $dto): void
    {
        $modelData = $dto->toModelData();
        $this->product = Product::create($modelData->toArray());

        $this->productDto = $dto->with(['id' => $this->product->id]);
        $this->isEditing = true;
    }

    /**
     * 🎉 Get contextual success message
     */
    private function getSuccessMessage(): string
    {
        $action = $this->isEditing ? 'updated' : 'created';

        return "Product \"{$this->name}\" {$action} successfully! ".
               ($this->isEditing ? '🎉' : '✨');
    }

    /**
     * 🎯 Get available status options for form
     */
    public function getStatusOptionsProperty(): Collection
    {
        return ProductStatus::options();
    }

    /**
     * 📊 Get form statistics using Collections
     */
    public function getFormStatisticsProperty(): Collection
    {
        $formData = $this->getFormDataCollection();

        return collect([
            'completion_percentage' => $this->calculateCompletionPercentage($formData),
            'field_count' => $formData->count(),
            'filled_fields' => $formData->filter(fn ($value) => ! empty($value))->count(),
            'has_validation_errors' => collect($this->getErrorBag()->getMessages())->isNotEmpty(),
            'is_editing' => $this->isEditing,
            'product_exists' => ! is_null($this->product),
        ]);
    }

    /**
     * 📈 Calculate form completion percentage
     */
    private function calculateCompletionPercentage(Collection $formData): float
    {
        $requiredFields = collect(['name', 'parent_sku', 'status']);
        $optionalFields = collect(['description', 'image_url']);
        $allFields = $requiredFields->merge($optionalFields);

        $filledRequired = $requiredFields->filter(fn ($field) => ! empty($formData->get($field))
        )->count();

        $filledOptional = $optionalFields->filter(fn ($field) => ! empty($formData->get($field))
        )->count();

        // Weight required fields more heavily
        $score = ($filledRequired * 2) + $filledOptional;
        $maxScore = ($requiredFields->count() * 2) + $optionalFields->count();

        return round(($score / $maxScore) * 100, 1);
    }

    /**
     * 🔄 Reset form to initial state
     */
    public function resetForm(): void
    {
        if ($this->isEditing && $this->productDto) {
            $this->fillFromDTO($this->productDto);
        } else {
            $this->reset(['name', 'parent_sku', 'description', 'status', 'image_url']);
            $this->status = ProductStatus::ACTIVE->value;
        }

        $this->resetErrorBag();
    }

    /**
     * 🎨 Render the component
     */
    public function render()
    {
        return view('livewire.products.product-form');
    }
}
