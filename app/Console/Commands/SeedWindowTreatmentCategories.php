<?php

namespace App\Console\Commands;

use App\Models\ShopifyTaxonomyCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedWindowTreatmentCategories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopify:seed-window-treatments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed window treatment categories based on Shopify taxonomy structure';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🪟 Seeding window treatment categories...');

        DB::beginTransaction();
        
        try {
            // Create the taxonomy tree for window treatments
            $this->createWindowTreatmentHierarchy();
            
            DB::commit();
            
            $this->info('✅ Successfully seeded window treatment categories');
            $this->showWindowTreatmentCategories();
            
            return 0;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("❌ Failed to seed categories: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Create the complete window treatment taxonomy hierarchy
     */
    private function createWindowTreatmentHierarchy(): void
    {
        // Based on Shopify's Standard Product Taxonomy
        $categories = [
            // Level 1: Home & Garden (should already exist)
            [
                'shopify_id' => 'gid://shopify/TaxonomyCategory/hg',
                'name' => 'Home & Garden',
                'full_name' => 'Home & Garden',
                'level' => 1,
                'is_leaf' => false,
                'is_root' => true,
                'parent_id' => null,
                'children_ids' => ['gid://shopify/TaxonomyCategory/hg-d'],
                'ancestor_ids' => []
            ],
            
            // Level 2: Decor
            [
                'shopify_id' => 'gid://shopify/TaxonomyCategory/hg-d',
                'name' => 'Decor',
                'full_name' => 'Home & Garden > Decor',
                'level' => 2,
                'is_leaf' => false,
                'is_root' => false,
                'parent_id' => 'gid://shopify/TaxonomyCategory/hg',
                'children_ids' => ['gid://shopify/TaxonomyCategory/hg-d-wt'],
                'ancestor_ids' => ['gid://shopify/TaxonomyCategory/hg']
            ],
            
            // Level 3: Window Treatments
            [
                'shopify_id' => 'gid://shopify/TaxonomyCategory/hg-d-wt',
                'name' => 'Window Treatments',
                'full_name' => 'Home & Garden > Decor > Window Treatments',
                'level' => 3,
                'is_leaf' => false,
                'is_root' => false,
                'parent_id' => 'gid://shopify/TaxonomyCategory/hg-d',
                'children_ids' => [
                    'gid://shopify/TaxonomyCategory/hg-d-wt-bs',
                    'gid://shopify/TaxonomyCategory/hg-d-wt-c',
                    'gid://shopify/TaxonomyCategory/hg-d-wt-v'
                ],
                'ancestor_ids' => ['gid://shopify/TaxonomyCategory/hg', 'gid://shopify/TaxonomyCategory/hg-d']
            ],
            
            // Level 4: Blinds & Shades (LEAF - our target category)
            [
                'shopify_id' => 'gid://shopify/TaxonomyCategory/hg-d-wt-bs',
                'name' => 'Blinds & Shades',
                'full_name' => 'Home & Garden > Decor > Window Treatments > Blinds & Shades',
                'level' => 4,
                'is_leaf' => true,
                'is_root' => false,
                'parent_id' => 'gid://shopify/TaxonomyCategory/hg-d-wt',
                'children_ids' => [],
                'ancestor_ids' => [
                    'gid://shopify/TaxonomyCategory/hg',
                    'gid://shopify/TaxonomyCategory/hg-d',
                    'gid://shopify/TaxonomyCategory/hg-d-wt'
                ],
                'attributes' => [
                    [
                        'name' => 'Color',
                        'values' => ['Black', 'White', 'Grey', 'Brown', 'Beige', 'Blue', 'Green']
                    ],
                    [
                        'name' => 'Material',
                        'values' => ['Fabric', 'Wood', 'Aluminum', 'Vinyl', 'Bamboo']
                    ],
                    [
                        'name' => 'Mount Type',
                        'values' => ['Inside Mount', 'Outside Mount', 'Ceiling Mount']
                    ],
                    [
                        'name' => 'Light Control',
                        'values' => ['Blackout', 'Room Darkening', 'Light Filtering', 'Sheer']
                    ],
                    [
                        'name' => 'Operating System',
                        'values' => ['Cordless', 'Corded', 'Motorized', 'Manual']
                    ]
                ]
            ],
            
            // Level 4: Curtains & Drapes
            [
                'shopify_id' => 'gid://shopify/TaxonomyCategory/hg-d-wt-c',
                'name' => 'Curtains & Drapes',
                'full_name' => 'Home & Garden > Decor > Window Treatments > Curtains & Drapes',
                'level' => 4,
                'is_leaf' => true,
                'is_root' => false,
                'parent_id' => 'gid://shopify/TaxonomyCategory/hg-d-wt',
                'children_ids' => [],
                'ancestor_ids' => [
                    'gid://shopify/TaxonomyCategory/hg',
                    'gid://shopify/TaxonomyCategory/hg-d',
                    'gid://shopify/TaxonomyCategory/hg-d-wt'
                ]
            ],
            
            // Level 4: Valances
            [
                'shopify_id' => 'gid://shopify/TaxonomyCategory/hg-d-wt-v',
                'name' => 'Valances',
                'full_name' => 'Home & Garden > Decor > Window Treatments > Valances',
                'level' => 4,
                'is_leaf' => true,
                'is_root' => false,
                'parent_id' => 'gid://shopify/TaxonomyCategory/hg-d-wt',
                'children_ids' => [],
                'ancestor_ids' => [
                    'gid://shopify/TaxonomyCategory/hg',
                    'gid://shopify/TaxonomyCategory/hg-d',
                    'gid://shopify/TaxonomyCategory/hg-d-wt'
                ]
            ]
        ];

        $bar = $this->output->createProgressBar(count($categories));
        $bar->start();

        foreach ($categories as $categoryData) {
            ShopifyTaxonomyCategory::updateOrCreate(
                ['shopify_id' => $categoryData['shopify_id']],
                $categoryData
            );
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Show the created window treatment categories
     */
    private function showWindowTreatmentCategories(): void
    {
        $this->newLine();
        $this->info('🎯 Window Treatment Category Hierarchy:');
        
        $windowCategories = ShopifyTaxonomyCategory::where('full_name', 'LIKE', '%Window%')
            ->orWhere('full_name', 'LIKE', '%Blind%')
            ->orWhere('full_name', 'LIKE', '%Decor%')
            ->orderBy('level')
            ->orderBy('name')
            ->get();
            
        foreach ($windowCategories as $category) {
            $indent = str_repeat('  ', $category->level - 1);
            $marker = $category->is_leaf ? '🎯' : '📁';
            $this->line("   {$indent}{$marker} {$category->name} ({$category->shopify_id})");
        }

        // Test product matching
        $this->newLine();
        $this->info('🧪 Testing Product Category Matching:');
        
        $testProducts = [
            'Blackout Roof Blind CK01',
            'Day Night Blind',
            'Roller Blind',
            'Venetian Blind'
        ];
        
        foreach ($testProducts as $productName) {
            $match = ShopifyTaxonomyCategory::getBestMatchForProduct($productName);
            if ($match) {
                $this->line("   • {$productName}");
                $this->line("     → {$match->full_name}");
                $this->line("     → ID: {$match->shopify_id}");
            } else {
                $this->warn("   • {$productName} → No match found");
            }
        }
    }
}
