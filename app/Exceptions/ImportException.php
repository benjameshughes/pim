<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ImportException extends Exception
{
    public static function csvParsingFailed(string $message, ?int $row = null): self
    {
        $errorMessage = $row ? "CSV parsing failed at row {$row}: {$message}" : "CSV parsing failed: {$message}";
        return new self($errorMessage);
    }

    public static function columnMappingFailed(string $column): self
    {
        return new self("Failed to map column: {$column}");
    }

    public static function variantCreationFailed(string $sku, string $reason): self
    {
        return new self("Failed to create variant {$sku}: {$reason}");
    }

    public static function constraintViolation(string $constraint, string $details): self
    {
        return new self("Constraint violation ({$constraint}): {$details}");
    }

    public function report(): bool
    {
        return true;
    }

    public function render(Request $request): Response|JsonResponse|null
    {
        // Only return JSON for API requests or AJAX requests
        if ($request->expectsJson() || $request->is('api/*') || $request->ajax()) {
            return response()->json([
                'error' => 'Import Error',
                'message' => $this->getMessage(),
            ], 422);
        }
        
        // For browser requests, return null to let Laravel/Flare handle it
        return null;
    }

    public function context(): array
    {
        return [
            'import_type' => 'product_variant',
            'timestamp' => now()->toISOString(),
        ];
    }
}
