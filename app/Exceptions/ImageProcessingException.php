<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ImageProcessingException extends Exception
{
    public static function downloadFailed(string $url, string $reason): self
    {
        return new self("Failed to download image from {$url}: {$reason}");
    }

    public static function invalidImageFormat(string $url, string $mimeType): self
    {
        return new self("Invalid image format from {$url}. Expected image, got: {$mimeType}");
    }

    public static function storageFailed(string $path, string $reason): self
    {
        return new self("Failed to store image at {$path}: {$reason}");
    }

    public static function mediaLibraryFailed(string $operation, string $reason): self
    {
        return new self("Media Library {$operation} failed: {$reason}");
    }

    public static function conversionFailed(string $conversion, string $reason): self
    {
        return new self("Image conversion '{$conversion}' failed: {$reason}");
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
                'error' => 'Image Processing Error',
                'message' => $this->getMessage(),
            ], 500);
        }
        
        // For browser requests, return null to let Laravel/Flare handle it
        return null;
    }

    public function context(): array
    {
        return [
            'processing_type' => 'image_download_and_conversion',
            'timestamp' => now()->toISOString(),
        ];
    }
}
