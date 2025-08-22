<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('User Model', function () {
    it('has correct fillable attributes', function () {
        $user = new User();
        
        $fillable = ['name', 'email', 'password'];
        expect($user->getFillable())->toEqual($fillable);
    });

    it('can generate initials', function () {
        $user = User::factory()->create(['name' => 'Jane Smith']);
        
        expect($user->initials())->toBe('JS');
    });

    it('handles single name for initials', function () {
        $user = User::factory()->create(['name' => 'Madonna']);
        
        expect($user->initials())->toBe('M');
    });
});