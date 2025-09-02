<?php

namespace App\Actions\Images;

use App\Actions\Base\BaseAction;
use App\Actions\Images\CreateImageRecordAction;
use App\Actions\Images\UploadImageToStorageAction;
use App\Jobs\ProcessImageJob;
use App\Models\Image;
use Illuminate\Support\Facades\Log;

/**
 * 📤 UPLOAD IMAGES ACTION
 *
 * Handles uploads using Actions then dispatches ProcessImageJob
 * Back to simple working pattern
 */
class UploadImagesAction extends BaseAction
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function performAction(...$params): array
    {
        $files = $params[0] ?? [];
        $metadata = $params[1] ?? [];

        if (empty($files)) {
            throw new \InvalidArgumentException('Files array is required');
        }

        // Validate files are uploaded files
        foreach ($files as $file) {
            if (!$file instanceof \Illuminate\Http\UploadedFile) {
                throw new \InvalidArgumentException('All files must be UploadedFile instances');
            }
        }

        // Process metadata - ensure tags are array format
        $processedMetadata = [
            'title' => $metadata['title'] ?? null,
            'alt_text' => $metadata['alt_text'] ?? null,
            'description' => $metadata['description'] ?? null,
            'folder' => $metadata['folder'] ?? null,
            'tags' => $this->processTags($metadata['tags'] ?? []),
        ];

        // Process each file using Actions then dispatch jobs  
        $uploadedImages = [];
        
        foreach ($files as $file) {
            try {
                // Step 1: Upload to storage using Action
                $uploadAction = new UploadImageToStorageAction();
                $uploadResult = $uploadAction->execute($file);
                
                if (!$uploadResult['success']) {
                    Log::error('Storage upload failed', [
                        'filename' => $file->getClientOriginalName(),
                        'error' => $uploadResult['message']
                    ]);
                    continue;
                }
                
                // Step 2: Create image record using Action  
                $createRecordAction = new CreateImageRecordAction();
                $recordResult = $createRecordAction->execute(
                    $uploadResult['data'], // storage data
                    $file->getClientOriginalName(),
                    $file->getMimeType(),
                    $processedMetadata
                );
                
                if (!$recordResult['success']) {
                    Log::error('Image record creation failed', [
                        'filename' => $file->getClientOriginalName(),
                        'error' => $recordResult['message']
                    ]);
                    continue;
                }
                
                $image = $recordResult['data']['image'];
                $uploadedImages[] = $image;
                
                // Step 3: Dispatch ProcessImageJob only
                // Variant generation will be chained after processing completes
                try {
                    ProcessImageJob::dispatch($image);
                    Log::info('📤 Image uploaded and processing queued', [
                        'image_id' => $image->id,
                        'filename' => $image->filename,
                    ]);
                } catch (\Exception $e) {
                    Log::error('❌ Failed to dispatch ProcessImageJob', [
                        'image_id' => $image->id,
                        'filename' => $image->filename,
                        'error' => $e->getMessage(),
                    ]);
                }
                
            } catch (\Exception $e) {
                Log::error('Image upload failed', [
                    'filename' => $file->getClientOriginalName(),
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }

        return $this->success('Images uploaded successfully', [
            'uploaded_images' => $uploadedImages,
            'upload_count' => count($uploadedImages),
            'metadata_applied' => $processedMetadata,
        ]);
    }

    /**
     * Process tags into proper array format
     */
    protected function processTags(mixed $tags): array
    {
        if (is_string($tags)) {
            return array_filter(array_map('trim', explode(',', $tags)));
        }

        if (is_array($tags)) {
            return array_filter($tags);
        }

        return [];
    }
}