<?php

namespace App\Services\Marketplace\API\Contracts;

use App\Services\Marketplace\API\Builders\ProductOperationBuilder;
use Illuminate\Support\Collection;

/**
 * 🛍️ PRODUCT OPERATIONS INTERFACE
 *
 * Defines contract for marketplace product operations.
 * Covers CRUD operations, bulk sync, and product management.
 */
interface ProductOperationsInterface
{
    /**
     * 🏗️ Create product operation builder
     */
    public function products(): ProductOperationBuilder;

    /**
     * ➕ Create a single product
     */
    public function createProduct(array $productData): array;

    /**
     * 📝 Update an existing product
     */
    public function updateProduct(string $productId, array $productData): array;

    /**
     * 🗑️ Delete a product
     */
    public function deleteProduct(string $productId): array;

    /**
     * 🔍 Get a single product
     */
    public function getProduct(string $productId): array;

    /**
     * 📋 Get multiple products with filtering
     */
    public function getProducts(array $filters = []): Collection;

    /**
     * 🚀 Bulk create products
     */
    public function bulkCreateProducts(array $products): array;

    /**
     * 🔄 Bulk update products
     */
    public function bulkUpdateProducts(array $products): array;

    /**
     * 🔗 Sync products from local to marketplace
     */
    public function syncProducts(Collection $localProducts): array;

    /**
     * 🏷️ Get marketplace categories/taxonomies
     */
    public function getCategories(): array;

    /**
     * 🎨 Upload and manage product images
     */
    public function uploadProductImages(string $productId, array $images): array;

    /**
     * 🔢 Update product inventory
     */
    public function updateInventory(string $productId, $inventoryData): array;

    /**
     * 💰 Update product pricing
     */
    public function updatePricing(string $productId, array $pricingData): array;

    /**
     * 🏃‍♂️ Get product variants
     */
    public function getProductVariants(string $productId): array;

    /**
     * ✅ Validate product data before operation
     */
    public function validateProductData(array $productData): array;
}
