<?php

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    // Reset permissions cache
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    
    // Create roles
    $adminRole = Role::firstOrCreate(['name' => 'admin']);
    $managerRole = Role::firstOrCreate(['name' => 'manager']);
    $userRole = Role::firstOrCreate(['name' => 'user']);
    
    // Create admin user with all permissions
    $this->adminUser = User::factory()->create(['email' => 'admin@test.com']);
    $this->adminUser->assignRole('admin');
    
    // Give admin all permissions
    $permissions = [
        'manage-system-settings', 'view-products', 'create-products', 'view-barcodes', 'view-dashboard'
    ];
    foreach ($permissions as $perm) {
        $permission = Permission::firstOrCreate(['name' => $perm]);
        $adminRole->givePermissionTo($permission);
    }
});

describe('Quick Authorization Test', function () {
    
    it('admin can access dashboard', function () {
        $this->actingAs($this->adminUser);
        
        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);
    });
    
    it('admin can access products index', function () {
        $this->actingAs($this->adminUser);
        
        $response = $this->get(route('products.index'));
        $response->assertStatus(200);
    });
    
    it('admin has expected permissions', function () {
        expect($this->adminUser->can('manage-system-settings'))->toBeTrue();
        expect($this->adminUser->can('view-products'))->toBeTrue();
        expect($this->adminUser->can('create-products'))->toBeTrue();
    });
});