<?php

use App\Models\User;
use App\Models\ImportSession;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ImportShowPageTest extends DuskTestCase
{
    public function test_import_show_page_loads_without_errors(): void
    {
        $user = User::factory()->create();
        $session = ImportSession::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'original_filename' => 'test-import.xlsx',
        ]);

        $this->browse(function (Browser $browser) use ($user, $session) {
            $browser->loginAs($user)
                    ->visit("/import/{$session->session_id}")
                    ->waitFor('h2')
                    ->assertSee('Import Session: test-import.xlsx')
                    ->assertDontSeeErrors();
        });
    }

    public function test_import_status_displays_correctly(): void
    {
        $user = User::factory()->create();
        $session = ImportSession::factory()->create([
            'user_id' => $user->id,
            'status' => 'processing',
            'progress_percentage' => 45,
            'original_filename' => 'processing-import.xlsx',
        ]);

        $this->browse(function (Browser $browser) use ($user, $session) {
            $browser->loginAs($user)
                    ->visit("/import/{$session->session_id}")
                    ->waitFor('.inline-flex') // Status badge
                    ->assertSee('Processing')
                    ->assertSee('45%')
                    ->assertDontSeeErrors();
        });
    }

    public function test_websocket_connection_for_progress_updates(): void
    {
        $user = User::factory()->create();
        $session = ImportSession::factory()->create([
            'user_id' => $user->id,
            'status' => 'processing',
            'original_filename' => 'websocket-test.xlsx',
        ]);

        $this->browse(function (Browser $browser) use ($user, $session) {
            $browser->loginAs($user)
                    ->visit("/import/{$session->session_id}")
                    ->waitFor('[x-data]')
                    ->pause(2000) // Give WebSocket time to connect
                    ->assertSeeIn('div', 'Live updates')
                    ->assertDontSeeErrors();
        });
    }

    public function test_import_statistics_display(): void
    {
        $user = User::factory()->create();
        $session = ImportSession::factory()->create([
            'user_id' => $user->id,
            'processed_rows' => 150,
            'successful_rows' => 140,
            'failed_rows' => 10,
            'total_rows' => 200,
            'original_filename' => 'stats-test.xlsx',
        ]);

        $this->browse(function (Browser $browser) use ($user, $session) {
            $browser->loginAs($user)
                    ->visit("/import/{$session->session_id}")
                    ->waitFor('.grid-cols-3') // Statistics grid
                    ->assertSee('150') // Processed
                    ->assertSee('140') // Successful
                    ->assertSee('10')  // Failed
                    ->assertDontSeeErrors();
        });
    }

    public function test_file_information_displays(): void
    {
        $user = User::factory()->create();
        $session = ImportSession::factory()->create([
            'user_id' => $user->id,
            'original_filename' => 'detailed-import.xlsx',
            'file_size' => 2048000, // 2MB
            'created_at' => now()->subMinutes(30),
        ]);

        $this->browse(function (Browser $browser) use ($user, $session) {
            $browser->loginAs($user)
                    ->visit("/import/{$session->session_id}")
                    ->waitFor('dl') // Definition list
                    ->assertSee('detailed-import.xlsx')
                    ->assertSee('2000.0 KB') // File size
                    ->assertSee('ago') // Created time
                    ->assertDontSeeErrors();
        });
    }

    public function test_time_updates_work_in_real_time(): void
    {
        $user = User::factory()->create();
        $session = ImportSession::factory()->create([
            'user_id' => $user->id,
            'created_at' => now()->subSeconds(30), // 30 seconds ago
            'original_filename' => 'time-test.xlsx',
        ]);

        $this->browse(function (Browser $browser) use ($user, $session) {
            $browser->loginAs($user)
                    ->visit("/import/{$session->session_id}")
                    ->waitFor('dd')
                    ->pause(2000) // Give time for Alpine to initialize
                    ->assertSeeIn('dd', 'ago') // Should show relative time
                    ->pause(2000) // Wait for time to update
                    ->assertSeeIn('dd', 'ago') // Still should show relative time
                    ->assertDontSeeErrors();
        });
    }

    public function test_progress_bar_displays_for_active_imports(): void
    {
        $user = User::factory()->create();
        $session = ImportSession::factory()->create([
            'user_id' => $user->id,
            'status' => 'processing',
            'progress_percentage' => 65,
            'current_operation' => 'Processing rows...',
            'original_filename' => 'progress-test.xlsx',
        ]);

        $this->browse(function (Browser $browser) use ($user, $session) {
            $browser->loginAs($user)
                    ->visit("/import/{$session->session_id}")
                    ->waitFor('.bg-blue-600') // Progress bar
                    ->assertAttribute('.bg-blue-600', 'style', 'width: 65%;')
                    ->assertSee('65%')
                    ->assertSee('Processing rows...')
                    ->assertDontSeeErrors();
        });
    }

    public function test_action_buttons_display_correctly(): void
    {
        $user = User::factory()->create();
        
        // Test completed import
        $completedSession = ImportSession::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'original_filename' => 'completed-actions.xlsx',
        ]);

        $this->browse(function (Browser $browser) use ($user, $completedSession) {
            $browser->loginAs($user)
                    ->visit("/import/{$completedSession->session_id}")
                    ->waitFor('.pt-4') // Actions section
                    ->assertSee('Back to Dashboard')
                    ->assertSee('Download Report')
                    ->assertDontSeeErrors();
        });
    }

    public function test_console_errors_on_show_page(): void
    {
        $user = User::factory()->create();
        $session = ImportSession::factory()->create([
            'user_id' => $user->id,
            'original_filename' => 'console-test.xlsx',
        ]);

        $this->browse(function (Browser $browser) use ($user, $session) {
            $browser->loginAs($user)
                    ->visit("/import/{$session->session_id}")
                    ->waitFor('h2')
                    ->pause(3000); // Give time for any async operations
            
            $logs = $browser->driver->manage()->getLog('browser');
            
            $errors = array_filter($logs, function ($log) {
                return $log['level'] === 'SEVERE';
            });

            if (!empty($errors)) {
                $errorMessages = array_map(function ($error) {
                    return $error['message'];
                }, $errors);
                
                $this->fail('Console errors found on show page: ' . implode("\n", $errorMessages));
            }

            expect($errors)->toBeEmpty();
        });
    }

    public function test_alpine_js_import_progress_component(): void
    {
        $user = User::factory()->create();
        $session = ImportSession::factory()->create([
            'user_id' => $user->id,
            'original_filename' => 'alpine-test.xlsx',
        ]);

        $this->browse(function (Browser $browser) use ($user, $session) {
            $browser->loginAs($user)
                    ->visit("/import/{$session->session_id}")
                    ->waitFor('[x-data*="importProgress"]')
                    ->pause(2000) // Give Alpine time to initialize
                    ->assertScript('
                        return document.querySelector("[x-data*=\\"importProgress\\"]")._x_dataStack !== undefined
                    ')
                    ->assertDontSeeErrors();
        });
    }

    protected function tearDown(): void
    {
        ImportSession::query()->delete();
        User::query()->delete();
        
        parent::tearDown();
    }
}