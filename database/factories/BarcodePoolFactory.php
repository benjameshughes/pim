<?php

namespace Database\Factories;

use App\Models\BarcodePool;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BarcodePool>
 */
class BarcodePoolFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'barcode' => $this->generateBarcode(),
            'barcode_type' => $this->faker->randomElement(['EAN13', 'UPC', 'CODE128']),
            'status' => 'available',
            'assigned_to_variant_id' => null,
            'assigned_at' => null,
            'notes' => null,
            'legacy_notes' => null,
            'date_first_used' => null,
            'is_legacy' => false,
            'import_batch_id' => null,
        ];
    }

    /**
     * Generate a valid EAN13 barcode
     */
    private function generateBarcode(): string
    {
        // Generate first 12 digits
        $first12 = str_pad($this->faker->numerify('############'), 12, '0', STR_PAD_LEFT);
        
        // Calculate check digit for EAN13
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $digit = (int) $first12[$i];
            $sum += ($i % 2 === 0) ? $digit : $digit * 3;
        }
        $checkDigit = (10 - ($sum % 10)) % 10;
        
        return $first12 . $checkDigit;
    }

    /**
     * Create assigned barcode
     */
    public function assigned($variantId = null)
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'assigned',
            'assigned_to_variant_id' => $variantId ?? $this->faker->numberBetween(1, 1000),
            'assigned_at' => now(),
            'date_first_used' => now(),
        ]);
    }

    /**
     * Create reserved barcode
     */
    public function reserved()
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'reserved',
        ]);
    }

    /**
     * Create legacy barcode
     */
    public function legacy()
    {
        return $this->state(fn (array $attributes) => [
            'is_legacy' => true,
            'status' => 'legacy_archive',
            'legacy_notes' => 'Migrated from legacy system',
        ]);
    }

    /**
     * Create EAN13 barcode
     */
    public function ean13()
    {
        return $this->state(fn (array $attributes) => [
            'barcode_type' => 'EAN13',
            'barcode' => $this->generateBarcode(),
        ]);
    }

    /**
     * Create UPC barcode
     */
    public function upc()
    {
        return $this->state(fn (array $attributes) => [
            'barcode_type' => 'UPC',
            'barcode' => $this->faker->numerify('############'),
        ]);
    }

    /**
     * Create with specific import batch
     */
    public function fromBatch($batchId)
    {
        return $this->state(fn (array $attributes) => [
            'import_batch_id' => $batchId,
        ]);
    }
}
