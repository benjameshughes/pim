<?php

use App\Livewire\Images\ImageCard;
use App\Models\Image;
use App\Models\User;
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

describe('ImageCard Livewire Component', function () {

    test('component renders successfully', function () {
        $image = Image::factory()->create();

        Livewire::test(ImageCard::class, ['image' => $image])
            ->assertStatus(200);
    });

    test('displays image information', function () {
        $image = Image::factory()->create([
            'title' => 'Test Image',
            'width' => 800,
            'height' => 600,
            'size' => 1024000, // 1MB
        ]);

        $component = Livewire::test(ImageCard::class, ['image' => $image])
            ->assertStatus(200);

        expect($component->html())->toContain('Test Image');
        expect($component->html())->toContain('800×600');
    });

    test('shows processing status for unprocessed images', function () {
        // Image without dimensions = still processing
        $image = Image::factory()->create([
            'width' => 0,
            'height' => 0,
        ]);

        $component = Livewire::test(ImageCard::class, ['image' => $image])
            ->assertStatus(200);

        expect($component->get('isProcessing'))->toBeTrue();
        expect($component->get('processingStatus'))->toBe('Processing...');
    });

    test('does not show processing status for processed images', function () {
        $image = Image::factory()->create([
            'width' => 800,
            'height' => 600,
        ]);

        $component = Livewire::test(ImageCard::class, ['image' => $image])
            ->assertStatus(200);

        expect($component->get('isProcessing'))->toBeFalse();
    });

    test('can toggle variants display', function () {
        $image = Image::factory()->create();

        $component = Livewire::test(ImageCard::class, ['image' => $image])
            ->assertSet('showVariants', false)
            ->call('toggleVariants')
            ->assertSet('showVariants', true)
            ->call('toggleVariants')
            ->assertSet('showVariants', false);
    });

    test('calculates variant count correctly', function () {
        $originalImage = Image::factory()->create();
        
        // Create variant images
        Image::factory()->count(3)->create([
            'folder' => 'variants',
            'tags' => ["original-{$originalImage->id}"],
        ]);

        $component = Livewire::test(ImageCard::class, ['image' => $originalImage])
            ->assertStatus(200);

        expect($component->get('variantCount'))->toBe(3);
    });

});

describe('ImageCard Event Handling', function () {

    test('handles processing progress updates', function () {
        $image = Image::factory()->create();

        $component = Livewire::test(ImageCard::class, ['image' => $image])
            ->set('isProcessing', true);

        // Simulate processing progress event
        $component->call('updateProcessingProgress', [
            'imageId' => $image->id,
            'status' => 'processing',
            'statusLabel' => 'Extracting metadata...',
        ]);

        expect($component->get('processingStatus'))->toBe('Extracting metadata...');
    });

    test('stops processing when success status received', function () {
        $image = Image::factory()->create();

        $component = Livewire::test(ImageCard::class, ['image' => $image])
            ->set('isProcessing', true);

        // Simulate success event
        $component->call('updateProcessingProgress', [
            'imageId' => $image->id,
            'status' => 'success',
            'statusLabel' => 'Completed!',
        ]);

        expect($component->get('isProcessing'))->toBeFalse();
    });

    test('ignores events for other images', function () {
        $image = Image::factory()->create();
        $otherImage = Image::factory()->create();

        $component = Livewire::test(ImageCard::class, ['image' => $image])
            ->set('isProcessing', true)
            ->set('processingStatus', 'Initial status');

        // Simulate event for different image
        $component->call('updateProcessingProgress', [
            'imageId' => $otherImage->id,
            'status' => 'success',
            'statusLabel' => 'Other image completed',
        ]);

        // Should not change state
        expect($component->get('isProcessing'))->toBeTrue();
        expect($component->get('processingStatus'))->toBe('Initial status');
    });

    test('handles image processed legacy event', function () {
        $image = Image::factory()->create();

        $component = Livewire::test(ImageCard::class, ['image' => $image])
            ->set('isProcessing', true);

        $component->call('onImageProcessed', [
            'image_id' => $image->id,
        ]);

        expect($component->get('isProcessing'))->toBeFalse();
    });

    test('handles variants generated legacy event', function () {
        $image = Image::factory()->create();

        $component = Livewire::test(ImageCard::class, ['image' => $image])
            ->set('isProcessing', true)
            ->set('showVariants', true);

        $component->call('onVariantsGenerated', [
            'original_image_id' => $image->id,
        ]);

        expect($component->get('isProcessing'))->toBeFalse();
        expect($component->get('variants'))->toBeNull(); // Should be cleared for reload
    });

});

describe('ImageCard Listeners', function () {

    test('has correct event listeners configured', function () {
        $image = Image::factory()->create();

        $component = Livewire::test(ImageCard::class, ['image' => $image]);
        
        $listeners = $component->instance()->getListeners();

        expect($listeners)->toHaveKey('echo:images,ImageProcessingCompleted');
        expect($listeners)->toHaveKey('echo:images,ImageVariantsGenerated');
        expect($listeners)->toHaveKey('echo:images,ImageProcessingProgress');
        
        expect($listeners['echo:images,ImageProcessingCompleted'])->toBe('onImageProcessed');
        expect($listeners['echo:images,ImageVariantsGenerated'])->toBe('onVariantsGenerated');
        expect($listeners['echo:images,ImageProcessingProgress'])->toBe('updateProcessingProgress');
    });

});

describe('ImageCard Variant Management', function () {

    test('loads variants when toggling to show', function () {
        $originalImage = Image::factory()->create();
        
        // Create variant images
        $variants = Image::factory()->count(2)->create([
            'folder' => 'variants',
            'tags' => ["original-{$originalImage->id}"],
        ]);

        $component = Livewire::test(ImageCard::class, ['image' => $originalImage])
            ->assertSet('variants', null)
            ->call('toggleVariants')
            ->assertSet('showVariants', true);

        // Variants should be loaded
        expect($component->get('variants'))->not()->toBeNull();
        expect($component->get('variants'))->toHaveCount(2);
    });

    test('does not reload variants if already loaded', function () {
        $originalImage = Image::factory()->create();
        
        $component = Livewire::test(ImageCard::class, ['image' => $originalImage]);
        
        // Manually set variants
        $component->set('variants', collect(['test']));
        
        // Toggle to show - should not reload
        $component->call('toggleVariants')
            ->assertSet('showVariants', true);

        expect($component->get('variants'))->toHaveCount(1);
    });

});