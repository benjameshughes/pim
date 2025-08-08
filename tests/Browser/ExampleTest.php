<?php

use Laravel\Dusk\Browser;

uses(Tests\DuskTestCase::class);

test('basic example', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/')
                ->assertSee('Laravel');
    });
});
