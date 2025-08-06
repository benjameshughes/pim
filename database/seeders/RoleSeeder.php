<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['name' => 'admin', 'description' => 'Full system access'],
            ['name' => 'manager', 'description' => 'Can manage products and users'],
            ['name' => 'editor', 'description' => 'Can edit products'],
            ['name' => 'viewer', 'description' => 'Can view products only'],
        ];

        foreach ($roles as $role) {
            \App\Models\Role::firstOrCreate(['name' => $role['name']], $role);
        }
    }
}
