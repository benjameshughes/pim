<?php

namespace App\Actions\Products;

use App\Exceptions\ProductWizard\ProductSaveException;
use App\Models\ProductVariant;
use App\Models\Stock;
use Illuminate\Support\Collection;

/**
 * 📦 SAVE VARIANT STOCK ACTION - New Architecture
 *
 * Handles independent stock management using the new Stock model
 * - Creates stock records with product_variant_id
 * - Maps variant indices to actual variant IDs
 * - Supports location-based stock management
 */
class SaveVariantStockAction
{
    /**
     * Execute stock save for wizard-generated variants
     *
     * @param  Collection  $variants  - Created ProductVariant models
     * @param  array  $stockData  - Wizard stock data (indexed by variant array position)
     * @param  string|null  $location  - Stock location (defaults to null for main stock)
     */
    public function execute(Collection $variants, array $stockData, ?string $location = null): array
    {
        try {
            $updatedRecords = 0;
            $errors = [];

            // Loop through variants (not array indices!)
            foreach ($variants as $index => $variant) {
                // Check if we have stock data for this variant position
                if (! isset($stockData[$index])) {
                    continue;
                }

                $stock = $stockData[$index];

                try {
                    // Create/update stock record with proper relationships
                    Stock::updateOrCreate(
                        [
                            'product_variant_id' => $variant->id,
                            'location' => $location, // null for default/main location
                        ],
                        [
                            'quantity' => $stock['quantity'] ?? 0,
                            'reserved' => 0, // New stock starts with no reservations
                            'incoming' => 0,
                            'minimum_level' => $stock['minimum_level'] ?? null,
                            'maximum_level' => $stock['maximum_level'] ?? null,
                            'status' => 'available',
                            'track_stock' => true,
                            'notes' => $stock['notes'] ?? null,
                            'last_counted_at' => now(), // Set count date for new stock
                        ]
                    );

                    $updatedRecords++;

                } catch (\Exception $e) {
                    $errors[] = "Variant {$variant->sku}: {$e->getMessage()}";
                }
            }

            if (! empty($errors)) {
                throw new \Exception('Some stock updates failed: '.implode(', ', $errors));
            }

            return [
                'success' => true,
                'updated_records' => $updatedRecords,
                'location' => $location ?? 'Default',
                'message' => "Stock updated for {$updatedRecords} variants".
                           ($location ? " at {$location}" : ' at default location'),
            ];

        } catch (\Exception $e) {
            throw ProductSaveException::stockUpdateFailed($e);
        }
    }

    /**
     * Set initial stock levels for new variants
     * Used when wizard doesn't have explicit stock data
     */
    public function setInitialStock(Collection $variants, int $defaultQuantity = 0): array
    {
        try {
            $createdRecords = 0;

            foreach ($variants as $variant) {
                Stock::create([
                    'product_variant_id' => $variant->id,
                    'location' => null,
                    'quantity' => $defaultQuantity,
                    'reserved' => 0,
                    'incoming' => 0,
                    'status' => 'available',
                    'track_stock' => true,
                    'last_counted_at' => now(),
                    'notes' => 'Initial stock created via Product Wizard',
                ]);

                $createdRecords++;
            }

            return [
                'success' => true,
                'created_records' => $createdRecords,
                'message' => "Initial stock records created for {$createdRecords} variants",
            ];

        } catch (\Exception $e) {
            throw ProductSaveException::stockCreationFailed($e);
        }
    }

    /**
     * Bulk update stock for multiple variants
     */
    public function bulkUpdate(array $variantIds, array $stockData, ?string $location = null): array
    {
        try {
            $updatedRecords = 0;

            foreach ($variantIds as $variantId) {
                Stock::updateOrCreate(
                    [
                        'product_variant_id' => $variantId,
                        'location' => $location,
                    ],
                    [
                        'quantity' => $stockData['quantity'] ?? 0,
                        'minimum_level' => $stockData['minimum_level'] ?? null,
                        'maximum_level' => $stockData['maximum_level'] ?? null,
                        'track_stock' => $stockData['track_stock'] ?? true,
                        'notes' => $stockData['notes'] ?? null,
                        'last_counted_at' => now(),
                    ]
                );

                $updatedRecords++;
            }

            return [
                'success' => true,
                'updated_records' => $updatedRecords,
                'message' => "Bulk stock update completed for {$updatedRecords} variants",
            ];

        } catch (\Exception $e) {
            throw ProductSaveException::bulkStockUpdateFailed($e);
        }
    }

    /**
     * Adjust stock levels (for inventory movements)
     */
    public function adjustStock(ProductVariant $variant, int $adjustment, string $reason, ?string $location = null): array
    {
        try {
            $stock = Stock::where('product_variant_id', $variant->id)
                ->where('location', $location)
                ->first();

            if (! $stock) {
                throw new \Exception("No stock record found for variant {$variant->sku} at location: ".($location ?? 'default'));
            }

            $stock->adjustStock($adjustment, $reason);

            return [
                'success' => true,
                'variant_sku' => $variant->sku,
                'old_quantity' => $stock->quantity - $adjustment,
                'new_quantity' => $stock->quantity,
                'adjustment' => $adjustment,
                'reason' => $reason,
                'message' => "Stock adjusted for {$variant->sku}: {$adjustment} ({$reason})",
            ];

        } catch (\Exception $e) {
            throw ProductSaveException::stockAdjustmentFailed($e);
        }
    }
}
