# Reusable Image Uploader Component Guide

## Overview

The reusable image uploader component provides a comprehensive solution for handling image uploads throughout your Laravel PIM application. It integrates seamlessly with your existing CloudFlare R2 processing pipeline, queue-based image processing, and Flux UI design system.

## Architecture

### Component Structure
```
app/Livewire/Components/ImageUploader.php       # Main Livewire component
resources/views/livewire/components/image-uploader.blade.php # View template
app/Traits/HasImageUpload.php                   # Integration trait
app/Http/Requests/ImageUploadRequest.php        # Validation request
app/Http/Controllers/ImageUploadController.php  # API controller (optional)
```

### Key Features
- **Drag & Drop Interface**: Modern file upload experience
- **Real-time Validation**: Client-side validation with server-side backup
- **Preview System**: Image thumbnails before and after upload
- **Progress Tracking**: Real-time upload and processing progress
- **Queue Integration**: Automatic integration with your R2 processing pipeline
- **Flexible Configuration**: Configurable for different use cases
- **Sortable Images**: Drag-to-reorder functionality
- **Multiple Image Types**: Support for main, detail, swatch, lifestyle, installation
- **WebSocket Integration**: Real-time processing status updates

## Basic Usage

### 1. Simple Implementation

```blade
<!-- Basic image uploader for a product -->
<livewire:components.image-uploader 
    :model-type="'product'"
    :model-id="$product->id"
    :image-type="'main'"
/>
```

### 2. With Custom Configuration

```blade
<!-- Advanced configuration -->
<livewire:components.image-uploader 
    :model-type="'variant'"
    :model-id="$variant->id"
    :image-type="'swatch'"
    :multiple="true"
    :max-files="5"
    :max-size="2048"
    :accept-types="['jpg', 'png', 'webp']"
    :process-immediately="true"
    :show-preview="true"
    :allow-reorder="false"
    :upload-text="'Upload swatch images'"
/>
```

## Integration with Existing Components

### Using the HasImageUpload Trait

```php
<?php

namespace App\Livewire\YourComponent;

use App\Traits\HasImageUpload;
use Livewire\Component;

class YourComponent extends Component
{
    use HasImageUpload;

    public function render()
    {
        // Get pre-configured settings for main images
        $uploaderConfig = $this->getImageUploaderConfig('main', $this->product);
        
        // Customize as needed
        $uploaderConfig['max_files'] = 3;
        
        return view('livewire.your-component', [
            'uploaderConfig' => $uploaderConfig
        ]);
    }

    // Handle upload completion
    #[On('images-uploaded')]
    public function handleImageUpload(array $data): void
    {
        $this->loadData(); // Refresh your component data
        session()->flash('success', "Uploaded {$data['count']} images!");
    }
}
```

### In Your Blade Template

```blade
<div>
    <!-- Your existing content -->
    
    <!-- Image uploader section -->
    <div class="mt-8">
        <livewire:components.image-uploader 
            :model-type="'product'"
            :model-id="$product->id"
            :image-type="'main'"
            :max-files="$uploaderConfig['max_files']"
            wire:key="product-images-{{ $product->id }}"
        />
    </div>
</div>
```

## Configuration Options

### Model Context
```php
'model_type' => 'product|variant|null',  // Target model type
'model_id' => 123,                       // Target model ID
```

### Upload Settings
```php
'image_type' => 'main',                  // Image type: main, detail, swatch, lifestyle, installation
'multiple' => true,                      // Allow multiple file selection
'max_files' => 10,                       // Maximum files per upload
'max_size' => 10240,                     // Max file size in KB
'accept_types' => ['jpg', 'jpeg', 'png', 'webp'], // Allowed file types
```

### Processing Options
```php
'process_immediately' => true,           // Queue for R2 processing immediately
'show_preview' => true,                  // Show image previews
'allow_reorder' => true,                 // Enable drag-to-reorder
```

### UI Options
```php
'show_upload_area' => true,              // Show the upload interface
'show_existing_images' => true,          // Show existing images
'view_mode' => 'grid',                   // 'grid' or 'list' view
'upload_text' => 'Custom upload text',   // Custom upload area text
```

## Event Handling

### Component Events
The image uploader dispatches several events that you can listen to:

```php
// In your parent component
#[On('images-uploaded')]
public function onImagesUploaded(array $data): void
{
    // Handle successful upload
    // $data contains: count, processed, model_type, model_id
}

#[On('image-deleted')]
public function onImageDeleted(array $data): void
{
    // Handle image deletion
    // $data contains: image_id
}

#[On('images-reordered')]
public function onImagesReordered(array $data): void
{
    // Handle reordering
    // $data contains: ordered_ids
}

#[On('image-processed')]
public function onImageProcessed($imageData): void
{
    // Handle R2 processing completion
    // Automatically handled by the component
}
```

## Advanced Usage Scenarios

### 1. Product Creation Workflow

```php
// In your product creation component
use HasImageUpload;

public function createProduct(): void
{
    // Create product first
    $this->product = Product::create([...]);
    
    // Show image uploader
    $this->showImageUploader = true;
}

#[On('images-uploaded')]
public function handleProductImages(array $data): void
{
    $this->redirect(route('products.view', $this->product));
}
```

### 2. Multi-Type Image Manager

```php
// Component with multiple image types
public string $activeImageType = 'main';
public array $imageTypes = ['main', 'detail', 'swatch', 'lifestyle'];

public function setActiveImageType(string $type): void
{
    $this->activeImageType = $type;
}

public function render()
{
    return view('livewire.multi-image-manager', [
        'uploaderConfig' => $this->getImageUploaderConfig($this->activeImageType, $this->product)
    ]);
}
```

### 3. Bulk Image Operations

```php
// Handle multiple uploads across different contexts
#[On('images-uploaded')]
public function handleBulkUpload(array $data): void
{
    $this->uploadCount += $data['count'];
    
    if ($this->uploadCount >= $this->targetCount) {
        session()->flash('success', 'All images uploaded successfully!');
        $this->redirect(route('products.index'));
    }
}
```

## API Usage (Optional)

For JavaScript-heavy applications, use the API endpoints:

```javascript
// Upload images via API
const formData = new FormData();
formData.append('files[]', file1);
formData.append('files[]', file2);
formData.append('image_type', 'main');
formData.append('model_type', 'product');
formData.append('model_id', 123);

fetch('/api/images/upload', {
    method: 'POST',
    body: formData,
    headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    }
})
.then(response => response.json())
.then(data => {
    console.log('Upload successful:', data);
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

## Migration from Existing Components

If you're migrating from the existing `ImageManager` component:

1. Replace direct file upload logic with the new component
2. Update event listeners to use new event names
3. Migrate configuration to use new prop system
4. Test thoroughly with existing data

The new component is designed to be a drop-in replacement with enhanced functionality and better integration capabilities.