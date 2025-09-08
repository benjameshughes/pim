<?php

use App\Models\SyncAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function seedRolesAndPermissions(): void {
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'manage-marketplace-connections']);
    Permission::firstOrCreate(['name' => 'view-sync-accounts']);
    Permission::firstOrCreate(['name' => 'create-sync-accounts']);
    Permission::firstOrCreate(['name' => 'edit-sync-accounts']);
    Permission::firstOrCreate(['name' => 'delete-sync-accounts']);
    Permission::firstOrCreate(['name' => 'test-sync-accounts']);

    $admin = Role::firstOrCreate(['name' => 'admin']);
    $manager = Role::firstOrCreate(['name' => 'manager']);
    $admin->givePermissionTo(Permission::all());
    $manager->givePermissionTo(['manage-marketplace-connections','view-sync-accounts','create-sync-accounts','edit-sync-accounts','test-sync-accounts']);
}

it('denies access to sync accounts routes for regular users', function () {
    seedRolesAndPermissions();
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('sync-accounts.index'))->assertForbidden();
});

it('allows managers to access sync accounts index/create and dashboard', function () {
    seedRolesAndPermissions();
    $user = User::factory()->create(['email' => 'manager-sync@example.com']);
    $user->assignRole('manager');
    $this->actingAs($user);

    $this->get(route('sync-accounts.index'))->assertOk();
    $this->get(route('sync-accounts.create'))->assertOk();

    $account = SyncAccount::create([
        'name' => 'main',
        'channel' => 'shopify',
        'display_name' => 'Shopify Main',
        'is_active' => true,
        'credentials' => [
            'store_url' => 'my-store.myshopify.com',
            'access_token' => str_repeat('a', 40),
        ],
    ]);

    $this->get(route('sync-accounts.dashboard', ['accountId' => $account->id]))->assertOk();
});

