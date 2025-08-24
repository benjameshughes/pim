<?php

namespace App\Actions\Images;

use App\Models\Image;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * 🔄 UPDATE IMAGE ACTION
 *
 * Handles image metadata updates with proper validation,
 * transaction safety, and error handling
 */
class UpdateImageAction
{
    /**
     * Execute image update with validation and transaction safety
     *
     * @param array<string, mixed> $data
     * @throws ValidationException When validation fails
     * @throws \Exception When update fails
     */
    public function execute(Image $image, array $data): Image
    {
        // Validate the input data
        $validator = Validator::make($data, [
            'title' => 'nullable|string|max:255',
            'alt_text' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'folder' => 'required|string|max:255',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validatedData = $validator->validated();

        DB::beginTransaction();

        try {
            // Log the update attempt
            Log::info('Attempting to update image', [
                'image_id' => $image->id,
                'filename' => $image->filename,
                'user_id' => auth()->id(),
                'changes' => array_keys($validatedData),
            ]);

            // Track what actually changed
            $originalData = $image->only(array_keys($validatedData));
            
            // Update the image
            $image->update($validatedData);

            // Log the changes
            $changes = [];
            foreach ($validatedData as $key => $value) {
                if ($originalData[$key] !== $value) {
                    $changes[$key] = [
                        'from' => $originalData[$key],
                        'to' => $value
                    ];
                }
            }

            DB::commit();

            // Log successful update
            Log::info('Image updated successfully', [
                'image_id' => $image->id,
                'filename' => $image->filename,
                'user_id' => auth()->id(),
                'changes' => $changes,
            ]);

            return $image->fresh();

        } catch (\Exception $e) {
            DB::rollBack();

            // Log the error
            Log::error('Failed to update image', [
                'image_id' => $image->id,
                'filename' => $image->filename,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            throw new \Exception(
                "Failed to update image '{$image->display_title}': {$e->getMessage()}"
            );
        }
    }
}