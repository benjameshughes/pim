<?php

namespace App\Services;

use App\Models\Stock;
use App\Models\ProductVariant;

/**
 * 📦 STOCK SERVICE - Independent Stock Management
 * 
 * Stock operates independently from variants and manages its own lifecycle
 */
class StockService
{
    /**
     * 📊 GET STOCK FOR VARIANT
     * Get stock record for a specific variant
     */
    public function getStockForVariant(int $variantId, ?string $location = null): ?Stock
    {
        return Stock::where('product_variant_id', $variantId)
            ->when($location, fn($q) => $q->where('location', $location))
            ->first();
    }

    /**
     * 📈 GET STOCK LEVEL FOR VARIANT
     * Get current stock quantity for a variant
     */
    public function getStockLevelForVariant(int $variantId, ?string $location = null): int
    {
        $stock = $this->getStockForVariant($variantId, $location);
        return $stock ? $stock->quantity : 0;
    }

    /**
     * ✅ GET AVAILABLE STOCK FOR VARIANT
     * Get available stock (quantity - reserved) for a variant
     */
    public function getAvailableStockForVariant(int $variantId, ?string $location = null): int
    {
        $stock = $this->getStockForVariant($variantId, $location);
        return $stock ? $stock->getAvailableQuantity() : 0;
    }

    /**
     * 🔍 CHECK IF VARIANT IN STOCK
     * Check if variant has available stock
     */
    public function isVariantInStock(int $variantId, ?string $location = null): bool
    {
        return $this->getAvailableStockForVariant($variantId, $location) > 0;
    }

    /**
     * 🏗️ CREATE STOCK RECORD
     * Create new stock record for a variant
     */
    public function createStock(array $data): Stock
    {
        return Stock::create([
            'product_variant_id' => $data['product_variant_id'],
            'quantity' => $data['quantity'] ?? 0,
            'reserved' => $data['reserved'] ?? null,
            'incoming' => $data['incoming'] ?? null,
            'minimum_level' => $data['minimum_level'] ?? null,
            'maximum_level' => $data['maximum_level'] ?? null,
            'location' => $data['location'] ?? null,
            'bin_location' => $data['bin_location'] ?? null,
            'status' => $data['status'] ?? 'available',
            'track_stock' => $data['track_stock'] ?? true,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * ✏️ UPDATE STOCK RECORD
     * Update existing stock record
     */
    public function updateStock(Stock $stock, array $data): Stock
    {
        $stock->update($data);
        return $stock->fresh();
    }

    /**
     * 🔧 ADJUST STOCK LEVEL
     * Adjust stock quantity with reason logging
     */
    public function adjustStock(int $variantId, int $adjustment, string $reason = null, ?string $location = null): ?Stock
    {
        $stock = $this->getStockForVariant($variantId, $location);
        
        if (!$stock) {
            // Create stock record if it doesn't exist
            $stock = $this->createStock([
                'product_variant_id' => $variantId,
                'quantity' => max(0, $adjustment),
                'location' => $location,
            ]);
        } else {
            $stock->adjustStock($adjustment, $reason);
        }

        return $stock;
    }

    /**
     * 📝 SET STOCK LEVEL
     * Set specific stock quantity with reason logging
     */
    public function setStock(int $variantId, int $newQuantity, string $reason = null, ?string $location = null): ?Stock
    {
        $stock = $this->getStockForVariant($variantId, $location);
        
        if (!$stock) {
            // Create stock record if it doesn't exist
            $stock = $this->createStock([
                'product_variant_id' => $variantId,
                'quantity' => $newQuantity,
                'location' => $location,
            ]);
        } else {
            $stock->setStock($newQuantity, $reason);
        }

        return $stock;
    }

    /**
     * 🔒 RESERVE STOCK
     * Reserve stock for a variant
     */
    public function reserveStock(int $variantId, int $quantity, ?string $location = null): bool
    {
        $stock = $this->getStockForVariant($variantId, $location);
        
        if (!$stock) {
            return false; // Can't reserve what doesn't exist
        }

        return $stock->reserveStock($quantity);
    }

    /**
     * 🔓 RELEASE RESERVED STOCK
     * Release reserved stock for a variant
     */
    public function releaseReservedStock(int $variantId, int $quantity, ?string $location = null): bool
    {
        $stock = $this->getStockForVariant($variantId, $location);
        
        if (!$stock) {
            return false;
        }

        $stock->releaseReserved($quantity);
        return true;
    }

    /**
     * 🗑️ DELETE STOCK RECORD
     * Remove stock record (independent operation)
     */
    public function deleteStock(Stock $stock): bool
    {
        return $stock->delete();
    }

    /**
     * 📈 BULK STOCK OPERATIONS
     * Create multiple stock records at once
     */
    public function bulkCreateStock(array $stockData): \Illuminate\Support\Collection
    {
        $stockRecords = collect();

        foreach ($stockData as $data) {
            $stock = $this->createStock($data);
            $stockRecords->push($stock);
        }

        return $stockRecords;
    }

    /**
     * 🧮 GET STOCK STATISTICS
     * Get stock statistics across all locations for a variant
     */
    public function getVariantStockStats(int $variantId): array
    {
        $stockRecords = Stock::where('product_variant_id', $variantId)->get();
        
        if ($stockRecords->isEmpty()) {
            return [
                'total_quantity' => 0,
                'total_reserved' => 0,
                'total_available' => 0,
                'total_incoming' => 0,
                'location_count' => 0,
                'low_stock_locations' => 0
            ];
        }

        return [
            'total_quantity' => $stockRecords->sum('quantity'),
            'total_reserved' => $stockRecords->sum('reserved'),
            'total_available' => $stockRecords->sum(fn($stock) => $stock->getAvailableQuantity()),
            'total_incoming' => $stockRecords->sum('incoming'),
            'location_count' => $stockRecords->count(),
            'low_stock_locations' => $stockRecords->filter(fn($stock) => $stock->isLowStock())->count()
        ];
    }

    /**
     * ⚠️ GET LOW STOCK VARIANTS
     * Get variants that are running low on stock
     */
    public function getLowStockVariants(): \Illuminate\Support\Collection
    {
        return Stock::lowStock()->with('productVariant')->get();
    }

    /**
     * 📍 GET STOCK BY LOCATION
     * Get all stock records for a specific location
     */
    public function getStockByLocation(string $location): \Illuminate\Support\Collection
    {
        return Stock::forLocation($location)->with('productVariant')->get();
    }
}