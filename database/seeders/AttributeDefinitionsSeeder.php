<?php

namespace Database\Seeders;

use App\Models\AttributeDefinition;
use Illuminate\Database\Seeder;

class AttributeDefinitionsSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // Core product attributes
        AttributeDefinition::create([
            'key' => 'brand',
            'name' => 'Brand',
            'description' => 'Product manufacturer or brand name',
            'data_type' => 'string',
            'validation_rules' => ['max_length' => 100],
            'is_inheritable' => true,
            'inheritance_strategy' => 'fallback',
            'is_required_for_products' => true,
            'is_system_attribute' => true,
            'sync_to_shopify' => true,
            'sync_to_ebay' => true,
            'sync_to_mirakl' => true,
            'marketplace_mappings' => [
                'shopify' => [
                    'field' => 'vendor',
                    'transform' => null,
                ],
                'ebay' => [
                    'field' => 'brand',
                    'transform' => null,
                ],
                'mirakl' => [
                    'field' => 'brand',
                    'transform' => null,
                ],
            ],
            'group' => 'basic',
            'sort_order' => 1,
            'icon' => 'building-storefront',
        ]);

        AttributeDefinition::create([
            'key' => 'material',
            'name' => 'Material',
            'description' => 'Primary material composition',
            'data_type' => 'enum',
            'enum_values' => [
                'PVC',
                'Fabric',
                'Wood',
                'Aluminum',
                'Composite',
                'Vinyl',
                'Polyester',
                'Cotton',
                'Bamboo',
                'Metal',
            ],
            'is_inheritable' => true,
            'inheritance_strategy' => 'fallback',
            'sync_to_shopify' => true,
            'sync_to_ebay' => true,
            'sync_to_mirakl' => true,
            'input_type' => 'select',
            'group' => 'physical',
            'sort_order' => 5,
            'icon' => 'cube',
        ]);

        AttributeDefinition::create([
            'key' => 'color_family',
            'name' => 'Color Family',
            'description' => 'General color category for filtering and search',
            'data_type' => 'enum',
            'enum_values' => [
                'White',
                'Black',
                'Grey',
                'Brown',
                'Beige',
                'Blue',
                'Green',
                'Red',
                'Yellow',
                'Purple',
                'Multi-Color',
            ],
            'is_inheritable' => false, // Variants should specify their own color
            'inheritance_strategy' => 'never',
            'sync_to_shopify' => true,
            'sync_to_ebay' => true,
            'input_type' => 'select',
            'group' => 'appearance',
            'sort_order' => 10,
            'icon' => 'swatch',
        ]);

        AttributeDefinition::create([
            'key' => 'room_type',
            'name' => 'Room Type',
            'description' => 'Recommended room types for this product',
            'data_type' => 'enum',
            'enum_values' => [
                'Living Room',
                'Bedroom',
                'Kitchen',
                'Bathroom',
                'Office',
                'Dining Room',
                'Conservatory',
                'Any Room',
            ],
            'is_inheritable' => true,
            'inheritance_strategy' => 'fallback',
            'sync_to_shopify' => true,
            'input_type' => 'select',
            'group' => 'marketing',
            'sort_order' => 15,
            'icon' => 'home',
        ]);

        AttributeDefinition::create([
            'key' => 'light_filtering',
            'name' => 'Light Filtering',
            'description' => 'How much light the blind blocks or filters',
            'data_type' => 'enum',
            'enum_values' => [
                'Blackout',
                'Room Darkening',
                'Light Filtering',
                'Sheer',
                'Day/Night',
            ],
            'is_inheritable' => true,
            'inheritance_strategy' => 'always', // Usually consistent across all variants
            'sync_to_shopify' => true,
            'sync_to_ebay' => true,
            'input_type' => 'select',
            'group' => 'functional',
            'sort_order' => 20,
            'icon' => 'sun',
        ]);

        AttributeDefinition::create([
            'key' => 'installation_type',
            'name' => 'Installation Type',
            'description' => 'How the blind is mounted or installed',
            'data_type' => 'enum',
            'enum_values' => [
                'Inside Mount',
                'Outside Mount',
                'Ceiling Mount',
                'Wall Mount',
                'No Drill',
                'Clip On',
            ],
            'is_inheritable' => true,
            'inheritance_strategy' => 'fallback',
            'sync_to_shopify' => true,
            'input_type' => 'select',
            'group' => 'functional',
            'sort_order' => 25,
            'icon' => 'wrench-screwdriver',
        ]);

        AttributeDefinition::create([
            'key' => 'warranty_years',
            'name' => 'Warranty (Years)',
            'description' => 'Product warranty period in years',
            'data_type' => 'number',
            'validation_rules' => [
                'min' => 0,
                'max' => 25,
            ],
            'default_value' => '1',
            'is_inheritable' => true,
            'inheritance_strategy' => 'fallback',
            'sync_to_shopify' => true,
            'sync_to_ebay' => true,
            'input_type' => 'number',
            'group' => 'marketing',
            'sort_order' => 30,
            'icon' => 'shield-check',
        ]);

        AttributeDefinition::create([
            'key' => 'child_safe',
            'name' => 'Child Safe',
            'description' => 'Whether this product meets child safety standards',
            'data_type' => 'boolean',
            'is_inheritable' => true,
            'inheritance_strategy' => 'fallback',
            'sync_to_shopify' => true,
            'sync_to_ebay' => true,
            'input_type' => 'checkbox',
            'group' => 'safety',
            'sort_order' => 35,
            'icon' => 'shield-exclamation',
        ]);

        AttributeDefinition::create([
            'key' => 'moisture_resistant',
            'name' => 'Moisture Resistant',
            'description' => 'Suitable for humid environments like bathrooms',
            'data_type' => 'boolean',
            'is_inheritable' => true,
            'inheritance_strategy' => 'fallback',
            'sync_to_shopify' => true,
            'input_type' => 'checkbox',
            'group' => 'functional',
            'sort_order' => 40,
            'icon' => 'droplets',
        ]);

        AttributeDefinition::create([
            'key' => 'energy_efficient',
            'name' => 'Energy Efficient',
            'description' => 'Helps with insulation and energy savings',
            'data_type' => 'boolean',
            'is_inheritable' => true,
            'inheritance_strategy' => 'fallback',
            'sync_to_shopify' => true,
            'input_type' => 'checkbox',
            'group' => 'marketing',
            'sort_order' => 45,
            'icon' => 'bolt',
        ]);

        AttributeDefinition::create([
            'key' => 'cleaning_instructions',
            'name' => 'Cleaning Instructions',
            'description' => 'How to clean and maintain this product',
            'data_type' => 'enum',
            'enum_values' => [
                'Wipe Clean',
                'Machine Washable',
                'Dry Clean Only',
                'Vacuum Only',
                'Spot Clean',
                'Professional Clean',
            ],
            'is_inheritable' => true,
            'inheritance_strategy' => 'fallback',
            'sync_to_shopify' => true,
            'input_type' => 'select',
            'group' => 'maintenance',
            'sort_order' => 50,
            'icon' => 'sparkles',
        ]);

        AttributeDefinition::create([
            'key' => 'origin_country',
            'name' => 'Country of Origin',
            'description' => 'Where the product is manufactured',
            'data_type' => 'enum',
            'enum_values' => [
                'United Kingdom',
                'China',
                'Germany',
                'Netherlands',
                'Belgium',
                'Italy',
                'Spain',
                'Poland',
                'Turkey',
                'Other',
            ],
            'is_inheritable' => true,
            'inheritance_strategy' => 'always',
            'sync_to_ebay' => true,
            'sync_to_mirakl' => true,
            'input_type' => 'select',
            'group' => 'basic',
            'sort_order' => 55,
            'icon' => 'globe-alt',
        ]);

        // Variant-specific attributes
        AttributeDefinition::create([
            'key' => 'slat_width',
            'name' => 'Slat Width (mm)',
            'description' => 'Width of individual slats for venetian blinds',
            'data_type' => 'number',
            'validation_rules' => [
                'min' => 10,
                'max' => 100,
            ],
            'is_inheritable' => false, // Variant-specific
            'inheritance_strategy' => 'never',
            'sync_to_shopify' => true,
            'input_type' => 'number',
            'group' => 'physical',
            'sort_order' => 60,
            'icon' => 'rectangle-group',
        ]);

        AttributeDefinition::create([
            'key' => 'control_type',
            'name' => 'Control Type',
            'description' => 'How the blind is operated',
            'data_type' => 'enum',
            'enum_values' => [
                'Cord Control',
                'Cordless',
                'Motorized',
                'Remote Control',
                'Smart Home',
                'Chain Control',
                'Wand Control',
            ],
            'is_inheritable' => false, // Can vary by size/variant
            'inheritance_strategy' => 'fallback',
            'sync_to_shopify' => true,
            'sync_to_ebay' => true,
            'input_type' => 'select',
            'group' => 'functional',
            'sort_order' => 65,
            'icon' => 'cog-6-tooth',
        ]);

        // Marketing attributes
        AttributeDefinition::create([
            'key' => 'style_theme',
            'name' => 'Style Theme',
            'description' => 'Design style or theme category',
            'data_type' => 'enum',
            'enum_values' => [
                'Modern',
                'Traditional',
                'Minimalist',
                'Industrial',
                'Scandinavian',
                'Rustic',
                'Contemporary',
                'Classic',
                'Luxury',
            ],
            'is_inheritable' => true,
            'inheritance_strategy' => 'fallback',
            'sync_to_shopify' => true,
            'input_type' => 'select',
            'group' => 'marketing',
            'sort_order' => 70,
            'icon' => 'star',
        ]);

        $this->command->info('✅ Created '.AttributeDefinition::count().' attribute definitions');
    }
}
