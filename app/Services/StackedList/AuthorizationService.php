<?php

namespace App\Services\StackedList;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class AuthorizationService
{
    /**
     * Check if user can perform bulk action on the model.
     */
    public function canPerformBulkAction(string $action, string $modelClass, ?int $userId = null): bool
    {
        $userId = $userId ?? auth()->id();
        
        if (!$userId) {
            return false;
        }

        // Check general bulk action permission
        $generalPermission = "bulk.{$action}";
        if (Gate::forUser($userId)->check($generalPermission)) {
            return true;
        }

        // Check model-specific permission
        $modelName = strtolower(class_basename($modelClass));
        $modelPermission = "{$modelName}.bulk.{$action}";
        
        return Gate::forUser($userId)->check($modelPermission);
    }

    /**
     * Check if user can view the stacked list data.
     */
    public function canViewStackedList(string $modelClass, ?int $userId = null): bool
    {
        $userId = $userId ?? auth()->id();
        
        if (!$userId) {
            return false;
        }

        $modelName = strtolower(class_basename($modelClass));
        
        return Gate::forUser($userId)->check("viewAny", $modelClass) ||
               Gate::forUser($userId)->check("{$modelName}.viewAny");
    }

    /**
     * Check if user can export data.
     */
    public function canExportData(string $modelClass, ?int $userId = null): bool
    {
        $userId = $userId ?? auth()->id();
        
        if (!$userId) {
            return false;
        }

        $modelName = strtolower(class_basename($modelClass));
        
        return Gate::forUser($userId)->check('export') ||
               Gate::forUser($userId)->check("{$modelName}.export");
    }

    /**
     * Filter models that user can access (for row-level security).
     */
    public function applyRowLevelSecurity($query, string $modelClass, ?int $userId = null)
    {
        $userId = $userId ?? auth()->id();
        
        if (!$userId) {
            return $query->whereRaw('1 = 0'); // No access
        }

        // Apply model-specific row-level security if method exists
        $modelName = strtolower(class_basename($modelClass));
        $methodName = "apply" . ucfirst($modelName) . "RowSecurity";
        
        if (method_exists($this, $methodName)) {
            return $this->$methodName($query, $userId);
        }

        // Default: no additional filtering
        return $query;
    }

    /**
     * Example of model-specific row-level security.
     */
    protected function applyProductRowSecurity($query, int $userId)
    {
        // Example: Users can only see products they created or are assigned to
        if (!auth()->user()->hasRole('admin')) {
            $query->where(function($q) use ($userId) {
                $q->where('created_by', $userId)
                  ->orWhereHas('assignedUsers', function($subQ) use ($userId) {
                      $subQ->where('user_id', $userId);
                  });
            });
        }
        
        return $query;
    }
}