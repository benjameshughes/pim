<?php

use App\Models\User;
use App\Models\ImportSession;
use Laravel\Dusk\Browser;

uses(Tests\DuskTestCase::class);

test('import dashboard loads without console errors', function () {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/import')
                ->waitFor('h2')
                ->assertSee('Import Dashboard')
                ->assertDontSeeErrors();
    });
});

test('dashboard displays statistics cards', function () {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/import')
                ->waitFor('.grid')
                ->assertSee('Total Imports')
                ->assertSee('Successful')
                ->assertSee('Failed')
                ->assertSee('Processing')
                ->assertDontSeeErrors();
    });
});

test('dashboard shows empty state when no imports', function () {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/import')
                ->waitFor('.text-center')
                ->assertSee('No imports yet')
                ->assertSee('Get started by creating your first import')
                ->assertSee('+ New Import')
                ->assertDontSeeErrors();
    });
});

test('console errors are captured and reported', function () {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/import')
                ->waitFor('h2')
                ->pause(3000); // Give time for any async operations
        
        // Get console logs to check for errors
        $logs = $browser->driver->manage()->getLog('browser');
        
        $errors = array_filter($logs, function ($log) {
            return $log['level'] === 'SEVERE';
        });

        // If there are console errors, display them
        if (!empty($errors)) {
            $errorMessages = array_map(function ($error) {
                return $error['message'];
            }, $errors);
            
            $this->fail('Console errors found: ' . implode("\n", $errorMessages));
        }

        expect($errors)->toBeEmpty();
    });
});

test('alpine js initialization works', function () {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/import')
                ->waitFor('[x-data]')
                ->pause(2000)
                ->assertScript('
                    return window.Alpine !== undefined && 
                           document.querySelector("[x-data]")._x_dataStack !== undefined
                ')
                ->assertDontSeeErrors();
    });
});

test('websocket echo configuration exists', function () {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/import')
                ->waitFor('[x-data]')
                ->pause(3000)
                ->assertScript('return window.Echo !== undefined')
                ->assertScript('return typeof window.Echo.channel === "function"')
                ->assertDontSeeErrors();
    });
});