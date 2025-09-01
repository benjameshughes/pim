<?php

use App\Livewire\Images\ImageCardSkeleton;
use App\Models\Image;
use App\Models\User;
use App\Enums\ImageProcessingStatus;
use App\Services\ImageProcessingTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    
    // Create and assign image permissions
    $permissions = ['manage-images', 'view-images'];
    foreach ($permissions as $permissionName) {
        Permission::findOrCreate($permissionName);
    }
    $this->user->givePermissionTo($permissions);
    
    $this->actingAs($this->user);
});

describe('ImageCardSkeleton Livewire Component', function () {

    test('component renders successfully', function () {
        $image = Image::factory()->create();

        Livewire::test(ImageCardSkeleton::class, ['image' => $image])
            ->assertStatus(200);
    });

    test('initializes with pending status for unprocessed image', function () {
        $image = Image::factory()->create([
            'width' => 0,
            'height' => 0,
        ]);

        $component = Livewire::test(ImageCardSkeleton::class, ['image' => $image])
            ->assertStatus(200);

        expect($component->get('status'))->toBe(ImageProcessingStatus::PENDING);
        expect($component->get('shouldShowActualCard'))->toBeFalse();
    });

    test('marks processed images as ready', function () {
        $image = Image::factory()->create([
            'width' => 800,
            'height' => 600,
        ]);

        $component = Livewire::test(ImageCardSkeleton::class, ['image' => $image])
            ->assertStatus(200);

        expect($component->get('status'))->toBe(ImageProcessingStatus::SUCCESS);
        expect($component->get('progress'))->toBe(100);
    });

    test('displays correct status messages', function () {
        $image = Image::factory()->create();

        $component = Livewire::test(ImageCardSkeleton::class, ['image' => $image]);

        // Test different status messages by simulating progress updates
        $component->call('updateProcessingProgress', [
            'imageId' => $image->id,
            'status' => 'uploading',
            'currentAction' => 'Uploading to storage...',
            'percentage' => 25,
        ]);
        expect(strtolower($component->get('statusMessage')))->toContain('upload');

        $component->call('updateProcessingProgress', [
            'imageId' => $image->id,
            'status' => 'processing',
            'currentAction' => 'Extracting metadata...',
            'percentage' => 50,
        ]);
        expect(strtolower($component->get('statusMessage')))->toContain('metadata');

        $component->call('updateProcessingProgress', [
            'imageId' => $image->id,
            'status' => 'optimising',
            'currentAction' => 'Generating variants...',
            'percentage' => 75,
        ]);
        expect(strtolower($component->get('statusMessage')))->toContain('variants');
    });

});

describe('ImageCardSkeleton Event Handling', function () {

    test('handles processing progress updates', function () {
        $image = Image::factory()->create();

        $component = Livewire::test(ImageCardSkeleton::class, ['image' => $image]);

        $component->call('updateProcessingProgress', [
            'imageId' => $image->id,
            'status' => 'processing',
            'currentAction' => 'Extracting metadata...',
            'percentage' => 50,
        ]);

        expect($component->get('status'))->toBe(ImageProcessingStatus::PROCESSING);
        expect($component->get('statusMessage'))->toBe('Extracting metadata...');
        expect($component->get('progress'))->toBe(50);
    });

    test('checks image availability when processing completes', function () {
        $image = Image::factory()->create([
            'width' => 800,
            'height' => 600,
        ]);

        $component = Livewire::test(ImageCardSkeleton::class, ['image' => $image]);

        $component->call('updateProcessingProgress', [
            'imageId' => $image->id,
            'status' => 'success',
            'currentAction' => 'Processing complete',
            'percentage' => 100,
        ]);

        expect($component->get('status'))->toBe(ImageProcessingStatus::SUCCESS);
        expect($component->get('shouldShowActualCard'))->toBeTrue();
    });

    test('ignores events for other images', function () {
        $image = Image::factory()->create();
        $otherImage = Image::factory()->create();

        $component = Livewire::test(ImageCardSkeleton::class, ['image' => $image])
            ->set('status', ImageProcessingStatus::PENDING)
            ->set('statusMessage', 'Initial message');

        $component->call('updateProcessingProgress', [
            'imageId' => $otherImage->id,
            'status' => 'success',
            'currentAction' => 'Other image done',
            'percentage' => 100,
        ]);

        expect($component->get('status'))->toBe(ImageProcessingStatus::PENDING);
        expect($component->get('statusMessage'))->toBe('Initial message');
    });

    test('handles variants generated event', function () {
        $image = Image::factory()->create();

        $component = Livewire::test(ImageCardSkeleton::class, ['image' => $image]);

        $component->call('onVariantsGenerated', [
            'original_image_id' => $image->id,
        ])->assertDispatched('check-image-ready');
    });

});

describe('ImageCardSkeleton Status Detection', function () {

    test('reads cached processing status', function () {
        $image = Image::factory()->create(['width' => 0, 'height' => 0]);
        
        // Mock the tracker to return processing status
        $tracker = $this->mock(ImageProcessingTracker::class);
        $tracker->shouldReceive('getStatus')
            ->with($image)
            ->once()
            ->andReturn(ImageProcessingStatus::PROCESSING);

        $component = Livewire::test(ImageCardSkeleton::class, ['image' => $image])
            ->assertStatus(200);

        expect($component->get('status'))->toBe(ImageProcessingStatus::PROCESSING);
    });

    test('handles missing cached status gracefully', function () {
        $image = Image::factory()->create(['width' => 0, 'height' => 0]);
        
        $tracker = $this->mock(ImageProcessingTracker::class);
        $tracker->shouldReceive('getStatus')
            ->with($image)
            ->once()
            ->andReturn(null);

        $component = Livewire::test(ImageCardSkeleton::class, ['image' => $image])
            ->assertStatus(200);

        expect($component->get('status'))->toBe(ImageProcessingStatus::PENDING);
    });

});

describe('ImageCardSkeleton Image Availability', function () {

    test('marks image as ready when dimensions available', function () {
        $image = Image::factory()->create([
            'width' => 800,
            'height' => 600,
        ]);

        $component = Livewire::test(ImageCardSkeleton::class, ['image' => $image]);
        
        $component->call('checkImageAvailability');

        expect($component->get('imageLoaded'))->toBeTrue();
        expect($component->get('shouldShowActualCard'))->toBeTrue();
    });

    test('does not mark as ready without dimensions', function () {
        $image = Image::factory()->create([
            'width' => 0,
            'height' => 0,
        ]);

        $component = Livewire::test(ImageCardSkeleton::class, ['image' => $image]);
        
        $component->call('checkImageAvailability');

        expect($component->get('imageLoaded'))->toBeFalse();
        expect($component->get('shouldShowActualCard'))->toBeFalse();
    });

    test('dispatches image ready event when complete', function () {
        $image = Image::factory()->create([
            'width' => 800,
            'height' => 600,
        ]);

        $component = Livewire::test(ImageCardSkeleton::class, ['image' => $image]);
        
        $component->call('verifyImageDownload')
            ->assertDispatched('image-ready');

        expect($component->get('imageLoaded'))->toBeTrue();
        expect($component->get('shouldShowActualCard'))->toBeTrue();
    });

});

describe('ImageCardSkeleton Listeners', function () {

    test('has correct event listeners configured', function () {
        $image = Image::factory()->create();

        $component = Livewire::test(ImageCardSkeleton::class, ['image' => $image]);
        
        $listeners = $component->instance()->getListeners();

        expect($listeners)->toHaveKey('echo:images,ImageProcessingProgress');
        expect($listeners)->toHaveKey('echo:images,ImageVariantsGenerated');
        
        expect($listeners['echo:images,ImageProcessingProgress'])->toBe('updateProcessingProgress');
        expect($listeners['echo:images,ImageVariantsGenerated'])->toBe('onVariantsGenerated');
    });

});