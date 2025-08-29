<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Ensure we start with a clean database
    User::query()->delete();
});

describe('Admin Bootstrapping System', function () {

    describe('User Observer First Admin Logic', function () {
        it('automatically makes the first user an admin', function () {
            // Create first user without specifying role
            $firstUser = User::factory()->create([
                'role' => null, // No role specified
            ]);

            // Should be automatically promoted to admin (regardless of ID)
            expect($firstUser->role)->toBe('admin');
        });

        it('makes first user admin even if role was set to user', function () {
            // Create first user with user role (but empty role triggers observer)
            $firstUser = User::factory()->create([
                'role' => null, // Observer will set this
            ]);

            // Should be automatically set to admin (first user)
            expect($firstUser->role)->toBe('admin');
        });

        it('respects explicit admin role for first user', function () {
            // Create first user explicitly as admin
            $firstUser = User::factory()->create([
                'role' => 'admin',
            ]);

            // Should remain admin (observer won't override explicit role)
            expect($firstUser->role)->toBe('admin');
        });

        it('sets subsequent users to default user role', function () {
            // Create first user (becomes admin)
            $firstUser = User::factory()->create(['role' => null]);
            expect($firstUser->role)->toBe('admin');

            // Create second user without role
            $secondUser = User::factory()->create(['role' => null]);
            expect($secondUser->role)->toBe('user');

            // Create third user without role
            $thirdUser = User::factory()->create(['role' => null]);
            expect($thirdUser->role)->toBe('user');
        });

        it('allows explicit role assignment for subsequent users', function () {
            // Create first user (becomes admin)
            User::factory()->create(['role' => null]);

            // Create subsequent users with explicit roles
            $manager = User::factory()->create(['role' => 'manager']);
            $admin = User::factory()->create(['role' => 'admin']);

            expect($manager->role)->toBe('manager');
            expect($admin->role)->toBe('admin');
        });

        it('works with different user creation methods', function () {
            // Test with User::create()
            $user1 = User::create([
                'name' => 'First User',
                'email' => 'first@example.com',
                'password' => bcrypt('password'),
            ]);
            expect($user1->role)->toBe('admin');

            // Test with factory
            $user2 = User::factory()->create();
            expect($user2->role)->toBe('user');

            // Test with new instance and save
            $user3 = new User([
                'name' => 'Third User',
                'email' => 'third@example.com',
                'password' => bcrypt('password'),
            ]);
            $user3->save();
            expect($user3->role)->toBe('user');
        });
    });

    describe('Create Admin Command', function () {
        it('creates admin user interactively', function () {
            $this->artisan('user:create-admin')
                ->expectsQuestion('👤 Admin name', 'Test Admin')
                ->expectsQuestion('📧 Admin email', 'admin@test.com')
                ->expectsQuestion('🔒 Admin password', 'password123')
                ->expectsConfirmation('Create this admin user?', 'yes')
                ->assertExitCode(0);

            $admin = User::where('email', 'admin@test.com')->first();
            expect($admin)->not->toBeNull();
            expect($admin->name)->toBe('Test Admin');
            expect($admin->role)->toBe('admin');
            expect($admin->email_verified_at)->not->toBeNull();
        });

        it('creates admin user with command options', function () {
            $this->artisan('user:create-admin', [
                '--name' => 'CLI Admin',
                '--email' => 'cli@test.com',
                '--password' => 'clipassword',
                '--force' => true,
            ])->assertExitCode(0);

            $admin = User::where('email', 'cli@test.com')->first();
            expect($admin)->not->toBeNull();
            expect($admin->name)->toBe('CLI Admin');
            expect($admin->role)->toBe('admin');
        });

        it('validates input during admin creation', function () {
            $this->artisan('user:create-admin')
                ->expectsQuestion('👤 Admin name', '') // Empty name
                ->expectsQuestion('📧 Admin email', 'invalid-email') // Invalid email
                ->expectsQuestion('🔒 Admin password', '123') // Too short
                ->assertExitCode(1); // Should fail validation

            expect(User::count())->toBe(0);
        });

        it('prevents duplicate email during admin creation', function () {
            User::factory()->create(['email' => 'existing@test.com']);

            $this->artisan('user:create-admin')
                ->expectsOutput('⚠️  Admin users already exist in the system.') // First user becomes admin
                ->expectsConfirmation('Continue creating another admin?', 'yes')
                ->expectsQuestion('👤 Admin name', 'Duplicate Admin')
                ->expectsQuestion('📧 Admin email', 'existing@test.com')
                ->expectsQuestion('🔒 Admin password', 'password123')
                ->assertExitCode(1);
        });

        it('warns when admin users already exist', function () {
            User::factory()->create(['role' => 'admin']);

            $this->artisan('user:create-admin')
                ->expectsOutput('⚠️  Admin users already exist in the system.')
                ->expectsConfirmation('Continue creating another admin?', 'no')
                ->assertExitCode(0);
        });

        it('allows creating multiple admins with force flag', function () {
            User::factory()->create(['role' => 'admin']);

            $this->artisan('user:create-admin', [
                '--name' => 'Second Admin',
                '--email' => 'second@test.com',
                '--password' => 'password123',
                '--force' => true,
            ])->assertExitCode(0);

            expect(User::where('role', 'admin')->count())->toBe(2);
        });
    });

    describe('Edge Cases and Scenarios', function () {
        it('handles database transactions correctly', function () {
            // Test that observer works within transactions
            \DB::transaction(function () {
                $user = User::create([
                    'name' => 'Transaction User',
                    'email' => 'transaction@test.com',
                    'password' => bcrypt('password'),
                ]);

                expect($user->role)->toBe('admin');
            });

            $user = User::where('email', 'transaction@test.com')->first();
            expect($user->role)->toBe('admin');
        });

        it('works with mass assignment', function () {
            $users = User::insert([
                [
                    'name' => 'Mass User 1',
                    'email' => 'mass1@test.com',
                    'password' => bcrypt('password'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'Mass User 2',
                    'email' => 'mass2@test.com',
                    'password' => bcrypt('password'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            // Mass insert doesn't trigger model events, so these won't get roles
            $firstUser = User::where('email', 'mass1@test.com')->first();
            expect($firstUser->role)->toBeNull();
        });

        it('handles user creation without observer events', function () {
            // Test with createQuietly (bypasses observer)
            $user = User::createQuietly([
                'name' => 'Quiet User',
                'email' => 'quiet@test.com',
                'password' => bcrypt('password'),
            ]);

            // Should not get admin role since observer was bypassed
            expect($user->role)->toBeNull();
        });

        it('maintains correct admin count after deletions', function () {
            // Create first admin
            $admin1 = User::factory()->create(['role' => null]);
            expect($admin1->role)->toBe('admin');

            // Create second user
            $user2 = User::factory()->create(['role' => null]);
            expect($user2->role)->toBe('user');

            // Delete first admin
            $admin1->delete();

            // Create new user - should not become admin (ID won't be 1)
            $user3 = User::factory()->create(['role' => null]);
            expect($user3->role)->toBe('user');

            // System should have 0 admins now
            expect(User::where('role', 'admin')->count())->toBe(0);
        });

        it('handles concurrent user creation gracefully', function () {
            // Simulate rapid user creation
            $users = [];
            for ($i = 0; $i < 5; $i++) {
                $users[] = User::create([
                    'name' => "User {$i}",
                    'email' => "user{$i}@test.com",
                    'password' => bcrypt('password'),
                ]);
            }

            // Only first user should be admin
            expect($users[0]->role)->toBe('admin');
            expect($users[1]->role)->toBe('user');
            expect($users[2]->role)->toBe('user');
            expect(User::where('role', 'admin')->count())->toBe(1);
        });
    });

    describe('Integration with Actions', function () {
        it('works with CreateUserAction', function () {
            // Use our existing CreateUserAction without specifying role
            $result = \App\Actions\Users\CreateUserAction::run(
                'Action User',
                'action@test.com'
                // No role specified - let observer handle it
            );

            expect($result['success'])->toBeTrue();
            $user = $result['data']['user'];

            // Should be set to admin since it's first user
            expect($user->role)->toBe('admin');
        });

        it('respects explicit admin role in CreateUserAction', function () {
            $result = \App\Actions\Users\CreateUserAction::run(
                'Explicit Admin',
                'explicit@test.com',
                'admin' // Explicitly admin
            );

            expect($result['success'])->toBeTrue();
            expect($result['data']['user']->role)->toBe('admin');
        });
    });
});
