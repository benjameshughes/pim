<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('confirmation modal is present on dashboard', function () {
    $response = $this->get('/dashboard');
    
    $response->assertStatus(200);
    $response->assertSee('confirmation-dialog');
    $response->assertSee('button'); // Flux buttons render as HTML button elements
});

it('modal has correct Alpine.js structure', function () {
    $response = $this->get('/dashboard');
    
    $response->assertSee('x-data', false);
    $response->assertSee('show: false', false);
    $response->assertSee('variant: \'danger\'', false);
});

it('modal has confirm and cancel buttons', function () {
    $response = $this->get('/dashboard');
    
    $response->assertSee('@click="confirm()"', false);
    $response->assertSee('@click="close()"', false);
    $response->assertSee('variant="danger"', false);
    $response->assertSee('variant="ghost"', false);
});

it('modal has proper dialog attributes', function () {
    $response = $this->get('/dashboard');
    
    $response->assertSee('<dialog', false);
    $response->assertSee('id="confirmation-dialog"', false);
});

it('global confirmAction function exists', function () {
    $response = $this->get('/dashboard');
    
    $response->assertSee('window.confirmAction', false);
    $response->assertSee('function(options)', false);
});