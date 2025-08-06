<?php

namespace App\StackedList;

use App\StackedList\Actions\Action;
use App\StackedList\Actions\BulkAction;
use App\StackedList\Columns\BadgeColumn;
use App\StackedList\Columns\TextColumn;
use App\Exceptions\StackedList\SchemaIntrospectionException;
use App\Exceptions\StackedList\InvalidModelException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DefaultStackedList extends StackedList
{
    protected string $modelClass;
    protected array $hiddenColumns = [
        'id', 'password', 'remember_token', 'email_verified_at', 
        'created_at', 'updated_at', 'deleted_at'
    ];
    protected array $badgeColumns = [
        'status', 'is_active', 'is_featured', 'is_published', 
        'active', 'featured', 'published', 'enabled'
    ];
    protected array $searchableTypes = [
        'string', 'text', 'varchar'
    ];

    public function __construct(string $modelClass)
    {
        if (!class_exists($modelClass)) {
            throw InvalidModelException::modelNotFound($modelClass);
        }

        if (!is_subclass_of($modelClass, Model::class)) {
            throw InvalidModelException::invalidEloquentModel($modelClass);
        }

        $this->modelClass = $modelClass;
    }

    /**
     * Create a new default stacked list for the given model.
     */
    public static function makeFor(string $modelClass): static
    {
        return new static($modelClass);
    }

    /**
     * Set columns to hide from auto-generation.
     */
    public function hideColumns(array $columns): static
    {
        $this->hiddenColumns = array_merge($this->hiddenColumns, $columns);
        return $this;
    }

    /**
     * Set additional columns to treat as badges.
     */
    public function badgeColumns(array $columns): static
    {
        $this->badgeColumns = array_merge($this->badgeColumns, $columns);
        return $this;
    }

    /**
     * Configure the default stacked list by auto-generating from the model.
     */
    public function configure(): static
    {
        $model = new $this->modelClass;
        $tableName = $model->getTable();
        $modelName = class_basename($this->modelClass);

        return $this
            ->title(Str::plural($modelName))
            ->subtitle("Manage your {$this->makeReadable(Str::plural($modelName))}")
            ->searchPlaceholder("Search {$this->makeReadable(Str::plural($modelName))}...")
            ->exportable()
            ->emptyState(
                "No {$this->makeReadable(Str::plural($modelName))} found",
                "Get started by creating your first {$this->makeReadable($modelName)}."
            )
            ->columns($this->generateColumns($tableName, $model))
            ->bulkActions($this->generateBulkActions());
    }

    /**
     * Auto-generate columns from the model's database table.
     */
    protected function generateColumns(string $tableName, Model $model): array
    {
        $columns = [];
        
        $tableColumns = cache()->remember(
            "stacked_list_schema_{$tableName}",
            now()->addHour(),
            function () use ($tableName, $model) {
                try {
                    return Schema::getColumns($tableName);
                } catch (\Exception $e) {
                    report(SchemaIntrospectionException::failedForTable($tableName, $e));
                    return $this->getFallbackSchemaColumns($model);
                }
            }
        );

        foreach ($tableColumns as $column) {
            $columnName = $column['name'];

            // Skip hidden columns
            if (in_array($columnName, $this->hiddenColumns)) {
                continue;
            }

            // Determine column type and create appropriate column
            if (in_array($columnName, $this->badgeColumns)) {
                $columns[] = $this->createBadgeColumn($columnName, $column);
            } elseif ($this->isDateColumn($columnName)) {
                $columns[] = $this->createDateColumn($columnName, $column);
            } elseif ($this->isRelationshipColumn($columnName)) {
                // Skip foreign key columns as they'll be handled by relationships
                continue;
            } else {
                $columns[] = $this->createTextColumn($columnName, $column);
            }

            // Limit to reasonable number of auto-generated columns
            if (count($columns) >= 6) {
                break;
            }
        }

        // Add relationship columns
        $relationshipColumns = $this->generateRelationshipColumns($model);
        $columns = array_merge($columns, array_slice($relationshipColumns, 0, 2));

        // Add actions column if we have space
        if (count($columns) <= 7) {
            $columns[] = $this->createActionsColumn();
        }

        return $columns;
    }

    /**
     * Create a text column for the given database column.
     */
    protected function createTextColumn(string $columnName, array $columnInfo): TextColumn
    {
        $column = TextColumn::make($columnName)
            ->label($this->makeReadable($columnName));

        // Make searchable if it's a text-based column
        if (in_array(strtolower($columnInfo['type_name'] ?? ''), $this->searchableTypes)) {
            $column->searchable();
        }

        // Make sortable for most column types
        if (!in_array(strtolower($columnInfo['type_name'] ?? ''), ['text', 'json'])) {
            $column->sortable();
        }

        // Special formatting for certain column types
        if (Str::contains($columnName, ['sku', 'code', 'reference'])) {
            $column->monospace();
        }

        if (Str::contains($columnName, ['name', 'title'])) {
            $column->medium();
        }

        return $column;
    }

    /**
     * Create a badge column for status-like fields.
     */
    protected function createBadgeColumn(string $columnName, array $columnInfo): BadgeColumn
    {
        $column = BadgeColumn::make($columnName)
            ->label($this->makeReadable($columnName))
            ->sortable();

        // Auto-configure common badge types
        if (in_array($columnName, ['status'])) {
            $column->status();
        } elseif (in_array($columnName, ['is_active', 'active', 'enabled'])) {
            $column->boolean('Active', 'Inactive');
        } elseif (in_array($columnName, ['is_featured', 'featured'])) {
            $column->boolean('Featured', 'Not Featured');
        } elseif (in_array($columnName, ['is_published', 'published'])) {
            $column->boolean('Published', 'Draft');
        }

        return $column;
    }

    /**
     * Create a formatted date column.
     */
    protected function createDateColumn(string $columnName, array $columnInfo): TextColumn
    {
        return TextColumn::make($columnName)
            ->label($this->makeReadable($columnName))
            ->sortable();
    }

    /**
     * Generate relationship columns if the model has obvious relationships.
     */
    protected function generateRelationshipColumns(Model $model): array
    {
        $columns = [];
        $reflection = new \ReflectionClass($model);

        // Look for common relationship methods
        $commonRelations = ['user', 'author', 'category', 'product', 'parent'];
        
        foreach ($commonRelations as $relation) {
            if ($reflection->hasMethod($relation)) {
                $method = $reflection->getMethod($relation);
                
                // Only include if it looks like a relationship method
                if ($method->isPublic() && $method->getNumberOfParameters() === 0) {
                    $columns[] = TextColumn::make("{$relation}.name")
                        ->label($this->makeReadable($relation))
                        ->sortable(false);
                }
            }
        }

        return $columns;
    }

    /**
     * Create a default actions column.
     */
    protected function createActionsColumn(): \App\StackedList\Columns\ActionsColumn
    {
        $modelName = strtolower(class_basename($this->modelClass));
        
        return \App\StackedList\Columns\ActionsColumn::make('actions')
            ->label('Actions')
            ->actions([
                Action::make('view')
                    ->label('View')
                    ->icon('eye')
                    ->route("{$modelName}s.show"),

                Action::make('edit')
                    ->label('Edit')
                    ->icon('pencil')
                    ->route("{$modelName}s.edit")
            ]);
    }

    /**
     * Generate default bulk actions.
     */
    protected function generateBulkActions(): array
    {
        $actions = [];

        // Add activate/deactivate if model likely has status
        if (in_array('status', Schema::getColumnListing((new $this->modelClass)->getTable()))) {
            $actions[] = BulkAction::activate();
            $actions[] = BulkAction::deactivate();
        }

        // Always add export and delete
        $actions[] = BulkAction::export();
        $actions[] = BulkAction::delete();

        return $actions;
    }

    /**
     * Check if a column name represents a date field.
     */
    protected function isDateColumn(string $columnName): bool
    {
        return Str::endsWith($columnName, ['_at', '_date']) || 
               in_array($columnName, ['date', 'datetime', 'timestamp']);
    }

    /**
     * Check if a column name represents a foreign key relationship.
     */
    protected function isRelationshipColumn(string $columnName): bool
    {
        return Str::endsWith($columnName, '_id') && $columnName !== 'id';
    }

    /**
     * Convert a snake_case or camelCase string to a readable label.
     */
    protected function makeReadable(string $string): string
    {
        return Str::title(str_replace(['_', '-'], ' ', Str::snake($string)));
    }

    /**
     * Fallback schema columns when introspection fails.
     */
    protected function getFallbackSchemaColumns(Model $model): array
    {
        return [
            ['name' => 'id', 'type_name' => 'bigint'],
            ['name' => 'name', 'type_name' => 'varchar'],
            ['name' => 'created_at', 'type_name' => 'timestamp'],
        ];
    }

    /**
     * Fallback columns when schema introspection fails.
     */
    protected function getFallbackColumns(Model $model): array
    {
        return [
            TextColumn::make('id')
                ->label('ID')
                ->sortable(),
            TextColumn::make('name')
                ->label('Name')
                ->searchable()
                ->medium()
                ->sortable(),
            $this->createActionsColumn()
        ];
    }
}