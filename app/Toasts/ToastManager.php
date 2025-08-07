<?php

namespace App\Toasts;

use App\Toasts\Contracts\ToastContract;
use App\Toasts\ToastAction;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Collection;

class ToastManager
{
    protected SessionManager $session;
    protected string $sessionKey;
    protected int $maxToasts;

    public function __construct(SessionManager $session)
    {
        $this->session = $session;
        $this->sessionKey = config('toasts.session_key', 'toasts');
        $this->maxToasts = config('toasts.max_toasts', 5);
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
}