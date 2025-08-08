<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductFeature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductFeature>
 */
class ProductFeatureFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['feature', 'detail']);
        
        $features = [
            'Water-resistant coating for durability',
            'Easy to clean surface',
            'UV protection to prevent fading',
            'Energy efficient design',
            'Child safety features included',
            'Cordless operation for safety',
            'Quick and easy installation',
            'Available in multiple colors',
            'Lightweight aluminum construction',
            'Smooth operating mechanism',
        ];
        
        $details = [
            'Made from high-quality materials',
            'Suitable for high humidity areas',
            'Compatible with smart home systems',
            '2-year manufacturer warranty included',
            'Professional installation recommended',
            'Care instructions included in packaging',
            'Dimensions may vary slightly',
            'Color matching guarantee',
            'Eco-friendly manufacturing process',
            'Fire retardant materials used',
        ];
        
        $content = $type === 'feature' 
            ? $this->faker->randomElement($features)
            : $this->faker->randomElement($details);

        return [
            'product_id' => Product::factory(),
            'type' => $type,
            'title' => $this->faker->optional(0.3)->words(2, true), // 30% chance of having a title
            'content' => $content,
            'sort_order' => $this->faker->numberBetween(1, 10),
        ];
    }

    /**
     * Indicate that the feature is a product feature.
     */
    public function feature(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'feature',
        ]);
    }

    /**
     * Indicate that the feature is a product detail.
     */
    public function detail(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'detail',
        ]);
    }

    /**
     * Indicate that the feature has a title.
     */
    public function withTitle(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => $this->faker->words(2, true),
        ]);
    }

    /**
     * Set a specific sort order.
     */
    public function order(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'sort_order' => $order,
        ]);
    }
}