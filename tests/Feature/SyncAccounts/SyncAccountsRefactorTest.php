<?php

use App\Livewire\SyncAccounts\Form;
use App\Models\SyncAccount;
use App\Models\User;
use App\Services\Marketplace\SyncAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeAdmin(): User {
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    $adminRole = Role::firstOrCreate(['name' => 'admin']);

    $user = User::factory()->create(['email' => 'admin-syncaccounts@example.com']);
    $user->assignRole($adminRole);
    return $user;
}

it('creates a Mirakl sync account via service with validation', function () {
    $admin = makeAdmin();
    $this->actingAs($admin);

    $svc = app(SyncAccountService::class);

    $account = $svc->upsert([
        'channel' => 'mirakl',
        'name' => 'main',
        'display_name' => 'Mirakl Main',
        'credentials' => [
            'base_url' => 'https://example.mirakl.net',
            'api_key' => 'secret-key-123',
        ],
        'settings' => [
            'currency' => 'GBP',
        ],
    ]);

    expect($account)->not()->toBeNull();
    expect($account->channel)->toBe('mirakl');
    expect($account->credentials['base_url'])->toBe('https://example.mirakl.net');
    expect($account->credentials['api_key'])->toBe('secret-key-123');
    expect($account->settings['currency'])->toBe('GBP');
});

it('uses Livewire Form to create a mirakl account and test connection', function () {
    $admin = makeAdmin();
    $this->actingAs($admin);

    // Create via Livewire Form
    Livewire::test(Form::class)
        ->set('channel', 'mirakl')
        ->set('name', 'main')
        ->set('display_name', 'Mirakl Account')
        ->set('credentials.base_url', 'https://freemansuk-prod.mirakl.net')
        ->set('credentials.api_key', 'test-api-key')
        ->set('settings.currency', 'GBP')
        ->call('save')
        ->assertDispatched('toast');

    $account = SyncAccount::where('channel', 'mirakl')->where('name', 'main')->first();
    expect($account)->not()->toBeNull();

    // Test connection (fake HTTP)
    Http::fake([
        'https://freemansuk-prod.mirakl.net/api/version' => Http::response(['version' => '1.0.0'], 200),
        // account not strictly needed for testConnection in our implementation
    ]);

    // Also call the service directly to ensure success persisted
    $svc = app(SyncAccountService::class);
    $result = $svc->testConnection($account);
    expect($result['success'] ?? false)->toBeTrue();

    $account->refresh();
    $stored = $account->connection_test_result;
    expect($stored)->not()->toBeNull();
    expect($stored['success'] ?? false)->toBeTrue();
});

it('edits an existing account via Livewire Form', function () {
    $admin = makeAdmin();
    $this->actingAs($admin);

    $account = SyncAccount::create([
        'name' => 'main',
        'channel' => 'mirakl',
        'display_name' => 'Old Name',
        'is_active' => true,
        'credentials' => [
            'base_url' => 'https://old.mirakl.net',
            'api_key' => 'old-key',
        ],
        'settings' => [
            'currency' => 'GBP',
        ],
    ]);

    Livewire::test(Form::class, ['accountId' => $account->id])
        ->set('display_name', 'New Name')
        ->set('credentials.base_url', 'https://new.mirakl.net')
        ->set('credentials.api_key', 'new-key-123')
        ->call('save')
        ->assertDispatched('toast');

    $account->refresh();
    expect($account->display_name)->toBe('New Name');
    expect($account->credentials['base_url'])->toBe('https://new.mirakl.net');
});

it('denies create for regular user via policy', function () {
    $user = User::factory()->create(); // no role
    $this->actingAs($user);

    $response = $this->get(route('sync-accounts.create'));
    $response->assertForbidden();
});
