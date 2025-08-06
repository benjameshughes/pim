<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BarcodeException extends Exception
{
    public static function poolExhausted(string $barcodeType): self
    {
        return new self("Barcode pool exhausted for type: {$barcodeType}");
    }

    public static function duplicateBarcode(string $barcode, int $variantId): self
    {
        return new self("Duplicate barcode {$barcode} already assigned to variant {$variantId}");
    }

    public static function invalidBarcodeFormat(string $barcode, string $expectedFormat): self
    {
        return new self("Invalid barcode format '{$barcode}'. Expected: {$expectedFormat}");
    }

    public static function assignmentFailed(string $barcode, int $variantId, string $reason): self
    {
        return new self("Failed to assign barcode {$barcode} to variant {$variantId}: {$reason}");
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
                'error' => 'Barcode Error',
                'message' => $this->getMessage(),
            ], 409);
        }
        
        // For browser requests, return null to let Laravel/Flare handle it
        return null;
    }

    public function context(): array
    {
        return [
            'barcode_operation' => 'assignment_or_validation',
            'timestamp' => now()->toISOString(),
        ];
    }
}
