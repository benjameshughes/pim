<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImageUploadRequest;
use App\Models\ProductImage;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Jobs\ProcessImageToR2;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImageUploadController extends Controller
{
    /**
     * Handle image upload via API/AJAX
     */
    public function upload(ImageUploadRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            
            $uploadedImages = [];
            $model = $this->resolveModel($request->model_type, $request->model_id);
            
            foreach ($request->file('files') as $file) {
                // Store temporarily
                $path = $file->store('product-images/temp', 'public');
                
                // Create ProductImage record
                $productImage = ProductImage::create([
                    'product_id' => $model instanceof Product ? $model->id : null,
                    'variant_id' => $model instanceof ProductVariant ? $model->id : null,
                    'image_path' => $path,
                    'original_filename' => $file->getClientOriginalName(),
                    'image_type' => $request->image_type,
                    'alt_text' => $request->alt_text,
                    'sort_order' => $this->getNextSortOrder($model, $request->image_type),
                    'processing_status' => ProductImage::PROCESSING_PENDING,
                    'storage_disk' => 'public',
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'metadata' => [
                        'uploaded_via' => 'api',
                        'uploaded_at' => now()->toISOString(),
                        'user_id' => auth()->id(),
                    ]
                ]);
                
                // Queue for processing
                if ($request->process_immediately) {
                    ProcessImageToR2::dispatch($productImage);
                }
                
                $uploadedImages[] = [
                    'id' => $productImage->id,
                    'filename' => $productImage->original_filename,
                    'size' => $productImage->file_size,
                    'type' => $productImage->image_type,
                    'status' => $productImage->processing_status,
                    'url' => $productImage->url,
                ];
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Images uploaded successfully',
                'data' => [
                    'uploaded_count' => count($uploadedImages),
                    'images' => $uploadedImages,
                    'queued_for_processing' => $request->process_immediately ? count($uploadedImages) : 0
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an image
     */
    public function delete(int $imageId): JsonResponse
    {
        try {
            $image = ProductImage::findOrFail($imageId);
            $image->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Image deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete image: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update image sort order
     */
    public function reorder(ImageUploadRequest $request): JsonResponse
    {
        try {
            $orderedIds = $request->input('ordered_ids', []);
            
            foreach ($orderedIds as $index => $imageId) {
                ProductImage::where('id', $imageId)->update(['sort_order' => $index + 1]);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Images reordered successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder images: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get images for a model
     */
    public function index(string $modelType, int $modelId, string $imageType = null): JsonResponse
    {
        try {
            $model = $this->resolveModel($modelType, $modelId);
            $query = ProductImage::query();
            
            if ($model instanceof Product) {
                $query->forProduct($model->id);
            } elseif ($model instanceof ProductVariant) {
                $query->forVariant($model->id);
            }
            
            if ($imageType) {
                $query->byType($imageType);
            }
            
            $images = $query->ordered()->get()->map(function ($image) {
                return [
                    'id' => $image->id,
                    'filename' => $image->original_filename,
                    'type' => $image->image_type,
                    'size' => $image->file_size,
                    'status' => $image->processing_status,
                    'sort_order' => $image->sort_order,
                    'url' => $image->url,
                    'variants' => $image->variants,
                    'created_at' => $image->created_at->toISOString(),
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => [
                    'images' => $images,
                    'total' => $images->count(),
                    'model_type' => $modelType,
                    'model_id' => $modelId,
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch images: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resolve model from type and ID
     */
    private function resolveModel(?string $modelType, ?int $modelId)
    {
        if (!$modelType || !$modelId) {
            return null;
        }
        
        return match ($modelType) {
            'product' => Product::findOrFail($modelId),
            'variant' => ProductVariant::findOrFail($modelId),
            default => throw new \InvalidArgumentException("Invalid model type: {$modelType}")
        };
    }

    /**
     * Get next sort order for images
     */
    private function getNextSortOrder($model, string $imageType): int
    {
        if (!$model) {
            return 1;
        }
        
        $query = ProductImage::where('image_type', $imageType);
        
        if ($model instanceof Product) {
            $query->where('product_id', $model->id);
        } elseif ($model instanceof ProductVariant) {
            $query->where('variant_id', $model->id);
        }
        
        return ($query->max('sort_order') ?? 0) + 1;
    }
}