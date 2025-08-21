<?php

namespace Database\Factories;

use App\Models\ChannelFieldDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChannelFieldDefinition>
 */
class ChannelFieldDefinitionFactory extends Factory
{
    protected $model = ChannelFieldDefinition::class;

    public function definition(): array
    {
        $channelTypes = ['shopify', 'amazon', 'ebay', 'mirakl'];
        $fieldTypes = ['TEXT', 'LONG_TEXT', 'INTEGER', 'DECIMAL', 'BOOLEAN', 'DATE', 'LIST', 'LIST_MULTIPLE_VALUES'];
        $fieldCodes = ['title', 'description', 'price', 'sku', 'brand', 'condition', 'category', 'weight', 'dimensions'];

        $fieldCode = $this->faker->randomElement($fieldCodes);
        $channelType = $this->faker->randomElement($channelTypes);

        return [
            'channel_type' => $channelType,
            'channel_subtype' => $this->generateChannelSubtype($channelType),
            'category' => $this->faker->optional(0.3)->randomElement(['electronics', 'clothing', 'home', 'books']),
            'field_code' => $fieldCode,
            'field_label' => $this->generateFieldLabel($fieldCode),
            'field_type' => $this->faker->randomElement($fieldTypes),
            'is_required' => $this->faker->boolean(40), // 40% chance of being required
            'description' => $this->faker->optional(0.7)->sentence(),
            'field_metadata' => $this->faker->optional(0.3)->randomElement([
                ['min_length' => 5, 'max_length' => 100],
                ['format' => 'email'],
                ['pattern' => '^[A-Z][a-z]+$'],
            ]),
            'validation_rules' => $this->faker->optional(0.4)->randomElement([
                ['required' => true, 'min' => 0],
                ['max_length' => 255],
                ['numeric' => true, 'min' => 0, 'max' => 99999.99],
            ]),
            'allowed_values' => $this->generateAllowedValues($fieldCode),
            'value_list_code' => $this->faker->optional(0.2)->randomElement(['colors', 'sizes', 'conditions', 'categories']),
            'discovered_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'last_verified_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'api_version' => $this->faker->optional(0.5)->randomElement(['v1', 'v2', '2024-01', '2024-07']),
            'is_active' => $this->faker->boolean(90), // 90% chance of being active
        ];
    }

    /**
     * Generate realistic channel subtype based on channel type
     */
    private function generateChannelSubtype(string $channelType): ?string
    {
        return match ($channelType) {
            'shopify' => $this->faker->randomElement(['main-store', 'backup-store', 'test-store']),
            'amazon' => $this->faker->randomElement(['us-seller', 'uk-seller', 'de-seller']),
            'ebay' => $this->faker->randomElement(['main-account', 'business-account']),
            'mirakl' => $this->faker->randomElement(['freemans', 'debenhams', 'operator-1']),
            default => null,
        };
    }

    /**
     * Generate realistic field labels based on field codes
     */
    private function generateFieldLabel(string $fieldCode): string
    {
        return match ($fieldCode) {
            'title' => 'Product Title',
            'description' => 'Product Description',
            'price' => 'Price',
            'sku' => 'SKU',
            'brand' => 'Brand',
            'condition' => 'Condition',
            'category' => 'Category',
            'weight' => 'Weight',
            'dimensions' => 'Dimensions',
            default => ucfirst($fieldCode),
        };
    }

    /**
     * Generate allowed values for LIST type fields
     */
    private function generateAllowedValues(string $fieldCode): ?array
    {
        return match ($fieldCode) {
            'condition' => ['new', 'used', 'refurbished', 'damaged'],
            'category' => ['electronics', 'clothing', 'home', 'books', 'sports'],
            default => $this->faker->optional(0.2)->randomElements(['option1', 'option2', 'option3', 'option4'], 3),
        };
    }

    /**
     * Create a required field
     */
    public function required(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_required' => true,
        ]);
    }

    /**
     * Create an optional field
     */
    public function optional(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_required' => false,
        ]);
    }

    /**
     * Create an active field
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Create an inactive field
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a field for a specific channel
     */
    public function forChannel(string $channelType, ?string $channelSubtype = null): static
    {
        return $this->state(fn (array $attributes) => [
            'channel_type' => $channelType,
            'channel_subtype' => $channelSubtype ?? $this->generateChannelSubtype($channelType),
        ]);
    }

    /**
     * Create a field with specific field code
     */
    public function withFieldCode(string $fieldCode): static
    {
        return $this->state(fn (array $attributes) => [
            'field_code' => $fieldCode,
            'field_label' => $this->generateFieldLabel($fieldCode),
        ]);
    }

    /**
     * Create a field with LIST type and allowed values
     */
    public function listType(?array $allowedValues = null): static
    {
        return $this->state(fn (array $attributes) => [
            'field_type' => 'LIST',
            'allowed_values' => $allowedValues ?? ['option1', 'option2', 'option3'],
        ]);
    }

    /**
     * Create a recently discovered field
     */
    public function recentlyDiscovered(): static
    {
        return $this->state(fn (array $attributes) => [
            'discovered_at' => now()->subHours(rand(1, 24)),
            'last_verified_at' => now()->subHours(rand(1, 12)),
        ]);
    }

    /**
     * Create an outdated field (not verified recently)
     */
    public function outdated(): static
    {
        return $this->state(fn (array $attributes) => [
            'discovered_at' => now()->subMonths(rand(2, 6)),
            'last_verified_at' => now()->subMonths(rand(1, 3)),
        ]);
    }
}
