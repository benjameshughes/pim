<?php

namespace App\UI\Toasts;

use App\UI\Toasts\Contracts\ToastContract;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Component;

/**
 * ToastManager Class
 * 
 * FilamentPHP-inspired toast management system.
 * Works exactly like the Table class with Htmlable interface.
 * Enables {{ $this->toasts }} magic in Blade templates.
 */
class ToastManager implements Htmlable
{
    protected SessionManager $session;
    protected string $sessionKey;
    protected int $maxToasts;
    protected ?Component $livewire = null;

    public function __construct(SessionManager $session)
    {
        $this->session = $session;
        $this->sessionKey = config('toasts.session_key', 'toasts');
        $this->maxToasts = config('toasts.max_toasts', 5);
    }
    
    /**
     * Set the Livewire component instance (like Table class)
     */
    public function setComponent(?Component $livewire): static
    {
        $this->livewire = $livewire;
        return $this;
    }

    /**
     * Add a toast to the current session.
     */
    public function add(ToastContract $toast): static
    {
        $toasts = $this->getToasts();

        // Add the new toast
        $toasts->push($toast);

        // Limit the number of toasts
        if ($toasts->count() > $this->maxToasts) {
            $toasts = $toasts->slice(-$this->maxToasts);
        }

        // Store back in session
        $this->storeToasts($toasts);

        return $this;
    }

    /**
     * Get all toasts from the session.
     */
    public function getToasts(): Collection
    {
        $toasts = $this->session->get($this->sessionKey, []);

        return collect($toasts)->map(function ($toastData) {
            // If it's already a Toast instance, return it
            if ($toastData instanceof ToastContract) {
                return $toastData;
            }

            // Otherwise, reconstruct from array data
            return $this->reconstructToast($toastData);
        });
    }

    /**
     * Alias for getToasts() - returns all toasts as flat collection.
     */
    public function all(): Collection
    {
        return $this->getToasts();
    }

    /**
     * Get toasts grouped by position.
     */
    public function getToastsByPosition(): Collection
    {
        return $this->getToasts()->groupBy(function (ToastContract $toast) {
            return $toast->getPosition();
        });
    }

    /**
     * Clear all toasts from the session.
     */
    public function clear(): static
    {
        $this->session->forget($this->sessionKey);

        return $this;
    }

    /**
     * Remove a specific toast by ID.
     */
    public function remove(string $toastId): static
    {
        $toasts = $this->getToasts()->filter(function (ToastContract $toast) use ($toastId) {
            return $toast->getId() !== $toastId;
        });

        $this->storeToasts($toasts);

        return $this;
    }

    /**
     * Flash all current toasts for the next request.
     */
    public function flash(): static
    {
        $toasts = $this->getToasts();
        
        $this->session->flash($this->sessionKey, $toasts->toArray());

        return $this;
    }

    /**
     * Get and clear all toasts (useful for displaying them).
     */
    public function flush(): Collection
    {
        $toasts = $this->getToasts();
        $this->clear();

        return $toasts;
    }

    /**
     * Store toasts in the session.
     */
    protected function storeToasts(Collection $toasts): void
    {
        $this->session->put($this->sessionKey, $toasts->map(function (ToastContract $toast) {
            return $toast->toArray();
        })->toArray());
    }

    /**
     * Reconstruct a toast from array data.
     */
    protected function reconstructToast(array $data): ToastContract
    {
        $toast = new Toast();
        
        // Set properties directly to preserve the original ID
        $reflection = new \ReflectionClass($toast);
        
        if (isset($data['id'])) {
            $idProperty = $reflection->getProperty('id');
            $idProperty->setAccessible(true);
            $idProperty->setValue($toast, $data['id']);
        }
        
        // Set basic properties
        if (isset($data['title'])) {
            $toast->title($data['title']);
        }
        
        if (isset($data['body'])) {
            $toast->body($data['body']);
        }
        
        $toast->type($data['type'] ?? 'info')
            ->position($data['position'] ?? config('toasts.defaults.position'))
            ->closable($data['closable'] ?? true)
            ->persistent($data['persistent'] ?? false)
            ->duration($data['duration'] ?? config('toasts.defaults.duration'));
        
        // Handle navigation persistence
        if (isset($data['navigatePersist'])) {
            $toast->persist($data['navigatePersist']);
        }

        if (!empty($data['icon'])) {
            $toast->icon($data['icon']);
        }

        if (!empty($data['data'])) {
            $toast->data($data['data']);
        }

        // Reconstruct actions if they exist
        if (!empty($data['actions'])) {
            foreach ($data['actions'] as $actionData) {
                // Handle case where actionData is already a ToastAction object
                if ($actionData instanceof ToastAction) {
                    $toast->action($actionData);
                    continue;
                }
                
                // Handle case where actionData is an array
                if (!is_array($actionData) || empty($actionData['label'])) {
                    continue;
                }

                $action = ToastAction::make($actionData['label']);

                if (!empty($actionData['url'])) {
                    $action->url($actionData['url']);
                }

                if (!empty($actionData['icon'])) {
                    $action->icon($actionData['icon']);
                }

                if (!empty($actionData['classes'])) {
                    $action->class($actionData['classes']);
                }

                $action->shouldCloseToast($actionData['should_close_toast'] ?? true);

                $toast->action($action);
            }
        }

        return $toast;
    }

    /**
     * Create a fluent API for quick toast creation.
     */
    public function success(string $title, ?string $body = null): Toast
    {
        return Toast::success()->title($title)->body($body);
    }

    /**
     * Create a fluent API for quick error toast creation.
     */
    public function error(string $title, ?string $body = null): Toast
    {
        return Toast::error()->title($title)->body($body);
    }

    /**
     * Create a fluent API for quick warning toast creation.
     */
    public function warning(string $title, ?string $body = null): Toast
    {
        return Toast::warning()->title($title)->body($body);
    }

    /**
     * Create a fluent API for quick info toast creation.
     */
    public function info(string $title, ?string $body = null): Toast
    {
        return Toast::info()->title($title)->body($body);
    }
    
    /**
     * Find a toast by ID
     */
    public function find(string $toastId): ?ToastContract
    {
        return $this->getToasts()->first(function (ToastContract $toast) use ($toastId) {
            return $toast->getId() === $toastId;
        });
    }
    
    /**
     * Convert toasts to array format for rendering
     */
    public function toArray(): array
    {
        $toasts = $this->getToasts();
        
        return [
            'toasts' => $toasts->map(fn($toast) => $toast->toArray())->toArray(),
            'toastsByPosition' => $this->getToastsByPosition()->map(function ($positionToasts, $position) {
                return [
                    'position' => $position,
                    'toasts' => $positionToasts->map(fn($toast) => $toast->toArray())->toArray(),
                ];
            })->toArray(),
            'hasToasts' => $toasts->isNotEmpty(),
            'positions' => $toasts->pluck('position')->unique()->values()->toArray(),
        ];
    }
    
    /**
     * Convert to HTML (implements Htmlable interface)
     * This enables {{ $this->toasts }} to work in Blade templates
     * Exactly like the Table class
     */
    public function toHtml(): string
    {
        return view('components.toast-container')->with([
            'toastManager' => $this,
            'toasts' => $this->getToasts(),
            'config' => $this->toArray(),
        ])->render();
    }
    
    /**
     * Convert to string (enables string casting)
     */
    public function __toString(): string
    {
        return $this->toHtml();
    }
}