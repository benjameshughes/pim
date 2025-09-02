<?php

namespace App\Actions\Images;

use App\Actions\Base\BaseAction;
use Illuminate\Http\UploadedFile;

/**
 * ✅ VALIDATE IMAGE FILE ACTION
 *
 * Single responsibility: Validate uploaded image files
 * Ensures file meets all requirements before processing
 */
class ValidateImageFileAction extends BaseAction
{
    protected int $maxFileSize = 10 * 1024 * 1024; // 10MB

    protected array $allowedMimeTypes = [
        'image/jpeg',
        'image/jpg', 
        'image/png',
        'image/webp',
        'image/gif',
    ];

    protected function performAction(...$params): array
    {
        $file = $params[0] ?? null;

        if (!$file instanceof UploadedFile) {
            throw new \InvalidArgumentException('File must be an UploadedFile instance');
        }

        // Check if file upload is valid
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('Invalid file upload');
        }

        // Check file size
        if ($file->getSize() > $this->maxFileSize) {
            throw new \InvalidArgumentException('File too large. Maximum size is ' . ($this->maxFileSize / 1024 / 1024) . 'MB');
        }

        // Check MIME type
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, $this->allowedMimeTypes)) {
            throw new \InvalidArgumentException('Invalid file type. Only images are allowed: ' . implode(', ', $this->allowedMimeTypes));
        }

        // Check file extension matches MIME type
        $extension = strtolower($file->getClientOriginalExtension());
        $expectedExtensions = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/webp' => ['webp'],
            'image/gif' => ['gif'],
        ];

        $validExtensions = $expectedExtensions[$mimeType] ?? [];
        if (!in_array($extension, $validExtensions)) {
            throw new \InvalidArgumentException('File extension does not match MIME type');
        }

        return $this->success('File validation passed', [
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $mimeType,
            'size' => $file->getSize(),
            'extension' => $extension,
        ]);
    }
}