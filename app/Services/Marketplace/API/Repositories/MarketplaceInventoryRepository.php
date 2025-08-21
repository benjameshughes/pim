<?php

namespace App\Services\Marketplace\API\Repositories;

use Illuminate\Support\Collection;

/**
 * 📦 MARKETPLACE INVENTORY REPOSITORY
 *
 * Repository for marketplace inventory operations.
 * Provides specialized methods for inventory management,
 * stock updates, reservations, and inventory tracking.
 */
class MarketplaceInventoryRepository extends AbstractMarketplaceRepository
{
    /**
     * 🔍 Find inventory for a single product by ID
     */
    public function find(string $productId): ?array
    {
        $inventory = $this->service->getInventoryLevels([$productId]);

        return $inventory->first();
    }

    /**
     * 📋 Get inventory levels for multiple products
     */
    public function all(array $filters = []): Collection
    {
        $productIds = $filters['product_ids'] ?? [];

        return $this->service->getInventoryLevels($productIds);
    }

    /**
     * ➕ Create inventory record (set initial stock)
     */
    public function create(array $data): array
    {
        $validation = $this->validateInventoryData($data);
        if (! $validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors'],
            ];
        }

        return $this->service->updateInventory($data['product_id'], $data['quantity']);
    }

    /**
     * 📝 Update inventory for a product
     */
    public function update(string $productId, array $data): array
    {
        $validation = $this->validateInventoryData($data);
        if (! $validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors'],
            ];
        }

        return $this->service->updateInventory($productId, $data['quantity']);
    }

    /**
     * 🗑️ Delete inventory (set to zero)
     */
    public function delete(string $productId): bool
    {
        $result = $this->service->updateInventory($productId, 0);

        return $result['success'] ?? false;
    }

    /**
     * 🚀 Bulk create inventory records
     */
    public function bulkCreate(array $records): array
    {
        return $this->bulkUpdate($records);
    }

    /**
     * 🔄 Bulk update inventory levels
     */
    public function bulkUpdate(array $records): array
    {
        $validated = [];
        $errors = [];

        foreach ($records as $index => $record) {
            $validation = $this->validateInventoryData($record);
            if ($validation['valid']) {
                $validated[] = $record;
            } else {
                $errors["record_{$index}"] = $validation['errors'];
            }
        }

        if (! empty($errors)) {
            return [
                'success' => false,
                'errors' => $errors,
                'valid_records' => count($validated),
                'invalid_records' => count($errors),
            ];
        }

        return $this->service->bulkUpdateInventory($validated);
    }

    /**
     * 📍 Get inventory by location/warehouse
     */
    public function byLocation(string $locationId): Collection
    {
        return $this->service->getInventoryByLocation($locationId);
    }

    /**
     * ⚠️ Get products with low stock
     */
    public function lowStock(int $threshold = 5): Collection
    {
        return $this->service->getLowStockProducts($threshold);
    }

    /**
     * 📈 Get inventory history for a product
     */
    public function getHistory(string $productId, int $days = 30): array
    {
        return $this->service->getInventoryHistory($productId, $days);
    }

    /**
     * 🔄 Sync inventory from local to marketplace
     */
    public function syncFromLocal(Collection $localInventory): array
    {
        return $this->service->syncInventoryToMarketplace($localInventory);
    }

    /**
     * ⬇️ Pull inventory updates from marketplace
     */
    public function pullFromMarketplace(): array
    {
        return $this->service->pullInventoryFromMarketplace();
    }

    /**
     * 🏪 Get available locations/warehouses
     */
    public function getLocations(): array
    {
        return $this->service->getLocations();
    }

    /**
     * 🎯 Reserve inventory for order
     */
    public function reserve(string $productId, int $quantity): array
    {
        if ($quantity <= 0) {
            return [
                'success' => false,
                'error' => 'Reservation quantity must be positive',
            ];
        }

        return $this->service->reserveInventory($productId, $quantity);
    }

    /**
     * ↩️ Release reserved inventory
     */
    public function release(string $productId, int $quantity): array
    {
        if ($quantity <= 0) {
            return [
                'success' => false,
                'error' => 'Release quantity must be positive',
            ];
        }

        return $this->service->releaseReservedInventory($productId, $quantity);
    }

    /**
     * ➕ Add to inventory (increase stock)
     */
    public function addStock(string $productId, int $quantity, string $reason = ''): array
    {
        if ($quantity <= 0) {
            return [
                'success' => false,
                'error' => 'Add quantity must be positive',
            ];
        }

        // Get current inventory
        $current = $this->find($productId);
        $currentQuantity = $current['quantity'] ?? 0;
        $newQuantity = $currentQuantity + $quantity;

        return $this->service->updateInventory($productId, $newQuantity);
    }

    /**
     * ➖ Remove from inventory (decrease stock)
     */
    public function removeStock(string $productId, int $quantity, string $reason = ''): array
    {
        if ($quantity <= 0) {
            return [
                'success' => false,
                'error' => 'Remove quantity must be positive',
            ];
        }

        // Get current inventory
        $current = $this->find($productId);
        $currentQuantity = $current['quantity'] ?? 0;
        $newQuantity = max(0, $currentQuantity - $quantity);

        return $this->service->updateInventory($productId, $newQuantity);
    }

    /**
     * 🔍 Find products with zero inventory
     */
    public function outOfStock(): static
    {
        return $this->where('quantity', 0);
    }

    /**
     * 📊 Find products with specific quantity
     */
    public function withQuantity(int $quantity): static
    {
        return $this->where('quantity', $quantity);
    }

    /**
     * 📈 Find products with quantity above threshold
     */
    public function withQuantityAbove(int $threshold): static
    {
        return $this->where('quantity', $threshold, '>');
    }

    /**
     * 📉 Find products with quantity below threshold
     */
    public function withQuantityBelow(int $threshold): static
    {
        return $this->where('quantity', $threshold, '<');
    }

    /**
     * 🔍 Find products with quantity in range
     */
    public function withQuantityBetween(int $min, int $max): static
    {
        return $this->where('quantity', $min, '>=')
            ->where('quantity', $max, '<=');
    }

    /**
     * 📋 Get inventory adjustment reasons
     */
    public function getAdjustmentReasons(): array
    {
        return $this->service->getAdjustmentReasons();
    }

    /**
     * 📊 Get inventory statistics
     */
    public function getStatistics(): array
    {
        $inventory = $this->all();

        $totalProducts = $inventory->count();
        $totalQuantity = $inventory->sum('quantity');
        $outOfStock = $inventory->where('quantity', 0)->count();
        $lowStock = $inventory->where('quantity', '>', 0)->where('quantity', '<=', 5)->count();
        $inStock = $inventory->where('quantity', '>', 5)->count();

        return [
            'total_products' => $totalProducts,
            'total_quantity' => $totalQuantity,
            'average_quantity' => $totalProducts > 0 ? round($totalQuantity / $totalProducts, 2) : 0,
            'out_of_stock' => $outOfStock,
            'low_stock' => $lowStock,
            'in_stock' => $inStock,
            'out_of_stock_percentage' => $totalProducts > 0 ? round(($outOfStock / $totalProducts) * 100, 2) : 0,
            'low_stock_percentage' => $totalProducts > 0 ? round(($lowStock / $totalProducts) * 100, 2) : 0,
        ];
    }

    /**
     * 📈 Get inventory trends
     */
    public function getTrends(int $days = 30): array
    {
        // This would typically require historical data
        // Implementation depends on marketplace API capabilities
        return [
            'period_days' => $days,
            'trends' => [
                'stock_additions' => 0,
                'stock_reductions' => 0,
                'reservations' => 0,
                'releases' => 0,
            ],
            'note' => 'Trend analysis requires historical inventory data from marketplace',
        ];
    }

    /**
     * ⚠️ Get critical inventory alerts
     */
    public function getCriticalAlerts(): array
    {
        $outOfStock = $this->outOfStock()->get();
        $lowStock = $this->lowStock(5);

        return [
            'out_of_stock' => $outOfStock->map(fn ($item) => [
                'product_id' => $item['product_id'],
                'product_title' => $item['product_title'] ?? 'Unknown',
                'quantity' => 0,
                'severity' => 'critical',
            ])->toArray(),
            'low_stock' => $lowStock->map(fn ($item) => [
                'product_id' => $item['product_id'],
                'product_title' => $item['product_title'] ?? 'Unknown',
                'quantity' => $item['quantity'],
                'severity' => 'warning',
            ])->toArray(),
        ];
    }

    /**
     * ✅ Validate inventory data
     */
    protected function validateInventoryData(array $data): array
    {
        $errors = [];

        if (! isset($data['product_id']) || empty($data['product_id'])) {
            $errors[] = 'Product ID is required';
        }

        if (! isset($data['quantity']) || ! is_numeric($data['quantity'])) {
            $errors[] = 'Quantity must be a number';
        } elseif ($data['quantity'] < 0) {
            $errors[] = 'Quantity cannot be negative';
        }

        if (isset($data['location_id']) && ! is_string($data['location_id'])) {
            $errors[] = 'Location ID must be a string';
        }

        // Use marketplace-specific validation
        $marketplaceValidation = $this->service->validateInventoryData($data);
        if (! $marketplaceValidation['valid']) {
            $errors = array_merge($errors, $marketplaceValidation['errors']);
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
