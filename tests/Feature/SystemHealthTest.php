<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('System Health Check', function () {

    test('database connection works', function () {
        expect(DB::connection()->getPdo())->not->toBeNull();
    });

    test('authentication system works', function () {
        expect(auth()->check())->toBe(true);
        expect(auth()->user())->toBeInstanceOf(User::class);
    });

    test('home page loads', function () {
        $response = $this->get('/');
        expect($response->status())->toBe(200);
    });

    test('dashboard loads without 500 errors', function () {
        $response = $this->get('/dashboard');
        expect($response->status())->toBeLessThan(500);
    });

    test('products page loads', function () {
        $response = $this->get('/products');
        expect($response->status())->toBeLessThan(500);
    });

    test('dam page loads', function () {
        $response = $this->get('/dam');
        expect($response->status())->toBeLessThan(500);
    });

    test('settings page loads', function () {
        $response = $this->get('/settings/profile');
        expect($response->status())->toBeLessThan(500);
    });

});
