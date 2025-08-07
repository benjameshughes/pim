<?php

namespace App\UI\Toasts\Concerns;

/**
 * HasContent Concern
 * 
 * Manages toast content including title, body, and icon.
 * FilamentPHP-inspired content management for toast notifications.
 */
trait HasContent
{
    protected string $title = '';
    protected string $body = '';
    protected ?string $icon = null;
    
    /**
     * Set the toast title
     */
    public function title(string $title): static
    {
        $this->title = $title;
        return $this;
    }
    
    /**
     * Set the toast body content
     */
    public function body(string $body): static
    {
        $this->body = $body;
        return $this;
    }
    
    /**
     * Set the toast icon
     */
    public function icon(string $icon): static
    {
        $this->icon = $icon;
        return $this;
    }
    
    /**
     * Get the toast title
     */
    public function getTitle(): string
    {
        return $this->title;
    }
    
    /**
     * Get the toast body
     */
    public function getBody(): string
    {
        return $this->body;
    }
    
    /**
     * Get the toast icon
     */
    public function getIcon(): ?string
    {
        return $this->icon;
    }
    
    /**
     * Check if toast has content (title or body)
     */
    public function hasContent(): bool
    {
        return !empty($this->title) || !empty($this->body);
    }
    
    /**
     * Check if toast has an icon
     */
    public function hasIcon(): bool
    {
        return $this->icon !== null;
    }
}