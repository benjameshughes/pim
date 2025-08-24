<?php

namespace App\Livewire\Components;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * 🎯✨ FLOATING ACTION BAR - LARAVEL CLOUD STYLE ✨🎯
 *
 * Sleek bottom sliding action bar for bulk operations
 * Inspired by modern UI patterns like Laravel Cloud
 */
class FloatingActionBar extends Component
{
    // Selection state (passed from parent)
    /** @var int[] */
    public array $selectedItems = [];

    // UI state
    public bool $expanded = false;

    public bool $visible = false;

    // Action configuration
    /** @var array<string, mixed> */
    public array $bulkAction = [
        'type' => '',
        'folder' => '',
        'tags_to_add' => '',
        'tags_to_remove' => '',
    ];

    // Primary quick actions (always visible)
    /** @var array<string, array<string, string>> */
    public array $quickActions = [
        'move_folder' => ['icon' => 'folder', 'label' => 'Move', 'color' => 'blue'],
        'add_tags' => ['icon' => 'tag', 'label' => 'Tag', 'color' => 'green'],
        'delete' => ['icon' => 'trash', 'label' => 'Delete', 'color' => 'red'],
    ];

    // Additional actions (shown in menu)
    /** @var array<string, array<string, string>> */
    public array $menuActions = [
        'remove_tags' => ['icon' => 'minus', 'label' => 'Remove Tags', 'color' => 'orange'],
    ];

    /**
     * 🎯 MOUNT COMPONENT
     */
    public function mount(array $selectedItems = []): void
    {
        $this->selectedItems = $selectedItems;
        $this->updateVisibility();
    }

    /**
     * 📊 UPDATE SELECTED ITEMS
     */
    public function updateSelectedItems(array $items): void
    {
        $this->selectedItems = $items;
        $this->updateVisibility();

        // Reset action when selection changes
        if (empty($items)) {
            $this->resetAction();
        }
    }

    /**
     * 👀 UPDATE VISIBILITY
     */
    protected function updateVisibility(): void
    {
        $this->visible = ! empty($this->selectedItems);

        // Auto-collapse when nothing selected
        if (! $this->visible) {
            $this->expanded = false;
        }
    }

    /**
     * 🔄 TOGGLE EXPANDED STATE
     */
    public function toggleExpanded(): void
    {
        $this->expanded = ! $this->expanded;
    }

    /**
     * ⚡ SET QUICK ACTION
     */
    public function setQuickAction(string $action): void
    {
        $this->bulkAction['type'] = $action;

        // Instant actions execute immediately
        if (in_array($action, ['delete'])) {
            $this->executeAction();

            return;
        }

        // Auto-expand for actions that need more input
        if (in_array($action, ['move_folder', 'add_tags', 'remove_tags'])) {
            $this->expanded = true;
        } else {
            $this->expanded = false;
        }
    }

    /**
     * 🚀 EXECUTE ACTION
     */
    public function executeAction(): void
    {
        if (empty($this->selectedItems) || empty($this->bulkAction['type'])) {
            return;
        }

        // Dispatch action to parent component
        $this->dispatch('floating-action-execute', [
            'action' => $this->bulkAction,
            'items' => $this->selectedItems,
        ]);

        // Reset state
        $this->resetAction();
    }

    /**
     * 🔄 RESET ACTION
     */
    public function resetAction(): void
    {
        $this->bulkAction = [
            'type' => '',
            'folder' => '',
            'tags_to_add' => '',
            'tags_to_remove' => '',
        ];
        $this->expanded = false;
    }

    /**
     * ❌ CLEAR SELECTION
     */
    public function clearSelection(): void
    {
        $this->dispatch('floating-action-clear-selection');
        $this->selectedItems = [];
        $this->updateVisibility();
        $this->resetAction();
    }

    /**
     * 📊 GET SELECTED COUNT
     */
    #[Computed]
    public function selectedCount(): int
    {
        return count($this->selectedItems);
    }

    /**
     * 📝 GET ACTION LABEL
     */
    #[Computed]
    public function actionLabel(): string
    {
        $type = $this->bulkAction['type'];

        return match ($type) {
            'move_folder' => 'Move to Folder',
            'add_tags' => 'Add Tags',
            'remove_tags' => 'Remove Tags',
            'delete' => 'Delete Images',
            default => 'More Options'
        };
    }

    /**
     * 📋 CHECK IF MENU ACTIONS EXIST
     */
    #[Computed]
    public function hasMenuActions(): bool
    {
        return ! empty($this->menuActions);
    }

    /**
     * 🎨 RENDER COMPONENT
     */
    public function render(): View
    {
        return view('livewire.components.floating-action-bar');
    }
}
