<?php

namespace App\UI\Components;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class Tab implements Htmlable
{
    protected string $key;

    protected string $label;

    protected ?string $icon = null;

    protected ?string $route = null;

    /** @var Collection<string, mixed> */
    protected Collection $routeParameters;

    protected ?string $url = null;

    protected string|int|Closure|null $badge = null;

    protected bool|Closure $hidden = false;

    protected bool|Closure $disabled = false;

    protected ?string $badgeColor = null;

    /** @var Collection<string, mixed> */
    protected Collection $extraAttributes;

    protected ?Closure $clickHandler = null;

    protected ?bool $wireNavigate = null;

    protected function __construct(string $key)
    {
        $this->key = $key;
        $this->label = $key; // Default label to key
        $this->routeParameters = collect();
        $this->extraAttributes = collect();
    }

    public static function make(string $key): self
    {
        return new self($key);
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function icon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * @param array<string, mixed>|Collection<string, mixed> $parameters
     */
    public function route(string $route, array|Collection $parameters = []): static
    {
        $this->route = $route;
        $this->routeParameters = collect($parameters);

        return $this;
    }

    public function url(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function badge(string|int|Closure|null $badge, ?string $color = null): static
    {
        $this->badge = $badge;
        $this->badgeColor = $color;

        return $this;
    }

    public function hidden(bool|Closure $condition = true): static
    {
        $this->hidden = $condition;

        return $this;
    }

    public function disabled(bool|Closure $condition = true): static
    {
        $this->disabled = $condition;

        return $this;
    }

    public function extraAttributes(array|Collection $attributes): static
    {
        $this->extraAttributes = $this->extraAttributes->merge($attributes);

        return $this;
    }

    public function onClick(Closure $handler): static
    {
        $this->clickHandler = $handler;

        return $this;
    }

    public function wireNavigate(bool $enabled = true): static
    {
        $this->wireNavigate = $enabled;

        return $this;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getRoute(): ?string
    {
        return $this->route;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRouteParameters(): array
    {
        return $this->routeParameters->toArray();
    }

    /**
     * @return Collection<string, mixed>
     */
    public function getRouteParametersCollection(): Collection
    {
        return $this->routeParameters;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getBadge(): string|int|null
    {
        if ($this->badge instanceof Closure) {
            return ($this->badge)();
        }

        return $this->badge;
    }

    public function getBadgeColor(): ?string
    {
        return $this->badgeColor;
    }

    public function isHidden(): bool
    {
        if ($this->hidden instanceof Closure) {
            return ($this->hidden)();
        }

        return $this->hidden;
    }

    public function isDisabled(): bool
    {
        if ($this->disabled instanceof Closure) {
            return ($this->disabled)();
        }

        return $this->disabled;
    }

    public function getExtraAttributes(): array
    {
        return $this->extraAttributes->toArray();
    }

    public function getExtraAttributesCollection(): Collection
    {
        return $this->extraAttributes;
    }

    public function hasClickHandler(): bool
    {
        return $this->clickHandler !== null;
    }

    public function executeClickHandler(): mixed
    {
        if ($this->clickHandler) {
            return ($this->clickHandler)();
        }

        return null;
    }

    public function shouldWireNavigate(): ?bool
    {
        return $this->wireNavigate;
    }

    /**
     * Convert the tab to an array representation for use in navigation
     */
    public function toArray(): array
    {
        if ($this->isHidden()) {
            return [];
        }

        $data = collect([
            'key' => $this->key,
            'label' => $this->label,
            'icon' => $this->icon,
            'disabled' => $this->isDisabled(),
            'extraAttributes' => $this->extraAttributes->toArray(),
        ]);

        // Handle badge
        $badge = $this->getBadge();
        if ($badge !== null && $badge !== '') {
            $data->put('badge', $badge);
            if ($this->badgeColor) {
                $data->put('badgeColor', $this->badgeColor);
            }
        }

        // Handle URL/route
        if ($this->url) {
            $data->put('url', $this->url);
        } elseif ($this->route) {
            $data->put('route', $this->route);
            $data->put('url', route($this->route, $this->routeParameters->toArray()));
        }

        // Handle click handler
        if ($this->hasClickHandler()) {
            $data->put('hasClickHandler', true);
        }

        // Handle wire:navigate only if explicitly set
        if ($this->wireNavigate !== null) {
            $data->put('wireNavigate', $this->shouldWireNavigate());
        }

        return $data->toArray();
    }

    /**
     * Convert the tab to a Collection representation for advanced manipulation
     */
    public function toCollection(): Collection
    {
        return collect($this->toArray());
    }

    /**
     * Determine if this tab is currently active
     */
    public function isActive(string $currentRoute, array $currentParameters = []): bool
    {
        if (! $this->route) {
            return false;
        }

        // Simple route name match
        if ($currentRoute === $this->route) {
            return true;
        }

        // Check if current route starts with this tab's route
        return str_starts_with($currentRoute, $this->route.'.');
    }

    public function toHtml(): string
    {
        return new HtmlString('');
    }

    public function __toString(): string
    {
        return $this->toHtml();
    }
}
