<?php

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    // Reset permissions cache
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    
    // Create all roles the system expects
    $adminRole = Role::firstOrCreate(['name' => 'admin']);
    $managerRole = Role::firstOrCreate(['name' => 'manager']); 
    $userRole = Role::firstOrCreate(['name' => 'user']);
    
    // Create comprehensive permissions
    $permissions = [
        'manage-system-settings', 'manage-users', 'view-products', 'create-products',
        'edit-products', 'delete-products', 'view-barcodes', 'import-barcodes',
        'view-pricing', 'edit-pricing', 'bulk-operations', 'sync-to-marketplace',
        'manage-marketplace-connections', 'view-images', 'manage-images', 
        'view-dashboard', 'import-products'
    ];
    
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }
    
    // Assign permissions to roles
    $adminRole->syncPermissions(Permission::all()); // Admin gets all permissions
    
    $managerPermissions = Permission::whereNotIn('name', [
        'manage-system-settings', 'manage-users'
    ])->get();
    $managerRole->syncPermissions($managerPermissions); // Manager gets most permissions
    
    $userPermissions = Permission::whereIn('name', [
        'view-products', 'view-barcodes', 'view-pricing', 'view-images', 'view-dashboard'
    ])->get();
    $userRole->syncPermissions($userPermissions); // User gets only view permissions
    
    // Create test users
    $this->adminUser = User::factory()->create(['email' => 'admin@test.com']);
    $this->adminUser->assignRole('admin');
    
    $this->managerUser = User::factory()->create(['email' => 'manager@test.com']);
    $this->managerUser->assignRole('manager');
    
    $this->regularUser = User::factory()->create(['email' => 'user@test.com']);
    $this->regularUser->assignRole('user');
});

describe('Admin User Route Access', function () {
    
    it('can access all management routes', function () {
        $this->actingAs($this->adminUser);
        
        // Should access user management
        $response = $this->get(route('management.users.index'));
        $response->assertStatus(200);
        
        // Should access user roles management  
        $response = $this->get(route('management.user-roles.index'));
        $response->assertStatus(200);
    });
    
    it('can access all product management routes', function () {
        $this->actingAs($this->adminUser);
        
        $response = $this->get(route('products.index'));
        $response->assertStatus(200);
        
        $response = $this->get(route('products.create'));
        $response->assertStatus(200);
        
        $response = $this->get(route('barcodes.index'));
        $response->assertStatus(200);
        
        $response = $this->get(route('import.products'));
        $response->assertStatus(200);
    });
    
    it('can access all operational routes', function () {
        $this->actingAs($this->adminUser);
        
        $response = $this->get(route('images.index'));
        $response->assertStatus(200);
        
        $response = $this->get(route('bulk.operations'));
        $response->assertStatus(200);
        
        $response = $this->get(route('pricing.dashboard'));
        $response->assertStatus(200);
    });
    
    it('can access marketplace routes', function () {
        $this->actingAs($this->adminUser);
        
        $response = $this->get(route('shopify.sync'));
        $response->assertStatus(200);
        
        $response = $this->get(route('marketplace.identifiers'));
        $response->assertStatus(200);
        
        $response = $this->get(route('sync-accounts.index'));
        $response->assertStatus(200);
    });
});

describe('Manager User Route Access', function () {
    
    it('cannot access admin-only management routes', function () {
        $this->actingAs($this->managerUser);
        
        // Should be blocked from user management
        $response = $this->get(route('management.users.index'));
        $response->assertStatus(403);
        
        $response = $this->get(route('management.user-roles.index'));
        $response->assertStatus(403);
    });
    
    it('can access product management routes', function () {
        $this->actingAs($this->managerUser);
        
        $response = $this->get(route('products.index'));
        $response->assertStatus(200);
        
        $response = $this->get(route('products.create'));
        $response->assertStatus(200);
        
        $response = $this->get(route('barcodes.index'));
        $response->assertStatus(200);
        
        $response = $this->get(route('import.products'));
        $response->assertStatus(200);
    });
    
    it('can access operational routes', function () {
        $this->actingAs($this->managerUser);
        
        $response = $this->get(route('images.index'));
        $response->assertStatus(200);
        
        $response = $this->get(route('bulk.operations'));
        $response->assertStatus(200);
        
        $response = $this->get(route('pricing.dashboard'));
        $response->assertStatus(200);
    });
    
    it('can access marketplace routes', function () {
        $this->actingAs($this->managerUser);
        
        $response = $this->get(route('shopify.sync'));
        $response->assertStatus(200);
        
        $response = $this->get(route('marketplace.identifiers'));
        $response->assertStatus(200);
        
        $response = $this->get(route('sync-accounts.index'));
        $response->assertStatus(200);
    });
});

describe('Regular User Route Access', function () {
    
    it('cannot access any management routes', function () {
        $this->actingAs($this->regularUser);
        
        $response = $this->get(route('management.users.index'));
        $response->assertStatus(403);
        
        $response = $this->get(route('management.user-roles.index'));
        $response->assertStatus(403);
    });
    
    it('can only view products, not create', function () {
        $this->actingAs($this->regularUser);
        
        // Can view products
        $response = $this->get(route('products.index'));
        $response->assertStatus(200);
        
        // Cannot create products
        $response = $this->get(route('products.create'));
        $response->assertStatus(403);
        
        // Cannot import
        $response = $this->get(route('import.products'));
        $response->assertStatus(403);
    });
    
    it('cannot access operational routes', function () {
        $this->actingAs($this->regularUser);
        
        // Can view images
        $response = $this->get(route('images.index'));
        $response->assertStatus(200);
        
        // Cannot bulk operations
        $response = $this->get(route('bulk.operations'));
        $response->assertStatus(403);
    });
    
    it('cannot access marketplace routes', function () {
        $this->actingAs($this->regularUser);
        
        $response = $this->get(route('shopify.sync'));
        $response->assertStatus(403);
        
        $response = $this->get(route('marketplace.identifiers'));
        $response->assertStatus(403);
        
        $response = $this->get(route('sync-accounts.index'));
        $response->assertStatus(403);
    });
    
    it('can access basic view routes', function () {
        $this->actingAs($this->regularUser);
        
        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);
        
        $response = $this->get(route('products.index'));
        $response->assertStatus(200);
        
        $response = $this->get(route('barcodes.index'));
        $response->assertStatus(200);
        
        $response = $this->get(route('pricing.dashboard'));
        $response->assertStatus(200);
        
        $response = $this->get(route('images.index'));
        $response->assertStatus(200);
    });
});

describe('Permission Verification', function () {
    
    it('admin has all expected permissions', function () {
        $adminPermissions = [
            'manage-system-settings', 'manage-users', 'create-products',
            'edit-products', 'delete-products', 'bulk-operations',
            'sync-to-marketplace', 'manage-marketplace-connections'
        ];
        
        foreach ($adminPermissions as $permission) {
            expect($this->adminUser->can($permission))
                ->toBeTrue("Admin should have {$permission} permission");
        }
    });
    
    it('manager has correct permissions', function () {
        // Should have most permissions
        $managerPermissions = [
            'create-products', 'edit-products', 'bulk-operations',
            'sync-to-marketplace', 'manage-marketplace-connections'
        ];
        
        foreach ($managerPermissions as $permission) {
            expect($this->managerUser->can($permission))
                ->toBeTrue("Manager should have {$permission} permission");
        }
        
        // Should NOT have admin permissions
        $adminOnlyPermissions = ['manage-system-settings', 'manage-users'];
        
        foreach ($adminOnlyPermissions as $permission) {
            expect($this->managerUser->can($permission))
                ->toBeFalse("Manager should NOT have {$permission} permission");
        }
    });
    
    it('regular user has only view permissions', function () {
        // Should have view permissions
        $viewPermissions = [
            'view-products', 'view-barcodes', 'view-pricing', 
            'view-images', 'view-dashboard'
        ];
        
        foreach ($viewPermissions as $permission) {
            expect($this->regularUser->can($permission))
                ->toBeTrue("User should have {$permission} permission");
        }
        
        // Should NOT have create/edit permissions
        $restrictedPermissions = [
            'create-products', 'edit-products', 'delete-products',
            'bulk-operations', 'sync-to-marketplace', 'manage-marketplace-connections',
            'manage-system-settings', 'manage-users'
        ];
        
        foreach ($restrictedPermissions as $permission) {
            expect($this->regularUser->can($permission))
                ->toBeFalse("User should NOT have {$permission} permission");
        }
    });
});