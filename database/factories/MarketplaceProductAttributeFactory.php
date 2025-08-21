<?php

namespace Database\Factories;

use App\Models\MarketplaceProductAttribute;
use App\Models\MarketplaceTaxonomy;
use App\Models\Product;
use App\Models\SyncAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MarketplaceProductAttribute>
 */
class MarketplaceProductAttributeFactory extends Factory
{
    protected $model = MarketplaceProductAttribute::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $syncAccount = SyncAccount::factory()->create();
        $taxonomy = MarketplaceTaxonomy::factory()->attribute()->create([
            'sync_account_id' => $syncAccount->id,
        ]);

        return [
            'product_id' => Product::factory(),
            'sync_account_id' => $syncAccount->id,
            'marketplace_taxonomy_id' => $taxonomy->id,
            'attribute_key' => $taxonomy->key,
            'attribute_name' => $taxonomy->name,
            'attribute_value' => fake()->word(),
            'display_value' => fake()->words(2, true),
            'data_type' => $taxonomy->data_type,
            'is_required' => $taxonomy->is_required,
            'value_metadata' => [],
            'sync_metadata' => [
                'assigned_via' => 'manual',
                'source' => 'user',
                'confidence' => 100,
            ],
            'assigned_at' => now(),
            'assigned_by' => null,
            'last_validated_at' => now(),
            'is_valid' => true,
        ];
    }

    /**
     * Indicate that the attribute is invalid.
     */
    public function invalid(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_valid' => false,
        ]);
    }

    /**
     * Indicate that the attribute was auto-assigned.
     */
    public function autoAssigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'sync_metadata' => array_merge($attributes['sync_metadata'] ?? [], [
                'assigned_via' => 'auto',
                'source' => 'product_data',
                'confidence' => fake()->numberBetween(50, 95),
            ]),
        ]);
    }

    /**
     * Set a specific attribute value.
     */
    public function withValue(string $value, ?string $displayValue = null): static
    {
        return $this->state(fn (array $attributes) => [
            'attribute_value' => $value,
            'display_value' => $displayValue ?: $value,
        ]);
    }

    /**
     * Associate with a specific product and sync account.
     */
    public function forProductAndMarketplace(Product $product, SyncAccount $syncAccount): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product->id,
            'sync_account_id' => $syncAccount->id,
        ]);
    }
}
