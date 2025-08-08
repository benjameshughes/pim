<?php

namespace App\Media\Actions;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Single Responsibility: Handle assignment of images to products/variants
 */
class ImageAssignmentAction
{
    public function execute(array $params): ImageAssignmentResult
    {
        try {
            DB::beginTransaction();

            $images = $this->getFilteredImages($params['filters'] ?? []);
            $target = $this->resolveTargetModel($params['modelType'], $params['model']);
            
            $assigned = collect();
            $failed = collect();

            foreach ($images as $image) {
                try {
                    $this->assignImageToModel($image, $target, $params['modelType']);
                    $assigned->push($image);
                } catch (\Exception $e) {
                    $failed->push([
                        'image' => $image,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            DB::commit();

            Log::info('Bulk image assignment completed', [
                'assigned' => $assigned->count(),
                'failed' => $failed->count(),
                'target_type' => $params['modelType'],
                'target_id' => $target?->id,
            ]);

            return new ImageAssignmentResult($assigned, $failed);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    protected function getFilteredImages(array $filters): Collection
    {
        $query = ProductImage::query();

        foreach ($filters as $key => $filter) {
            if ($key === 'selected' && is_array($filter)) {
                // Handle selected images filter
                $query->whereIn('id', $filter);
            } else {
                match ($filter) {
                    'unassigned' => $query->whereNull('product_id')->whereNull('variant_id'),
                    'failed' => $query->where('processing_status', ProductImage::PROCESSING_FAILED),
                    'pending' => $query->where('processing_status', ProductImage::PROCESSING_PENDING),
                    'completed' => $query->where('processing_status', ProductImage::PROCESSING_COMPLETED),
                    'product' => $query->whereNotNull('product_id')->whereNull('variant_id'),
                    'variant' => $query->whereNotNull('variant_id')->whereNull('product_id'),
                    default => null
                };
            }
        }

        return $query->get();
    }

    protected function resolveTargetModel(string $modelType, Model|int $model): ?Model
    {
        if ($model instanceof Model) {
            return $model;
        }

        return match ($modelType) {
            'product' => Product::findOrFail($model),
            'variant' => ProductVariant::findOrFail($model),
            default => throw new \InvalidArgumentException("Invalid model type: {$modelType}")
        };
    }

    protected function assignImageToModel(ProductImage $image, ?Model $target, string $modelType): void
    {
        if (!$target) {
            throw new \Exception('Target model not found');
        }

        // Only assign unassigned images
        if ($image->product_id || $image->variant_id) {
            throw new \Exception('Image already assigned');
        }

        $updateData = ['sort_order' => $this->getNextSortOrder($target, $image->image_type)];

        if ($modelType === 'product') {
            $updateData['product_id'] = $target->id;
        } elseif ($modelType === 'variant') {
            $updateData['variant_id'] = $target->id;
        }

        $image->update($updateData);

        Log::info('Image assigned to model', [
            'image_id' => $image->id,
            'model_type' => $modelType,
            'model_id' => $target->id,
        ]);
    }

    protected function getNextSortOrder(Model $model, string $imageType): int
    {
        $query = ProductImage::where('image_type', $imageType);

        if ($model instanceof Product) {
            $query->where('product_id', $model->id);
        } elseif ($model instanceof ProductVariant) {
            $query->where('variant_id', $model->id);
        }

        return ($query->max('sort_order') ?? 0) + 1;
    }
}

/**
 * Value Object for assignment results
 */
class ImageAssignmentResult
{
    public function __construct(
        public readonly Collection $assigned,
        public readonly Collection $failed
    ) {}

    public function hasFailures(): bool
    {
        return $this->failed->isNotEmpty();
    }

    public function getAssignedCount(): int
    {
        return $this->assigned->count();
    }

    public function getFailedCount(): int
    {
        return $this->failed->count();
    }

    public function getMessage(): string
    {
        $assigned = $this->getAssignedCount();
        $failed = $this->getFailedCount();

        if ($failed === 0) {
            return "Successfully assigned {$assigned} images";
        }

        return "Assigned {$assigned} images, {$failed} failed";
    }
}

/**
 * Value Object for bulk operation results
 */
class BulkOperationResult
{
    public function __construct(
        public readonly array $results
    ) {}

    public function hasFailures(): bool
    {
        return collect($this->results)->some(fn($result) => $result->hasFailures());
    }

    public function getTotalOperations(): int
    {
        return count($this->results);
    }

    public function getSuccessfulOperations(): int
    {
        return collect($this->results)->filter(fn($result) => !$result->hasFailures())->count();
    }

    public function getMessage(): string
    {
        $total = $this->getTotalOperations();
        $successful = $this->getSuccessfulOperations();
        $failed = $total - $successful;

        if ($failed === 0) {
            return "All {$total} bulk operations completed successfully";
        }

        return "{$successful}/{$total} bulk operations completed successfully";
    }
}