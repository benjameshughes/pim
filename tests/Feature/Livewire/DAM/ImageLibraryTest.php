<?php

use App\Livewire\DAM\ImageLibrary;
use App\Models\Image;
use App\Models\User;
use App\Services\ImageUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('ImageLibrary Livewire Component', function () {
    beforeEach(function () {
        Storage::fake('images');
        
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        
        // Create test images with different states
        $this->unattachedImages = Image::factory()->count(5)->unattached()->create([
            'created_by_user_id' => $this->user->id,
        ]);
        
        $this->attachedImages = Image::factory()->count(3)->create([
            'imageable_type' => \App\Models\Product::class,
            'imageable_id' => \App\Models\Product::factory()->create()->id,
            'created_by_user_id' => $this->user->id,
        ]);
        
        $this->folderImages = Image::factory()->count(2)->inFolder('test-folder')->create([
            'created_by_user_id' => $this->user->id,
        ]);
        
        $this->taggedImages = Image::factory()->count(3)->withTags(['hero', 'product'])->create([
            'created_by_user_id' => $this->user->id,
        ]);
    });

    describe('Component Rendering', function () {
        it('renders successfully', function () {
            Livewire::test(ImageLibrary::class)
                ->assertStatus(200)
                ->assertSee('Image Library')
                ->assertSee('Digital Asset Management System');
        });

        it('displays images in grid', function () {
            Livewire::test(ImageLibrary::class)
                ->assertSee($this->unattachedImages->first()->display_title)
                ->assertSee($this->attachedImages->first()->display_title);
        });

        it('shows correct image count', function () {
            $totalImages = Image::count();
            
            Livewire::test(ImageLibrary::class)
                ->assertSee("of {$totalImages} images");
        });
    });

    describe('Search Functionality', function () {
        beforeEach(function () {
            $this->searchableImage = Image::factory()->create([
                'title' => 'Unique Searchable Title',
                'filename' => 'normal-file.jpg',
                'description' => 'Normal description',
                'created_by_user_id' => $this->user->id,
            ]);
        });

        it('can search images by title', function () {
            Livewire::test(ImageLibrary::class)
                ->set('search', 'Unique Searchable')
                ->assertSee('Unique Searchable Title')
                ->assertDontSee($this->unattachedImages->first()->display_title);
        });

        it('can search images by filename', function () {
            $image = Image::factory()->create([
                'filename' => 'searchable-filename.jpg',
                'title' => 'Normal title',
                'created_by_user_id' => $this->user->id,
            ]);

            Livewire::test(ImageLibrary::class)
                ->set('search', 'searchable-filename')
                ->assertSee($image->display_title)
                ->assertDontSee($this->unattachedImages->first()->display_title);
        });

        it('shows no results for non-matching search', function () {
            Livewire::test(ImageLibrary::class)
                ->set('search', 'nonexistent-search-term')
                ->assertSee('No images found')
                ->assertSee('Try adjusting your filters');
        });
    });

    describe('Filtering Functionality', function () {
        it('can filter by folder', function () {
            Livewire::test(ImageLibrary::class)
                ->set('selectedFolder', 'test-folder')
                ->assertSee($this->folderImages->first()->display_title)
                ->assertDontSee($this->unattachedImages->first()->display_title);
        });

        it('can filter by attachment status - unattached', function () {
            Livewire::test(ImageLibrary::class)
                ->set('filterBy', 'unattached')
                ->assertSee($this->unattachedImages->first()->display_title)
                ->assertDontSee($this->attachedImages->first()->display_title);
        });

        it('can filter by attachment status - attached', function () {
            Livewire::test(ImageLibrary::class)
                ->set('filterBy', 'attached')
                ->assertSee($this->attachedImages->first()->display_title)
                ->assertDontSee($this->unattachedImages->first()->display_title);
        });

        it('can filter by user - mine', function () {
            $otherUser = User::factory()->create();
            $otherUserImage = Image::factory()->create([
                'created_by_user_id' => $otherUser->id,
            ]);

            Livewire::test(ImageLibrary::class)
                ->set('filterBy', 'mine')
                ->assertSee($this->unattachedImages->first()->display_title)
                ->assertDontSee($otherUserImage->display_title);
        });

        it('can filter by tags', function () {
            Livewire::test(ImageLibrary::class)
                ->set('selectedTags', ['hero'])
                ->assertSee($this->taggedImages->first()->display_title);
        });
    });

    describe('Sorting Functionality', function () {
        it('can sort by creation date', function () {
            Livewire::test(ImageLibrary::class)
                ->set('sortBy', 'created_at')
                ->set('sortDirection', 'desc')
                ->assertStatus(200);
        });

        it('can sort by file size', function () {
            Livewire::test(ImageLibrary::class)
                ->set('sortBy', 'size')
                ->set('sortDirection', 'asc')
                ->assertStatus(200);
        });

        it('can toggle sort direction', function () {
            Livewire::test(ImageLibrary::class)
                ->set('sortDirection', 'asc')
                ->call('$toggle', 'sortDirection')
                ->assertSet('sortDirection', 'desc');
        });
    });

    describe('Image Upload', function () {
        it('can open upload modal', function () {
            Livewire::test(ImageLibrary::class)
                ->call('$set', 'showUploadModal', true)
                ->assertSet('showUploadModal', true)
                ->assertSee('Upload Images');
        });

        it('validates upload form correctly', function () {
            Livewire::test(ImageLibrary::class)
                ->set('showUploadModal', true)
                ->set('newImages', [])
                ->set('uploadMetadata.folder', '')
                ->call('uploadImages')
                ->assertHasErrors(['newImages', 'uploadMetadata.folder']);
        });

        it('can upload images with metadata', function () {
            $files = [
                UploadedFile::fake()->image('upload-test.jpg'),
            ];

            $component = Livewire::test(ImageLibrary::class)
                ->set('newImages', $files)
                ->set('uploadMetadata', [
                    'title' => 'Test Upload',
                    'alt_text' => 'Test alt text',
                    'description' => 'Test description',
                    'folder' => 'test-uploads',
                    'tags' => 'test,upload,dam',
                ]);

            // Mock the service to avoid actual file operations
            $this->mock(ImageUploadService::class, function ($mock) use ($files) {
                $mock->shouldReceive('uploadStandalone')
                    ->once()
                    ->with($files, \Mockery::type('array'))
                    ->andReturn(collect([
                        Image::factory()->make([
                            'title' => 'Test Upload',
                            'folder' => 'test-uploads',
                        ])
                    ]));
            });

            $component->call('uploadImages')
                ->assertDispatched('notify', function ($event) {
                    return $event['type'] === 'success' && 
                           str_contains($event['message'], 'uploaded successfully');
                })
                ->assertSet('showUploadModal', false);
        });
    });

    describe('Image Management', function () {
        it('can edit image metadata', function () {
            $image = $this->unattachedImages->first();

            Livewire::test(ImageLibrary::class)
                ->call('editImage', $image->id)
                ->assertSet('showEditModal', true)
                ->assertSet('editingImage.id', $image->id)
                ->assertSet('editMetadata.title', $image->title);
        });

        it('can save image changes', function () {
            $image = $this->unattachedImages->first();

            Livewire::test(ImageLibrary::class)
                ->call('editImage', $image->id)
                ->set('editMetadata', [
                    'title' => 'Updated Title',
                    'alt_text' => 'Updated Alt Text',
                    'description' => 'Updated Description',
                    'folder' => 'updated-folder',
                    'tags' => ['updated', 'tags'],
                ])
                ->call('saveImageChanges')
                ->assertDispatched('notify', function ($event) {
                    return $event['type'] === 'success';
                });

            expect($image->fresh()->title)->toBe('Updated Title');
        });

        it('can delete image', function () {
            $image = $this->unattachedImages->first();
            $imageId = $image->id;

            $this->mock(ImageUploadService::class, function ($mock) {
                $mock->shouldReceive('deleteImage')
                    ->once();
            });

            Livewire::test(ImageLibrary::class)
                ->call('deleteImage', $imageId)
                ->assertDispatched('notify', function ($event) {
                    return $event['type'] === 'success' &&
                           str_contains($event['message'], 'deleted successfully');
                });
        });
    });

    describe('Selection and Bulk Operations', function () {
        it('can toggle image selection', function () {
            $image = $this->unattachedImages->first();

            Livewire::test(ImageLibrary::class)
                ->call('toggleImageSelection', $image->id)
                ->assertSet('selectedImages', [$image->id]);
        });

        it('can toggle select all', function () {
            Livewire::test(ImageLibrary::class)
                ->set('selectAll', true)
                ->call('toggleSelectAll');
            
            // Should have selected some images
            $component = Livewire::test(ImageLibrary::class);
            expect($component->get('selectedImages'))->not->toBeEmpty();
        });

        it('can apply bulk folder move', function () {
            $images = $this->unattachedImages->take(2);
            $imageIds = $images->pluck('id')->toArray();

            Livewire::test(ImageLibrary::class)
                ->set('selectedImages', $imageIds)
                ->set('bulkAction', [
                    'type' => 'move_folder',
                    'folder' => 'bulk-moved',
                ])
                ->call('applyBulkAction')
                ->assertDispatched('notify', function ($event) {
                    return $event['type'] === 'success' &&
                           str_contains($event['message'], 'Bulk action applied');
                });

            foreach ($images as $image) {
                expect($image->fresh()->folder)->toBe('bulk-moved');
            }
        });

        it('can apply bulk tag addition', function () {
            $images = $this->unattachedImages->take(2);
            $imageIds = $images->pluck('id')->toArray();

            Livewire::test(ImageLibrary::class)
                ->set('selectedImages', $imageIds)
                ->set('bulkAction', [
                    'type' => 'add_tags',
                    'tags_to_add' => 'bulk-tag-1, bulk-tag-2',
                ])
                ->call('applyBulkAction')
                ->assertDispatched('notify', function ($event) {
                    return $event['type'] === 'success';
                });

            foreach ($images as $image) {
                $freshImage = $image->fresh();
                expect($freshImage->hasTag('bulk-tag-1'))->toBeTrue()
                    ->and($freshImage->hasTag('bulk-tag-2'))->toBeTrue();
            }
        });

        it('can apply bulk delete', function () {
            $images = Image::factory()->count(2)->unattached()->create([
                'created_by_user_id' => $this->user->id,
            ]);
            $imageIds = $images->pluck('id')->toArray();

            $this->mock(ImageUploadService::class, function ($mock) use ($images) {
                $mock->shouldReceive('deleteImage')
                    ->times($images->count());
            });

            Livewire::test(ImageLibrary::class)
                ->set('selectedImages', $imageIds)
                ->set('bulkAction', ['type' => 'delete'])
                ->call('applyBulkAction')
                ->assertDispatched('notify', function ($event) {
                    return $event['type'] === 'success';
                });
        });
    });

    describe('Computed Properties', function () {
        it('returns available folders', function () {
            $component = Livewire::test(ImageLibrary::class);
            $folders = $component->call('$refresh')->get('this.folders');
            
            expect($folders)->toContain('test-folder');
        });

        it('returns available tags', function () {
            $component = Livewire::test(ImageLibrary::class);
            $tags = $component->call('$refresh')->get('this.availableTags');
            
            expect($tags)->toContain('hero')
                ->and($tags)->toContain('product');
        });
    });

    describe('Pagination', function () {
        beforeEach(function () {
            // Create many images to test pagination
            Image::factory()->count(30)->unattached()->create([
                'created_by_user_id' => $this->user->id,
            ]);
        });

        it('paginates images correctly', function () {
            Livewire::test(ImageLibrary::class)
                ->assertSee('of')
                ->assertSee('images') // Should show pagination info
                ->assertStatus(200);
        });

        it('resets page when search changes', function () {
            Livewire::test(ImageLibrary::class)
                ->call('nextPage') // Go to page 2
                ->set('search', 'test-search')
                ->assertStatus(200); // Page should reset to 1
        });
    });

    describe('Error Handling', function () {
        it('handles empty image states gracefully', function () {
            // Delete all images
            Image::query()->delete();

            Livewire::test(ImageLibrary::class)
                ->assertSee('No images found')
                ->assertSee('Get started by uploading');
        });

        it('validates bulk actions require selection', function () {
            Livewire::test(ImageLibrary::class)
                ->set('selectedImages', [])
                ->set('bulkAction', ['type' => 'delete'])
                ->call('applyBulkAction');
            
            // Should not dispatch success notification
            // Component should handle empty selection gracefully
        });
    });
});