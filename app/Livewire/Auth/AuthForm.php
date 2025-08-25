<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Rules\AllowedEmail;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Maize\MagicLogin\Facades\MagicLink;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * 🔐 MAGIC LOGIN AUTH FORM
 *
 * Handles email-based passwordless authentication
 * Supports both registration and login flows
 */
class AuthForm extends Component
{
    #[Validate(['required', 'email'])]
    public string $email = '';

    public bool $emailSent = false;
    public bool $isLoading = false;
    public string $mode = 'login'; // 'login' or 'register'

    /**
     * 🎯 SEND MAGIC LINK
     * 
     * Validates email, creates user if needed, and sends magic link
     */
    public function sendMagicLink()
    {
        $this->isLoading = true;
        
        try {
            // Rate limiting check
            $key = 'magic-link:' . request()->ip() . ':' . $this->email;
            
            if (RateLimiter::tooManyAttempts($key, 3)) {
                $seconds = RateLimiter::availableIn($key);
                
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => "Too many requests. Please wait {$seconds} seconds before trying again."
                ]);
                
                $this->isLoading = false;
                return;
            }

            // Validate with custom rule
            $this->validate([
                'email' => ['required', 'email', new AllowedEmail()]
            ]);

            // Find or create user
            $user = User::firstOrCreate(
                ['email' => $this->email],
                [
                    'name' => $this->extractNameFromEmail($this->email),
                    'email_verified_at' => null,
                ]
            );

            // Generate and send magic link
            MagicLink::send(
                authenticatable: $user,
                redirectUrl: route('dashboard'),
                expiration: now()->addMinutes(30) // 30-minute expiration
            );

            // Hit rate limiter
            RateLimiter::hit($key);

            // Show success state
            $this->emailSent = true;

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Magic link sent! Check your email and click the link to sign in. 📧'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->isLoading = false;
            throw $e;
        } catch (\Exception $e) {
            $this->isLoading = false;
            logger()->error('Magic link send error', [
                'email' => $this->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to send magic link. Please try again.'
            ]);
        }
        
        $this->isLoading = false;
    }

    /**
     * 🔄 REQUEST NEW LINK
     * 
     * Allow user to request another magic link
     */
    public function requestNewLink()
    {
        $this->emailSent = false;
        $this->sendMagicLink();
    }

    /**
     * 🏠 BACK TO FORM
     * 
     * Return to the email input form
     */
    public function backToForm()
    {
        $this->reset(['emailSent', 'email']);
    }

    /**
     * 📧 EXTRACT NAME FROM EMAIL
     * 
     * Create a reasonable name from email address
     */
    private function extractNameFromEmail(string $email): string
    {
        $localPart = explode('@', $email)[0];
        
        // Convert dots/underscores to spaces and title case
        return Str::title(str_replace(['.', '_', '-'], ' ', $localPart));
    }

    /**
     * 🔄 UPDATED HOOKS
     * 
     * Reset email sent state when email changes
     */
    public function updatedEmail()
    {
        if ($this->emailSent) {
            $this->emailSent = false;
        }
    }

    /**
     * 🎨 RENDER
     */
    public function render()
    {
        return view('livewire.auth.auth-form')
            ->layout('components.layouts.guest');
    }
}
