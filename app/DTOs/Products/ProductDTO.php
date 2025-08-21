<?php

namespace App\DTOs\Products;

use App\Enums\ProductStatus;
use App\Http\Requests\Products\StoreProductRequest;
use App\Models\Product;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Livewire\Wireable;

/**
 * 🔥✨ COLLECTION-POWERED PRODUCT DTO ✨🔥
 *
 * Sexy Collection-first approach following HeaderMappingDTO patterns
 */
readonly class ProductDTO implements Arrayable, Wireable
{
    public function __construct(
        public string $name,
        public string $parentSku,
        public ?string $description,
        public ProductStatus $status,
        public ?string $imageUrl,
        public ?int $id = null,
        public Collection $validationErrors = new Collection,
        public Collection $metadata = new Collection,
    ) {}

    /**
     * 🚀 Create from StoreProductRequest using Collections
     */
    public static function fromRequest(StoreProductRequest $request): self
    {
        $validated = collect($request->validated());
        $extra = collect($request->except(array_keys($request->rules())));

        return new self(
            name: $validated->get('name'),
            parentSku: $validated->get('parent_sku'),
            description: $validated->get('description'),
            status: ProductStatus::from($validated->get('status')),
            imageUrl: $validated->get('image_url'),
            metadata: $extra->filter(fn ($value) => $value !== null), // Only non-null metadata
        );
    }

    /**
     * 🎯 Create from Product model using Collections
     */
    public static function fromModel(Product $product): self
    {
        $attributes = collect($product->getAttributes());

        // Core fields that map directly to DTO properties
        $coreFields = collect(['name', 'parent_sku', 'description', 'status', 'image_url', 'id']);

        // Handle status conversion safely
        $statusValue = $attributes->get('status', 'active');
        $status = is_string($statusValue) && in_array($statusValue, ['active', 'inactive'])
            ? ProductStatus::from($statusValue)
            : ProductStatus::ACTIVE; // Default fallback

        return new self(
            name: $attributes->get('name') ?? 'Untitled Product',
            parentSku: $attributes->get('parent_sku') ?? 'TEMP-'.uniqid(),
            description: $attributes->get('description'),
            status: $status,
            imageUrl: $attributes->get('image_url'),
            id: $product->id,
            metadata: $attributes->except($coreFields->toArray())->filter(fn ($value) => $value !== null),
        );
    }

    /**
     * 🔄 Create from array using Collection transformations
     */
    public static function fromArray(array $data): self
    {
        $collection = collect($data);

        return new self(
            name: $collection->get('name'),
            parentSku: $collection->get('parent_sku') ?? $collection->get('parentSku'),
            description: $collection->get('description'),
            status: ProductStatus::from($collection->get('status')),
            imageUrl: $collection->get('image_url') ?? $collection->get('imageUrl'),
            id: $collection->get('id'),
            metadata: $collection->except(['name', 'parent_sku', 'parentSku', 'description', 'status', 'image_url', 'imageUrl', 'id'])
        );
    }

    /**
     * ✅ Collection-based validation with business rules
     */
    public function validate(): Collection
    {
        return collect([
            $this->validateName(),
            $this->validateSku(),
            $this->validateStatus(),
            $this->validateImageUrl(),
            $this->validateBusinessRules(),
        ])->filter()->flatten();
    }

    /**
     * 🏷️ Validate name field
     */
    private function validateName(): Collection
    {
        return collect()
            ->when(empty($this->name), fn ($c) => $c->push('Name is required'))
            ->when(strlen($this->name) < 3, fn ($c) => $c->push('Name must be at least 3 characters'))
            ->when(strlen($this->name) > 255, fn ($c) => $c->push('Name must not exceed 255 characters'))
            ->when(! preg_match('/^[\w\s\-\.]+$/', $this->name), fn ($c) => $c->push('Name contains invalid characters'));
    }

    /**
     * 🔖 Validate SKU field with business logic
     */
    private function validateSku(): Collection
    {
        return collect()
            ->when(empty($this->parentSku), fn ($c) => $c->push('Parent SKU is required'))
            ->when(! preg_match('/^[A-Z0-9\-]{3,20}$/', $this->parentSku), fn ($c) => $c->push('SKU must be 3-20 characters, uppercase letters, numbers and hyphens only'))
            ->when($this->isDuplicateSku(), fn ($c) => $c->push('SKU already exists in the database'));
    }

    /**
     * 🎯 Validate status enum
     */
    private function validateStatus(): Collection
    {
        return collect()
            ->when(! ProductStatus::values()->contains($this->status->value), fn ($c) => $c->push('Status must be one of: '.ProductStatus::values()->implode(', '))
            );
    }

    /**
     * 🖼️ Validate image URL
     */
    private function validateImageUrl(): Collection
    {
        return collect()
            ->when($this->imageUrl && ! filter_var($this->imageUrl, FILTER_VALIDATE_URL),
                fn ($c) => $c->push('Image URL must be a valid URL')
            );

        // Note: Removed strict file extension requirement
        // Many valid image URLs don't end with file extensions:
        // - CDN URLs: https://cdn.example.com/image/12345
        // - Dynamic URLs: https://example.com/api/image?id=123
        // - Query parameter URLs: https://images.com/photo.jpg?w=800
        // The basic URL validation is sufficient for most use cases
    }

    /**
     * 🎪 Validate business rules
     */
    private function validateBusinessRules(): Collection
    {
        return collect()
            ->when($this->status === ProductStatus::INACTIVE && $this->hasActiveVariants(), fn ($c) => $c->push('Cannot deactivate product with active variants')
            );
    }

    /**
     * 🔍 Check for duplicate SKU using Collection query
     */
    private function isDuplicateSku(): bool
    {
        return Product::where('parent_sku', $this->parentSku)
            ->when($this->id, fn ($query) => $query->where('id', '!=', $this->id))
            ->exists();
    }

    /**
     * 💎 Check if product has active variants
     */
    private function hasActiveVariants(): bool
    {
        return $this->id && Product::find($this->id)?->variants()
            ->where('status', 'active')
            ->exists();
    }

    /**
     * 🔄 Compare with another DTO using Collections
     */
    public function hasChanges(self $other): Collection
    {
        return collect([
            'name' => $this->name !== $other->name,
            'parentSku' => $this->parentSku !== $other->parentSku,
            'description' => $this->description !== $other->description,
            'status' => $this->status !== $other->status,
            'imageUrl' => $this->imageUrl !== $other->imageUrl,
        ])->filter();
    }

    /**
     * 📋 Get changed field names as Collection
     */
    public function getChangedFields(self $other): Collection
    {
        return $this->hasChanges($other)->keys();
    }

    /**
     * 🎯 Get validation rules as Collection of Collections
     */
    public function getValidationRules(?int $productId = null): Collection
    {
        return collect([
            'name' => collect(['required', 'min:3', 'max:255']),
            'parent_sku' => collect(['required'])->when($productId, fn ($c) => $c->push("unique:products,parent_sku,{$productId}")
            ),
            'description' => collect(['nullable', 'max:1000']),
            'status' => collect(['required'])->push(
                'in:'.ProductStatus::values()->implode(',')
            ),
            'image_url' => collect(['nullable', 'url']),
        ])->map(fn (Collection $rules) => $rules->toArray()); // Convert to arrays for Laravel validation
    }

    /**
     * 🗂️ Convert to model data using Collection filtering
     */
    public function toModelData(): Collection
    {
        return collect([
            'name' => $this->name,
            'parent_sku' => $this->parentSku,
            'description' => $this->description,
            'status' => $this->status->value,
            'image_url' => $this->imageUrl,
        ])->filter(fn ($value) => $value !== null);
    }

    /**
     * 📊 Get DTO statistics using Collections
     */
    public function getStatistics(): Collection
    {
        return collect([
            'field_count' => collect([
                'name', 'parentSku', 'description', 'status', 'imageUrl',
            ])->count(),
            'filled_fields' => collect([
                'name' => ! empty($this->name),
                'parentSku' => ! empty($this->parentSku),
                'description' => ! empty($this->description),
                'status' => isset($this->status),
                'imageUrl' => ! empty($this->imageUrl),
            ])->filter()->count(),
            'completeness_percentage' => function () {
                $total = 5; // Total possible fields
                $filled = collect([
                    $this->name, $this->parentSku, $this->description,
                    $this->status, $this->imageUrl,
                ])->filter(fn ($value) => ! empty($value))->count();

                return round(($filled / $total) * 100, 1);
            },
            'validation_errors' => $this->validationErrors->count(),
            'metadata_count' => $this->metadata->count(),
            'has_id' => ! is_null($this->id),
            'status_info' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'is_active' => $this->status->isActive(),
            ],
        ]);
    }

    /**
     * 📋 Convert to array for JSON serialization using Collections
     */
    public function toArray(): array
    {
        return collect([
            'name' => $this->name,
            'parent_sku' => $this->parentSku,
            'description' => $this->description,
            'status' => $this->status->value,
            'image_url' => $this->imageUrl,
            'id' => $this->id,
            'validation_errors' => $this->validationErrors->toArray(),
            'metadata' => $this->metadata->toArray(),
        ])->filter(fn ($value, $key) => ! in_array($key, ['validation_errors', 'metadata']) || ! empty($value)
        )->toArray();
    }

    /**
     * 🔄 Livewire Wireable - Convert to Livewire property
     */
    public function toLivewire(): array
    {
        return collect([
            'name' => $this->name,
            'parentSku' => $this->parentSku,
            'description' => $this->description,
            'status' => $this->status->value,
            'imageUrl' => $this->imageUrl,
            'id' => $this->id,
            'validationErrors' => $this->validationErrors->toArray(),
            'metadata' => $this->metadata->toArray(),
        ])->toArray();
    }

    /**
     * 🔄 Livewire Wireable - Create from Livewire property
     */
    public static function fromLivewire($value): self
    {
        $data = collect($value);

        return new self(
            name: $data->get('name'),
            parentSku: $data->get('parentSku'),
            description: $data->get('description'),
            status: ProductStatus::from($data->get('status')),
            imageUrl: $data->get('imageUrl'),
            id: $data->get('id'),
            validationErrors: collect($data->get('validationErrors', [])),
            metadata: collect($data->get('metadata', [])),
        );
    }

    /**
     * ✨ Create a new instance with updated data using Collections
     */
    public function with(array $updates): self
    {
        $current = collect([
            'name' => $this->name,
            'parentSku' => $this->parentSku,
            'description' => $this->description,
            'status' => $this->status->value,
            'imageUrl' => $this->imageUrl,
            'id' => $this->id,
        ]);

        $merged = $current->merge($updates);

        return new self(
            name: $merged->get('name'),
            parentSku: $merged->get('parentSku'),
            description: $merged->get('description'),
            status: ProductStatus::from($merged->get('status')),
            imageUrl: $merged->get('imageUrl'),
            id: $merged->get('id'),
            validationErrors: $this->validationErrors,
            metadata: $this->metadata,
        );
    }
}
