<?php

namespace App\Media;

use App\Media\Actions\ImageAssignmentAction;
use App\Media\Actions\ImageProcessingAction;
use App\Media\Actions\ImageStorageAction;
use App\Media\Actions\ImageUploadAction;
use App\Media\Actions\ImageUploadResult;
use App\Media\Actions\ImageProcessingResult;
use App\Media\Actions\ImageStorageResult;
use App\Media\Actions\BulkOperationResult;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

/**
 * ImageManager - Fluent API for image operations
 * 
 * Examples:
 * ImageManager::for('product', $product)->type('main')->upload($files)->process()->store();
 * ImageManager::bulk()->filter(['unassigned'])->assignTo('product', $product)->execute();
 * ImageManager::gallery()->forModel($product)->type('main')->render();
 */
class ImageManager
{
    protected ?string $modelType = null;
    protected ?Model $model = null;
    protected ?int $modelId = null;
    protected string $imageType = 'main';
    protected bool $multiple = true;
    protected int $maxSize = 10240; // KB
    protected array $acceptTypes = ['jpg', 'jpeg', 'png', 'webp'];
    protected bool $createThumbnails = true;
    protected bool $processImmediately = true;
    protected string $storageDisk = 'images';
    protected array $filters = [];
    protected ?Collection $images = null;
    protected array $operations = [];

    public function __construct()
    {
        // Initialize with sensible defaults
    }

    /**
     * Create new ImageManager instance
     */
    public static function make(): static
    {
        return new static();
    }

    /**
     * Set target model for image operations
     */
    public static function for(string $modelType, Model|int $model): static
    {
        $instance = new static();
        $instance->modelType = $modelType;
        
        if ($model instanceof Model) {
            $instance->model = $model;
            $instance->modelId = $model->id;
        } else {
            $instance->modelId = $model;
        }

        return $instance;
    }

    /**
     * Start bulk operations
     */
    public static function bulk(): static
    {
        return new static();
    }

    /**
     * Start gallery/display operations
     */
    public static function gallery(): static
    {
        $instance = new static();
        $instance->operations[] = 'gallery';
        return $instance;
    }

    /**
     * Set image type
     */
    public function type(string $type): static
    {
        $this->imageType = $type;
        return $this;
    }

    /**
     * Allow multiple file uploads
     */
    public function allowMultiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;
        return $this;
    }

    /**
     * Set maximum file size in KB
     */
    public function maxSize(string|int $size): static
    {
        if (is_string($size)) {
            // Parse sizes like "10MB", "5GB"
            $this->maxSize = $this->parseSizeString($size);
        } else {
            $this->maxSize = $size;
        }
        return $this;
    }

    /**
     * Set accepted file types
     */
    public function acceptTypes(array $types): static
    {
        $this->acceptTypes = $types;
        return $this;
    }

    /**
     * Enable/disable thumbnail creation
     */
    public function createThumbnails(bool $create = true): static
    {
        $this->createThumbnails = $create;
        return $this;
    }

    /**
     * Set storage disk
     */
    public function uploadTo(string $disk): static
    {
        $this->storageDisk = $disk;
        return $this;
    }

    /**
     * Process images immediately vs queue
     */
    public function processImmediately(bool $immediately = true): static
    {
        $this->processImmediately = $immediately;
        return $this;
    }

    /**
     * Set model for operations
     */
    public function forModel(Model $model): static
    {
        $this->model = $model;
        $this->modelId = $model->id;
        $this->modelType = $this->getModelType($model);
        return $this;
    }

    /**
     * Add filters for bulk operations
     */
    public function filter(array|string $filters): static
    {
        $this->filters = is_array($filters) ? $filters : [$filters];
        return $this;
    }

    /**
     * Upload files
     */
    public function upload(array|UploadedFile $files): ImageUploadResult
    {
        return app(ImageUploadAction::class)->execute(
            files: is_array($files) ? $files : [$files],
            config: $this->getConfig()
        );
    }

    /**
     * Process uploaded images
     */
    public function process(?Collection $images = null): ImageProcessingResult
    {
        return app(ImageProcessingAction::class)->execute(
            images: $images ?? $this->images,
            config: $this->getConfig()
        );
    }

    /**
     * Store images to final location
     */
    public function store(?Collection $images = null): ImageStorageResult
    {
        return app(ImageStorageAction::class)->execute(
            images: $images ?? $this->images,
            config: $this->getConfig()
        );
    }

    /**
     * Assign images to model
     */
    public function assignTo(string $modelType, Model|int $model): static
    {
        $this->operations[] = [
            'type' => 'assign',
            'modelType' => $modelType,
            'model' => $model,
        ];
        return $this;
    }

    /**
     * Execute bulk operations
     */
    public function execute(): BulkOperationResult
    {
        $results = [];
        
        foreach ($this->operations as $operation) {
            switch ($operation['type']) {
                case 'assign':
                    $results[] = app(ImageAssignmentAction::class)->execute([
                        'filters' => $this->filters,
                        'modelType' => $operation['modelType'],
                        'model' => $operation['model'],
                    ]);
                    break;
            }
        }

        return new BulkOperationResult($results);
    }

    /**
     * Render Livewire component
     */
    public function render(): string
    {
        $componentName = $this->determineComponent();
        
        return view("media.components.{$componentName}", [
            'config' => $this->getConfig(),
            'model' => $this->model,
            'modelType' => $this->modelType,
            'imageType' => $this->imageType,
        ])->render();
    }

    /**
     * Get configuration array for actions
     */
    protected function getConfig(): array
    {
        return [
            'modelType' => $this->modelType,
            'modelId' => $this->modelId,
            'imageType' => $this->imageType,
            'multiple' => $this->multiple,
            'maxSize' => $this->maxSize,
            'acceptTypes' => $this->acceptTypes,
            'createThumbnails' => $this->createThumbnails,
            'processImmediately' => $this->processImmediately,
            'storageDisk' => $this->storageDisk,
            'filters' => $this->filters,
        ];
    }

    /**
     * Parse size strings like "10MB" into KB
     */
    protected function parseSizeString(string $size): int
    {
        $size = strtoupper(trim($size));
        $number = (int) $size;
        
        if (str_contains($size, 'GB')) {
            return $number * 1024 * 1024;
        } elseif (str_contains($size, 'MB')) {
            return $number * 1024;
        }
        
        return $number; // Assume KB if no unit
    }

    /**
     * Get model type from model instance
     */
    protected function getModelType(Model $model): string
    {
        return match (get_class($model)) {
            Product::class => 'product',
            ProductVariant::class => 'variant',
            default => throw new \InvalidArgumentException('Unsupported model type')
        };
    }

    /**
     * Determine which component to render
     */
    protected function determineComponent(): string
    {
        if (in_array('gallery', $this->operations)) {
            return 'image-gallery';
        }
        
        return 'image-uploader';
    }
}