<?php

namespace App\Livewire\Auth;

use Livewire\Component;

/**
 * 🎉 MAGIC LINK SUCCESS COMPONENT
 *
 * Shows success message after magic link login
 * Provides option to redirect to dashboard
 */
class MagicLinkSuccess extends Component
{
    public bool $autoRedirect = false;

    public function mount()
    {
        // Auto-redirect after 3 seconds
        $this->autoRedirect = true;
    }

    public function goToDashboard()
    {
        return $this->redirect(route('dashboard'), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.magic-link-success')
            ->layout('components.layouts.guest');
    }
}