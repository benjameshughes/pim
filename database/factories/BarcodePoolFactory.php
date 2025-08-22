<?php

namespace Database\Factories;

use App\Models\BarcodePool;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BarcodePool>
 */
class BarcodePoolFactory extends Factory
{
    protected $model = BarcodePool::class;

    public function definition(): array
    {
        return [
            'barcode' => $this->generateValidEAN13(),
            'barcode_type' => 'EAN13',
            'status' => 'available',
            'is_legacy' => false,
            'row_number' => fake()->numberBetween(40000, 100000), // Start from 40,000 as specified
            'quality_score' => fake()->numberBetween(7, 10), // High quality by default
            'import_batch_id' => 'factory_' . fake()->uuid(),
            'legacy_sku' => null,
            'legacy_status' => null,
            'legacy_product_name' => null,
            'legacy_brand' => null,
            'legacy_updated' => null,
            'legacy_notes' => null,
            'notes' => fake()->optional()->sentence(),
            'metadata' => null,
            'assigned_to_variant_id' => null,
            'assigned_at' => null,
            'date_first_used' => null,
        ];
    }

    /**
     * Generate a valid EAN13 barcode with proper check digit
     */
    private function generateValidEAN13(): string
    {
        // Generate first 12 digits
        $base = str_pad(fake()->unique()->numberBetween(500000000000, 599999999999), 12, '0', STR_PAD_LEFT);
        
        // Calculate check digit
        $checkDigit = $this->calculateEAN13CheckDigit($base);
        
        return $base . $checkDigit;
    }

    /**
     * Calculate EAN13 check digit
     */
    private function calculateEAN13CheckDigit(string $code): string
    {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $digit = (int) $code[$i];
            $sum += ($i % 2 === 0) ? $digit : $digit * 3;
        }
        
        $checkDigit = (10 - ($sum % 10)) % 10;
        
        return (string) $checkDigit;
    }

    /**
     * Available status
     */
    public function available(): static
    {
        return $this->state([
            'status' => 'available',
            'assigned_to_variant_id' => null,
            'assigned_at' => null,
        ]);
    }

    /**
     * Assigned status
     */
    public function assigned(): static
    {
        return $this->state([
            'status' => 'assigned',
            'assigned_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'date_first_used' => fake()->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    /**
     * Legacy barcode (from rows < 40,000)
     */
    public function legacy(): static
    {
        return $this->state([
            'is_legacy' => true,
            'status' => 'legacy_archive',
            'row_number' => fake()->numberBetween(1, 39999),
            'quality_score' => fake()->numberBetween(4, 7), // Lower quality
            'legacy_sku' => fake()->regexify('[A-Z]{2,3}-[0-9]{3,4}'),
            'legacy_product_name' => fake()->words(3, true),
            'legacy_brand' => fake()->company(),
            'legacy_status' => fake()->randomElement(['active', 'discontinued', 'archived']),
            'legacy_updated' => fake()->date(),
            'legacy_notes' => 'Legacy data from row ' . fake()->numberBetween(1, 39999),
        ]);
    }

    /**
     * High quality barcode
     */
    public function highQuality(): static
    {
        return $this->state([
            'quality_score' => fake()->numberBetween(8, 10),
            'row_number' => fake()->numberBetween(50000, 100000), // Later rows = higher quality
        ]);
    }

    /**
     * Low quality barcode
     */
    public function lowQuality(): static
    {
        return $this->state([
            'quality_score' => fake()->numberBetween(1, 6),
            'status' => fake()->randomElement(['available', 'problematic']),
        ]);
    }

    /**
     * Reserved status
     */
    public function reserved(): static
    {
        return $this->state([
            'status' => 'reserved',
            'notes' => 'Reserved for ' . fake()->company(),
        ]);
    }

    /**
     * Problematic status
     */
    public function problematic(): static
    {
        return $this->state([
            'status' => 'problematic',
            'quality_score' => fake()->numberBetween(1, 4),
            'notes' => 'Quality issues: ' . fake()->sentence(),
        ]);
    }

    /**
     * For specific barcode type
     */
    public function type(string $type): static
    {
        return $this->state([
            'barcode_type' => $type,
        ]);
    }
}