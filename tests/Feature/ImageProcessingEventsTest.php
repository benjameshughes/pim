<?php

use App\Events\Images\ImageProcessingProgress;
use App\Events\Images\ImageProcessingCompleted;
use App\Events\Images\ImageVariantsGenerated;
use App\Enums\ImageProcessingStatus;
use App\Models\Image;
use Illuminate\Broadcasting\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

describe('ImageProcessingProgress Event', function () {

    test('can be constructed with required parameters', function () {
        $event = new ImageProcessingProgress(
            imageId: 123,
            status: ImageProcessingStatus::PROCESSING,
            currentAction: 'Extracting metadata...',
            percentage: 50
        );

        expect($event->imageId)->toBe(123);
        expect($event->status)->toBe(ImageProcessingStatus::PROCESSING);
        expect($event->currentAction)->toBe('Extracting metadata...');
        expect($event->percentage)->toBe(50);
    });

    test('broadcasts on correct channel', function () {
        $event = new ImageProcessingProgress(
            imageId: 123,
            status: ImageProcessingStatus::PROCESSING,
            currentAction: 'Processing...',
            percentage: 50
        );

        $channels = $event->broadcastOn();

        expect($channels)->toHaveCount(1);
        expect($channels[0])->toBeInstanceOf(Channel::class);
        expect($channels[0]->name)->toBe('images');
    });

    test('has correct broadcast name', function () {
        $event = new ImageProcessingProgress(
            imageId: 123,
            status: ImageProcessingStatus::PROCESSING,
            currentAction: 'Processing...',
            percentage: 50
        );

        expect($event->broadcastAs())->toBe('ImageProcessingProgress');
    });

    test('includes correct data in broadcast', function () {
        $event = new ImageProcessingProgress(
            imageId: 123,
            status: ImageProcessingStatus::SUCCESS,
            currentAction: 'Processing complete',
            percentage: 100
        );

        $broadcastData = $event->broadcastWith();

        expect($broadcastData)->toHaveKey('imageId', 123);
        expect($broadcastData)->toHaveKey('status', 'success');
        expect($broadcastData)->toHaveKey('statusLabel', 'Success');
        expect($broadcastData)->toHaveKey('statusColor', 'green');
        expect($broadcastData)->toHaveKey('statusIcon', 'check-circle');
        expect($broadcastData)->toHaveKey('currentAction', 'Processing complete');
        expect($broadcastData)->toHaveKey('percentage', 100);
    });

    test('calculates percentage correctly', function () {
        $event = new ImageProcessingProgress(
            imageId: 123,
            status: ImageProcessingStatus::PROCESSING,
            currentAction: 'Processing...',
            percentage: 33
        );

        $broadcastData = $event->broadcastWith();

        expect($broadcastData['percentage'])->toBe(33);
    });

    test('handles zero percentage gracefully', function () {
        $event = new ImageProcessingProgress(
            imageId: 123,
            status: ImageProcessingStatus::PENDING,
            currentAction: 'Queued...',
            percentage: 0
        );

        $broadcastData = $event->broadcastWith();

        expect($broadcastData['percentage'])->toBe(0);
    });

});

describe('ImageProcessingCompleted Event', function () {

    test('broadcasts on correct channel', function () {
        $image = Image::factory()->create();
        $event = new ImageProcessingCompleted($image);

        $channels = $event->broadcastOn();

        expect($channels)->toHaveCount(1);
        expect($channels[0])->toBeInstanceOf(Channel::class);
        expect($channels[0]->name)->toBe('images');
    });

    test('includes image data in broadcast', function () {
        $image = Image::factory()->create([
            'title' => 'Test Image',
            'width' => 800,
            'height' => 600,
        ]);

        $event = new ImageProcessingCompleted($image);
        $broadcastData = $event->broadcastWith();

        expect($broadcastData)->toHaveKey('image_id', $image->id);
        expect($broadcastData)->toHaveKey('status', 'processed');
        expect($broadcastData)->toHaveKey('message');
        expect($broadcastData)->toHaveKey('image');
        expect($broadcastData['image'])->toHaveKey('width', 800);
        expect($broadcastData['image'])->toHaveKey('height', 600);
    });

});

describe('ImageVariantsGenerated Event', function () {

    test('can be constructed with image and variants', function () {
        $originalImage = Image::factory()->create();
        $variants = Image::factory()->count(3)->create();

        $event = new ImageVariantsGenerated($originalImage, $variants->toArray());

        expect($event->originalImage->id)->toBe($originalImage->id);
        expect($event->generatedVariants)->toHaveCount(3);
    });

    test('broadcasts on correct channel', function () {
        $image = Image::factory()->create();
        $event = new ImageVariantsGenerated($image, []);

        $channels = $event->broadcastOn();

        expect($channels)->toHaveCount(1);
        expect($channels[0])->toBeInstanceOf(Channel::class);
        expect($channels[0]->name)->toBe('images');
    });

    test('includes variant data in broadcast', function () {
        $originalImage = Image::factory()->create(['title' => 'Original Image']);
        $variants = Image::factory()->count(2)->create();

        $event = new ImageVariantsGenerated($originalImage, $variants->all());
        $broadcastData = $event->broadcastWith();

        expect($broadcastData)->toHaveKey('original_image_id', $originalImage->id);
        expect($broadcastData)->toHaveKey('status', 'variants_generated');
        expect($broadcastData)->toHaveKey('variant_count', 2);
        expect($broadcastData)->toHaveKey('variants');
        expect($broadcastData['message'])->toContain('2 variants generated');
    });

});

describe('Event Integration', function () {

    test('events can be dispatched', function () {
        Event::fake();

        $image = Image::factory()->create();

        ImageProcessingProgress::dispatch(
            $image->id,
            ImageProcessingStatus::PROCESSING,
            'Testing...'
        );

        ImageProcessingCompleted::dispatch($image);

        ImageVariantsGenerated::dispatch($image, []);

        Event::assertDispatched(ImageProcessingProgress::class);
        Event::assertDispatched(ImageProcessingCompleted::class);
        Event::assertDispatched(ImageVariantsGenerated::class);
    });

    test('progress event dispatched with correct parameters', function () {
        Event::fake();

        $imageId = 123;
        $status = ImageProcessingStatus::UPLOADING;
        $action = 'Uploading to storage...';

        ImageProcessingProgress::dispatch($imageId, $status, $action, 0, 1);

        Event::assertDispatched(ImageProcessingProgress::class, function ($event) use ($imageId, $status, $action) {
            return $event->imageId === $imageId
                && $event->status === $status
                && $event->currentAction === $action;
        });
    });

});