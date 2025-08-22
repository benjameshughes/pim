<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('User Authentication', function () {
    it('can register a new user', function () {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        expect(User::where('email', 'test@example.com')->exists())->toBeTrue();
        $this->assertAuthenticated();
    });

    it('can login with valid credentials', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->assertAuthenticated();
    });

    it('cannot login with invalid credentials', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $this->assertGuest();
    });

    it('can logout', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post('/logout');

        $this->assertGuest();
    });
});

describe('Password Reset', function () {
    it('can request password reset', function () {
        $user = User::factory()->create(['email' => 'test@example.com']);

        $response = $this->post('/forgot-password', [
            'email' => 'test@example.com',
        ]);

        $response->assertSessionHasNoErrors();
    });

    it('cannot request reset for non-existent email', function () {
        $response = $this->post('/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertSessionHasErrors(['email']);
    });
});

describe('Email Verification', function () {
    it('can verify email', function () {
        $user = User::factory()->unverified()->create();

        expect($user->hasVerifiedEmail())->toBeFalse();
        
        $user->markEmailAsVerified();
        
        expect($user->hasVerifiedEmail())->toBeTrue();
    });
});