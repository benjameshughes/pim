<?php

namespace Database\Factories;

use App\Models\ChannelValueList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChannelValueList>
 */
class ChannelValueListFactory extends Factory
{
    protected $model = ChannelValueList::class;

    public function definition(): array
    {
        $channelTypes = ['shopify', 'amazon', 'ebay', 'mirakl'];
        $channelType = $this->faker->randomElement($channelTypes);
        $listCode = $this->faker->randomElement(['colors', 'sizes', 'conditions', 'categories', 'brands', 'materials']);

        return [
            'channel_type' => $channelType,
            'channel_subtype' => $this->generateChannelSubtype($channelType),
            'list_code' => $listCode,
            'list_name' => $this->generateListName($listCode),
            'list_description' => $this->faker->optional(0.7)->sentence(),
            'allowed_values' => $this->generateAllowedValues($listCode),
            'value_metadata' => $this->faker->optional(0.3)->randomElement([
                ['sort_order' => 'alphabetical', 'default_value' => 'none'],
                ['hierarchical' => true, 'max_depth' => 3],
                ['allow_custom' => false, 'validation' => 'strict'],
            ]),
            'values_count' => 0, // Will be calculated from allowed_values
            'discovered_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'last_synced_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'api_version' => $this->faker->optional(0.5)->randomElement(['v1', 'v2', '2024-01', '2024-07']),
            'is_active' => $this->faker->boolean(90),
            'sync_status' => $this->faker->randomElement(['synced', 'pending', 'failed']),
            'sync_error' => $this->faker->optional(0.1)->sentence(),
        ];
    }

    /**
     * After creating, calculate values_count from allowed_values
     */
    public function configure(): static
    {
        return $this->afterMaking(function (ChannelValueList $valueList) {
            $valueList->values_count = count($valueList->allowed_values ?? []);
        });
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
     * Generate realistic list names based on list codes
     */
    private function generateListName(string $listCode): string
    {
        return match ($listCode) {
            'colors' => 'Available Colors',
            'sizes' => 'Size Options',
            'conditions' => 'Item Conditions',
            'categories' => 'Product Categories',
            'brands' => 'Brand Names',
            'materials' => 'Material Types',
            default => ucfirst($listCode).' List',
        };
    }

    /**
     * Generate realistic allowed values based on list code
     */
    private function generateAllowedValues(string $listCode): array
    {
        return match ($listCode) {
            'colors' => ['Red', 'Blue', 'Green', 'Black', 'White', 'Yellow', 'Purple', 'Orange', 'Pink', 'Gray'],
            'sizes' => ['XS', 'S', 'M', 'L', 'XL', 'XXL', '2XL', '3XL'],
            'conditions' => ['New', 'Used - Like New', 'Used - Good', 'Used - Fair', 'Refurbished', 'Damaged'],
            'categories' => ['Electronics', 'Clothing', 'Home & Garden', 'Books', 'Sports', 'Toys', 'Beauty'],
            'brands' => ['Nike', 'Apple', 'Samsung', 'Sony', 'Adidas', 'Microsoft', 'Amazon', 'Google'],
            'materials' => ['Cotton', 'Polyester', 'Wool', 'Silk', 'Leather', 'Plastic', 'Metal', 'Wood'],
            default => $this->faker->words(rand(5, 15)),
        };
    }

    /**
     * Create a synced value list
     */
    public function synced(): static
    {
        return $this->state(fn (array $attributes) => [
            'sync_status' => 'synced',
            'sync_error' => null,
            'last_synced_at' => now(),
        ]);
    }

    /**
     * Create a failed value list
     */
    public function failed(?string $error = null): static
    {
        return $this->state(fn (array $attributes) => [
            'sync_status' => 'failed',
            'sync_error' => $error ?? 'API connection timeout',
            'last_synced_at' => now()->subHours(rand(1, 24)),
        ]);
    }

    /**
     * Create a pending value list
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'sync_status' => 'pending',
            'sync_error' => null,
            'last_synced_at' => null,
        ]);
    }

    /**
     * Create a value list for a specific channel
     */
    public function forChannel(string $channelType, ?string $channelSubtype = null): static
    {
        return $this->state(fn (array $attributes) => [
            'channel_type' => $channelType,
            'channel_subtype' => $channelSubtype ?? $this->generateChannelSubtype($channelType),
        ]);
    }

    /**
     * Create a value list with specific code and values
     */
    public function withValues(string $listCode, array $values): static
    {
        return $this->state(fn (array $attributes) => [
            'list_code' => $listCode,
            'list_name' => $this->generateListName($listCode),
            'allowed_values' => $values,
            'values_count' => count($values),
        ]);
    }

    /**
     * Create a recently synced value list
     */
    public function recentlySynced(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_synced_at' => now()->subHours(rand(1, 6)),
            'sync_status' => 'synced',
        ]);
    }

    /**
     * Create an outdated value list
     */
    public function outdated(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_synced_at' => now()->subMonths(rand(1, 3)),
            'discovered_at' => now()->subMonths(rand(2, 6)),
        ]);
    }

    /**
     * Create an active value list
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Create an inactive value list
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a large value list
     */
    public function large(): static
    {
        return $this->state(fn (array $attributes) => [
            'allowed_values' => $this->faker->words(rand(50, 200)),
        ])->afterMaking(function (ChannelValueList $valueList) {
            $valueList->values_count = count($valueList->allowed_values);
        });
    }

    /**
     * Create a small value list
     */
    public function small(): static
    {
        return $this->state(fn (array $attributes) => [
            'allowed_values' => $this->faker->words(rand(2, 5)),
        ])->afterMaking(function (ChannelValueList $valueList) {
            $valueList->values_count = count($valueList->allowed_values);
        });
    }
}
