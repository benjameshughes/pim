<?php

namespace App\StackedList\Concerns;

use App\StackedList\DefaultStackedList;
use App\StackedList\StackedList;

trait HasAutoStackedList
{
    /**
     * The model class to use for auto-generation.
     */
    protected string $autoStackedListModel;

    /**
     * Columns to hide from auto-generation.
     */
    protected array $hideColumns = [];

    /**
     * Additional columns to treat as badges.
     */
    protected array $badgeColumns = [];

    /**
     * Custom title override.
     */
    protected ?string $customTitle = null;

    /**
     * Custom subtitle override.
     */
    protected ?string $customSubtitle = null;

    /**
     * Get the auto-configured stacked list.
     */
    public function getAutoStackedList(): StackedList
    {
        if (!isset($this->autoStackedListModel)) {
            throw new \InvalidArgumentException('autoStackedListModel property must be defined');
        }

        $list = DefaultStackedList::makeFor($this->autoStackedListModel)
            ->hideColumns($this->hideColumns)
            ->badgeColumns($this->badgeColumns);

        if ($this->customTitle) {
            $list->title($this->customTitle);
        }

        if ($this->customSubtitle) {
            $list->subtitle($this->customSubtitle);
        }

        return $list->configure();
    }

    /**
     * Get the stacked list configuration array.
     */
    public function getAutoStackedListConfig(): array
    {
        return $this->getAutoStackedList()->toArray();
    }

    /**
     * Set columns to hide from auto-generation.
     */
    protected function hideColumns(array $columns): static
    {
        $this->hideColumns = array_merge($this->hideColumns, $columns);
        return $this;
    }

    /**
     * Set additional columns to treat as badges.
     */
    protected function badgeColumns(array $columns): static
    {
        $this->badgeColumns = array_merge($this->badgeColumns, $columns);
        return $this;
    }

    /**
     * Set a custom title for the list.
     */
    protected function listTitle(string $title): static
    {
        $this->customTitle = $title;
        return $this;
    }

    /**
     * Set a custom subtitle for the list.
     */
    protected function listSubtitle(string $subtitle): static
    {
        $this->customSubtitle = $subtitle;
        return $this;
    }
}