<?php

namespace Database\Factories;

use App\Models\MarketplaceTaxonomy;
use App\Models\SyncAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MarketplaceTaxonomy>
 */
class MarketplaceTaxonomyFactory extends Factory
{
    protected $model = MarketplaceTaxonomy::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sync_account_id' => SyncAccount::factory(),
            'taxonomy_type' => fake()->randomElement(['category', 'attribute', 'value']),
            'external_id' => 'ext_'.fake()->unique()->word(),
            'external_parent_id' => null,
            'name' => fake()->words(2, true),
            'key' => fake()->unique()->slug(),
            'description' => fake()->sentence(),
            'level' => 1,
            'is_leaf' => fake()->boolean(),
            'is_required' => fake()->boolean(),
            'data_type' => fake()->randomElement(['text', 'integer', 'decimal', 'boolean', 'list']),
            'validation_rules' => null,
            'metadata' => [],
            'properties' => [],
            'last_synced_at' => now(),
            'is_active' => true,
            'sync_version' => '1.0',
        ];
    }

    /**
     * Indicate that the taxonomy is a category.
     */
    public function category(): static
    {
        return $this->state(fn (array $attributes) => [
            'taxonomy_type' => 'category',
            'data_type' => null,
            'is_required' => false,
        ]);
    }

    /**
     * Indicate that the taxonomy is an attribute.
     */
    public function attribute(): static
    {
        return $this->state(fn (array $attributes) => [
            'taxonomy_type' => 'attribute',
            'data_type' => fake()->randomElement(['text', 'integer', 'decimal', 'boolean', 'list']),
        ]);
    }

    /**
     * Indicate that the taxonomy is a value.
     */
    public function value(): static
    {
        return $this->state(fn (array $attributes) => [
            'taxonomy_type' => 'value',
            'data_type' => null,
            'is_required' => false,
        ]);
    }

    /**
     * Indicate that the taxonomy is required.
     */
    public function required(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_required' => true,
        ]);
    }

    /**
     * Indicate that the taxonomy is optional.
     */
    public function optional(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_required' => false,
        ]);
    }

    /**
     * Indicate that the taxonomy has choices (for list type attributes).
     */
    public function withChoices(array $choices): static
    {
        return $this->state(fn (array $attributes) => [
            'data_type' => 'list',
            'validation_rules' => ['choices' => $choices],
        ]);
    }

    /**
     * Indicate that the taxonomy is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
