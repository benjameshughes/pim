<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    #[On('echo:test-channel,TestEvent')]
    public function receivedTest($event)
    {
        $this->dispatch('success', 'Echo event received: ' . $event['message']);
    }

    public function testPusher()
    {
        try {
            // Simple test using Laravel's built-in broadcasting test
            broadcast(new \App\Events\TestEvent(['message' => 'Hello from Pusher!']));
            $this->dispatch('success', 'Test event sent to Pusher!');
        } catch (\Exception $e) {
            $this->dispatch('error', 'Broadcasting failed: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.dashboard');
    }
}
