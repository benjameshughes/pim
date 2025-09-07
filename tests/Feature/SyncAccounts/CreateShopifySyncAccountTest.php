<?php

use App\Livewire\SyncAccounts\Form;
use App\Models\SalesChannel;
use App\Models\SyncAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeAdminForShopifyCreate(): User {
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    $adminRole = Role::firstOrCreate(['name' => 'admin']);
    $user = User::factory()->create(['email' => 'admin-shopify-create@example.com']);
    $user->assignRole($adminRole);
    return $user;
}

it('creates a Shopify sync account via Livewire Form and auto-creates SalesChannel', function () {
    $admin = makeAdminForShopifyCreate();
    $this->actingAs($admin);

    Livewire::test(Form::class)
        ->set('channel', 'shopify')
        ->set('name', 'main')
        ->set('display_name', 'My Shopify')
        ->set('credentials.store_url', 'https://my-store.myshopify.com')
        ->set('credentials.access_token', str_repeat('a', 40))
        ->call('save')
        ->assertDispatched('toast');

    $account = SyncAccount::where('channel', 'shopify')->where('name', 'main')->first();
    expect($account)->not()->toBeNull();

    $salesChannel = SalesChannel::where('config->sync_account_id', $account->id)->first();
    expect($salesChannel)->not()->toBeNull();
    expect($salesChannel->code)->toBe('shopify_main');
});

