<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // Create root categories
        $rollerBlinds = Category::create([
            'name' => 'Roller Blinds',
            'slug' => 'roller-blinds',
            'description' => 'Modern roller blind window treatments',
            'sort_order' => 1,
        ]);

        $venetianBlinds = Category::create([
            'name' => 'Venetian Blinds',
            'slug' => 'venetian-blinds',
            'description' => 'Classic venetian blind window treatments',
            'sort_order' => 2,
        ]);

        // Roller Blinds subcategories
        Category::create([
            'name' => 'Blackout',
            'slug' => 'blackout',
            'description' => 'Complete light blocking roller blinds',
            'parent_id' => $rollerBlinds->id,
            'sort_order' => 1,
        ]);

        Category::create([
            'name' => 'Daylight',
            'slug' => 'daylight',
            'description' => 'Light filtering roller blinds for daytime use',
            'parent_id' => $rollerBlinds->id,
            'sort_order' => 2,
        ]);

        // Venetian Blinds subcategories
        Category::create([
            'name' => 'PVC',
            'slug' => 'pvc',
            'description' => 'PVC venetian blinds - durable and easy to clean',
            'parent_id' => $venetianBlinds->id,
            'sort_order' => 1,
        ]);

        Category::create([
            'name' => 'Aluminium',
            'slug' => 'aluminium',
            'description' => 'Lightweight aluminium venetian blinds',
            'parent_id' => $venetianBlinds->id,
            'sort_order' => 2,
        ]);

        $this->command->info('Window blind categories created successfully!');
    }
}
