<?php

namespace App\StackedList\Concerns;

trait Configurable
{
    protected string $title = 'Items';
    protected ?string $subtitle = null;
    protected string $searchPlaceholder = 'Search...';
    protected bool $exportEnabled = false;
    protected ?string $emptyTitle = null;
    protected ?string $emptyDescription = null;
    protected ?array $emptyAction = null;

    /**
     * Set the title of the stacked list.
     */
    public function title(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Set the subtitle of the stacked list.
     */
    public function subtitle(string $subtitle): static
    {
        $this->subtitle = $subtitle;
        return $this;
    }

    /**
     * Set the search placeholder text.
     */
    public function searchPlaceholder(string $placeholder): static
    {
        $this->searchPlaceholder = $placeholder;
        return $this;
    }

    /**
     * Enable export functionality.
     */
    public function exportable(bool $enabled = true): static
    {
        $this->exportEnabled = $enabled;
        return $this;
    }

    /**
     * Set the empty state configuration.
     */
    public function emptyState(string $title, string $description, ?array $action = null): static
    {
        $this->emptyTitle = $title;
        $this->emptyDescription = $description;
        $this->emptyAction = $action;
        return $this;
    }

    /**
     * Get the title.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Get the subtitle.
     */
    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    /**
     * Get the search placeholder.
     */
    public function getSearchPlaceholder(): string
    {
        return $this->searchPlaceholder;
    }

    /**
     * Check if export is enabled.
     */
    public function isExportEnabled(): bool
    {
        return $this->exportEnabled;
    }

    /**
     * Get empty state configuration.
     */
    public function getEmptyState(): array
    {
        return [
            'title' => $this->emptyTitle ?? 'No items found',
            'description' => $this->emptyDescription ?? 'No items to display.',
            'action' => $this->emptyAction,
        ];
    }
}