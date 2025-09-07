<?php

use App\Livewire\SyncAccounts\Form;
use App\Models\SalesChannel;
use App\Models\SyncAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeAdminForEditShopify(): User {
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    $adminRole = Role::firstOrCreate(['name' => 'admin']);
    $user = User::factory()->create(['email' => 'admin-edit-shopify@example.com']);
    $user->assignRole($adminRole);
    return $user;
}

it('persists channel when editing to Shopify and updates SalesChannel', function () {
    $admin = makeAdminForEditShopify();
    $this->actingAs($admin);

    // Seed a non-shopify account
    $account = SyncAccount::create([
        'name' => 'main',
        'channel' => 'mirakl',
        'display_name' => 'Mirakl Main',
        'is_active' => true,
        'credentials' => [
            'base_url' => 'https://example.mirakl.net',
            'api_key' => 'abc1234567',
        ],
        'settings' => [],
    ]);

    // Ensure sales channel created
    $this->assertNotNull(SalesChannel::where('config->sync_account_id', $account->id)->first());

    // Edit to Shopify
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

