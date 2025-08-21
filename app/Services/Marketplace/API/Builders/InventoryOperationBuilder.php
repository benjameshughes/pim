<?php

namespace App\Services\Marketplace\API\Builders;

use App\Services\Marketplace\API\AbstractMarketplaceService;
use Illuminate\Support\Collection;

/**
 * 📦 INVENTORY OPERATION BUILDER
 *
 * Fluent API builder for marketplace inventory operations.
 * Provides chainable methods for configuring inventory operations.
 *
 * Usage:
 * $result = $service->inventory()
 *     ->forProducts($productIds)
 *     ->atLocation($locationId)
 *     ->updateQuantity(100)
 *     ->withReason('restock')
 *     ->execute();
 */
class InventoryOperationBuilder
{
    protected AbstractMarketplaceService $service;

    protected array $productIds = [];

    protected ?string $locationId = null;

    protected ?int $quantity = null;

    protected string $operation = 'get';

    protected array $updates = [];

    protected bool $bulkMode = false;

    protected int $threshold = 5;

    protected string $reason = '';

    protected array $filters = [];

    protected bool $includeHistory = false;

    protected bool $includeReserved = false;

    protected int $historyDays = 30;

    public function __construct(AbstractMarketplaceService $service)
    {
        $this->service = $service;
    }

    /**
     * 🎯 Specify products to operate on
     */
    public function forProducts(array $productIds): static
    {
        $this->productIds = $productIds;

        return $this;
    }

    /**
     * 🎯 Specify single product to operate on
     */
    public function forProduct(string $productId): static
    {
        $this->productIds = [$productId];

        return $this;
    }

    /**
     * 📍 Specify location/warehouse
     */
    public function atLocation(string $locationId): static
    {
        $this->locationId = $locationId;

        return $this;
    }

    /**
     * 🔄 Set operation to update inventory
     */
    public function updateQuantity(int $quantity): static
    {
        $this->operation = 'update';
        $this->quantity = $quantity;

        return $this;
    }

    /**
     * ➕ Set operation to add to inventory
     */
    public function addQuantity(int $quantity): static
    {
        $this->operation = 'add';
        $this->quantity = $quantity;

        return $this;
    }

    /**
     * ➖ Set operation to subtract from inventory
     */
    public function subtractQuantity(int $quantity): static
    {
        $this->operation = 'subtract';
        $this->quantity = $quantity;

        return $this;
    }

    /**
     * 🚀 Set operation to bulk update
     */
    public function bulkUpdate(array $updates): static
    {
        $this->operation = 'bulk_update';
        $this->bulkMode = true;
        $this->updates = $updates;

        return $this;
    }

    /**
     * 🔄 Set operation to sync inventory
     */
    public function sync(Collection $localInventory): static
    {
        $this->operation = 'sync';
        $this->updates = $localInventory->toArray();

        return $this;
    }

    /**
     * ⚠️ Set operation to get low stock products
     */
    public function getLowStock(int $threshold = 5): static
    {
        $this->operation = 'low_stock';
        $this->threshold = $threshold;

        return $this;
    }

    /**
     * 📈 Set operation to get inventory history
     */
    public function getHistory(int $days = 30): static
    {
        $this->operation = 'history';
        $this->historyDays = $days;
        $this->includeHistory = true;

        return $this;
    }

    /**
     * 🎯 Reserve inventory for orders
     */
    public function reserve(int $quantity): static
    {
        $this->operation = 'reserve';
        $this->quantity = $quantity;

        return $this;
    }

    /**
     * ↩️ Release reserved inventory
     */
    public function release(int $quantity): static
    {
        $this->operation = 'release';
        $this->quantity = $quantity;

        return $this;
    }

    /**
     * 📝 Add reason for inventory change
     */
    public function withReason(string $reason): static
    {
        $this->reason = $reason;

        return $this;
    }

    /**
     * 📊 Include inventory history in response
     */
    public function includeHistory(): static
    {
        $this->includeHistory = true;

        return $this;
    }

    /**
     * 🔒 Include reserved inventory in response
     */
    public function includeReserved(): static
    {
        $this->includeReserved = true;

        return $this;
    }

    /**
     * ⚙️ Add custom filter
     */
    public function withFilter(string $key, $value): static
    {
        $this->filters[$key] = $value;

        return $this;
    }

    /**
     * 🚀 Execute the configured inventory operation
     */
    public function execute(): array
    {
        return match ($this->operation) {
            'get' => $this->executeGet(),
            'update' => $this->executeUpdate(),
            'add' => $this->executeAdd(),
            'subtract' => $this->executeSubtract(),
            'bulk_update' => $this->executeBulkUpdate(),
            'sync' => $this->executeSync(),
            'low_stock' => $this->executeLowStock(),
            'history' => $this->executeHistory(),
            'reserve' => $this->executeReserve(),
            'release' => $this->executeRelease(),
            default => throw new \InvalidArgumentException("Unknown operation: {$this->operation}")
        };
    }

    /**
     * 📋 Get inventory levels
     */
    protected function executeGet(): array
    {
        if (! empty($this->productIds)) {
            $inventory = $this->service->getInventoryLevels($this->productIds);
        } elseif ($this->locationId) {
            $inventory = $this->service->getInventoryByLocation($this->locationId);
        } else {
            $inventory = $this->service->getInventoryLevels();
        }

        return [
            'success' => true,
            'data' => $inventory,
            'count' => $inventory->count(),
        ];
    }

    /**
     * 🔄 Update inventory quantity
     */
    protected function executeUpdate(): array
    {
        if (empty($this->productIds) || count($this->productIds) > 1) {
            throw new \InvalidArgumentException('Update operation requires exactly one product ID');
        }

        return $this->service->updateInventory($this->productIds[0], $this->quantity);
    }

    /**
     * ➕ Add to inventory
     */
    protected function executeAdd(): array
    {
        // This would typically get current inventory and add to it
        // Implementation depends on marketplace API capabilities
        if (empty($this->productIds) || count($this->productIds) > 1) {
            throw new \InvalidArgumentException('Add operation requires exactly one product ID');
        }

        // Get current inventory first
        $currentInventory = $this->service->getInventoryLevels([$this->productIds[0]]);
        $current = $currentInventory->first()['quantity'] ?? 0;
        $newQuantity = $current + $this->quantity;

        return $this->service->updateInventory($this->productIds[0], $newQuantity);
    }

    /**
     * ➖ Subtract from inventory
     */
    protected function executeSubtract(): array
    {
        if (empty($this->productIds) || count($this->productIds) > 1) {
            throw new \InvalidArgumentException('Subtract operation requires exactly one product ID');
        }

        // Get current inventory first
        $currentInventory = $this->service->getInventoryLevels([$this->productIds[0]]);
        $current = $currentInventory->first()['quantity'] ?? 0;
        $newQuantity = max(0, $current - $this->quantity);

        return $this->service->updateInventory($this->productIds[0], $newQuantity);
    }

    /**
     * 🚀 Execute bulk update
     */
    protected function executeBulkUpdate(): array
    {
        return $this->service->bulkUpdateInventory($this->updates);
    }

    /**
     * 🔄 Execute sync operation
     */
    protected function executeSync(): array
    {
        $localInventory = collect($this->updates);

        return $this->service->syncInventoryToMarketplace($localInventory);
    }

    /**
     * ⚠️ Get low stock products
     */
    protected function executeLowStock(): array
    {
        $lowStock = $this->service->getLowStockProducts($this->threshold);

        return [
            'success' => true,
            'data' => $lowStock,
            'count' => $lowStock->count(),
            'threshold' => $this->threshold,
        ];
    }

    /**
     * 📈 Get inventory history
     */
    protected function executeHistory(): array
    {
        if (empty($this->productIds) || count($this->productIds) > 1) {
            throw new \InvalidArgumentException('History operation requires exactly one product ID');
        }

        $history = $this->service->getInventoryHistory($this->productIds[0], $this->historyDays);

        return [
            'success' => true,
            'data' => $history,
            'product_id' => $this->productIds[0],
            'days' => $this->historyDays,
        ];
    }

    /**
     * 🎯 Reserve inventory
     */
    protected function executeReserve(): array
    {
        if (empty($this->productIds) || count($this->productIds) > 1) {
            throw new \InvalidArgumentException('Reserve operation requires exactly one product ID');
        }

        return $this->service->reserveInventory($this->productIds[0], $this->quantity);
    }

    /**
     * ↩️ Release reserved inventory
     */
    protected function executeRelease(): array
    {
        if (empty($this->productIds) || count($this->productIds) > 1) {
            throw new \InvalidArgumentException('Release operation requires exactly one product ID');
        }

        return $this->service->releaseReservedInventory($this->productIds[0], $this->quantity);
    }

    /**
     * 📊 Get operation summary
     */
    public function getSummary(): array
    {
        return [
            'operation' => $this->operation,
            'product_ids' => $this->productIds,
            'location_id' => $this->locationId,
            'quantity' => $this->quantity,
            'bulk_mode' => $this->bulkMode,
            'threshold' => $this->threshold,
            'reason' => $this->reason,
            'include_history' => $this->includeHistory,
            'include_reserved' => $this->includeReserved,
            'history_days' => $this->historyDays,
            'custom_filters' => array_keys($this->filters),
        ];
    }
}
