<?php

namespace App\UI\Toasts\Contracts;

/**
 * Toast Contract Interface
 * 
 * FilamentPHP-inspired contract for toast notifications.
 * Defines the core interface for building and sending toast notifications.
 */
interface ToastContract
{
    /**
     * Create a new toast instance
     */
    public static function make(): static;
    
    /**
     * Set the toast title
     */
    public function title(string $title): static;
    
    /**
     * Set the toast body content
     */
    public function body(string $body): static;
    
    /**
     * Set the toast icon
     */
    public function icon(string $icon): static;
    
    /**
     * Set the toast type
     */
    public function type(string $type): static;
    
    /**
     * Set toast color
     */
    public function color(string $color): static;
    
    /**
     * Set toast duration in milliseconds
     */
    public function duration(int $duration): static;
    
    /**
     * Set toast duration in seconds
     */
    public function seconds(int $seconds): static;
    
    /**
     * Make toast persistent (requires manual dismiss)
     */
    public function persistent(): static;
    
    /**
     * Set toast position
     */
    public function position(string $position): static;
    
    /**
     * Add action to toast
     */
    public function action($action): static;
    
    /**
     * Add multiple actions to toast
     */
    public function actions(array $actions): static;
    
    /**
     * Send the toast notification
     */
    public function send(): void;
    
    /**
     * Convert toast to array format
     */
    public function toArray(): array;
    
    /**
     * Get unique toast identifier
     */
    public function getId(): string;
}