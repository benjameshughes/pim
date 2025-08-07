<?php

namespace App\StackedList\Concerns;

use Livewire\Attributes\Url;

trait CanSortStackedList
{
    #[Url(except: '')]
    public string $stackedListSortBy = '';

    #[Url(except: 'asc')]
    public string $stackedListSortDirection = 'asc';

    #[Url]
    public array $stackedListSortStack = [];

    public function stackedListSortColumn(string $column, bool $multi = false): void
    {
        if ($multi) {
            $this->addToStackedListSortStack($column);
        } else {
            $this->stackedListSortStack = [];
            
            if ($this->stackedListSortBy === $column) {
                $this->stackedListSortDirection = $this->stackedListSortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                $this->stackedListSortBy = $column;
                $this->stackedListSortDirection = 'asc';
            }
        }
        
        $this->resetPage();
    }

    protected function addToStackedListSortStack(string $column): void
    {
        $existingIndex = collect($this->stackedListSortStack)->search(fn($sort) => $sort['column'] === $column);

        if ($existingIndex !== false) {
            // Toggle direction
            $this->stackedListSortStack[$existingIndex]['direction'] = 
                $this->stackedListSortStack[$existingIndex]['direction'] === 'asc' ? 'desc' : 'asc';
        } else {
            // Add new column
            $this->stackedListSortStack[] = [
                'column' => $column,
                'direction' => 'asc',
                'priority' => count($this->stackedListSortStack) + 1
            ];
        }

        // Update primary sort
        $this->stackedListSortBy = $column;
        $this->stackedListSortDirection = collect($this->stackedListSortStack)
            ->firstWhere('column', $column)['direction'] ?? 'asc';
    }

    public function clearAllStackedListSorts(): void
    {
        $this->stackedListSortStack = [];
        $this->stackedListSortBy = '';
        $this->stackedListSortDirection = 'asc';
        $this->resetPage();
    }

    public function removeStackedListSortColumn(string $column): void
    {
        $this->stackedListSortStack = collect($this->stackedListSortStack)
            ->reject(fn($sort) => $sort['column'] === $column)
            ->values()
            ->toArray();
        
        // If we removed the current primary sort, update it
        if ($this->stackedListSortBy === $column) {
            $firstSort = collect($this->stackedListSortStack)->first();
            if ($firstSort) {
                $this->stackedListSortBy = $firstSort['column'];
                $this->stackedListSortDirection = $firstSort['direction'];
            } else {
                $this->stackedListSortBy = '';
                $this->stackedListSortDirection = 'asc';
            }
        }
        
        $this->resetPage();
    }
}