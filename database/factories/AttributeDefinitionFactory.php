<?php

namespace Database\Factories;

use App\Models\AttributeDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttributeDefinition>
 */
class AttributeDefinitionFactory extends Factory
{
    protected $model = AttributeDefinition::class;

    public function definition(): array
    {
        $key = fake()->randomElement(['brand', 'material', 'color', 'size', 'weight', 'style']);

        return [
            'key' => $key,
            'name' => ucfirst($key),
            'description' => fake()->sentence(),
            'data_type' => fake()->randomElement(['string', 'numeric', 'boolean', 'enum', 'json']),
            'validation_rules' => null,
            'enum_values' => null,
            'default_value' => null,
            'is_inheritable' => fake()->boolean(80), // 80% chance of being inheritable
            'inheritance_strategy' => fake()->randomElement(['fallback', 'always', 'never']),
            'is_required_for_products' => fake()->boolean(20), // 20% chance of being required
            'is_required_for_variants' => fake()->boolean(20),
            'is_unique_per_product' => false,
            'is_system_attribute' => fake()->boolean(30),
            'marketplace_mappings' => null,
            'sync_to_shopify' => fake()->boolean(),
            'sync_to_ebay' => fake()->boolean(),
            'sync_to_mirakl' => fake()->boolean(),
            'input_type' => fake()->randomElement(['text', 'number', 'select', 'checkbox', 'textarea']),
            'ui_options' => null,
            'sort_order' => fake()->numberBetween(0, 100),
            'group' => fake()->randomElement(['general', 'appearance', 'dimensions', 'marketplace']),
            'icon' => null,
            'is_active' => true,
            'deprecated_at' => null,
            'replaced_by' => null,
        ];
    }

    public function enum(): static
    {
        return $this->state([
            'data_type' => 'enum',
            'enum_values' => ['Option 1', 'Option 2', 'Option 3'],
        ]);
    }

    public function required(): static
    {
        return $this->state([
            'is_required_for_products' => true,
            'is_required_for_variants' => true,
        ]);
    }
}
