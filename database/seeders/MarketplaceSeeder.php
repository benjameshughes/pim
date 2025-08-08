<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class MarketplaceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $marketplaces = [
            [
                'name' => 'eBay Business Outlet',
                'platform' => 'ebay',
                'code' => 'ebo',
                'status' => 'active',
                'default_settings' => [
                    'shipping_cost' => 5.35,
                    'default_category' => 'Home & Garden',
                    'return_policy' => '30 days',
                    'dispatch_time' => '1 business day',
                ],
            ],
            [
                'name' => 'eBay BMS',
                'platform' => 'ebay',
                'code' => 'ebay_bms',
                'status' => 'active',
                'default_settings' => [
                    'shipping_cost' => 5.35,
                    'default_category' => 'Home & Garden',
                    'return_policy' => '30 days',
                    'dispatch_time' => '1 business day',
                ],
            ],
            [
                'name' => 'Amazon Business Outlet',
                'platform' => 'amazon',
                'code' => 'amazon_bo',
                'status' => 'active',
                'default_settings' => [
                    'fulfillment_method' => 'FBM',
                    'default_category' => 'Home & Kitchen',
                    'handling_time' => 1,
                ],
            ],
            [
                'name' => 'Amazon FBA',
                'platform' => 'amazon',
                'code' => 'amazon_fba',
                'status' => 'active',
                'default_settings' => [
                    'fulfillment_method' => 'FBA',
                    'default_category' => 'Home & Kitchen',
                    'handling_time' => 0,
                ],
            ],
            [
                'name' => 'Amazon B2B',
                'platform' => 'amazon',
                'code' => 'amazon_b2b',
                'status' => 'active',
                'default_settings' => [
                    'fulfillment_method' => 'FBA',
                    'business_only' => true,
                    'quantity_discounts' => true,
                ],
            ],
            [
                'name' => 'Wayfair',
                'platform' => 'wayfair',
                'code' => 'wayfair',
                'status' => 'active',
                'default_settings' => [
                    'default_category' => 'Window Treatments',
                    'lead_time' => '3-5 business days',
                ],
            ],
            [
                'name' => 'Direct Sales',
                'platform' => 'direct',
                'code' => 'direct',
                'status' => 'active',
                'default_settings' => [
                    'shipping_cost' => 4.95,
                    'free_shipping_threshold' => 50.00,
                ],
            ],
            [
                'name' => 'Shopify Store',
                'platform' => 'shopify',
                'code' => 'shopify',
                'status' => 'active',
                'default_settings' => [
                    'auto_publish' => true,
                    'inventory_tracking' => true,
                    'seo_optimization' => true,
                    'metafields_enabled' => true,
                ],
            ],
        ];

        foreach ($marketplaces as $marketplace) {
            \App\Models\Marketplace::updateOrCreate(
                ['code' => $marketplace['code']],
                $marketplace
            );
        }
    }
}
