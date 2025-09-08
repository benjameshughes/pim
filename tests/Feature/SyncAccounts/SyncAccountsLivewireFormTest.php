<?php

use App\Livewire\SyncAccounts\Form;
use App\Models\SalesChannel;
use App\Models\SyncAccount;
use App\Models\User;
use App\Services\Marketplace\SyncAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeManagerForSync(): User {
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'manage-marketplace-connections']);
    Permission::firstOrCreate(['name' => 'view-sync-accounts']);
    Permission::firstOrCreate(['name' => 'create-sync-accounts']);
    Permission::firstOrCreate(['name' => 'edit-sync-accounts']);
    Permission::firstOrCreate(['name' => 'test-sync-accounts']);
    $role = Role::firstOrCreate(['name' => 'manager']);
    $role->givePermissionTo(['manage-marketplace-connections','view-sync-accounts','create-sync-accounts','edit-sync-accounts','test-sync-accounts']);
    $user = User::factory()->create(['email' => 'manager-livewire-sync@example.com']);
    $user->assignRole($role);
    return $user;
}

it('validates required fields on create', function () {
    $this->actingAs(makeManagerForSync());

    Livewire::test(Form::class)
        ->call('save')
        ->assertHasErrors(['channel' => 'required', 'name' => 'required']);
});

it('creates a Shopify account and auto-creates SalesChannel', function () {
    $this->actingAs(makeManagerForSync());

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

it('edits an existing account to Shopify and updates SalesChannel', function () {
    $this->actingAs(makeManagerForSync());

    $svc = app(SyncAccountService::class);
    $account = $svc->upsert([
        'channel' => 'mirakl',
        'name' => 'main',
        'display_name' => 'Mirakl Main',
        'credentials' => [
            'base_url' => 'https://example.mirakl.net',
            'api_key' => 'secret-1234567',
        ],
    ]);

    Livewire::test(Form::class, ['accountId' => $account->id])
        ->set('channel', 'shopify')
        ->set('credentials.store_url', 'https://my-store.myshopify.com')
        ->set('credentials.access_token', str_repeat('a', 40))
        ->call('save')
        ->assertDispatched('toast');

    $account->refresh();
    expect($account->channel)->toBe('shopify');

    $salesChannel = SalesChannel::where('config->sync_account_id', $account->id)->first();
    expect($salesChannel)->not()->toBeNull();
    expect($salesChannel->code)->toBe('shopify_main');
});

it('tests connection via Livewire using service mock', function () {
    $this->actingAs(makeManagerForSync());

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

    $mock = Mockery::mock(SyncAccountService::class);
    $mock->shouldReceive('testConnection')->once()->andReturn(['success' => true]);
    app()->instance(SyncAccountService::class, $mock);

    Livewire::test(Form::class, ['accountId' => $account->id])
        ->call('testConnection')
        ->assertDispatched('toast');
});

