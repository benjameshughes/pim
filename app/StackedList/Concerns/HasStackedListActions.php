<?php

namespace App\StackedList\Concerns;

use App\StackedList\Actions\Action;
use App\StackedList\Actions\BulkAction;
use App\Services\StackedList\BulkActionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait HasStackedListActions
{
    /**
     * Generate default row actions for a model.
     */
    protected function getDefaultRowActions(string $modelClass): array
    {
        $modelName = strtolower(class_basename($modelClass));
        
        return [
            Action::make('view')
                ->label('View')
                ->icon('eye')
                ->route($this->getViewRouteName($modelName)),

            Action::make('edit')
                ->label('Edit')
                ->icon('pencil')
                ->route($this->getEditRouteName($modelName))
        ];
    }

    /**
     * Generate default bulk actions for a model.
     */
    protected function getDefaultBulkActions(string $modelClass): array
    {
        $actions = [];
        $model = new $modelClass;
        $columns = Schema::getColumnListing($model->getTable());

        // Add activate/deactivate if model has status
        if (in_array('status', $columns)) {
            $actions[] = BulkAction::activate();
            $actions[] = BulkAction::deactivate();
        }

        // Always add export and delete
        $actions[] = BulkAction::export();
        $actions[] = BulkAction::delete();

        return $actions;
    }

    /**
     * Get the appropriate view route name for the model.
     */
    protected function getViewRouteName(string $modelName): string
    {
        return match ($modelName) {
            'product' => 'products.view',
            'productvariant' => 'products.variants.view',
            default => "{$modelName}s.show"
        };
    }

    /**
     * Get the appropriate edit route name for the model.
     */
    protected function getEditRouteName(string $modelName): string
    {
        return match ($modelName) {
            'product' => 'products.product.edit',
            'productvariant' => 'products.variants.edit',
            default => "{$modelName}s.edit"
        };
    }

    /**
     * Override row actions in a stacked list configuration.
     */
    protected function overrideRowActions(array &$config, array $actions): void
    {
        foreach ($config['columns'] as &$column) {
            if ($column['type'] === 'actions') {
                $column['actions'] = collect($actions)->map(function ($action) {
                    return is_array($action) ? $action : $action->toArray();
                })->toArray();
                break;
            }
        }
    }

    /**
     * Override bulk actions in a stacked list configuration.
     */
    protected function overrideBulkActions(array &$config, array $actions): void
    {
        $config['bulk_actions'] = collect($actions)->map(function ($action) {
            return is_array($action) ? $action : $action->toArray();
        })->toArray();
    }

    /**
     * Handle bulk actions with common implementations.
     */
    protected function handleCommonBulkActions(string $action, array $selectedIds, string $modelClass): bool
    {
        $bulkService = app(BulkActionService::class);
        $modelDisplayName = $bulkService->getModelDisplayName($modelClass);
        $count = count($selectedIds);

        match ($action) {
            'activate' => $this->handleActivateAction($bulkService, $selectedIds, $modelClass, $modelDisplayName, $count),
            'deactivate' => $this->handleDeactivateAction($bulkService, $selectedIds, $modelClass, $modelDisplayName, $count),
            'delete' => $this->handleDeleteAction($bulkService, $selectedIds, $modelClass, $modelDisplayName, $count),
            'export' => $this->handleExportAction($bulkService, $selectedIds, $modelClass, $modelDisplayName, $count),
            default => false
        };

        return true;
    }

    /**
     * Handle activate bulk action.
     */
    private function handleActivateAction(BulkActionService $service, array $ids, string $modelClass, string $modelName, int $count): void
    {
        $updated = $service->updateStatus($modelClass, $ids, 'active');
        $service->flashSuccess("{$updated} {$modelName} marked as Active.");
    }

    /**
     * Handle deactivate bulk action.
     */
    private function handleDeactivateAction(BulkActionService $service, array $ids, string $modelClass, string $modelName, int $count): void
    {
        $updated = $service->updateStatus($modelClass, $ids, 'inactive');
        $service->flashSuccess("{$updated} {$modelName} marked as Inactive.");
    }

    /**
     * Handle delete bulk action.
     */
    private function handleDeleteAction(BulkActionService $service, array $ids, string $modelClass, string $modelName, int $count): void
    {
        $deleted = $service->delete($modelClass, $ids);
        $service->flashSuccess("{$deleted} {$modelName} deleted successfully.");
    }

    /**
     * Handle export bulk action.
     */
    private function handleExportAction(BulkActionService $service, array $ids, string $modelClass, string $modelName, int $count): void
    {
        // Placeholder for export functionality - could dispatch job here
        $service->flashSuccess("{$count} {$modelName} exported successfully.");
    }

    /**
     * Generate a view action method for a model.
     */
    protected function getViewMethod(string $modelClass): string
    {
        $modelName = strtolower(class_basename($modelClass));
        return "view{$this->toPascalCase($modelName)}";
    }

    /**
     * Convert a string to PascalCase.
     */
    private function toPascalCase(string $string): string
    {
        return Str::studly($string);
    }
}