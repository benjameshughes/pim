<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait HasStackedListScopes
{
    /**
     * Scope for stacked list search functionality.
     */
    public function scopeStackedListSearch(Builder $query, string $search, array $searchableFields): Builder
    {
        if (empty($search) || empty($searchableFields)) {
            return $query;
        }

        return $query->where(function (Builder $subQuery) use ($search, $searchableFields) {
            foreach ($searchableFields as $field) {
                if (str_contains($field, '.')) {
                    // Relationship search
                    [$relation, $column] = explode('.', $field, 2);
                    $subQuery->orWhereHas($relation, function (Builder $relationQuery) use ($column, $search) {
                        $relationQuery->where($column, 'like', "%{$search}%");
                    });
                } else {
                    // Direct column search
                    $subQuery->orWhere($field, 'like', "%{$search}%");
                }
            }
        });
    }

    /**
     * Scope for applying stacked list filters.
     */
    public function scopeStackedListFilter(Builder $query, string $field, $value, array $filterConfig = []): Builder
    {
        if (empty($value)) {
            return $query;
        }

        if (isset($filterConfig['relation'])) {
            // Relationship filter
            return $query->whereHas($filterConfig['relation'], function (Builder $relationQuery) use ($filterConfig, $value) {
                $relationQuery->where($filterConfig['column'], $value);
            });
        }

        // Direct column filter
        return match ($filterConfig['type'] ?? 'select') {
            'select' => $query->where($field, $value),
            'multiselect' => $query->whereIn($field, (array) $value),
            'date_range' => $query->whereBetween($field, (array) $value),
            'numeric_range' => $query->whereBetween($field, (array) $value),
            default => $query->where($field, $value)
        };
    }

    /**
     * Scope for applying multiple sorting columns.
     */
    public function scopeStackedListSort(Builder $query, array $sorts): Builder
    {
        foreach ($sorts as $sort) {
            if (str_contains($sort['column'], '.')) {
                [$relation, $column] = explode('.', $sort['column'], 2);
                $query->orderBy($relation . '.' . $column, $sort['direction']);
            } else {
                $query->orderBy($sort['column'], $sort['direction']);
            }
        }

        return $query;
    }

    /**
     * Scope for status filtering (commonly used).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for inactive records.
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', 'inactive');
    }
}