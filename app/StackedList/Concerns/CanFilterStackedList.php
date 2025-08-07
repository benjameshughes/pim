<?php

namespace App\StackedList\Concerns;

use Livewire\Attributes\Url;

trait CanFilterStackedList
{
    #[Url]
    public array $stackedListFilters = [];

    #[Url(except: 10)]
    public int $stackedListPerPage = 10;

    public function updatedStackedListFilters(): void
    {
        $this->resetPage();
    }

    public function clearStackedListFilters(): void
    {
        $this->stackedListSearch = '';
        $this->stackedListFilters = [];
        $this->resetPage();
    }
}