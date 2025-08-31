<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    public $showTest = false;

    public $user = null;

    public function mount()
    {
        // Authorize viewing dashboard
        $this->authorize('view-dashboard');
    }

    #[On('echo:test,.TestEvent')]
    public function showUser($event)
    {
        $this->showTest = true;
        $this->user = (object) $event['user'];
    }

    public function testBroadcast()
    {
        broadcast(new \App\Events\TestEvent(auth()->user()));
    }

    public function render()
    {
        return view('livewire.dashboard');
    }
}
