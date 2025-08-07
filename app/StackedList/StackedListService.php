<?php

namespace App\StackedList;

use Livewire\Component;
use App\StackedList\StackedListBuilder;

class StackedListService
{
    protected Component $component;
    protected StackedListBuilder $builder;

    public function __construct(Component $component, StackedListBuilder $builder)
    {
        $this->component = $component;
        $this->builder = $builder;
    }

    /**
     * Register the StackedList with a Livewire component at runtime
     */
    public static function register(Component $component, StackedListBuilder $builder): void
    {
        $service = new static($component, $builder);
        $service->injectIntoComponent();
    }

    /**
     * Inject StackedList functionality into the Livewire component
     */
    protected function injectIntoComponent(): void
    {
        // Get the model from the builder
        $model = $this->builder->getModel();
        
        if (!$model) {
            throw new \InvalidArgumentException('StackedListBuilder must have a model set');
        }

        // Inject properties into the component
        $this->component->stackedListModel = $model;
        $this->component->stackedListBuilder = $this->builder;
        
        // Initialize default values if they don't exist
        if (!property_exists($this->component, 'stackedListSearch')) {
            $this->component->stackedListSearch = '';
        }
        if (!property_exists($this->component, 'stackedListFilters')) {
            $this->component->stackedListFilters = [];
        }
        if (!property_exists($this->component, 'stackedListSortBy')) {
            $this->component->stackedListSortBy = '';
        }
        if (!property_exists($this->component, 'stackedListSortDirection')) {
            $this->component->stackedListSortDirection = 'asc';
        }
        if (!property_exists($this->component, 'stackedListSortStack')) {
            $this->component->stackedListSortStack = [];
        }
        if (!property_exists($this->component, 'stackedListPerPage')) {
            $this->component->stackedListPerPage = 10;
        }
        if (!property_exists($this->component, 'stackedListSelectedItems')) {
            $this->component->stackedListSelectedItems = [];
        }
        if (!property_exists($this->component, 'stackedListSelectAll')) {
            $this->component->stackedListSelectAll = false;
        }

        // Inject methods into the component using PHP's magic
        $this->injectMethods();
    }

    /**
     * Inject methods into the component using closures
     */
    protected function injectMethods(): void
    {
        $builder = $this->builder;
        $component = $this->component;

        // Inject stackedListData as a computed property
        $component->stackedListData = function() use ($component, $builder) {
            $query = $component->buildStackedListQuery();
            $config = $builder->toArray();
            
            // Load relationships
            if (!empty($config['with'])) {
                $query->with($config['with']);
            }
            
            // Load counts
            if (!empty($config['withCount'])) {
                $query->withCount($config['withCount']);
            }
            
            return $query->paginate($component->stackedListPerPage);
        };

        // Inject buildStackedListQuery method
        $component->buildStackedListQuery = function() use ($component, $builder) {
            $model = $component->stackedListModel;
            $query = (new $model)->newQuery();
            
            // Apply search
            $config = $builder->toArray();
            if (!empty($component->stackedListSearch) && !empty($config['searchable'])) {
                $query->where(function ($subQuery) use ($config, $component) {
                    foreach ($config['searchable'] as $field) {
                        if (str_contains($field, '.')) {
                            // Relationship search
                            [$relation, $column] = explode('.', $field, 2);
                            $subQuery->orWhereHas($relation, function ($relationQuery) use ($column, $component) {
                                $relationQuery->where($column, 'like', "%{$component->stackedListSearch}%");
                            });
                        } else {
                            // Direct column search
                            $subQuery->orWhere($field, 'like', "%{$component->stackedListSearch}%");
                        }
                    }
                });
            }
            
            // Apply filters
            foreach ($component->stackedListFilters as $key => $value) {
                if (empty($value)) continue;
                
                $filterConfig = $config['filters'][$key] ?? null;
                if (!$filterConfig) continue;

                $column = $filterConfig['column'] ?? $key;
                
                if (isset($filterConfig['relation'])) {
                    // Relationship filter
                    $query->whereHas($filterConfig['relation'], function ($relationQuery) use ($filterConfig, $value) {
                        $relationQuery->where($filterConfig['column'], $value);
                    });
                } else {
                    // Direct column filter
                    match ($filterConfig['type'] ?? 'select') {
                        'select' => $query->where($column, $value),
                        'multiselect' => $query->whereIn($column, (array) $value),
                        default => $query->where($column, $value)
                    };
                }
            }
            
            // Apply sorting
            if (!empty($component->stackedListSortStack)) {
                // Multi-column sorting
                foreach (collect($component->stackedListSortStack)->sortBy('priority') as $sort) {
                    if (str_contains($sort['column'], '.')) {
                        [$relation, $column] = explode('.', $sort['column'], 2);
                        $query->orderBy($relation . '.' . $column, $sort['direction']);
                    } else {
                        $query->orderBy($sort['column'], $sort['direction']);
                    }
                }
            } elseif ($component->stackedListSortBy) {
                // Single column sorting
                if (str_contains($component->stackedListSortBy, '.')) {
                    [$relation, $column] = explode('.', $component->stackedListSortBy, 2);
                    $query->orderBy($relation . '.' . $column, $component->stackedListSortDirection);
                } else {
                    $query->orderBy($component->stackedListSortBy, $component->stackedListSortDirection);
                }
            }
            
            return $query;
        };
    }
}