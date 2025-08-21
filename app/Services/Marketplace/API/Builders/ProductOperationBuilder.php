<?php

namespace App\Services\Marketplace\API\Builders;

use App\Services\Marketplace\API\AbstractMarketplaceService;
use Illuminate\Support\Collection;

/**
 * 🛍️ PRODUCT OPERATION BUILDER
 *
 * Fluent API builder for marketplace product operations.
 * Provides chainable methods for configuring product operations.
 *
 * Usage:
 * $result = $service->products()
 *     ->create($productData)
 *     ->withImages($images)
 *     ->withCategories($categories)
 *     ->withInventory($inventory)
 *     ->validateBeforeSubmit()
 *     ->execute();
 */
class ProductOperationBuilder
{
    protected AbstractMarketplaceService $service;

    protected string $operation;

    protected array $productData = [];

    protected array $images = [];

    protected array $categories = [];

    protected array $inventory = [];

    protected array $pricing = [];

    protected array $variants = [];

    protected array $metadata = [];

    protected array $options = [];

    protected bool $validateBeforeSubmit = false;

    protected bool $bulkMode = false;

    protected int $batchSize = 50;

    protected $progressCallback = null;

    protected ?string $productId = null;

    public function __construct(AbstractMarketplaceService $service)
    {
        $this->service = $service;
    }

    /**
     * ➕ Set operation to create product
     */
    public function create(array $productData): static
    {
        $this->operation = 'create';
        $this->productData = $productData;

        return $this;
    }

    /**
     * 📝 Set operation to update product
     */
    public function update(string $productId, array $productData): static
    {
        $this->operation = 'update';
        $this->productId = $productId;
        $this->productData = $productData;

        return $this;
    }

    /**
     * 🗑️ Set operation to delete product
     */
    public function delete(string $productId): static
    {
        $this->operation = 'delete';
        $this->productId = $productId;

        return $this;
    }

    /**
     * 🚀 Enable bulk mode for multiple products
     */
    public function bulk(array $products): static
    {
        $this->bulkMode = true;
        $this->productData = $products;

        return $this;
    }

    /**
     * 🔄 Set operation to sync products
     */
    public function sync(Collection $localProducts): static
    {
        $this->operation = 'sync';
        $this->productData = $localProducts->toArray();

        return $this;
    }

    /**
     * 🎨 Add images to product operation
     */
    public function withImages(array $images): static
    {
        $this->images = $images;

        return $this;
    }

    /**
     * 🏷️ Add categories to product operation
     */
    public function withCategories(array $categories): static
    {
        $this->categories = $categories;

        return $this;
    }

    /**
     * 📦 Add inventory data to product operation
     */
    public function withInventory(array $inventory): static
    {
        $this->inventory = $inventory;

        return $this;
    }

    /**
     * 💰 Add pricing data to product operation
     */
    public function withPricing(array $pricing): static
    {
        $this->pricing = $pricing;

        return $this;
    }

    /**
     * 🎯 Add product variants
     */
    public function withVariants(array $variants): static
    {
        $this->variants = $variants;

        return $this;
    }

    /**
     * 📊 Add metadata/custom fields
     */
    public function withMetadata(array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * ⚙️ Add additional options
     */
    public function withOptions(array $options): static
    {
        $this->options = array_merge($this->options, $options);

        return $this;
    }

    /**
     * ✅ Enable validation before submission
     */
    public function validateBeforeSubmit(): static
    {
        $this->validateBeforeSubmit = true;

        return $this;
    }

    /**
     * 📦 Set batch size for bulk operations
     */
    public function withBatchSize(int $size): static
    {
        $this->batchSize = max(1, min(100, $size)); // Limit between 1-100

        return $this;
    }

    /**
     * 📈 Set progress callback for bulk operations
     */
    public function withProgressCallback(callable $callback): static
    {
        $this->progressCallback = $callback;

        return $this;
    }

    /**
     * 🚀 Execute the configured operation
     */
    public function execute(): array
    {
        // Validate before execution if requested
        if ($this->validateBeforeSubmit) {
            $validation = $this->validateOperation();
            if (! $validation['valid']) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'validation_errors' => $validation['errors'],
                ];
            }
        }

        // Prepare operation data
        $operationData = $this->buildOperationData();

        // Execute based on operation type
        return match ($this->operation) {
            'create' => $this->executeCreate($operationData),
            'update' => $this->executeUpdate($operationData),
            'delete' => $this->executeDelete(),
            'sync' => $this->executeSync($operationData),
            default => throw new \InvalidArgumentException("Unknown operation: {$this->operation}")
        };
    }

    /**
     * 🏗️ Build operation data from configured options
     */
    protected function buildOperationData(): array
    {
        $data = $this->productData;

        // Add images if specified
        if (! empty($this->images)) {
            $data['images'] = $this->images;
        }

        // Add categories if specified
        if (! empty($this->categories)) {
            $data['categories'] = $this->categories;
        }

        // Add inventory if specified
        if (! empty($this->inventory)) {
            $data['inventory'] = $this->inventory;
        }

        // Add pricing if specified
        if (! empty($this->pricing)) {
            $data['pricing'] = $this->pricing;
        }

        // Add variants if specified
        if (! empty($this->variants)) {
            $data['variants'] = $this->variants;
        }

        // Add metadata if specified
        if (! empty($this->metadata)) {
            $data['metadata'] = $this->metadata;
        }

        // Merge additional options
        $data = array_merge($data, $this->options);

        return $data;
    }

    /**
     * ➕ Execute create operation
     */
    protected function executeCreate(array $data): array
    {
        if ($this->bulkMode) {
            return $this->service->bulkCreateProducts($data);
        }

        return $this->service->createProduct($data);
    }

    /**
     * 📝 Execute update operation
     */
    protected function executeUpdate(array $data): array
    {
        if ($this->bulkMode) {
            return $this->service->bulkUpdateProducts($data);
        }

        return $this->service->updateProduct($this->productId, $data);
    }

    /**
     * 🗑️ Execute delete operation
     */
    protected function executeDelete(): array
    {
        return $this->service->deleteProduct($this->productId);
    }

    /**
     * 🔄 Execute sync operation
     */
    protected function executeSync(array $data): array
    {
        $localProducts = collect($data);

        return $this->service->syncProducts($localProducts);
    }

    /**
     * ✅ Validate the configured operation
     */
    protected function validateOperation(): array
    {
        $errors = [];

        // Validate based on operation
        switch ($this->operation) {
            case 'create':
                if (empty($this->productData)) {
                    $errors[] = 'Product data is required for create operation';
                }
                break;

            case 'update':
                if (empty($this->productId)) {
                    $errors[] = 'Product ID is required for update operation';
                }
                if (empty($this->productData)) {
                    $errors[] = 'Product data is required for update operation';
                }
                break;

            case 'delete':
                if (empty($this->productId)) {
                    $errors[] = 'Product ID is required for delete operation';
                }
                break;

            case 'sync':
                if (empty($this->productData)) {
                    $errors[] = 'Product data is required for sync operation';
                }
                break;
        }

        // Validate product data structure if present
        if (! empty($this->productData) && ! $this->bulkMode) {
            $validation = $this->service->validateProductData($this->productData);
            if (! $validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * 📊 Get operation summary
     */
    public function getSummary(): array
    {
        return [
            'operation' => $this->operation ?? 'none',
            'product_id' => $this->productId,
            'bulk_mode' => $this->bulkMode,
            'has_images' => ! empty($this->images),
            'has_categories' => ! empty($this->categories),
            'has_inventory' => ! empty($this->inventory),
            'has_pricing' => ! empty($this->pricing),
            'has_variants' => ! empty($this->variants),
            'has_metadata' => ! empty($this->metadata),
            'validate_before_submit' => $this->validateBeforeSubmit,
            'batch_size' => $this->batchSize,
        ];
    }
}
