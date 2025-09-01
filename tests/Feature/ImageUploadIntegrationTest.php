<?php

use App\Actions\Images\UploadImagesAction;
use App\Enums\ImageProcessingStatus;
use App\Events\Images\ImageProcessingProgress;
use App\Jobs\ProcessImageJob;
use App\Jobs\GenerateImageVariantsJob;
use App\Models\Image;
use App\Models\User;
use App\Services\ImageProcessingTracker;
use App\Services\ImageUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    
    // Create and assign image permissions
    $permissions = ['manage-images', 'view-images', 'upload-images', 'delete-images'];
    foreach ($permissions as $permissionName) {
        Permission::findOrCreate($permissionName);
    }
    $this->user->givePermissionTo($permissions);
    
    $this->actingAs($this->user);
    Storage::fake('images');
    Queue::fake();
    Event::fake();
});

describe('Complete Image Upload Workflow', function () {

    test('single image upload with async processing', function () {
        $uploadService = app(ImageUploadService::class);
        $file = UploadedFile::fake()->image('test.jpg', 800, 600);

        // Upload image asynchronously
        $image = $uploadService->upload($file, [], true, true);

        // Image should be created with placeholder dimensions
        expect($image)->toBeInstanceOf(Image::class);
        expect($image->filename)->not()->toBeEmpty();
        expect($image->original_filename)->not()->toBeEmpty();
        expect($image->width)->toBe(0); // Will be filled by job
        expect($image->height)->toBe(0); // Will be filled by job

        // Files should be stored
        Storage::disk('images')->assertExists($image->filename);

        // Jobs should be dispatched
        Queue::assertPushed(ProcessImageJob::class, function ($job) use ($image) {
            return $job->image->id === $image->id;
        });

        Queue::assertPushed(GenerateImageVariantsJob::class, function ($job) use ($image) {
            return $job->image->id === $image->id;
        });
    });

    test('multiple image upload with processing tracking', function () {
        $uploadService = app(ImageUploadService::class);
        $files = [
            UploadedFile::fake()->image('test1.jpg'),
            UploadedFile::fake()->image('test2.png'),
            UploadedFile::fake()->image('test3.gif'),
        ];

        $images = $uploadService->uploadMultiple($files, ['folder' => 'products'], true, true);

        expect($images)->toHaveCount(3);

        foreach ($images as $image) {
            expect($image->folder)->toBe('products');
            expect($image->width)->toBe(0); // Async processing
            Storage::disk('images')->assertExists($image->filename);
        }

        // Should dispatch jobs for each image
        Queue::assertPushed(ProcessImageJob::class, 3);
        Queue::assertPushed(GenerateImageVariantsJob::class, 3);
    });

    test('upload action integration with livewire', function () {
        $uploadAction = app(UploadImagesAction::class);
        $files = [UploadedFile::fake()->image('test.jpg')];
        $metadata = [
            'title' => 'Test Image',
            'folder' => 'products',
            'tags' => 'red,featured',
        ];

        $result = $uploadAction->execute($files, $metadata);

        expect($result['success'])->toBeTrue();
        expect($result['data']['upload_count'])->toBe(1);
        expect($result['data']['uploaded_images'])->toHaveCount(1);

        $image = $result['data']['uploaded_images'][0];
        expect($image->title)->toBe('Test Image');
        expect($image->folder)->toBe('products');
        expect($image->tags)->toBe(['red', 'featured']);
    });

});

describe('Image Processing Job Integration', function () {

    test('process image job updates dimensions and dispatches events', function () {
        Event::fake([ImageProcessingProgress::class]);
        
        $image = Image::factory()->create([
            'width' => 0,
            'height' => 0,
            'filename' => 'test.jpg'
        ]);

        // Create a fake image file for processing
        Storage::disk('images')->put('test.jpg', UploadedFile::fake()->image('test.jpg', 800, 600)->getContent());

        $job = new ProcessImageJob($image);
        $tracker = app(ImageProcessingTracker::class);

        $job->handle($tracker);

        // Image should be updated with dimensions
        $image->refresh();
        expect($image->width)->toBe(800);
        expect($image->height)->toBe(600);
        expect($image->mime_type)->toBe('image/jpeg');

        // Events should be dispatched
        Event::assertDispatched(ImageProcessingProgress::class, function ($event) use ($image) {
            return $event->imageId === $image->id && $event->status === ImageProcessingStatus::UPLOADING;
        });

        Event::assertDispatched(ImageProcessingProgress::class, function ($event) use ($image) {
            return $event->imageId === $image->id && $event->status === ImageProcessingStatus::PROCESSING;
        });

        Event::assertDispatched(ImageProcessingProgress::class, function ($event) use ($image) {
            return $event->imageId === $image->id && $event->status === ImageProcessingStatus::SUCCESS;
        });
    });

    test('variant generation job creates variants and dispatches events', function () {
        Event::fake([ImageProcessingProgress::class]);
        
        $image = Image::factory()->create([
            'width' => 800,
            'height' => 600,
            'filename' => 'test.jpg'
        ]);

        // Create original image file
        Storage::disk('images')->put('test.jpg', UploadedFile::fake()->image('test.jpg', 800, 600)->getContent());

        $job = new GenerateImageVariantsJob($image, ['thumb', 'small']);
        $action = app(\App\Actions\Images\ProcessImageVariantsAction::class);
        $tracker = app(ImageProcessingTracker::class);

        $job->handle($action, $tracker);

        // Events should be dispatched
        Event::assertDispatched(ImageProcessingProgress::class, function ($event) use ($image) {
            return $event->imageId === $image->id && $event->status === ImageProcessingStatus::OPTIMISING;
        });

        Event::assertDispatched(ImageProcessingProgress::class, function ($event) use ($image) {
            return $event->imageId === $image->id && $event->status === ImageProcessingStatus::SUCCESS;
        });
    });

});

describe('Processing Status Tracking', function () {

    test('processing tracker maintains status cache', function () {
        $tracker = app(ImageProcessingTracker::class);
        $image = Image::factory()->create();

        // Set status
        $tracker->setStatus($image, ImageProcessingStatus::PROCESSING);
        
        // Should retrieve same status
        expect($tracker->getStatus($image))->toBe(ImageProcessingStatus::PROCESSING);

        // Update status
        $tracker->setStatus($image, ImageProcessingStatus::SUCCESS);
        expect($tracker->getStatus($image))->toBe(ImageProcessingStatus::SUCCESS);
    });

    test('status tracking integrates with jobs', function () {
        $tracker = app(ImageProcessingTracker::class);
        $image = Image::factory()->create(['filename' => 'test.jpg']);

        // Initially pending
        $tracker->setStatus($image, ImageProcessingStatus::PENDING);
        expect($tracker->getStatus($image))->toBe(ImageProcessingStatus::PENDING);

        // Create fake image for processing
        Storage::disk('images')->put('test.jpg', UploadedFile::fake()->image('test.jpg')->getContent());

        // Process
        $job = new ProcessImageJob($image);
        $job->handle($tracker);

        // Should be completed
        expect($tracker->getStatus($image))->toBe(ImageProcessingStatus::SUCCESS);
    });

});

describe('Error Handling in Workflow', function () {

    test('handles missing image file gracefully', function () {
        $image = Image::factory()->create([
            'filename' => 'nonexistent.jpg',
            'width' => 0,
            'height' => 0
        ]);

        $job = new ProcessImageJob($image);
        $tracker = app(ImageProcessingTracker::class);

        // Should handle missing file
        try {
            $job->handle($tracker);
            expect(false)->toBeTrue('Expected exception was not thrown');
        } catch (\App\Exceptions\ImageReprocessException $e) {
            // Exception was thrown as expected, now simulate the failed callback
            $job->failed($e);
            
            // Status should be updated to failed after failed() callback
            expect($tracker->getStatus($image))->toBe(ImageProcessingStatus::FAILED);
        }
    });

    test('job failure updates processing status', function () {
        $image = Image::factory()->create(['filename' => 'test.jpg']);
        $job = new ProcessImageJob($image);
        $tracker = app(ImageProcessingTracker::class);
        
        // Set initial status
        $tracker->setStatus($image, ImageProcessingStatus::PROCESSING);

        // Simulate job failure
        $exception = new \Exception('Test error');
        $job->failed($exception);

        // Status should be updated to failed
        expect($tracker->getStatus($image))->toBe(ImageProcessingStatus::FAILED);
    });

});

describe('Real-time UI Integration', function () {

    test('upload workflow supports real-time tracking', function () {
        $files = [UploadedFile::fake()->image('test.jpg')];
        $uploadAction = app(UploadImagesAction::class);

        $result = $uploadAction->execute($files, []);

        expect($result['success'])->toBeTrue();
        
        $uploadedImages = $result['data']['uploaded_images'];
        expect($uploadedImages)->toHaveCount(1);

        $image = $uploadedImages[0];
        
        // Image should have no dimensions initially (will be processed)
        expect($image->width)->toBe(0);
        expect($image->height)->toBe(0);

        // Jobs should be queued for processing
        Queue::assertPushed(ProcessImageJob::class, function ($job) use ($image) {
            return $job->image->id === $image->id;
        });
    });

    test('processing status enum provides UI data', function () {
        $status = ImageProcessingStatus::UPLOADING;

        expect($status->label())->toBe('Uploading');
        expect($status->color())->toBe('blue');
        expect($status->icon())->toBe('arrow-up-tray');

        $status = ImageProcessingStatus::OPTIMISING;

        expect($status->label())->toBe('Optimising');
        expect($status->color())->toBe('purple');
        expect($status->icon())->toBe('sparkles');
    });

});

describe('File Storage Integration', function () {

    test('upload service handles file storage correctly', function () {
        $uploadService = app(ImageUploadService::class);
        $file = UploadedFile::fake()->image('original-name.jpg', 400, 300);

        $image = $uploadService->upload($file);

        // File should be stored with UUID name
        expect($image->filename)->not()->toBe('original-name.jpg');
        expect($image->filename)->toMatch('/^[0-9a-f-]{36}\.jpg$/');
        expect($image->original_filename)->not()->toBeEmpty();

        // File should exist in storage
        Storage::disk('images')->assertExists($image->filename);

        // URL should be accessible
        expect($image->url)->not()->toBeEmpty();
        expect($image->url)->toContain($image->filename);
    });

    test('handles different file types', function () {
        $uploadService = app(ImageUploadService::class);
        
        $jpgFile = UploadedFile::fake()->image('test.jpg');
        $pngFile = UploadedFile::fake()->image('test.png');
        $gifFile = UploadedFile::fake()->image('test.gif');

        $jpgImage = $uploadService->upload($jpgFile);
        $pngImage = $uploadService->upload($pngFile);
        $gifImage = $uploadService->upload($gifFile);

        expect($jpgImage->mime_type)->toBe('image/jpeg');
        expect($pngImage->mime_type)->toBe('image/png'); 
        expect($gifImage->mime_type)->toBe('image/gif');

        expect($jpgImage->filename)->toEndWith('.jpg');
        expect($pngImage->filename)->toEndWith('.png');
        expect($gifImage->filename)->toEndWith('.gif');
    });

});