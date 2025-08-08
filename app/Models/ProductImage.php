<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'variant_id',
        'image_path',
        'original_filename',
        'image_type',
        'sort_order',
        'alt_text',
        'metadata',
        'processing_status',
        'storage_disk',
        'file_size',
        'mime_type',
        'dimensions',
    ];

    protected $casts = [
        'metadata' => 'array',
        'dimensions' => 'array',
        'file_size' => 'integer',
    ];

    protected $appends = ['url', 'variants'];

    public const PROCESSING_PENDING = 'pending';

    public const PROCESSING_IN_PROGRESS = 'processing';

    public const PROCESSING_COMPLETED = 'completed';

    public const PROCESSING_FAILED = 'failed';

    public const SIZES = [
        'thumbnail' => ['width' => 150, 'height' => 150, 'quality' => 85],
        'small' => ['width' => 300, 'height' => 300, 'quality' => 85],
        'medium' => ['width' => 600, 'height' => 600, 'quality' => 90],
        'large' => ['width' => 1200, 'height' => 1200, 'quality' => 95],
        'xlarge' => ['width' => 2000, 'height' => 2000, 'quality' => 95],
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductImageVariant::class);
    }

    public function getUrlAttribute(): string
    {
        // Use R2 public URL for processed images, fallback to local storage
        if ($this->storage_disk === 'images' && $this->processing_status === self::PROCESSING_COMPLETED) {
            return Storage::disk('images')->url($this->image_path);
        }

        // Fallback to public disk for local/unprocessed images
        return Storage::disk('public')->url($this->image_path);
    }

    public function getVariantsAttribute(): array
    {
        if ($this->processing_status !== self::PROCESSING_COMPLETED) {
            return [];
        }

        $variants = [];
        foreach (self::SIZES as $size => $config) {
            $path = $this->getVariantPath($size);
            $variants[$size] = [
                'url' => Storage::disk($this->storage_disk ?: 'images')->url($path),
                'width' => $config['width'],
                'height' => $config['height'],
            ];
        }

        return $variants;
    }

    public function getVariantUrl(string $size = 'medium'): string
    {
        if ($this->processing_status !== self::PROCESSING_COMPLETED || ! isset(self::SIZES[$size])) {
            return $this->url;
        }

        $path = $this->getVariantPath($size);

        return Storage::disk($this->storage_disk ?: 'images')->url($path);
    }

    public function getVariantPath(string $size): string
    {
        $pathInfo = pathinfo($this->image_path);

        return "{$pathInfo['dirname']}/{$pathInfo['filename']}_{$size}.{$pathInfo['extension']}";
    }

    public function getFullPathAttribute(): string
    {
        return Storage::disk($this->storage_disk ?: 'public')->path($this->image_path);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('image_type', $type);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('created_at');
    }

    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId)->whereNull('variant_id');
    }

    public function scopeForVariant($query, int $variantId)
    {
        return $query->where('variant_id', $variantId)->whereNull('product_id');
    }

    public function scopeMain($query)
    {
        return $query->where('image_type', 'main');
    }

    public function scopeProcessed($query)
    {
        return $query->where('processing_status', self::PROCESSING_COMPLETED);
    }

    public function scopePending($query)
    {
        return $query->where('processing_status', self::PROCESSING_PENDING);
    }

    public function isMain(): bool
    {
        return $this->image_type === 'main';
    }

    public function isSwatch(): bool
    {
        return $this->image_type === 'swatch';
    }

    public function isProcessed(): bool
    {
        return $this->processing_status === self::PROCESSING_COMPLETED;
    }

    public function isPending(): bool
    {
        return $this->processing_status === self::PROCESSING_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->processing_status === self::PROCESSING_IN_PROGRESS;
    }

    public function isFailed(): bool
    {
        return $this->processing_status === self::PROCESSING_FAILED;
    }

    public function markAsProcessing(): void
    {
        $this->update(['processing_status' => self::PROCESSING_IN_PROGRESS]);
    }

    public function markAsCompleted(): void
    {
        $this->update(['processing_status' => self::PROCESSING_COMPLETED]);
    }

    public function markAsFailed(?string $error = null): void
    {
        $metadata = $this->metadata ?? [];
        if ($error) {
            $metadata['processing_error'] = $error;
        }

        $this->update([
            'processing_status' => self::PROCESSING_FAILED,
            'metadata' => $metadata,
        ]);
    }

    public function delete(): ?bool
    {
        $disk = $this->storage_disk ?: 'public';

        // Delete original image
        if (Storage::disk($disk)->exists($this->image_path)) {
            Storage::disk($disk)->delete($this->image_path);
        }

        // Delete all variants
        if ($this->isProcessed()) {
            foreach (array_keys(self::SIZES) as $size) {
                $variantPath = $this->getVariantPath($size);
                if (Storage::disk($disk)->exists($variantPath)) {
                    Storage::disk($disk)->delete($variantPath);
                }
            }
        }

        return parent::delete();
    }
}
