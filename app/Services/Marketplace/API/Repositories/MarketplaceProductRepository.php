<?php

namespace App\Services\Marketplace\API\Repositories;

use Illuminate\Support\Collection;

/**
 * 🛍️ MARKETPLACE PRODUCT REPOSITORY
 *
 * Repository for marketplace product operations.
 * Provides specialized methods for product CRUD operations,
 * category management, image handling, and inventory updates.
 */
class MarketplaceProductRepository extends AbstractMarketplaceRepository
{
    /**
     * 🔍 Find a single product by ID
     */
    public function find(string $id): ?array
    {
        $result = $this->service->getProduct($id);

        return $result['success'] ? $result['data']['product'] ?? $result['data'] : null;
    }

    /**
     * 📋 Get multiple products with optional filtering
     */
    public function all(array $filters = []): Collection
    {
        return $this->service->getProducts($filters);
    }

    /**
     * ➕ Create a new product
     */
    public function create(array $data): array
    {
        $validation = $this->validateProductData($data);
        if (! $validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors'],
            ];
        }

        $sanitized = $this->sanitizeData($data);

        return $this->service->createProduct($sanitized);
    }

    /**
     * 📝 Update an existing product
     */
    public function update(string $id, array $data): array
    {
        $sanitized = $this->sanitizeData($data);

        return $this->service->updateProduct($id, $sanitized);
    }

    /**
     * 🗑️ Delete a product
     */
    public function delete(string $id): bool
    {
        $result = $this->service->deleteProduct($id);

        return $result['success'] ?? false;
    }

    /**
     * 🚀 Bulk create multiple products
     */
    public function bulkCreate(array $products): array
    {
        $validated = [];
        $errors = [];

        foreach ($products as $index => $product) {
            $validation = $this->validateProductData($product);
            if ($validation['valid']) {
                $validated[] = $this->sanitizeData($product);
            } else {
                $errors["product_{$index}"] = $validation['errors'];
            }
        }

        if (! empty($errors)) {
            return [
                'success' => false,
                'errors' => $errors,
                'valid_products' => count($validated),
                'invalid_products' => count($errors),
            ];
        }

        return $this->service->bulkCreateProducts($validated);
    }

    /**
     * 🔄 Bulk update multiple products
     */
    public function bulkUpdate(array $products): array
    {
        $sanitized = array_map([$this, 'sanitizeData'], $products);

        return $this->service->bulkUpdateProducts($sanitized);
    }

    /**
     * 🔄 Sync products from local to marketplace
     */
    public function syncFromLocal(Collection $localProducts): array
    {
        return $this->service->syncProducts($localProducts);
    }

    /**
     * 🏷️ Get marketplace categories/taxonomies
     */
    public function getCategories(): array
    {
        return $this->service->getCategories();
    }

    /**
     * 🎨 Upload and manage product images
     */
    public function uploadImages(string $productId, array $images): array
    {
        return $this->service->uploadProductImages($productId, $images);
    }

    /**
     * 📦 Update product inventory
     */
    public function updateInventory(string $productId, array $inventoryData): array
    {
        return $this->service->updateInventory($productId, $inventoryData);
    }

    /**
     * 💰 Update product pricing
     */
    public function updatePricing(string $productId, array $pricingData): array
    {
        return $this->service->updatePricing($productId, $pricingData);
    }

    /**
     * 🏃‍♂️ Get product variants
     */
    public function getVariants(string $productId): array
    {
        return $this->service->getProductVariants($productId);
    }

    /**
     * 🔍 Search products by title/description
     */
    public function search(string $query): Collection
    {
        return $this->where('title', $query, 'contains')
            ->orWhere('description', $query, 'contains')
            ->get();
    }

    /**
     * 🏷️ Find products by category
     */
    public function byCategory(string $categoryId): static
    {
        return $this->where('category_id', $categoryId);
    }

    /**
     * 💰 Find products by price range
     */
    public function byPriceRange(float $minPrice, float $maxPrice): static
    {
        return $this->where('price', $minPrice, '>=')
            ->where('price', $maxPrice, '<=');
    }

    /**
     * 📦 Find products with low inventory
     */
    public function withLowInventory(int $threshold = 5): static
    {
        return $this->where('inventory_quantity', $threshold, '<=');
    }

    /**
     * 🎯 Find products by SKU
     */
    public function bySku(string $sku): ?array
    {
        return $this->where('sku', $sku)->first();
    }

    /**
     * 📊 Find products by status
     */
    public function byStatus(string $status): static
    {
        return $this->where('status', $status);
    }

    /**
     * 🏢 Find products by vendor/brand
     */
    public function byVendor(string $vendor): static
    {
        return $this->where('vendor', $vendor);
    }

    /**
     * 📅 Find products created/updated since date
     */
    public function since(string $date): static
    {
        return $this->where('updated_at', $date, '>=');
    }

    /**
     * 🎯 Find products with specific tags
     */
    public function withTags(array $tags): static
    {
        return $this->where('tags', $tags, 'in');
    }

    /**
     * 🔍 Custom query helper for OR conditions
     */
    public function orWhere(string $field, $value, string $operator = '='): static
    {
        $this->queryFilters[] = [
            'field' => $field,
            'value' => $value,
            'operator' => $operator,
            'condition' => 'OR',
        ];

        return $this;
    }

    /**
     * ✅ Validate product data before operations
     */
    protected function validateProductData(array $data): array
    {
        $requiredFields = ['title'];
        $errors = [];

        // Check required fields
        foreach ($requiredFields as $field) {
            if (! isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Field '{$field}' is required";
            }
        }

        // Validate title length
        if (isset($data['title']) && strlen($data['title']) > 255) {
            $errors[] = 'Product title cannot exceed 255 characters';
        }

        // Validate price if provided
        if (isset($data['price']) && (! is_numeric($data['price']) || $data['price'] < 0)) {
            $errors[] = 'Price must be a positive number';
        }

        // Validate inventory quantity if provided
        if (isset($data['inventory_quantity']) && (! is_numeric($data['inventory_quantity']) || $data['inventory_quantity'] < 0)) {
            $errors[] = 'Inventory quantity must be a non-negative number';
        }

        // Validate SKU uniqueness if provided
        if (isset($data['sku']) && ! empty($data['sku'])) {
            $existing = $this->bySku($data['sku']);
            if ($existing) {
                $errors[] = "SKU '{$data['sku']}' already exists";
            }
        }

        // Use marketplace-specific validation
        $marketplaceValidation = $this->service->validateProductData($data);
        if (! $marketplaceValidation['valid']) {
            $errors = array_merge($errors, $marketplaceValidation['errors']);
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * 📊 Get product statistics
     */
    public function getStatistics(): array
    {
        $total = $this->count();
        $active = $this->byStatus('active')->count();
        $lowInventory = $this->withLowInventory()->count();

        return [
            'total_products' => $total,
            'active_products' => $active,
            'inactive_products' => $total - $active,
            'low_inventory_products' => $lowInventory,
            'average_price' => $this->getAveragePrice(),
        ];
    }

    /**
     * 💰 Get average product price
     */
    protected function getAveragePrice(): float
    {
        $products = $this->get();
        $prices = $products->pluck('price')->filter()->values();

        return $prices->count() > 0 ? $prices->average() : 0;
    }
}
