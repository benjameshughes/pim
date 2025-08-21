<?php

namespace App\Services\Marketplace\API\Repositories;

use App\Services\Marketplace\API\AbstractMarketplaceService;
use Illuminate\Support\Collection;

/**
 * 🗂️ ABSTRACT MARKETPLACE REPOSITORY
 *
 * Base repository class for marketplace data operations.
 * Implements the Repository Pattern for consistent data access across marketplaces.
 * Provides common CRUD operations and query building functionality.
 */
abstract class AbstractMarketplaceRepository
{
    protected AbstractMarketplaceService $service;

    protected array $queryFilters = [];

    protected array $includes = [];

    protected int $limit = 50;

    protected int $offset = 0;

    protected string $sortBy = 'created_at';

    protected string $sortDirection = 'desc';

    public function __construct(AbstractMarketplaceService $service)
    {
        $this->service = $service;
    }

    /**
     * 🔍 Find a single record by ID
     */
    abstract public function find(string $id): ?array;

    /**
     * 📋 Get multiple records with optional filtering
     */
    abstract public function all(array $filters = []): Collection;

    /**
     * ➕ Create a new record
     */
    abstract public function create(array $data): array;

    /**
     * 📝 Update an existing record
     */
    abstract public function update(string $id, array $data): array;

    /**
     * 🗑️ Delete a record
     */
    abstract public function delete(string $id): bool;

    /**
     * 🚀 Bulk create multiple records
     */
    abstract public function bulkCreate(array $records): array;

    /**
     * 🔄 Bulk update multiple records
     */
    abstract public function bulkUpdate(array $records): array;

    /**
     * 🔍 Build query with filters
     */
    public function where(string $field, $value, string $operator = '='): static
    {
        $this->queryFilters[] = [
            'field' => $field,
            'value' => $value,
            'operator' => $operator,
        ];

        return $this;
    }

    /**
     * 📋 Add relationships to include in results
     */
    public function with(array $includes): static
    {
        $this->includes = array_merge($this->includes, $includes);

        return $this;
    }

    /**
     * 🔢 Set result limit
     */
    public function limit(int $limit): static
    {
        $this->limit = max(1, min(500, $limit));

        return $this;
    }

    /**
     * ⏭️ Set result offset for pagination
     */
    public function offset(int $offset): static
    {
        $this->offset = max(0, $offset);

        return $this;
    }

    /**
     * 📊 Set sorting options
     */
    public function orderBy(string $field, string $direction = 'desc'): static
    {
        $this->sortBy = $field;
        $this->sortDirection = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        return $this;
    }

    /**
     * 🔍 Execute query and get results
     */
    public function get(): Collection
    {
        $filters = $this->buildQueryFilters();

        return $this->all($filters);
    }

    /**
     * 🎯 Get first result matching query
     */
    public function first(): ?array
    {
        $originalLimit = $this->limit;
        $this->limit = 1;

        $results = $this->get();

        $this->limit = $originalLimit; // Restore original limit

        return $results->first();
    }

    /**
     * 📊 Count records matching query
     */
    public function count(): int
    {
        $filters = $this->buildQueryFilters();

        return $this->all($filters)->count();
    }

    /**
     * ✅ Check if any records exist matching query
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * 📄 Paginate results
     */
    public function paginate(int $page = 1, int $perPage = 50): array
    {
        $this->limit = $perPage;
        $this->offset = ($page - 1) * $perPage;

        $results = $this->get();
        $total = $this->count();

        return [
            'data' => $results,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => ceil($total / $perPage),
                'has_more' => ($page * $perPage) < $total,
            ],
        ];
    }

    /**
     * 🏗️ Build query filters from configured options
     */
    protected function buildQueryFilters(): array
    {
        $filters = [];

        // Add field filters
        foreach ($this->queryFilters as $filter) {
            $key = $filter['field'];
            if ($filter['operator'] === '=') {
                $filters[$key] = $filter['value'];
            } else {
                $filters[$key] = [
                    'value' => $filter['value'],
                    'operator' => $filter['operator'],
                ];
            }
        }

        // Add pagination
        $filters['limit'] = $this->limit;
        $filters['offset'] = $this->offset;

        // Add sorting
        $filters['sort_by'] = $this->sortBy;
        $filters['sort_direction'] = $this->sortDirection;

        // Add includes
        if (! empty($this->includes)) {
            $filters['include'] = $this->includes;
        }

        return $filters;
    }

    /**
     * 🔄 Reset query builder to defaults
     */
    public function reset(): static
    {
        $this->queryFilters = [];
        $this->includes = [];
        $this->limit = 50;
        $this->offset = 0;
        $this->sortBy = 'created_at';
        $this->sortDirection = 'desc';

        return $this;
    }

    /**
     * 📊 Get query builder summary
     */
    public function getQuerySummary(): array
    {
        return [
            'filters_count' => count($this->queryFilters),
            'includes' => $this->includes,
            'limit' => $this->limit,
            'offset' => $this->offset,
            'sort_by' => $this->sortBy,
            'sort_direction' => $this->sortDirection,
            'built_filters' => $this->buildQueryFilters(),
        ];
    }

    /**
     * 🔧 Get the underlying service instance
     */
    public function getService(): AbstractMarketplaceService
    {
        return $this->service;
    }

    /**
     * ✅ Validate data before operations
     */
    protected function validateData(array $data, array $requiredFields = []): array
    {
        $errors = [];

        foreach ($requiredFields as $field) {
            if (! isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Field '{$field}' is required";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * 🛡️ Sanitize data for marketplace API
     */
    protected function sanitizeData(array $data): array
    {
        // Remove null values
        $data = array_filter($data, fn ($value) => $value !== null);

        // Trim string values
        array_walk_recursive($data, function (&$value) {
            if (is_string($value)) {
                $value = trim($value);
            }
        });

        return $data;
    }
}
