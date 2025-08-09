<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AttributeDefinition>
 */
class AttributeDefinitionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->slug(2),
            'label' => $this->faker->words(2, true),
            'data_type' => $this->faker->randomElement(['string', 'number', 'boolean', 'json']),
            'category' => $this->faker->randomElement(['appearance', 'technical', 'commercial', 'logistics']),
            'applies_to' => $this->faker->randomElement(['product', 'variant', 'both']),
            'is_required' => $this->faker->boolean(30), // 30% chance of being required
            'validation_rules' => [],
            'description' => $this->faker->sentence(),
            'sort_order' => $this->faker->numberBetween(1, 100),
            'is_active' => true,
        ];
    }

    /**
     * Mark attribute as required
     */
    public function required()
    {
        return $this->state(fn (array $attributes) => [
            'is_required' => true,
        ]);
    }

    /**
     * Mark attribute as for products
     */
    public function forProducts()
    {
        return $this->state(fn (array $attributes) => [
            'applies_to' => 'product',
        ]);
    }

    /**
     * Mark attribute as for variants
     */
    public function forVariants()
    {
        return $this->state(fn (array $attributes) => [
            'applies_to' => 'variant',
        ]);
    }

    /**
     * Mark attribute as inactive
     */
    public function inactive()
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create specific attribute types
     */
    public function color()
    {
        return $this->state(fn (array $attributes) => [
            'key' => 'color',
            'label' => 'Color',
            'data_type' => 'string',
            'category' => 'appearance',
            'applies_to' => 'variant',
        ]);
    }

    public function material()
    {
        return $this->state(fn (array $attributes) => [
            'key' => 'material',
            'label' => 'Material',
            'data_type' => 'string',
            'category' => 'technical',
            'applies_to' => 'product',
        ]);
    }
}
