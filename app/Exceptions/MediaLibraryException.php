<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MediaLibraryException extends Exception
{
    public static function migrationFailed(string $path, string $reason): self
    {
        return new self("Media migration failed for {$path}: {$reason}");
    }

    public static function fileNotFound(string $path): self
    {
        return new self("Media file not found: {$path}");
    }

    public static function conversionFailed(string $conversion, string $mediaId, string $reason): self
    {
        return new self("Media conversion '{$conversion}' failed for media {$mediaId}: {$reason}");
    }

    public static function collectionNotFound(string $collection): self
    {
        return new self("Media collection '{$collection}' not found or not configured");
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
                'error' => 'Media Library Error',
                'message' => $this->getMessage(),
            ], 500);
        }
        
        // For browser requests, return null to let Laravel/Flare handle it
        return null;
    }

    public function context(): array
    {
        return [
            'media_operation' => 'library_management',
            'timestamp' => now()->toISOString(),
        ];
    }
}
