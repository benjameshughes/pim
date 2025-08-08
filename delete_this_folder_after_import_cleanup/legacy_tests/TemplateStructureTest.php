<?php

use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('can render dashboard with new template structure', function () {
    $response = $this->get('/dashboard');

    $response->assertStatus(200)
        ->assertSee('dashboard'); // Check Livewire component is rendered
});

it('can render barcodes index with new template structure', function () {
    $response = $this->get('/barcodes');

    $response->assertStatus(200)
        ->assertSee('barcode'); // Check Livewire component is rendered
});

it('can render pricing index with new template structure', function () {
    $response = $this->get('/pricing');

    $response->assertStatus(200);
});

it('can render images index with new template structure', function () {
    $response = $this->get('/images');

    $response->assertStatus(200);
});

it('can render import index with new template structure', function () {
    $response = $this->get('/import');

    $response->assertStatus(200);
});

it('can render archive index with new template structure', function () {
    $response = $this->get('/archive');

    $response->assertStatus(200);
});

it('can render export index with new template structure', function () {
    $response = $this->get('/export');

    $response->assertStatus(200)
        ->assertSee('Export Features Coming Soon');
});

it('can render admin users with new template structure', function () {
    $response = $this->get('/admin/users');

    $response->assertStatus(200)
        ->assertSee('User Management Coming Soon');
});

it('can render admin roles with new template structure', function () {
    $response = $this->get('/admin/roles');

    $response->assertStatus(200)
        ->assertSee('Role Management Coming Soon');
});
