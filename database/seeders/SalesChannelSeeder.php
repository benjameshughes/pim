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
                'name' => 'Retail',
                'code' => 'retail',
                'description' => 'Base retail pricing (default)',
                'status' => 'active',
                'config' => [
                    'display_name' => 'Retail Price',
                    'icon' => 'tag',
                    'color' => '#6366F1',
                    'default_markup_percentage' => 0.0,
                    'platform_fee_percentage' => 0.0,
                    'payment_fee_percentage' => 0.0,
                    'default_currency' => 'GBP',
                    'base_shipping_cost' => 0.00,
                    'free_shipping_available' => false,
                    'free_shipping_threshold' => 0.00,
                    'auto_sync' => false,
                    'priority' => 0, // Highest priority = default
                ],
            ],
            [
                'name' => 'Shopify',
                'code' => 'shopify',
                'description' => 'Shopify online store',
                'status' => 'active',
                'config' => [
                    'display_name' => 'Shopify',
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
                ],
            ],
            [
                'name' => 'eBay',
                'code' => 'ebay',
                'description' => 'eBay marketplace',
                'status' => 'active',
                'config' => [
                    'display_name' => 'eBay',
                    'icon' => 'store',
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
                ],
            ],
            [
                'name' => 'Direct Sales',
                'code' => 'direct',
                'description' => 'Direct sales (no commission)',
                'status' => 'active',
                'config' => [
                    'display_name' => 'Direct Sales',
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
                    'priority' => 3,
                ],
            ],
        ];

        foreach ($channels as $channel) {
            SalesChannel::updateOrCreate(
                ['code' => $channel['code']],
                $channel
            );
        }
    }
}
