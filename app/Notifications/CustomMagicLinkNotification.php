<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Maize\MagicLogin\Notifications\MagicLinkNotification as BaseMagicLinkNotification;

/**
 * 📧 CUSTOM MAGIC LINK EMAIL NOTIFICATION
 *
 * Beautiful branded email template for magic login links
 * Extends the package's base notification with custom styling
 */
class CustomMagicLinkNotification extends BaseMagicLinkNotification
{
    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $appName = config('app.name');
        
        return (new MailMessage)
            ->subject("🔐 Your secure access link for {$appName}")
            ->greeting("Hello {$notifiable->name}!")
            ->line('We received a request to sign you into your account.')
            ->line('Click the button below to securely access your dashboard:')
            ->action('🚀 Sign In to ' . $appName, $this->uri)
            ->line('This link will expire in **30 minutes** for your security.')
            ->line('If you didn\'t request this email, you can safely ignore it.')
            ->salutation('Stay secure,<br>The ' . $appName . ' Team')
            ->with([
                'level' => 'info',
                'actionColor' => '#3B82F6', // Blue color for the button
            ]);
    }
}
