<?php

namespace App\StackedList\Concerns;

trait CanSelectStackedList
{
    public array $stackedListSelectedItems = [];
    public bool $stackedListSelectAll = false;

    public function updatedStackedListSelectedItems(): void
    {
        // Clean the array
        $this->stackedListSelectedItems = array_values(array_unique(array_filter($this->stackedListSelectedItems)));
        
        // Update header checkbox based on array
        $pageIds = $this->stackedListData->pluck('id')->toArray();
        
        if (empty($pageIds)) {
            $this->stackedListSelectAll = false;
            return;
        }
        
        // Check if ALL page items are selected
        $pageIds = array_map('strval', $pageIds);
        $selectedIds = array_map('strval', $this->stackedListSelectedItems);
        
        $allSelected = true;
        foreach ($pageIds as $id) {
            if (!in_array($id, $selectedIds)) {
                $allSelected = false;
                break;
            }
        }
        
        $this->stackedListSelectAll = $allSelected;
    }
    
    public function updatedStackedListSelectAll(): void
    {
        $pageIds = $this->stackedListData->pluck('id')->toArray();
        
        if (empty($pageIds)) {
            return;
        }
        
        if ($this->stackedListSelectAll) {
            // Add all page items to selection
            $this->stackedListSelectedItems = array_values(array_unique(array_merge($this->stackedListSelectedItems, $pageIds)));
        } else {
            // Remove all page items from selection
            $this->stackedListSelectedItems = array_values(array_diff($this->stackedListSelectedItems, $pageIds));
        }
    }

    public function clearStackedListSelection(): void
    {
        $this->stackedListSelectedItems = [];
        $this->stackedListSelectAll = false;
    }
}