# Image System Guide

## Overview

The image system provides a comprehensive solution for handling image uploads throughout your Laravel PIM application. It features a decoupled pivot table architecture with CloudFlare R2 storage integration, supporting both products and variants with flexible relationships.

## Architecture

### Database Structure
```
images                 # Core image metadata and R2 URLs
├── id, filename, path, url, mime_type, size
├── width, height, title, alt_text, description
└── folder, tags, created_at, updated_at

image_product          # Product ↔ Image pivot table
├── image_id, product_id
├── is_primary, sort_order
└── created_at, updated_at

image_variant          # Variant ↔ Image pivot table  
├── image_id, product_variant_id
├── is_primary, sort_order
└── created_at, updated_at
```

### Component Structure
```
app/Models/Image.php                            # Core Image model with pivot helpers
app/Services/ImageUploadService.php             # R2 upload service
app/Actions/Images/                             # Image management actions
app/Livewire/Images/                            # Livewire components
resources/views/livewire/images/                # Blade templates
```

### Key Features
- **Pivot Table Architecture**: Flexible many-to-many relationships
- **R2 Cloud Storage**: Direct CloudFlare R2 integration 
- **DAM System**: Digital Asset Management with folders and tags
- **Attachment System**: Smart attach/detach with duplicate prevention
- **Primary Image Support**: Per-product/variant primary image tracking
- **Sort Order Management**: Automatic ordering with manual override
- **Bulk Operations**: Mass upload and assignment capabilities

## Basic Usage

### 1. Upload Images

```php
// Upload image using the service
use App\Services\ImageUploadService;

$imageUploadService = app(ImageUploadService::class);
$image = $imageUploadService->upload($uploadedFile, [
    'folder' => 'products',
    'title' => 'Product Hero Image',
    'tags' => ['hero', 'product']
]);
```

### 2. Attach to Products/Variants

```php
// Attach image to product
$image->attachTo($product);

// Attach as primary image
$image->attachTo($product, ['is_primary' => true]);

// Attach to variant with custom sort order
$image->attachTo($variant, ['sort_order' => 5]);

// Check if image is attached
if ($image->isAttachedTo($product)) {
    echo "Image is attached to product";
}

// Detach image from model
$image->detachFrom($product);

// Set image as primary for a model
$image->setPrimaryFor($variant);
```

### 3. Using Livewire Components

```blade
<!-- Image selector component for product -->
<livewire:images.image-selector
    :model="$product"
    :max-selection="5"
    :multiple="true"
/>

<!-- Image selector component for variant -->
<livewire:images.image-selector
    :model="$variant"
    :max-selection="3"
    :multiple="true"
/>

<!-- Bulk image operations -->
<livewire:bulk-operations.bulk-image-operation 
    :selected-products="$selectedProducts"
/>
```

## Integration with Existing Components

### Using with Actions Pattern

```php
<?php

namespace App\Actions\Products;

use App\Models\Image;
use App\Models\Product;
use App\Services\ImageUploadService;
use Illuminate\Http\UploadedFile;

class AttachImagesAction
{
    public function __construct(
        private ImageUploadService $imageUploadService
    ) {}

    public function handle(Product $product, array $imageIds): void
    {
        foreach ($imageIds as $imageId) {
            $image = Image::find($imageId);
            if ($image && !$image->isAttachedTo($product)) {
                $image->attachTo($product);
            }
        }
    }

    public function uploadAndAttach(Product $product, array $files): array
    {
        $uploadedImages = [];
        
        foreach ($files as $file) {
            $image = $this->imageUploadService->upload($file, [
                'folder' => 'products',
                'tags' => ['product']
            ]);
            
            $image->attachTo($product);
            $uploadedImages[] = $image;
        }
        
        return $uploadedImages;
    }
}
```

### Livewire Component Integration

```php
<?php

namespace App\Livewire\Products;

use App\Actions\Products\AttachImagesAction;
use App\Models\Product;
use Livewire\Component;
use Livewire\WithFileUploads;

class ProductImageManager extends Component
{
    use WithFileUploads;

    public Product $product;
    public $files = [];

    public function mount(Product $product)
    {
        $this->product = $product;
    }

    public function attachExistingImages(array $imageIds)
    {
        app(AttachImagesAction::class)->handle($this->product, $imageIds);
        $this->dispatch('images-attached', count: count($imageIds));
    }

    public function uploadFiles()
    {
        $this->validate([
            'files.*' => 'image|mimes:jpg,jpeg,png,webp|max:10240'
        ]);

        $uploadedImages = app(AttachImagesAction::class)
            ->uploadAndAttach($this->product, $this->files);

        $this->files = [];
        $this->dispatch('images-uploaded', count: count($uploadedImages));
    }

    public function render()
    {
        return view('livewire.products.product-image-manager');
    }
}
```

### Blade Template Example

```blade
<div>
    <!-- Existing Images -->
    <div class="grid grid-cols-4 gap-4 mb-6">
        @foreach($product->images as $image)
            <div class="relative">
                <img src="{{ $image->url }}" alt="{{ $image->alt_text }}" 
                     class="w-full h-32 object-cover rounded">
                
                @if($image->isPrimaryFor($product))
                    <span class="absolute top-2 left-2 bg-blue-500 text-white px-2 py-1 text-xs rounded">
                        Primary
                    </span>
                @endif
                
                <button wire:click="detachImage({{ $image->id }})" 
                        class="absolute top-2 right-2 bg-red-500 text-white p-1 rounded">
                    <x-lucide-x class="w-4 h-4" />
                </button>
            </div>
        @endforeach
    </div>

    <!-- Upload New Images -->
    <div class="mb-4">
        <input type="file" wire:model="files" multiple accept="image/*">
        <button wire:click="uploadFiles" class="ml-2 px-4 py-2 bg-blue-500 text-white rounded">
            Upload Images
        </button>
    </div>

    <!-- Image Selector Component -->
    <livewire:images.image-selector :model="$product" />
</div>
```

## Configuration Options

### ImageUploadService Configuration
```php
// In your component or action
$imageUploadService = app(ImageUploadService::class);

// Upload with custom options
$image = $imageUploadService->upload($file, [
    'folder' => 'products',              // Organize images in folders
    'title' => 'Product Hero Image',     // Set image title
    'alt_text' => 'Product description', // Accessibility text
    'description' => 'Main product photo', // Image description
    'tags' => ['hero', 'product'],      // Categorization tags
]);
```

### Image Model Options
```php
// Attach with pivot data
$image->attachTo($product, [
    'is_primary' => true,                // Mark as primary image
    'sort_order' => 1,                   // Control display order
]);

// Check attachment status
$isAttached = $image->isAttachedTo($product);
$isPrimary = $image->isPrimaryFor($product);

// Manage image metadata
$image->addTag('featured');
$image->removeTag('draft');
$image->moveToFolder('archived');
```

### Livewire Component Configuration
```php
// In your Livewire component
public function rules()
{
    return [
        'files.*' => 'image|mimes:jpg,jpeg,png,webp|max:10240', // 10MB max
        'files' => 'max:5', // Maximum 5 files per upload
    ];
}

// Component properties
public $maxFiles = 10;               // Maximum files per selection
public $allowMultiple = true;        // Enable multiple selection
public $showExisting = true;         // Show existing images
public $enableReorder = true;        // Allow drag-to-reorder
```

## Event Handling

### Livewire Events
The image system dispatches events for real-time updates:

```php
// In your parent component
#[On('images-uploaded')]
public function onImagesUploaded(array $data): void
{
    // Handle successful upload and attachment
    // $data contains: count, images, model_type, model_id
    $this->dispatch('refresh-images');
    session()->flash('success', "Uploaded {$data['count']} images!");
}

#[On('images-attached')]
public function onImagesAttached(array $data): void
{
    // Handle image attachment to model
    // $data contains: count, image_ids, model
    $this->loadImages(); // Refresh image list
}

#[On('image-detached')]
public function onImageDetached(array $data): void
{
    // Handle image detachment
    // $data contains: image_id, model_type, model_id
    $this->loadImages();
}

#[On('primary-image-changed')]
public function onPrimaryImageChanged(array $data): void
{
    // Handle primary image change
    // $data contains: image_id, model_type, model_id
    $this->loadImages();
}
```

### Custom Event Dispatching
```php
// In your Livewire component
public function attachImages(array $imageIds)
{
    $attachedCount = 0;
    
    foreach ($imageIds as $imageId) {
        $image = Image::find($imageId);
        if ($image && !$image->isAttachedTo($this->model)) {
            $image->attachTo($this->model);
            $attachedCount++;
        }
    }
    
    $this->dispatch('images-attached', [
        'count' => $attachedCount,
        'image_ids' => $imageIds,
        'model' => [
            'type' => get_class($this->model),
            'id' => $this->model->id
        ]
    ]);
}

public function setPrimaryImage(int $imageId)
{
    $image = Image::find($imageId);
    if ($image && $image->isAttachedTo($this->model)) {
        $image->setPrimaryFor($this->model);
        
        $this->dispatch('primary-image-changed', [
            'image_id' => $imageId,
            'model_type' => get_class($this->model),
            'model_id' => $this->model->id
        ]);
    }
}
```

## Advanced Usage Scenarios

### 1. Product Creation with Images

```php
<?php

namespace App\Livewire\Products;

use App\Actions\Products\CreateProductAction;
use App\Actions\Products\AttachImagesAction;
use App\Models\Product;
use Livewire\Component;
use Livewire\WithFileUploads;

class ProductCreationWizard extends Component
{
    use WithFileUploads;

    public $name, $description;
    public $files = [];
    public Product $product;
    public $step = 1;

    public function createProduct()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string'
        ]);

        $this->product = app(CreateProductAction::class)->handle([
            'name' => $this->name,
            'description' => $this->description,
            'status' => 'draft'
        ]);

        $this->step = 2; // Move to image upload step
    }

    public function uploadImages()
    {
        $this->validate([
            'files.*' => 'image|mimes:jpg,jpeg,png,webp|max:10240'
        ]);

        if (!empty($this->files)) {
            $uploadedImages = app(AttachImagesAction::class)
                ->uploadAndAttach($this->product, $this->files);

            if (!empty($uploadedImages)) {
                $uploadedImages[0]->setPrimaryFor($this->product);
            }

            session()->flash('success', 'Product created with ' . count($uploadedImages) . ' images!');
            return $this->redirect(route('products.show', $this->product));
        }

        session()->flash('success', 'Product created successfully!');
        return $this->redirect(route('products.show', $this->product));
    }
}
```

### 2. Bulk Image Management

```php
<?php

namespace App\Livewire\BulkOperations;

use App\Models\Image;
use App\Models\Product;
use Livewire\Component;

class BulkImageAttachment extends Component
{
    public $selectedProducts = [];
    public $selectedImages = [];
    public $attachmentMode = 'add'; // 'add', 'replace', 'remove'

    public function executeAttachment()
    {
        $processedCount = 0;
        
        foreach ($this->selectedProducts as $productId) {
            $product = Product::find($productId);
            if (!$product) continue;

            foreach ($this->selectedImages as $imageId) {
                $image = Image::find($imageId);
                if (!$image) continue;

                match($this->attachmentMode) {
                    'add' => $this->addImage($product, $image),
                    'replace' => $this->replaceImages($product, $image),
                    'remove' => $this->removeImage($product, $image),
                };
                
                $processedCount++;
            }
        }

        $this->dispatch('bulk-operation-complete', [
            'processed' => $processedCount,
            'operation' => $this->attachmentMode
        ]);
    }

    private function addImage(Product $product, Image $image): void
    {
        if (!$image->isAttachedTo($product)) {
            $image->attachTo($product);
        }
    }

    private function replaceImages(Product $product, Image $image): void
    {
        // Detach all existing images
        foreach ($product->images as $existingImage) {
            $existingImage->detachFrom($product);
        }
        
        // Attach new image as primary
        $image->attachTo($product, ['is_primary' => true]);
    }

    private function removeImage(Product $product, Image $image): void
    {
        if ($image->isAttachedTo($product)) {
            $image->detachFrom($product);
        }
    }
}
```

### 3. Image Variant Management

```php
<?php

namespace App\Livewire\Products;

use App\Models\ProductVariant;
use App\Models\Image;
use Livewire\Component;

class VariantImageManager extends Component
{
    public ProductVariant $variant;
    public $availableImages = [];
    public $inheritFromProduct = true;

    public function mount(ProductVariant $variant)
    {
        $this->variant = $variant;
        $this->loadAvailableImages();
    }

    public function loadAvailableImages()
    {
        // Show product images and unattached images
        $productImages = $this->variant->product->images;
        $unattachedImages = Image::unattached()->get();
        
        $this->availableImages = $productImages
            ->merge($unattachedImages)
            ->unique('id')
            ->sortBy('created_at');
    }

    public function toggleInheritance()
    {
        $this->inheritFromProduct = !$this->inheritFromProduct;
        
        if ($this->inheritFromProduct) {
            // Copy product images to variant
            foreach ($this->variant->product->images as $image) {
                if (!$image->isAttachedTo($this->variant)) {
                    $image->attachTo($this->variant);
                }
            }
        } else {
            // Remove inherited images, keep only variant-specific
            foreach ($this->variant->images as $image) {
                if ($image->isAttachedTo($this->variant->product)) {
                    $image->detachFrom($this->variant);
                }
            }
        }
        
        $this->dispatch('variant-inheritance-changed');
    }

    public function attachImage(int $imageId)
    {
        $image = Image::find($imageId);
        if ($image && !$image->isAttachedTo($this->variant)) {
            $image->attachTo($this->variant);
            $this->dispatch('image-attached-to-variant');
        }
    }
}
```

## API Usage (Optional)

For API-driven applications, create RESTful endpoints:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Actions\Products\AttachImagesAction;
use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\Product;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;

class ImageController extends Controller
{
    public function __construct(
        private ImageUploadService $imageUploadService,
        private AttachImagesAction $attachImagesAction
    ) {}

    public function upload(Request $request)
    {
        $request->validate([
            'files.*' => 'image|mimes:jpg,jpeg,png,webp|max:10240',
            'folder' => 'nullable|string',
            'tags' => 'nullable|array'
        ]);

        $uploadedImages = [];
        
        foreach ($request->file('files') as $file) {
            $image = $this->imageUploadService->upload($file, [
                'folder' => $request->input('folder', 'uploads'),
                'tags' => $request->input('tags', [])
            ]);
            
            $uploadedImages[] = [
                'id' => $image->id,
                'url' => $image->url,
                'filename' => $image->filename,
                'size' => $image->size
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Images uploaded successfully',
            'images' => $uploadedImages
        ]);
    }

    public function attach(Request $request, Product $product)
    {
        $request->validate([
            'image_ids' => 'required|array',
            'image_ids.*' => 'exists:images,id'
        ]);

        $this->attachImagesAction->handle($product, $request->input('image_ids'));

        return response()->json([
            'success' => true,
            'message' => 'Images attached successfully'
        ]);
    }

    public function setPrimary(Request $request, Product $product, Image $image)
    {
        if ($image->isAttachedTo($product)) {
            $image->setPrimaryFor($product);
            
            return response()->json([
                'success' => true,
                'message' => 'Primary image set successfully'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Image is not attached to this product'
        ], 400);
    }
}
```

### JavaScript Frontend Example

```javascript
class ImageManager {
    constructor(baseUrl) {
        this.baseUrl = baseUrl;
        this.csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    }

    async uploadImages(files, options = {}) {
        const formData = new FormData();
        
        files.forEach(file => formData.append('files[]', file));
        if (options.folder) formData.append('folder', options.folder);
        if (options.tags) formData.append('tags', JSON.stringify(options.tags));

        const response = await fetch(`${this.baseUrl}/api/images/upload`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': this.csrfToken
            }
        });

        return await response.json();
    }

    async attachToProduct(productId, imageIds) {
        const response = await fetch(`${this.baseUrl}/api/products/${productId}/images/attach`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': this.csrfToken
            },
            body: JSON.stringify({ image_ids: imageIds })
        });

        return await response.json();
    }

    async setPrimary(productId, imageId) {
        const response = await fetch(`${this.baseUrl}/api/products/${productId}/images/${imageId}/primary`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': this.csrfToken
            }
        });

        return await response.json();
    }
}

// Usage example
const imageManager = new ImageManager(window.location.origin);

document.getElementById('file-upload').addEventListener('change', async (e) => {
    const files = Array.from(e.target.files);
    
    try {
        const uploadResult = await imageManager.uploadImages(files, {
            folder: 'products',
            tags: ['product', 'hero']
        });

        if (uploadResult.success) {
            const imageIds = uploadResult.images.map(img => img.id);
            await imageManager.attachToProduct(productId, imageIds);
            
            // Set first image as primary
            if (imageIds.length > 0) {
                await imageManager.setPrimary(productId, imageIds[0]);
            }
            
            console.log('Images uploaded and attached successfully!');
        }
    } catch (error) {
        console.error('Upload failed:', error);
    }
});
```

## Security Considerations

### Validation
- File type validation (MIME type checking)
- File size limits (configurable)
- Image dimension requirements (minimum 300x300px)
- CSRF protection on all uploads
- Authentication required for all operations

### File Handling
- Temporary storage before processing
- Automatic cleanup of failed uploads
- Secure file naming (prevents path traversal)
- Virus scanning integration (if available)

### Permissions
```php
// Add authorization logic in ImageUploadRequest
public function authorize(): bool
{
    // Example: Check if user can upload to this model
    if ($this->model_type === 'product') {
        return auth()->user()->can('update', Product::find($this->model_id));
    }
    
    return true;
}
```

## Performance Optimizations

### Queue Configuration
```php
// In config/queue.php
'connections' => [
    'image-processing' => [
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'images', // Single queue for all image processing
        'retry_after' => 300,
    ],
],
```

### Chunked Uploads
For large files, consider implementing chunked uploads:

```php
// In your component
public bool $useChunkedUpload = true;
public int $chunkSize = 1024 * 1024; // 1MB chunks
```

### Memory Management
```php
// Optimize for large batches
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300);
```

## Testing

### Feature Testing
```php
// Test image upload
public function test_can_upload_images(): void
{
    Storage::fake('public');
    
    $product = Product::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg', 800, 600);
    
    Livewire::test(ImageUploader::class)
        ->set('modelType', 'product')
        ->set('modelId', $product->id)
        ->set('files', [$file])
        ->call('upload')
        ->assertHasNoErrors()
        ->assertDispatched('images-uploaded');
        
    $this->assertDatabaseHas('product_images', [
        'product_id' => $product->id,
        'original_filename' => 'test.jpg'
    ]);
}
```

### Unit Testing
```php
// Test trait functionality
public function test_image_uploader_config(): void
{
    $component = new TestComponentWithTrait();
    $config = $component->getImageUploaderConfig('main', $this->product);
    
    $this->assertEquals('product', $config['model_type']);
    $this->assertEquals($this->product->id, $config['model_id']);
    $this->assertEquals('main', $config['image_type']);
}
```

## Troubleshooting

### Common Issues

1. **Files not uploading**
   - Check file size limits in php.ini
   - Verify CSRF token is present
   - Check user permissions

2. **Processing fails**
   - Verify queue worker is running
   - Check CloudFlare R2 credentials
   - Monitor application logs

3. **Preview not showing**
   - Ensure temporary URL generation is working
   - Check file permissions
   - Verify image file is valid

### Debug Mode
```php
// Enable debug logging in the component
public bool $debug = true;

private function log(string $message, array $context = []): void
{
    if ($this->debug) {
        \Log::debug("ImageUploader: {$message}", $context);
    }
}
```

## Best Practices

1. **Always use wire:key** for dynamic components
2. **Implement proper error handling** for network failures
3. **Provide user feedback** for long-running operations
4. **Optimize images before upload** when possible
5. **Use progressive enhancement** for JavaScript features
6. **Test with various file types and sizes**
7. **Monitor upload performance** and queue processing times
8. **Implement proper cleanup** for failed uploads

## Integration Examples

See the example components for complete implementation patterns:
- `/app/Livewire/Examples/VariantImageManager.php` - Multi-type image management
- `/app/Livewire/Examples/ProductCreationWithImages.php` - Workflow integration

## Database Queries and Performance

### Optimized Queries
```php
// Efficient image loading with pivot data
$product = Product::with([
    'images' => function ($query) {
        $query->orderBy('image_product.sort_order')
              ->orderBy('images.created_at');
    }
])->find($productId);

// Get primary images efficiently
$productsWithPrimary = Product::with([
    'images' => function ($query) {
        $query->wherePivot('is_primary', true);
    }
])->get();

// DAM queries - find unattached images
$unattachedImages = Image::unattached()
    ->inFolder('products')
    ->withTag('unused')
    ->orderBy('created_at', 'desc')
    ->paginate(20);

// Search images across all attachments
$searchResults = Image::search('product photo')
    ->with(['products', 'variants'])
    ->get();
```

### Database Indexes
Ensure these indexes exist for optimal performance:

```sql
-- Pivot table indexes
CREATE INDEX idx_image_product_image_id ON image_product(image_id);
CREATE INDEX idx_image_product_product_id ON image_product(product_id);
CREATE INDEX idx_image_product_primary ON image_product(is_primary);
CREATE INDEX idx_image_product_sort ON image_product(sort_order);

CREATE INDEX idx_image_variant_image_id ON image_variant(image_id);
CREATE INDEX idx_image_variant_variant_id ON image_variant(product_variant_id);
CREATE INDEX idx_image_variant_primary ON image_variant(is_primary);

-- Images table indexes
CREATE INDEX idx_images_folder ON images(folder);
CREATE INDEX idx_images_tags ON images(tags); -- JSON index
CREATE INDEX idx_images_created ON images(created_at);
```

## Monitoring and Statistics

### Image Statistics Command
```bash
# Get comprehensive image statistics
php artisan images:stats

# Output includes:
# - Total images and storage usage
# - Attachment status (attached vs unattached)
# - Folder distribution
# - Tag usage
# - R2 storage health
```

### Health Checks
```php
// Check for orphaned images
$orphanedCount = Image::unattached()->count();

// Check for missing R2 files
$missingFiles = Image::whereNull('url')->count();

// Check for oversized images
$oversizedImages = Image::where('size', '>', 10 * 1024 * 1024)->count(); // 10MB+

// Folder usage statistics
$folderStats = Image::selectRaw('folder, COUNT(*) as count, SUM(size) as total_size')
    ->groupBy('folder')
    ->get();
```

## Migration Guide

### From Old ImageManager Component

If migrating from a previous image system:

1. **Database Migration**
   ```php
   // Update existing polymorphic relationships to pivot tables
   // Run data migration script to transfer imageable_type/imageable_id to pivot tables
   ```

2. **Component Updates**
   ```php
   // Replace old component calls
   // OLD: <livewire:image-manager :model="$product" />
   // NEW: <livewire:images.image-selector :model="$product" />
   ```

3. **Method Calls**
   ```php
   // Update attachment logic
   // OLD: $product->images()->attach($imageId)
   // NEW: $image->attachTo($product)
   
   // Update primary image setting
   // OLD: $product->images()->updateExistingPivot($imageId, ['is_primary' => true])
   // NEW: $image->setPrimaryFor($product)
   ```

4. **Event Handling**
   ```php
   // Update event listeners
   // OLD: #[On('image-uploaded')]
   // NEW: #[On('images-uploaded')] or #[On('images-attached')]
   ```

### Testing Migration
```php
// Verify data integrity after migration
$this->assertDatabaseHas('image_product', ['image_id' => $imageId, 'product_id' => $productId]);
$this->assertTrue($image->isAttachedTo($product));
$this->assertTrue($image->isPrimaryFor($product));
```