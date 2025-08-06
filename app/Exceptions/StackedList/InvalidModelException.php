<?php

namespace App\Exceptions\StackedList;

use InvalidArgumentException;

class InvalidModelException extends InvalidArgumentException
{
    public static function modelNotFound(string $modelClass): static
    {
        return new static("Model class '{$modelClass}' does not exist or is not accessible.");
    }

    public static function invalidEloquentModel(string $modelClass): static
    {
        return new static("Class '{$modelClass}' must extend Illuminate\Database\Eloquent\Model.");
    }
}