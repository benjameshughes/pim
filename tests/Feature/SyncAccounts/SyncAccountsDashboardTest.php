<?php

use App\Models\SyncAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeManagerForDashboard(): User {
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'manage-marketplace-connections']);
    $role = Role::firstOrCreate(['name' => 'manager']);
    $role->givePermissionTo(['manage-marketplace-connections']);
    $u = User::factory()->create(['email' => 'manager-dashboard@example.com']);
    $u->assignRole($role);
    return $u;
}

it('shows the per-account dashboard with counts and renders ok', function () {
    $this->actingAs(makeManagerForDashboard());

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

    // Seed one synced record to ensure counts work with 'status' col
    DB::table('sync_statuses')->insert([
        'product_id' => 1,
        'sync_account_id' => $account->id,
        'channel' => 'shopify',
        'status' => 'synced',
        'external_id' => 'gid://shopify/Product/123',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->get(route('sync-accounts.dashboard', ['accountId' => $account->id]))
        ->assertOk()
        ->assertSee('Shopify Main')
        ->assertSee('Products Linked');
});

