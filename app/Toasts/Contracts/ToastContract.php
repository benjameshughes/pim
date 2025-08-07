<?php

namespace App\Toasts\Contracts;

interface ToastContract
{
    /**
     * Create a new toast notification.
     */
    public static function make(): static;

    /**
     * Set the toast title.
     */
    public function title(string $title): static;

    /**
     * Set the toast body content.
     */
    public function body(?string $body): static;

    /**
     * Set the toast type.
     */
    public function type(string $type): static;

    /**
     * Set the toast position.
     */
    public function position(string $position): static;

    /**
     * Make the toast closable.
     */
    public function closable(bool $closable = true): static;

    /**
     * Make the toast persistent (won't auto-dismiss).
     */
    public function persistent(bool $persistent = true): static;

    /**
     * Send the toast notification.
     */
    public function send(): static;

    /**
     * Get the toast ID.
     */
    public function getId(): string;

    /**
     * Get the toast title.
     */
    public function getTitle(): string;

    /**
     * Get the toast body.
     */
    public function getBody(): ?string;

    /**
     * Get the toast type.
     */
    public function getType(): string;

    /**
     * Get the toast position.
     */
    public function getPosition(): string;

    /**
     * Check if the toast is closable.
     */
    public function isClosable(): bool;

    /**
     * Check if the toast is persistent.
     */
    public function isPersistent(): bool;
}