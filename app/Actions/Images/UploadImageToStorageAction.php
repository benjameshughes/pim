<?php

namespace App\Actions\Images;

use App\Actions\Base\BaseAction;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ☁️ UPLOAD IMAGE TO STORAGE ACTION
 *
 * Single responsibility: Upload image file to R2 cloud storage
 * Returns storage path and public URL for further processing
 */
class UploadImageToStorageAction extends BaseAction
{
    protected string $disk = 'images'; // R2 disk

    protected function performAction(...$params): array
    {
        $file = $params[0] ?? null;
        $customFilename = $params[1] ?? null;

        if (!$file instanceof UploadedFile && !$file instanceof File) {
            throw new \InvalidArgumentException('File must be an UploadedFile or File instance');
        }

        // Generate unique filename if not provided
        if ($customFilename) {
            $filename = $customFilename;
        } else {
            $extension = $file instanceof UploadedFile 
                ? $file->getClientOriginalExtension()
                : pathinfo($file->getFilename(), PATHINFO_EXTENSION);
            $filename = Str::uuid() . '.' . $extension;
        }

        // Upload to R2 storage
        try {
            $path = Storage::disk($this->disk)->putFileAs('', $file, $filename);
            
            if (!$path) {
                throw new \RuntimeException('Failed to upload file to storage');
            }

            $url = Storage::disk($this->disk)->url($path);

            // Verify upload was successful
            if (!Storage::disk($this->disk)->exists($filename)) {
                throw new \RuntimeException('File upload verification failed');
            }

            $size = Storage::disk($this->disk)->size($filename);

        } catch (\Exception $e) {
            throw new \RuntimeException('Storage upload failed: ' . $e->getMessage());
        }

        return $this->success('File uploaded to storage successfully', [
            'filename' => $filename,
            'path' => $path,
            'url' => $url,
            'size' => $size,
            'disk' => $this->disk,
        ]);
    }
}