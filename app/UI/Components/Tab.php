<?php

namespace App\UI\Components;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class Tab implements Htmlable
{
    protected string $key;
    protected string $label;
    protected ?string $icon = null;
    protected ?string $route = null;
    protected array $routeParameters = [];
    protected ?string $url = null;
    protected string|int|Closure|null $badge = null;
    protected bool|Closure $hidden = false;
    protected bool|Closure $disabled = false;
    protected ?string $badgeColor = null;
    protected array $extraAttributes = [];
    protected ?Closure $clickHandler = null;
    protected ?bool $wireNavigate = null;

    protected function __construct(string $key)
    {
        $this->key = $key;
        $this->label = $key; // Default label to key
    }

    public static function make(string $key): static
    {
        return new static($key);
    }

    public function label(string $label): static
    {
        $this->label = $label;
        return $this;
    }

    public function icon(string $icon): static
    {
        $this->icon = $icon;
        return $this;
    }

    public function route(string $route, array $parameters = []): static
    {
        $this->route = $route;
        $this->routeParameters = $parameters;
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

    public function extraAttributes(array $attributes): static
    {
        $this->extraAttributes = array_merge($this->extraAttributes, $attributes);
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

    public function getRouteParameters(): array
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

        $data = [
            'key' => $this->key,
            'label' => $this->label,
            'icon' => $this->icon,
            'disabled' => $this->isDisabled(),
            'extraAttributes' => $this->extraAttributes,
        ];

        // Handle badge
        $badge = $this->getBadge();
        if ($badge !== null && $badge !== '') {
            $data['badge'] = $badge;
            if ($this->badgeColor) {
                $data['badgeColor'] = $this->badgeColor;
            }
        }

        // Handle URL/route
        if ($this->url) {
            $data['url'] = $this->url;
        } elseif ($this->route) {
            $data['route'] = $this->route;
            $data['url'] = route($this->route, $this->routeParameters);
        }

        // Handle click handler
        if ($this->hasClickHandler()) {
            $data['hasClickHandler'] = true;
        }

        // Handle wire:navigate only if explicitly set
        if ($this->wireNavigate !== null) {
            $data['wireNavigate'] = $this->shouldWireNavigate();
        }

        return $data;
    }

    /**
     * Determine if this tab is currently active
     */
    public function isActive(string $currentRoute, array $currentParameters = []): bool
    {
        if (!$this->route) {
            return false;
        }

        // Simple route name match
        if ($currentRoute === $this->route) {
            return true;
        }

        // Check if current route starts with this tab's route
        return str_starts_with($currentRoute, $this->route . '.');
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