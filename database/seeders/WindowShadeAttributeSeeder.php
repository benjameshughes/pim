<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class WindowShadeAttributeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $attributeDefinitions = [
            // Physical specifications - Product level
            [
                'key' => 'max_drop',
                'label' => 'Maximum Drop',
                'data_type' => 'string',
                'category' => 'physical',
                'applies_to' => 'product',
                'description' => 'Maximum fabric drop available (e.g., 160cm)',
                'sort_order' => 10,
            ],
            [
                'key' => 'tube_diameter',
                'label' => 'Tube Diameter',
                'data_type' => 'string',
                'category' => 'physical',
                'applies_to' => 'product',
                'description' => 'Roller tube diameter (e.g., 25mm)',
                'sort_order' => 20,
            ],
            [
                'key' => 'mechanism_type',
                'label' => 'Mechanism Type',
                'data_type' => 'string',
                'category' => 'functional',
                'applies_to' => 'product',
                'validation_rules' => [
                    'options' => ['sidewinder', 'spring', 'chain', 'motorized', 'cordless'],
                ],
                'sort_order' => 30,
            ],
            [
                'key' => 'material_type',
                'label' => 'Material Type',
                'data_type' => 'string',
                'category' => 'physical',
                'applies_to' => 'product',
                'validation_rules' => [
                    'options' => ['polyester', 'fabric', 'wood', 'metal', 'pvc', 'aluminum'],
                ],
                'sort_order' => 40,
            ],

            // Functional features - Product level
            [
                'key' => 'opacity_level',
                'label' => 'Light Control Level',
                'data_type' => 'string',
                'category' => 'functional',
                'applies_to' => 'product',
                'validation_rules' => [
                    'options' => ['100% blackout', '95% light filtering', 'sheer', 'semi-opaque'],
                ],
                'sort_order' => 50,
            ],
            [
                'key' => 'thermal_properties',
                'label' => 'Thermal Properties',
                'data_type' => 'boolean',
                'category' => 'functional',
                'applies_to' => 'product',
                'sort_order' => 60,
            ],
            [
                'key' => 'child_safety',
                'label' => 'Child Safety Features',
                'data_type' => 'boolean',
                'category' => 'functional',
                'applies_to' => 'product',
                'sort_order' => 70,
            ],
            [
                'key' => 'fire_rating',
                'label' => 'Fire Rating',
                'data_type' => 'string',
                'category' => 'compliance',
                'applies_to' => 'product',
                'description' => 'Fire safety classification',
                'sort_order' => 80,
            ],

            // Installation - Product level
            [
                'key' => 'fitting_type',
                'label' => 'Fitting Type',
                'data_type' => 'string',
                'category' => 'physical',
                'applies_to' => 'product',
                'validation_rules' => [
                    'options' => ['face fix', 'top fix', 'recess', 'universal'],
                ],
                'sort_order' => 90,
            ],
            [
                'key' => 'minimum_recess_depth',
                'label' => 'Minimum Recess Depth',
                'data_type' => 'string',
                'category' => 'physical',
                'applies_to' => 'product',
                'description' => 'Minimum depth required for installation (e.g., 10cm)',
                'sort_order' => 100,
            ],

            // Variant-specific attributes
            [
                'key' => 'fabric_width_difference',
                'label' => 'Fabric Width Difference',
                'data_type' => 'string',
                'category' => 'physical',
                'applies_to' => 'variant',
                'description' => 'Difference between total width and fabric width (e.g., 4cm)',
                'sort_order' => 110,
            ],
            [
                'key' => 'actual_fabric_width',
                'label' => 'Actual Fabric Width',
                'data_type' => 'number',
                'category' => 'physical',
                'applies_to' => 'variant',
                'description' => 'Actual width of fabric in cm',
                'sort_order' => 120,
            ],

            // Care & maintenance
            [
                'key' => 'care_instructions',
                'label' => 'Care Instructions',
                'data_type' => 'string',
                'category' => 'functional',
                'applies_to' => 'product',
                'description' => 'How to clean and maintain the blind',
                'sort_order' => 130,
            ],
            [
                'key' => 'warranty_period',
                'label' => 'Warranty Period',
                'data_type' => 'string',
                'category' => 'compliance',
                'applies_to' => 'product',
                'description' => 'Warranty duration (e.g., 12 months)',
                'sort_order' => 140,
            ],
        ];

        foreach ($attributeDefinitions as $definition) {
            \App\Models\AttributeDefinition::updateOrCreate(
                ['key' => $definition['key']],
                $definition
            );
        }
    }
}
