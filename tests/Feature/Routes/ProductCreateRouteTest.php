<?php

use App\Models\User;

test('product create route returns 200', function () {
    $user = User::factory()->create();
    
    $response = $this->actingAs($user)->get('/products/create');
    
    $response->assertStatus(200);
});

test('product create route requires authentication', function () {
    $response = $this->get('/products/create');
    
    // Should redirect to login
    $response->assertStatus(302);
    $response->assertRedirect('/login');
});

test('product create route loads the wizard component', function () {
    $user = User::factory()->create();
    
    $response = $this->actingAs($user)->get('/products/create');
    
    $response->assertStatus(200);
    $response->assertSeeLivewire('product-wizard');
});

test('product create route uses correct view', function () {
    $user = User::factory()->create();
    
    $response = $this->actingAs($user)->get(route('products.create'));
    
    $response->assertStatus(200);
    $response->assertViewIs('products.create');
});