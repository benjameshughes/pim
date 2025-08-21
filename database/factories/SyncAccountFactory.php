<?php

namespace Database\Factories;

use App\Models\SyncAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SyncAccount>
 */
class SyncAccountFactory extends Factory
{
    protected $model = SyncAccount::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['main', 'uk', 'us', 'de']),
            'channel' => fake()->randomElement(['shopify', 'ebay', 'amazon', 'mirakl']),
            'display_name' => fake()->company().' Store',
            'is_active' => fake()->boolean(80), // 80% chance of being active
            'credentials' => [
                'api_key' => fake()->uuid(),
                'api_secret' => fake()->sha256(),
            ],
            'settings' => [
                'auto_sync' => fake()->boolean(),
                'sync_variants' => fake()->boolean(),
                'currency' => fake()->randomElement(['GBP', 'USD', 'EUR']),
            ],
        ];
    }

    /**
     * Create a Shopify account
     */
    public function shopify(): static
    {
        return $this->state([
            'channel' => 'shopify',
            'name' => 'main',
            'display_name' => 'Main Shopify Store',
            'credentials' => [
                'store_url' => 'test-store.myshopify.com',
                'access_token' => fake()->sha256(),
                'api_version' => '2024-07',
            ],
            'settings' => [
                'auto_sync' => true,
                'sync_variants' => true,
                'use_color_separation' => true,
                'use_taxonomy' => true,
            ],
        ]);
    }

    /**
     * Create an eBay account
     */
    public function ebay(): static
    {
        return $this->state([
            'channel' => 'ebay',
            'name' => fake()->randomElement(['uk', 'us', 'de']),
            'display_name' => 'eBay '.fake()->randomElement(['UK', 'US', 'Germany']),
            'credentials' => [
                'client_id' => fake()->uuid(),
                'client_secret' => fake()->sha256(),
                'site_id' => fake()->randomElement([0, 3, 77]), // US, UK, Germany
            ],
            'settings' => [
                'default_listing_type' => 'fixed_price',
                'default_duration' => 30,
                'auto_sync' => fake()->boolean(),
                'currency' => fake()->randomElement(['USD', 'GBP', 'EUR']),
            ],
        ]);
    }

    /**
     * Create an active account
     */
    public function active(): static
    {
        return $this->state(['is_active' => true]);
    }

    /**
     * Create an inactive account
     */
    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
