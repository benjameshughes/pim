<?php

namespace App\Exceptions\StackedList;

use Exception;

class SchemaIntrospectionException extends Exception
{
    public static function failedForTable(string $tableName, Exception $previous): static
    {
        return new static(
            "Failed to introspect schema for table '{$tableName}'. Falling back to basic configuration.",
            0,
            $previous
        );
    }
}