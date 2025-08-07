<?php

namespace App\StackedList\Concerns;

use Livewire\Attributes\Url;

trait CanSearchStackedList
{
    #[Url(except: '')]
    public string $stackedListSearch = '';

    public function updatedStackedListSearch(): void
    {
        $this->resetPage();
    }
}