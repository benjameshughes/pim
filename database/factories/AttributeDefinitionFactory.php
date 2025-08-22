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
        $name = fake()->randomElement(['brand', 'material', 'color', 'size', 'weight', 'style']);
        
        return [
            'name' => $name,
            'label' => ucfirst($name),
            'type' => fake()->randomElement(['text', 'number', 'select', 'boolean']),
            'description' => fake()->sentence(),
            'is_required' => fake()->boolean(20), // 20% chance of being required
            'is_unique' => false,
            'validation_rules' => null,
            'options' => null,
            'group' => fake()->randomElement(['basic', 'advanced', 'marketplace']),
            'sort_order' => fake()->numberBetween(0, 100),
            'is_active' => true,
            'is_inheritable' => fake()->boolean(80), // 80% chance of being inheritable
            'marketplace_mappings' => null,
        ];
    }

    public function select(): static
    {
        return $this->state([
            'type' => 'select',
            'options' => ['Option 1', 'Option 2', 'Option 3'],
        ]);
    }

    public function required(): static
    {
        return $this->state([
            'is_required' => true,
        ]);
    }
}