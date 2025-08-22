<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('User Profile Management', function () {
    it('can update profile information', function () {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ]);

        $this->actingAs($user);

        $component = Livewire::test('settings.profile')
            ->set('name', 'Updated Name')
            ->set('email', 'updated@example.com')
            ->call('save');

        $user->refresh();
        expect($user->name)->toBe('Updated Name');
        expect($user->email)->toBe('updated@example.com');
    });

    it('can change password', function () {
        $user = User::factory()->create([
            'password' => bcrypt('oldpassword'),
        ]);

        $this->actingAs($user);

        $component = Livewire::test('settings.password')
            ->set('current_password', 'oldpassword')
            ->set('password', 'newpassword123')
            ->set('password_confirmation', 'newpassword123')
            ->call('save');

        $user->refresh();
        expect(Hash::check('newpassword123', $user->password))->toBeTrue();
    });

    it('requires current password to change password', function () {
        $user = User::factory()->create([
            'password' => bcrypt('oldpassword'),
        ]);

        $this->actingAs($user);

        $component = Livewire::test('settings.password')
            ->set('current_password', 'wrongpassword')
            ->set('password', 'newpassword123')
            ->set('password_confirmation', 'newpassword123')
            ->call('save')
            ->assertHasErrors(['current_password']);
    });

    it('can delete account', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $component = Livewire::test('settings.delete-user-form')
            ->set('password', 'password')
            ->call('deleteUser');

        expect(User::find($user->id))->toBeNull();
        $this->assertGuest();
    });
});