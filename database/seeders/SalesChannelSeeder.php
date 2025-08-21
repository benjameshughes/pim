<?php

namespace Database\Seeders;

use App\Models\SalesChannel;
use Illuminate\Database\Seeder;

class SalesChannelSeeder extends Seeder
{
    public function run(): void
    {
        $channels = [
            [
                'name' => 'Shopify',
                'display_name' => 'Shopify',
                'slug' => 'shopify',
                'is_active' => true,
                'icon' => 'shopping-bag',
                'color' => '#95BF46',
                'default_markup_percentage' => 5.0,
                'platform_fee_percentage' => 2.9,
                'payment_fee_percentage' => 0.3,
                'default_currency' => 'GBP',
                'base_shipping_cost' => 3.99,
                'free_shipping_available' => true,
                'free_shipping_threshold' => 50.00,
                'auto_sync' => true,
                'priority' => 1,
                'description' => 'Shopify online store',
            ],
            [
                'name' => 'eBay',
                'display_name' => 'eBay',
                'slug' => 'ebay',
                'is_active' => true,
                'icon' => 'building-storefront',
                'color' => '#E53E3E',
                'default_markup_percentage' => 15.0,
                'platform_fee_percentage' => 10.0,
                'payment_fee_percentage' => 2.9,
                'default_currency' => 'GBP',
                'base_shipping_cost' => 4.99,
                'free_shipping_available' => true,
                'free_shipping_threshold' => 75.00,
                'auto_sync' => true,
                'priority' => 2,
                'description' => 'eBay marketplace',
            ],
            [
                'name' => 'Amazon',
                'display_name' => 'Amazon',
                'slug' => 'amazon',
                'is_active' => false,
                'icon' => 'shopping-cart',
                'color' => '#FF9500',
                'default_markup_percentage' => 20.0,
                'platform_fee_percentage' => 15.0,
                'payment_fee_percentage' => 0.0,
                'default_currency' => 'GBP',
                'base_shipping_cost' => 0.00,
                'free_shipping_available' => true,
                'free_shipping_threshold' => 25.00,
                'auto_sync' => false,
                'priority' => 3,
                'description' => 'Amazon marketplace',
            ],
            [
                'name' => 'Direct Sales',
                'display_name' => 'Direct Sales',
                'slug' => 'direct',
                'is_active' => true,
                'icon' => 'home',
                'color' => '#38B2AC',
                'default_markup_percentage' => 0.0,
                'platform_fee_percentage' => 0.0,
                'payment_fee_percentage' => 1.4,
                'default_currency' => 'GBP',
                'base_shipping_cost' => 2.99,
                'free_shipping_available' => true,
                'free_shipping_threshold' => 30.00,
                'auto_sync' => false,
                'priority' => 4,
                'description' => 'Direct sales (no commission)',
            ],
            [
                'name' => 'Mirakl',
                'display_name' => 'Mirakl',
                'slug' => 'mirakl',
                'is_active' => true,
                'icon' => 'globe-alt',
                'color' => '#6B73FF',
                'default_markup_percentage' => 10.0,
                'platform_fee_percentage' => 5.0,
                'payment_fee_percentage' => 0.0,
                'default_currency' => 'GBP',
                'base_shipping_cost' => 5.99,
                'free_shipping_available' => false,
                'free_shipping_threshold' => 0.00,
                'auto_sync' => true,
                'priority' => 5,
                'description' => 'Mirakl marketplace platform',
            ],
        ];

        foreach ($channels as $channel) {
            SalesChannel::updateOrCreate(
                ['slug' => $channel['slug']],
                $channel
            );
        }
    }
}
