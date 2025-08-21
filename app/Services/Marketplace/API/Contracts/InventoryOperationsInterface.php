<?php

namespace App\Services\Marketplace\API\Contracts;

use App\Services\Marketplace\API\Builders\InventoryOperationBuilder;
use Illuminate\Support\Collection;

/**
 * 📦 INVENTORY OPERATIONS INTERFACE
 *
 * Defines contract for marketplace inventory operations.
 * Covers stock management, availability, and synchronization.
 */
interface InventoryOperationsInterface
{
    /**
     * 🏗️ Create inventory operation builder
     */
    public function inventory(): InventoryOperationBuilder;

    /**
     * 📊 Get inventory levels for products
     */
    public function getInventoryLevels(array $productIds = []): Collection;

    /**
     * 🔄 Update inventory for a single product
     */
    public function updateInventory(string $productId, $inventoryData): array;

    /**
     * 🚀 Bulk update inventory levels
     */
    public function bulkUpdateInventory(array $inventoryUpdates): array;

    /**
     * 📍 Get inventory by location/warehouse
     */
    public function getInventoryByLocation(string $locationId): Collection;

    /**
     * ⚠️ Get low stock alerts
     */
    public function getLowStockProducts(int $threshold = 5): Collection;

    /**
     * 📈 Get inventory movement history
     */
    public function getInventoryHistory(string $productId, int $days = 30): array;

    /**
     * 🔄 Sync inventory from local to marketplace
     */
    public function syncInventoryToMarketplace(Collection $localInventory): array;

    /**
     * ⬇️ Pull inventory updates from marketplace
     */
    public function pullInventoryFromMarketplace(): array;

    /**
     * 🏪 Get available locations/warehouses
     */
    public function getLocations(): array;

    /**
     * 🎯 Reserve inventory for orders
     */
    public function reserveInventory(string $productId, int $quantity): array;

    /**
     * ↩️ Release reserved inventory
     */
    public function releaseReservedInventory(string $productId, int $quantity): array;

    /**
     * ✅ Validate inventory data
     */
    public function validateInventoryData(array $inventoryData): array;

    /**
     * 📋 Get inventory adjustment reasons
     */
    public function getAdjustmentReasons(): array;
}
