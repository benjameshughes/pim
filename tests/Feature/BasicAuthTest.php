<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Basic Authentication', function () {
    it('can create a user', function () {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        expect($user->name)->toBe('Test User');
        expect($user->email)->toBe('test@example.com');
    });

    it('can get user initials', function () {
        $user = User::factory()->create(['name' => 'John Doe']);
        
        expect($user->initials())->toBe('JD');
    });
});