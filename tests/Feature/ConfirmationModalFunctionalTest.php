<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('confirmation modal renders successfully without errors', function () {
    $response = $this->get('/dashboard');
    
    $response->assertStatus(200);
    
    // Essential modal elements are present
    $response->assertSee('confirmation-dialog');
    $response->assertSee('x-data');
    $response->assertSee('confirmAction');
    
    // Modal has buttons
    $response->assertSee('button');
    
    // Modal has Alpine.js functionality
    $response->assertSee('@click');
    $response->assertSee('x-text');
    $response->assertSee('x-show');
});

test('modal structure supports danger variant', function () {
    $response = $this->get('/dashboard');
    
    // Check that danger variant structure exists
    $response->assertSee('variant === &#039;danger&#039;', false);
    $response->assertSee('show: false', false);
    $response->assertSee('variant: \'danger\'', false);
});

test('modal has wire action capability', function () {
    $response = $this->get('/dashboard');
    
    $response->assertSee('wireAction', false);
    $response->assertSee('wireComponent', false);
    $response->assertSee('this.wireComponent.call', false);
});