<?php

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    // Reset permissions cache
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    
    // Create roles (all three expected by the system)
    $adminRole = Role::firstOrCreate(['name' => 'admin']);
    $managerRole = Role::firstOrCreate(['name' => 'manager']);
    $userRole = Role::firstOrCreate(['name' => 'user']);
    
    // Create essential permissions
    $manageSystemPerm = Permission::firstOrCreate(['name' => 'manage-system-settings']);
    $viewProductsPerm = Permission::firstOrCreate(['name' => 'view-products']);
    
    // Assign permissions
    $adminRole->givePermissionTo($manageSystemPerm);
    $adminRole->givePermissionTo($viewProductsPerm);
    $userRole->givePermissionTo($viewProductsPerm);
    
    // Create test users
    $this->adminUser = User::factory()->create(['email' => 'admin@test.com']);
    $this->adminUser->assignRole('admin');
    
    $this->regularUser = User::factory()->create(['email' => 'user@test.com']);
    $this->regularUser->assignRole('user');
});

describe('Basic Authorization Tests', function () {
    
    it('admin can access management pages', function () {
        expect($this->adminUser->can('manage-system-settings'))->toBeTrue();
        expect($this->adminUser->hasRole('admin'))->toBeTrue();
    });
    
    it('regular user cannot access management pages', function () {
        expect($this->regularUser->can('manage-system-settings'))->toBeFalse();
        expect($this->regularUser->hasRole('admin'))->toBeFalse();
        expect($this->regularUser->hasRole('user'))->toBeTrue();
    });
    
    it('both users can view products', function () {
        expect($this->adminUser->can('view-products'))->toBeTrue();
        expect($this->regularUser->can('view-products'))->toBeTrue();
    });
});

describe('Route Protection Tests', function () {
    
    it('admin can access user management route', function () {
        $this->actingAs($this->adminUser);
        
        $response = $this->get(route('management.users.index'));
        $response->assertStatus(200);
    });
    
    it('regular user is blocked from user management route', function () {
        $this->actingAs($this->regularUser);
        
        $response = $this->get(route('management.users.index'));
        $response->assertStatus(403);
    });
    
    it('both users can access products page', function () {
        $this->actingAs($this->adminUser);
        $response = $this->get(route('products.index'));
        $response->assertStatus(200);
        
        $this->actingAs($this->regularUser);
        $response = $this->get(route('products.index'));
        $response->assertStatus(200);
    });
});