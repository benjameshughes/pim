<?php

use App\Models\Image;
use App\Models\User;
use App\Services\ImageUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('DAM System Debugging', function () {
    beforeEach(function () {
        Storage::fake('images'); // Fake R2 storage for testing
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    it('can create image with correct database fields', function () {
        // Test direct Image model creation to verify database schema
        $image = Image::create([
            'filename' => 'test-image.jpg',
            'url' => 'https://example.com/test-image.jpg',
            'file_size' => 1024, // Note: migration expects 'file_size', not 'size'
            'mime_type' => 'image/jpeg',
            'title' => 'Test Image',
            'folder' => 'testing',
            'user_id' => $this->user->id, // Note: migration expects 'user_id', not 'created_by_user_id'
        ]);

        expect(Image::count())->toBe(1);
        expect($image->filename)->toBe('test-image.jpg');
        expect($image->user_id)->toBe($this->user->id);
    });

    it('reveals image upload service issues', function () {
        $file = UploadedFile::fake()->image('test.jpg', 800, 600);
        
        $service = new ImageUploadService();
        
        // This should reveal the exact error when uploading
        expect(fn() => $service->uploadStandalone([$file], [
            'title' => 'Debug Test',
            'folder' => 'debug',
        ]))->toThrow(Exception::class);
    });

    it('tests livewire image library component directly', function () {
        // Test the actual Livewire component
        $component = Livewire::test('d-a-m.image-library');
        
        // Should load without errors
        $component->assertOk();
        
        // Check initial state
        expect($component->get('images'))->toHaveCount(0);
        expect($component->get('showUploadModal'))->toBe(false);
    });

    it('tests image upload through livewire component', function () {
        $file = UploadedFile::fake()->image('livewire-test.jpg', 600, 400);
        
        $component = Livewire::test('d-a-m.image-library')
            ->set('newImages', [$file])
            ->set('uploadMetadata', [
                'title' => 'Livewire Test',
                'folder' => 'livewire-testing',
                'tags' => ['test', 'debugging'],
            ]);
            
        // This should reveal the specific upload error
        try {
            $component->call('uploadImages');
            $this->fail('Expected upload to fail and reveal the error');
        } catch (Exception $e) {
            // Log the actual error for debugging
            dump("Upload error: " . $e->getMessage());
            expect($e->getMessage())->toContain(''); // Will show the actual error
        }
    });

    it('checks database table structure', function () {
        // Test what columns actually exist
        $columns = \DB::getSchemaBuilder()->getColumnListing('images');
        
        dump("Actual images table columns:", $columns);
        
        // Check for expected columns
        expect($columns)->toContain('filename');
        expect($columns)->toContain('url');
        expect($columns)->toContain('file_size'); // This should exist
        expect($columns)->toContain('user_id'); // This should exist, not 'created_by_user_id'
        
        // Check polymorphic columns
        if (in_array('attachable_type', $columns)) {
            expect($columns)->toContain('attachable_id');
            dump("Migration uses 'attachable_*' columns");
        } elseif (in_array('imageable_type', $columns)) {
            expect($columns)->toContain('imageable_id');
            dump("Migration uses 'imageable_*' columns");
        }
    });

    it('identifies model vs migration mismatches', function () {
        $image = new Image();
        $fillable = $image->getFillable();
        
        dump("Image model fillable fields:", $fillable);
        
        $columns = \DB::getSchemaBuilder()->getColumnListing('images');
        dump("Database columns:", $columns);
        
        // Check for mismatches
        $mismatches = [];
        foreach ($fillable as $field) {
            if (!in_array($field, $columns)) {
                $mismatches[] = $field;
            }
        }
        
        if (!empty($mismatches)) {
            dump("Fields in model but not in database:", $mismatches);
        }
        
        // This test will help identify the specific mismatches
        expect($mismatches)->toBe([]); // This will fail and show the mismatches
    });
});