<?php

namespace App\UI\Toasts;

use App\UI\Toasts\Concerns\HasActions;
use App\UI\Toasts\Concerns\HasContent;
use App\UI\Toasts\Concerns\HasStyling;
use App\UI\Toasts\Concerns\HasTiming;
use App\UI\Toasts\Contracts\ToastContract;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;

/**
 * Toast Class
 * 
 * FilamentPHP-inspired toast notification builder.
 * Provides a fluent API for creating rich toast notifications.
 */
class Toast implements ToastContract, Arrayable
{
    use HasContent;
    use HasStyling;
    use HasTiming;
    use HasActions;

    protected string $id;
    protected bool $navigatePersist = false; // Whether toast persists across wire:navigate
    protected array $data = [];

    public function __construct()
    {
        $this->id = Str::uuid()->toString();
        // Initialize defaults from config
        $this->position = config('toasts.defaults.position', 'top-right');
        $this->duration = config('toasts.defaults.duration', 4000);
        $this->type = config('toasts.defaults.type', 'info');
        $this->closable = config('toasts.defaults.closable', true);
        $this->persistent = config('toasts.defaults.persistent', false);
    }

    /**
     * Create a new toast notification.
     */
    public static function make(): static
    {
        return new static();
    }

    /**
     * Create a success toast (FilamentPHP style)
     */
    public static function success(?string $title = null): static
    {
        $toast = static::make()->success();
        
        if ($title !== null) {
            $toast->title($title);
        }
        
        return $toast;
    }

    /**
     * Create an error toast (FilamentPHP style)
     */
    public static function error(?string $title = null): static
    {
        $toast = static::make()->error();
        
        if ($title !== null) {
            $toast->title($title);
        }
        
        return $toast;
    }

    /**
     * Create a warning toast (FilamentPHP style)
     */
    public static function warning(?string $title = null): static
    {
        $toast = static::make()->warning();
        
        if ($title !== null) {
            $toast->title($title);
        }
        
        return $toast;
    }

    /**
     * Create an info toast (FilamentPHP style)
     */
    public static function info(?string $title = null): static
    {
        $toast = static::make()->info();
        
        if ($title !== null) {
            $toast->title($title);
        }
        
        return $toast;
    }

    /**
     * Make the toast persist across wire:navigate page changes (FilamentPHP-inspired)
     */
    public function persist(bool $persist = true): static
    {
        $this->navigatePersist = $persist;
        return $this;
    }

    /**
     * Check if toast persists across navigation
     */
    public function getNavigatePersist(): bool
    {
        return $this->navigatePersist;
    }

    /**
     * Add custom data to the toast
     */
    public function data(array $data): static
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    /**
     * Send the toast notification (FilamentPHP style)
     */
    public function send(): void
    {
        app(\App\UI\Toasts\ToastManager::class)->add($this);
    }

    /**
     * Get the toast ID
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get custom data
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Convert the toast to an array (FilamentPHP-inspired)
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->getTitle(),
            'body' => $this->getBody(),
            'icon' => $this->getIcon(),
            'type' => $this->getType(),
            'color' => $this->getColor(),
            'variant' => $this->getVariant(),
            'position' => $this->getPosition(),
            'duration' => $this->getDuration(),
            'persistent' => $this->isPersistent(),
            'closable' => $this->isClosable(),
            'navigatePersist' => $this->navigatePersist,
            'actions' => $this->getActionsArray(),
            'data' => $this->data,
            'classes' => $this->getClasses(),
            'styles' => $this->getStyles(),
        ];
    }
}