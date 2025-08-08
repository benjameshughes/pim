<?php

namespace Database\Seeders;

use App\Models\AttributeDefinition;
use Illuminate\Database\Seeder;

class CoreAttributeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $attributes = [
            // Color Attribute
            [
                'key' => 'color',
                'label' => 'Color',
                'data_type' => 'string',
                'category' => 'appearance',
                'applies_to' => 'variant',
                'is_required' => false,
                'validation_rules' => [
                    'options' => [
                        'white', 'cream', 'ivory', 'beige', 'light-grey', 'grey', 'dark-grey', 'charcoal', 'black',
                        'brown', 'walnut', 'oak', 'mahogany', 'cherry', 'pine', 'bamboo',
                        'red', 'burgundy', 'pink', 'coral', 'orange', 'yellow', 'gold',
                        'green', 'olive', 'sage', 'forest-green', 'blue', 'navy', 'royal-blue', 'sky-blue',
                        'purple', 'lavender', 'violet', 'teal', 'turquoise', 'mint',
                        'bronze', 'copper', 'brass', 'silver', 'chrome', 'satin-nickel', 'brushed-nickel',
                        'antique-bronze', 'oil-rubbed-bronze', 'pewter'
                    ]
                ],
                'description' => 'Primary color of the product variant',
                'sort_order' => 10,
                'is_active' => true,
            ],

            // Width Attribute
            [
                'key' => 'width',
                'label' => 'Width',
                'data_type' => 'number',
                'category' => 'dimensions',
                'applies_to' => 'variant',
                'is_required' => false,
                'validation_rules' => [
                    'min' => 10,
                    'max' => 500,
                    'unit' => 'cm',
                    'common_sizes' => [30, 45, 60, 90, 120, 150, 180, 210, 240, 270, 300]
                ],
                'description' => 'Width of the blind or curtain in centimeters',
                'sort_order' => 20,
                'is_active' => true,
            ],

            // Drop Attribute
            [
                'key' => 'drop',
                'label' => 'Drop',
                'data_type' => 'number',
                'category' => 'dimensions',
                'applies_to' => 'variant',
                'is_required' => false,
                'validation_rules' => [
                    'min' => 10,
                    'max' => 400,
                    'unit' => 'cm',
                    'common_sizes' => [60, 90, 120, 150, 180, 210, 240, 270, 300]
                ],
                'description' => 'Drop/length of the blind or curtain in centimeters',
                'sort_order' => 30,
                'is_active' => true,
            ],

            // Material Attribute
            [
                'key' => 'material',
                'label' => 'Material',
                'data_type' => 'string',
                'category' => 'construction',
                'applies_to' => 'both',
                'is_required' => false,
                'validation_rules' => [
                    'options' => [
                        // Blind materials
                        'aluminium', 'wood', 'faux-wood', 'bamboo', 'vinyl', 'pvc',
                        'fabric', 'polyester', 'cotton', 'linen', 'silk', 'voile',
                        'blackout-fabric', 'translucent-fabric', 'screen-fabric',
                        
                        // Curtain materials  
                        'cotton-blend', 'linen-blend', 'polyester-blend', 'silk-blend',
                        'velvet', 'chenille', 'jacquard', 'brocade', 'damask',
                        'thermal-fabric', 'blackout-polyester', 'sheer-polyester',
                        
                        // Hardware materials
                        'steel', 'stainless-steel', 'brass', 'bronze', 'chrome',
                        'plastic', 'composite', 'carbon-fiber'
                    ]
                ],
                'description' => 'Primary material construction of the product',
                'sort_order' => 40,
                'is_active' => true,
            ],

            // Control Type Attribute
            [
                'key' => 'control_type',
                'label' => 'Control Type',
                'data_type' => 'string',
                'category' => 'operation',
                'applies_to' => 'both',
                'is_required' => false,
                'validation_rules' => [
                    'options' => [
                        // Manual controls
                        'cord', 'chain', 'wand', 'crank', 'pull-cord', 'side-chain',
                        'continuous-chain', 'tilt-wand', 'lift-cord',
                        
                        // Cordless/safe controls
                        'cordless', 'spring-assisted', 'top-down-bottom-up',
                        'cellular-cordless', 'push-pull',
                        
                        // Motorized controls
                        'motorized', 'electric-motor', 'battery-motor', 'solar-motor',
                        'remote-control', 'wall-switch', 'app-controlled', 'voice-controlled',
                        'smart-home-compatible', 'alexa-compatible', 'google-home-compatible',
                        
                        // Traditional
                        'manual', 'hand-operated'
                    ]
                ],
                'description' => 'How the blind or curtain is operated/controlled',
                'sort_order' => 50,
                'is_active' => true,
            ],

            // Operation Type Attribute  
            [
                'key' => 'operation_type',
                'label' => 'Operation Type',
                'data_type' => 'string',
                'category' => 'functionality',
                'applies_to' => 'both',
                'is_required' => false,
                'validation_rules' => [
                    'options' => [
                        // Blind operations
                        'roll-up', 'roll-down', 'tilt-only', 'lift-and-tilt',
                        'horizontal-tilt', 'vertical-tilt', 'fold-up', 'concertina',
                        'roman-fold', 'cascade', 'waterfall',
                        
                        // Curtain operations
                        'draw', 'traverse', 'pull-across', 'tie-back',
                        'pinch-pleat', 'grommet', 'rod-pocket', 'tab-top',
                        
                        // Specialized
                        'top-down-bottom-up', 'day-night', 'dual-shade',
                        'panel-glide', 'sliding-panel', 'bifold'
                    ]
                ],
                'description' => 'The mechanical operation method of the window treatment',
                'sort_order' => 60,
                'is_active' => true,
            ],

            // Fitting Type Attribute
            [
                'key' => 'fitting_type',
                'label' => 'Fitting Type',
                'data_type' => 'string',
                'category' => 'installation',
                'applies_to' => 'both',
                'is_required' => false,
                'validation_rules' => [
                    'options' => [
                        // No drill options
                        'no-drill', 'stick-on', 'magnetic', 'tension-fit',
                        'clamp-on', 'clip-on', 'adhesive', 'suction-cup',
                        'pressure-fit', 'twist-and-fit',
                        
                        // Drill required options
                        'drill-fix', 'screw-fix', 'bracket-mount', 'top-fix',
                        'side-fix', 'ceiling-mount', 'wall-mount', 'recess-mount',
                        'face-mount', 'outside-mount', 'inside-mount',
                        
                        // Professional installation
                        'professional-install', 'made-to-measure', 'custom-fit'
                    ]
                ],
                'description' => 'Installation method required for the product',
                'sort_order' => 70,
                'is_active' => true,
            ],

            // Additional useful attributes for window treatments

            // Light Control
            [
                'key' => 'light_control',
                'label' => 'Light Control',
                'data_type' => 'string',
                'category' => 'functionality',
                'applies_to' => 'both',
                'is_required' => false,
                'validation_rules' => [
                    'options' => [
                        'blackout', 'room-darkening', 'light-filtering', 
                        'translucent', 'sheer', 'opaque', 'semi-opaque',
                        'day-night', 'dual-opacity', 'variable-opacity'
                    ]
                ],
                'description' => 'Level of light control provided by the product',
                'sort_order' => 80,
                'is_active' => true,
            ],

            // Privacy Level
            [
                'key' => 'privacy_level',
                'label' => 'Privacy Level',
                'data_type' => 'string',
                'category' => 'functionality',
                'applies_to' => 'both',
                'is_required' => false,
                'validation_rules' => [
                    'options' => [
                        'high-privacy', 'medium-privacy', 'low-privacy',
                        'full-privacy', 'partial-privacy', 'minimal-privacy',
                        'day-privacy', 'night-privacy', 'variable-privacy'
                    ]
                ],
                'description' => 'Level of privacy provided by the product',
                'sort_order' => 90,
                'is_active' => true,
            ],

            // Energy Efficiency
            [
                'key' => 'energy_efficiency',
                'label' => 'Energy Efficiency',
                'data_type' => 'string',
                'category' => 'performance',
                'applies_to' => 'both',
                'is_required' => false,
                'validation_rules' => [
                    'options' => [
                        'thermal-insulating', 'heat-reducing', 'uv-protection',
                        'energy-saving', 'thermal-backing', 'insulated',
                        'draft-reducing', 'temperature-regulating'
                    ]
                ],
                'description' => 'Energy efficiency properties of the product',
                'sort_order' => 100,
                'is_active' => true,
            ],
        ];

        foreach ($attributes as $attributeData) {
            AttributeDefinition::updateOrCreate(
                ['key' => $attributeData['key']],
                $attributeData
            );
        }

        $this->command->info('Core attributes seeded successfully!');
        $this->command->info('Created ' . count($attributes) . ' attribute definitions.');
    }
}