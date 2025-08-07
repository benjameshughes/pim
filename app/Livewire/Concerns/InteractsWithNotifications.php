<?php

namespace App\Livewire\Concerns;

/**
 * InteractsWithNotifications Trait
 * 
 * Simplified FilamentPHP-inspired notification system for Livewire components.
 * Pure Livewire - no Alpine.js complexity.
 */
trait InteractsWithNotifications
{
    /**
     * Notification messages for the current request
     */
    public array $notifications = [];
    
    /**
     * Add a success notification
     */
    public function notifySuccess(string $title, ?string $body = null): void
    {
        $this->addNotification('success', $title, $body);
    }
    
    /**
     * Add an error notification  
     */
    public function notifyError(string $title, ?string $body = null): void
    {
        $this->addNotification('error', $title, $body);
    }
    
    /**
     * Add a warning notification
     */
    public function notifyWarning(string $title, ?string $body = null): void
    {
        $this->addNotification('warning', $title, $body);
    }
    
    /**
     * Add an info notification
     */
    public function notifyInfo(string $title, ?string $body = null): void
    {
        $this->addNotification('info', $title, $body);
    }
    
    /**
     * Add notification to the array
     */
    protected function addNotification(string $type, string $title, ?string $body = null): void
    {
        $this->notifications[] = [
            'id' => uniqid(),
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'timestamp' => now()->timestamp,
        ];
    }
    
    /**
     * Clear all notifications
     */
    public function clearNotifications(): void
    {
        $this->notifications = [];
    }
    
    /**
     * Remove specific notification
     */
    public function removeNotification(string $id): void
    {
        $this->notifications = array_filter($this->notifications, fn($notification) => $notification['id'] !== $id);
    }
    
    /**
     * Get notifications for rendering
     */
    public function getNotifications(): array
    {
        return $this->notifications;
    }
}