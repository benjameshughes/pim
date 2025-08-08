<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Don't overwrite existing users - just ensure we have at least one
        if (User::count() === 0) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }

        // Seed the new marketplace and attribute structures
        $this->call([
            MarketplaceSeeder::class,
            WindowShadeAttributeSeeder::class,
            CoreAttributeSeeder::class,
        ]);
    }
}
