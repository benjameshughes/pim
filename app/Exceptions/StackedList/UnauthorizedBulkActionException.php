<?php

namespace App\Exceptions\StackedList;

use Illuminate\Auth\Access\AuthorizationException;

class UnauthorizedBulkActionException extends AuthorizationException
{
    public static function forAction(string $action, string $modelClass): static
    {
        $modelName = class_basename($modelClass);
        
        return new static("You are not authorized to perform '{$action}' bulk action on {$modelName} records.");
    }
}