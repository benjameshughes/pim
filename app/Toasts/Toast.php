<?php

namespace App\Toasts;

use App\Toasts\Concerns\HasActions;
use App\Toasts\Concerns\HasIcon;
use App\Toasts\Concerns\HasStyling;
use App\Toasts\Concerns\HasTiming;
use App\Toasts\Contracts\ToastContract;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;

class Toast implements ToastContract, Arrayable
{
    use HasActions;
    use HasIcon;
    use HasStyling;
    use HasTiming;

    protected string $id;
    public string $title = '';
    public ?string $body = null;
    public string $type = 'info';
    public string $position;
    public bool $closable = true;
    public bool $persistent = false;
    public array $data = [];

    public function __construct()
    {
        $this->id = Str::uuid()->toString();
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
     * Create a success toast notification.
     */
    public static function success(?string $title = null, ?string $body = null): static
    {
        $toast = static::make()->type('success');
        
        if ($title !== null) {
            $toast->title($title);
        }
        
        if ($body !== null) {
            $toast->body($body);
        }
        
        return $toast;
    }

    /**
     * Create an error toast notification.
     */
    public static function error(?string $title = null, ?string $body = null): static
    {
        $toast = static::make()->type('error');
        
        if ($title !== null) {
            $toast->title($title);
        }
        
        if ($body !== null) {
            $toast->body($body);
        }
        
        return $toast;
    }

    /**
     * Create a warning toast notification.
     */
    public static function warning(?string $title = null, ?string $body = null): static
    {
        $toast = static::make()->type('warning');
        
        if ($title !== null) {
            $toast->title($title);
        }
        
        if ($body !== null) {
            $toast->body($body);
        }
        
        return $toast;
    }

    /**
     * Create an info toast notification.
     */
    public static function info(?string $title = null, ?string $body = null): static
    {
        $toast = static::make()->type('info');
        
        if ($title !== null) {
            $toast->title($title);
        }
        
        if ($body !== null) {
            $toast->body($body);
        }
        
        return $toast;
    }

    /**
     * Set the toast title.
     */
    public function title(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Set the toast body content.
     */
    public function body(?string $body): static
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Set the toast type.
     */
    public function type(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Set the toast position.
     */
    public function position(string $position): static
    {
        $this->position = $position;

        return $this;
    }

    /**
     * Make the toast closable.
     */
    public function closable(bool $closable = true): static
    {
        $this->closable = $closable;

        return $this;
    }

    /**
     * Make the toast persistent (won't auto-dismiss).
     */
    public function persistent(bool $persistent = true): static
    {
        $this->persistent = $persistent;

        return $this;
    }

    /**
     * Add custom data to the toast.
     */
    public function data(array $data): static
    {
        $this->data = array_merge($this->data, $data);

        return $this;
    }

    /**
     * Send the toast notification.
     */
    public function send(): static
    {
        app(\App\Toasts\ToastManager::class)->add($this);

        return $this;
    }

    /**
     * Get the toast ID.
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get the toast title.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Get the toast body.
     */
    public function getBody(): ?string
    {
        return $this->body;
    }

    /**
     * Get the toast type.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the toast position.
     */
    public function getPosition(): string
    {
        return $this->position;
    }

    /**
     * Check if the toast is closable.
     */
    public function isClosable(): bool
    {
        return $this->closable;
    }

    /**
     * Check if the toast is persistent.
     */
    public function isPersistent(): bool
    {
        return $this->persistent;
    }

    /**
     * Get custom data.
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get the toast styling configuration.
     */
    public function getTypeConfig(): array
    {
        return config("toasts.types.{$this->type}", config('toasts.types.info'));
    }

    /**
     * Get the position configuration.
     */
    public function getPositionConfig(): array
    {
        return config("toasts.positions.{$this->position}", config('toasts.positions.top-right'));
    }

    /**
     * Convert the toast to an array.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title ?? '',
            'body' => $this->body,
            'type' => $this->type,
            'position' => $this->position,
            'closable' => $this->closable,
            'persistent' => $this->persistent,
            'duration' => $this->duration ?? config('toasts.defaults.duration', 4000),
            'icon' => $this->icon ?? null,
            'actions' => array_map(fn($action) => $action->toArray(), $this->actions ?? []),
            'data' => $this->data ?? [],
            'type_config' => $this->getTypeConfig(),
            'position_config' => $this->getPositionConfig(),
        ];
    }
}