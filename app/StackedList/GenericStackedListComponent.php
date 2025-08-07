<?php

namespace App\StackedList;

use App\StackedList\Concerns\InteractsWithStackedList;
use Livewire\Component;
use Livewire\Attributes\Reactive;

/**
 * Generic Livewire component that can render any StackedList configuration.
 * This is how FilamentPHP does it - one generic component that gets configured at runtime.
 */
class GenericStackedListComponent extends Component
{
    use InteractsWithStackedList;

    public string $stackedListId;
    public string $modelClass;
    public array $configuration = [];

    public function mount(string $stackedListId, string $modelClass, array $configuration = [])
    {
        $this->stackedListId = $stackedListId;
        $this->modelClass = $modelClass;
        $this->configuration = $configuration;
    }

    /**
     * Configure the StackedList table dynamically.
     */
    public function stackedList(Table $table): Table
    {
        $config = $this->configuration;

        $table->model($this->modelClass);

        if (isset($config['title'])) {
            $table->title($config['title']);
        }

        if (isset($config['subtitle'])) {
            $table->subtitle($config['subtitle']);
        }

        if (isset($config['searchable'])) {
            $table->searchable($config['searchable']);
        }

        if (isset($config['columns'])) {
            $table->columns($this->buildColumns($config['columns']));
        }

        if (isset($config['bulkActions'])) {
            $table->bulkActions($this->buildBulkActions($config['bulkActions']));
        }

        if (isset($config['actions'])) {
            $table->actions($this->buildActions($config['actions']));
        }

        if (isset($config['with'])) {
            $table->with($config['with']);
        }

        if (isset($config['withCount'])) {
            $table->withCount($config['withCount']);
        }

        return $table;
    }

    protected function buildColumns(array $columnsConfig): array
    {
        $columns = [];
        foreach ($columnsConfig as $columnConfig) {
            if (is_string($columnConfig)) {
                $columns[] = \App\StackedList\Columns\Column::make($columnConfig);
            } elseif (is_array($columnConfig)) {
                $column = \App\StackedList\Columns\Column::make($columnConfig['name'] ?? '');
                
                if (isset($columnConfig['label'])) {
                    $column->label($columnConfig['label']);
                }
                
                if (isset($columnConfig['sortable']) && $columnConfig['sortable']) {
                    $column->sortable();
                }
                
                $columns[] = $column;
            }
        }
        return $columns;
    }

    protected function buildBulkActions(array $actionsConfig): array
    {
        $actions = [];
        foreach ($actionsConfig as $actionConfig) {
            if (is_string($actionConfig)) {
                $actions[] = \App\StackedList\Actions\BulkAction::make($actionConfig);
            } elseif (is_array($actionConfig)) {
                $action = \App\StackedList\Actions\BulkAction::make($actionConfig['key'] ?? '');
                
                if (isset($actionConfig['label'])) {
                    $action->label($actionConfig['label']);
                }
                
                $actions[] = $action;
            }
        }
        return $actions;
    }

    protected function buildActions(array $actionsConfig): array
    {
        $actions = [];
        foreach ($actionsConfig as $actionConfig) {
            if (is_string($actionConfig)) {
                $actions[] = \App\StackedList\Actions\Action::make($actionConfig);
            } elseif (is_array($actionConfig)) {
                $action = \App\StackedList\Actions\Action::make($actionConfig['key'] ?? '');
                
                if (isset($actionConfig['label'])) {
                    $action->label($actionConfig['label']);
                }
                
                if (isset($actionConfig['route'])) {
                    $action->route($actionConfig['route']);
                }
                
                $actions[] = $action;
            }
        }
        return $actions;
    }

    public function handleBulkAction(string $action, array $selectedIds): void
    {
        // Handle common bulk actions or dispatch events
        $this->dispatch('bulkAction', [
            'action' => $action,
            'selectedIds' => $selectedIds,
            'modelClass' => $this->modelClass
        ]);
    }

    public function render()
    {
        return view('livewire.generic-stacked-list');
    }
}