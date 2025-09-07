<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Reset permissions and roles
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    // Create roles if they don't exist
    $adminRole = Role::firstOrCreate(['name' => 'admin']);
    $managerRole = Role::firstOrCreate(['name' => 'manager']);
    $userRole = Role::firstOrCreate(['name' => 'user']);

    // Create key permissions for testing
    $permissions = [
        'manage-system-settings',
        'manage-users',
        'view-products',
        'create-products',
        'edit-products',
        'delete-products',
        'view-barcodes',
        'import-barcodes',
        'view-pricing',
        'edit-pricing',
        'bulk-operations',
        'view-system-logs',
        'sync-to-marketplace',
        'manage-marketplace-connections',
        'view-images',
        'manage-images',
        'upload-images',
        'delete-images',
        'view-dashboard',
        'import-products',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }

    // Assign all permissions to admin
    $adminRole->syncPermissions(Permission::all());

    // Assign manager permissions (most permissions except system management)
    $managerPermissions = Permission::whereNotIn('name', [
        'manage-system-settings',
        'manage-users',
        'delete-products',
        'view-system-logs',
    ])->get();
    $managerRole->syncPermissions($managerPermissions);

    // Assign basic view permissions to user role
    $userPermissions = Permission::whereIn('name', [
        'view-products',
        'view-barcodes',
        'view-pricing',
        'view-images',
        'view-dashboard',
    ])->get();
    $userRole->syncPermissions($userPermissions);

    // Create test users with roles
    $this->adminUser = User::factory()->create([
        'name' => 'Admin User',
        'email' => 'admin@test.com',
    ]);
    $this->adminUser->assignRole('admin');

    $this->managerUser = User::factory()->create([
        'name' => 'Manager User',
        'email' => 'manager@test.com',
    ]);
    $this->managerUser->assignRole('manager');

    $this->regularUser = User::factory()->create([
        'name' => 'Regular User',
        'email' => 'user@test.com',
    ]);
    $this->regularUser->assignRole('user');
});

describe('Admin User Authorization', function () {

    it('can access all management pages', function () {
        $this->actingAs($this->adminUser);

        // Admin should access user management
        $response = $this->get(route('management.users.index'));
        $response->assertStatus(200);

        // Admin should access user roles management
        $response = $this->get(route('management.user-roles.index'));
        $response->assertStatus(200);

        // Admin should access system logs
        $response = $this->get(route('log-dashboard'));
        $response->assertStatus(200);
    });

    it('can access all product management features', function () {
        $this->actingAs($this->adminUser);

        // Products
        $response = $this->get(route('products.index'));
        $response->assertStatus(200);

        $response = $this->get(route('products.create'));
        $response->assertStatus(200);

        // Pricing
        $response = $this->get(route('pricing.dashboard'));
        $response->assertStatus(200);

        // Import
        $response = $this->get(route('import.products'));
        $response->assertStatus(200);

        // Barcodes
        $response = $this->get(route('barcodes.index'));
        $response->assertStatus(200);

        $response = $this->get(route('barcodes.import'));
        $response->assertStatus(200);
    });

    it('can access all marketplace features', function () {
        $this->actingAs($this->adminUser);

        // Shopify sync
        $response = $this->get(route('shopify.sync'));
        $response->assertStatus(200);

        // Marketplace management
        $response = $this->get(route('marketplace.identifiers'));
        $response->assertStatus(200);

        // Sync accounts
        $response = $this->get(route('sync-accounts.index'));
        $response->assertStatus(200);

        $response = $this->get(route('sync-accounts.create'));
        $response->assertStatus(200);
    });

    it('can access all operational features', function () {
        $this->actingAs($this->adminUser);

        // Bulk operations
        $response = $this->get(route('bulk.operations'));
        $response->assertStatus(200);

        // Images
        $response = $this->get(route('images.index'));
        $response->assertStatus(200);
    });

    it('has all expected permissions', function () {
        expect($this->adminUser->can('manage-system-settings'))->toBeTrue();
        expect($this->adminUser->can('manage-users'))->toBeTrue();
        expect($this->adminUser->can('delete-products'))->toBeTrue();
        expect($this->adminUser->can('view-system-logs'))->toBeTrue();
        expect($this->adminUser->can('bulk-operations'))->toBeTrue();
    });
});

describe('Manager User Authorization', function () {

    it('cannot access admin-only management pages', function () {
        $this->actingAs($this->managerUser);

        // Manager should NOT access user management
        $response = $this->get(route('management.users.index'));
        $response->assertStatus(403);

        // Manager should NOT access user roles management
        $response = $this->get(route('management.user-roles.index'));
        $response->assertStatus(403);

        // Manager should NOT access system logs
        $response = $this->get(route('log-dashboard'));
        $response->assertStatus(403);
    });

    it('can access product management features', function () {
        $this->actingAs($this->managerUser);

        // Products - should work
        $response = $this->get(route('products.index'));
        $response->assertStatus(200);

        $response = $this->get(route('products.create'));
        $response->assertStatus(200);

        // Pricing - should work
        $response = $this->get(route('pricing.dashboard'));
        $response->assertStatus(200);

        // Import - should work
        $response = $this->get(route('import.products'));
        $response->assertStatus(200);

        // Barcodes - should work
        $response = $this->get(route('barcodes.index'));
        $response->assertStatus(200);

        $response = $this->get(route('barcodes.import'));
        $response->assertStatus(200);
    });

    it('can access marketplace features', function () {
        $this->actingAs($this->managerUser);

        // Shopify sync - should work
        $response = $this->get(route('shopify.sync'));
        $response->assertStatus(200);

        // Marketplace management - should work
        $response = $this->get(route('marketplace.identifiers'));
        $response->assertStatus(200);

        // Sync accounts - should work
        $response = $this->get(route('sync-accounts.index'));
        $response->assertStatus(200);

        $response = $this->get(route('sync-accounts.create'));
        $response->assertStatus(200);
    });

    it('can access operational features', function () {
        $this->actingAs($this->managerUser);

        // Bulk operations - should work
        $response = $this->get(route('bulk.operations'));
        $response->assertStatus(200);

        // Images - should work
        $response = $this->get(route('images.index'));
        $response->assertStatus(200);
    });

    it('has expected permissions but not admin ones', function () {
        // Manager permissions
        expect($this->managerUser->can('create-products'))->toBeTrue();
        expect($this->managerUser->can('edit-products'))->toBeTrue();
        expect($this->managerUser->can('view-pricing'))->toBeTrue();
        expect($this->managerUser->can('bulk-operations'))->toBeTrue();
        expect($this->managerUser->can('sync-to-marketplace'))->toBeTrue();

        // Admin-only permissions should be denied
        expect($this->managerUser->can('manage-system-settings'))->toBeFalse();
        expect($this->managerUser->can('manage-users'))->toBeFalse();
        expect($this->managerUser->can('delete-products'))->toBeFalse();
        expect($this->managerUser->can('view-system-logs'))->toBeFalse();
    });
});

describe('Regular User Authorization', function () {

    it('cannot access any management pages', function () {
        $this->actingAs($this->regularUser);

        // Regular user should NOT access user management
        $response = $this->get(route('management.users.index'));
        $response->assertStatus(403);

        // Regular user should NOT access user roles management
        $response = $this->get(route('management.user-roles.index'));
        $response->assertStatus(403);

        // Regular user should NOT access system logs
        $response = $this->get(route('log-dashboard'));
        $response->assertStatus(403);
    });

    it('cannot access create/edit product features', function () {
        $this->actingAs($this->regularUser);

        // Can view products but not create
        $response = $this->get(route('products.index'));
        $response->assertStatus(200);

        $response = $this->get(route('products.create'));
        $response->assertStatus(403);

        // Cannot import
        $response = $this->get(route('import.products'));
        $response->assertStatus(403);

        // Cannot import barcodes
        $response = $this->get(route('barcodes.import'));
        $response->assertStatus(403);
    });

    it('cannot access marketplace management features', function () {
        $this->actingAs($this->regularUser);

        // Should NOT access Shopify sync
        $response = $this->get(route('shopify.sync'));
        $response->assertStatus(403);

        // Should NOT access marketplace management
        $response = $this->get(route('marketplace.identifiers'));
        $response->assertStatus(403);

        // Should NOT access sync accounts
        $response = $this->get(route('sync-accounts.index'));
        $response->assertStatus(403);

        $response = $this->get(route('marketplace.add-integration'));
        $response->assertStatus(403);
    });

    it('cannot access operational features', function () {
        $this->actingAs($this->regularUser);

        // Should NOT access bulk operations
        $response = $this->get(route('bulk.operations'));
        $response->assertStatus(403);
    });

    it('can only access view-only features', function () {
        $this->actingAs($this->regularUser);

        // Dashboard - should work
        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);

        // Products view - should work
        $response = $this->get(route('products.index'));
        $response->assertStatus(200);

        // Barcodes view - should work
        $response = $this->get(route('barcodes.index'));
        $response->assertStatus(200);

        // Pricing view - should work
        $response = $this->get(route('pricing.dashboard'));
        $response->assertStatus(200);

        // Images view - should work
        $response = $this->get(route('images.index'));
        $response->assertStatus(200);
    });

    it('has only view permissions', function () {
        // View permissions - should work
        expect($this->regularUser->can('view-products'))->toBeTrue();
        expect($this->regularUser->can('view-barcodes'))->toBeTrue();
        expect($this->regularUser->can('view-pricing'))->toBeTrue();
        expect($this->regularUser->can('view-images'))->toBeTrue();
        expect($this->regularUser->can('view-dashboard'))->toBeTrue();

        // All other permissions should be denied
        expect($this->regularUser->can('create-products'))->toBeFalse();
        expect($this->regularUser->can('edit-products'))->toBeFalse();
        expect($this->regularUser->can('delete-products'))->toBeFalse();
        expect($this->regularUser->can('import-products'))->toBeFalse();
        expect($this->regularUser->can('bulk-operations'))->toBeFalse();
        expect($this->regularUser->can('sync-to-marketplace'))->toBeFalse();
        expect($this->regularUser->can('manage-marketplace-connections'))->toBeFalse();
        expect($this->regularUser->can('manage-system-settings'))->toBeFalse();
        expect($this->regularUser->can('manage-users'))->toBeFalse();
        expect($this->regularUser->can('view-system-logs'))->toBeFalse();
    });
});

describe('Sidebar Navigation Authorization', function () {

    it('shows full navigation for admin users', function () {
        $this->actingAs($this->adminUser);

        $response = $this->get('/dashboard');

        // Admin should see all navigation sections
        $response->assertSee('Dashboard');
        $response->assertSee('Products');
        $response->assertSee('Media');
        $response->assertSee('Sales Channels');
        $response->assertSee('Operations');
        $response->assertSee('Management');
    });

    it('shows limited navigation for manager users', function () {
        $this->actingAs($this->managerUser);

        $response = $this->get('/dashboard');

        // Manager should see most sections but not Management
        $response->assertSee('Dashboard');
        $response->assertSee('Products');
        $response->assertSee('Media');
        $response->assertSee('Sales Channels');
        $response->assertSee('Operations');
        $response->assertDontSee('Management'); // Should not see admin section
    });

    it('shows minimal navigation for regular users', function () {
        $this->actingAs($this->regularUser);

        $response = $this->get('/dashboard');

        // Regular user should only see view sections
        $response->assertSee('Dashboard');
        $response->assertSee('Products');
        $response->assertSee('Media');
        $response->assertDontSee('Sales Channels'); // No marketplace access
        $response->assertDontSee('Operations'); // No bulk operations access
        $response->assertDontSee('Management'); // No admin access
    });
});

describe('Livewire Component Authorization', function () {

    it('blocks unauthorized users from protected Livewire components', function () {
        $this->actingAs($this->regularUser);

        // Test ProductWizard component (requires create-products)
        \Livewire\Livewire::test(\App\Livewire\ProductWizard::class)
            ->assertStatus(403);

        // Test UserIndex component (requires manage-system-settings)
        \Livewire\Livewire::test(\App\Livewire\Management\Users\UserIndex::class)
            ->assertStatus(403);
    });

    it('allows authorized users to access protected Livewire components', function () {
        $this->actingAs($this->adminUser);

        // Admin should access UserIndex
        \Livewire\Livewire::test(\App\Livewire\Management\Users\UserIndex::class)
            ->assertStatus(200);
    });
});
