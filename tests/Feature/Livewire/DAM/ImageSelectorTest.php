<?php

use App\Livewire\DAM\ImageSelector;
use App\Models\Image;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('ImageSelector Livewire Component', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        
        $this->product = Product::factory()->create();
        $this->variant = ProductVariant::factory()->create(['product_id' => $this->product->id]);
        
        // Create various images for testing
        $this->unattachedImages = Image::factory()->count(5)->unattached()->create([
            'created_by_user_id' => $this->user->id,
        ]);
        
        // Create images already attached to different models
        $this->attachedToOther = Image::factory()->count(3)->create([
            'imageable_type' => Product::class,
            'imageable_id' => Product::factory()->create()->id,
            'created_by_user_id' => $this->user->id,
        ]);
        
        // Create images with different folders and tags
        $this->folderImages = Image::factory()->count(2)->inFolder('products')->unattached()->create([
            'created_by_user_id' => $this->user->id,
        ]);
        
        $this->taggedImages = Image::factory()->count(3)->withTags(['hero', 'gallery'])->unattached()->create([
            'created_by_user_id' => $this->user->id,
        ]);
    });

    describe('Component Initialization', function () {
        it('renders in closed state by default', function () {
            Livewire::test(ImageSelector::class)
                ->assertSet('show', false)
                ->assertDontSee('Link Images to Product');
        });

        it('opens when receiving open-image-selector event', function () {
            Livewire::test(ImageSelector::class)
                ->dispatch('open-image-selector', 
                    targetType: 'product', 
                    targetId: $this->product->id,
                    options: ['maxSelection' => 5, 'allowMultiple' => true]
                )
                ->assertSet('show', true)
                ->assertSet('targetType', 'product')
                ->assertSet('targetId', $this->product->id)
                ->assertSet('maxSelection', 5)
                ->assertSet('allowMultiple', true)
                ->assertSee('Link Images to Product');
        });

        it('closes when receiving close-image-selector event', function () {
            Livewire::test(ImageSelector::class)
                ->dispatch('open-image-selector', 
                    targetType: 'product', 
                    targetId: $this->product->id
                )
                ->assertSet('show', true)
                ->call('closeSelector')
                ->assertSet('show', false);
        });

        it('resets state when opening', function () {
            $component = Livewire::test(ImageSelector::class)
                ->set('selectedImageIds', [1, 2, 3])
                ->set('search', 'previous search')
                ->dispatch('open-image-selector', 
                    targetType: 'product', 
                    targetId: $this->product->id
                );

            $component->assertSet('selectedImageIds', [])
                ->assertSet('search', '');
        });
    });

    describe('Target Model Loading', function () {
        it('loads product as target model', function () {
            $component = Livewire::test(ImageSelector::class)
                ->dispatch('open-image-selector', 
                    targetType: 'product', 
                    targetId: $this->product->id
                );

            expect($component->get('targetModel')->id)->toBe($this->product->id);
        });

        it('loads variant as target model', function () {
            $component = Livewire::test(ImageSelector::class)
                ->dispatch('open-image-selector', 
                    targetType: 'variant', 
                    targetId: $this->variant->id
                );

            expect($component->get('targetModel')->id)->toBe($this->variant->id);
        });

        it('throws exception for invalid target type', function () {
            expect(fn() => 
                Livewire::test(ImageSelector::class)
                    ->dispatch('open-image-selector', 
                        targetType: 'invalid', 
                        targetId: 1
                    )
            )->toThrow(InvalidArgumentException::class, 'Invalid target type: invalid');
        });
    });

    describe('Image Filtering and Display', function () {
        it('shows only unattached images by default', function () {
            Livewire::test(ImageSelector::class)
                ->dispatch('open-image-selector', 
                    targetType: 'product', 
                    targetId: $this->product->id
                )
                ->assertSee($this->unattachedImages->first()->display_title)
                ->assertDontSee($this->attachedToOther->first()->display_title);
        });

        it('can search images by title and filename', function () {
            $searchableImage = Image::factory()->unattached()->create([
                'title' => 'Unique Searchable Title',
                'created_by_user_id' => $this->user->id,
            ]);

            Livewire::test(ImageSelector::class)
                ->dispatch('open-image-selector', 
                    targetType: 'product', 
                    targetId: $this->product->id
                )
                ->set('search', 'Unique Searchable')
                ->assertSee('Unique Searchable Title')
                ->assertDontSee($this->unattachedImages->first()->display_title);
        });

        it('can filter by folder', function () {
            Livewire::test(ImageSelector::class)
                ->dispatch('open-image-selector', 
                    targetType: 'product', 
                    targetId: $this->product->id
                )
                ->set('selectedFolder', 'products')
                ->assertSee($this->folderImages->first()->display_title);
        });

        it('can filter by tags', function () {
            Livewire::test(ImageSelector::class)
                ->dispatch('open-image-selector', 
                    targetType: 'product', 
                    targetId: $this->product->id
                )
                ->set('selectedTags', ['hero'])
                ->assertSee($this->taggedImages->first()->display_title);
        });

        it('excludes images already attached to the target model', function () {
            // Attach an image to the target product
            $attachedImage = $this->unattachedImages->first();
            $attachedImage->attachTo($this->product);

            Livewire::test(ImageSelector::class)
                ->dispatch('open-image-selector', 
                    targetType: 'product', 
                    targetId: $this->product->id
                )
                ->assertDontSee($attachedImage->display_title)
                ->assertSee($this->unattachedImages->skip(1)->first()->display_title);
        });
    });

    describe('Image Selection', function () {
        it('can toggle image selection', function () {
            $image = $this->unattachedImages->first();

            Livewire::test(ImageSelector::class)
                ->dispatch('open-image-selector', 
                    targetType: 'product', 
                    targetId: $this->product->id
                )
                ->call('toggleImageSelection', $image->id)
                ->assertSet('selectedImageIds', [$image->id]);
        });

        it('can deselect image by toggling again', function () {
            $image = $this->unattachedImages->first();

            Livewire::test(ImageSelector::class)
                ->dispatch('open-image-selector', 
                    targetType: 'product', 
                    targetId: $this->product->id
                )
                ->call('toggleImageSelection', $image->id)
                ->call('toggleImageSelection', $image->id)
                ->assertSet('selectedImageIds', []);
        });

        it('respects maximum selection limit', function () {
            $images = $this->unattachedImages->take(3);

            $component = Livewire::test(ImageSelector::class)
                ->dispatch('open-image-selector', 
                    targetType: 'product', 
                    targetId: $this->product->id,
                    options: ['maxSelection' => 2, 'allowMultiple' => true]
                );

            // Select first two images
            $component->call('toggleImageSelection', $images[0]->id)
                ->call('toggleImageSelection', $images[1]->id)
                ->assertCount('selectedImageIds', 2);

            // Trying to select third should not work
            $component->call('toggleImageSelection', $images[2]->id)
                ->assertCount('selectedImageIds', 2)
                ->assertNotContains('selectedImageIds', $images[2]->id);
        });

        it('only allows single selection when allowMultiple is false', function () {
            $images = $this->unattachedImages->take(2);

            $component = Livewire::test(ImageSelector::class)
                ->dispatch('open-image-selector', 
                    targetType: 'product', 
                    targetId: $this->product->id,
                    options: ['allowMultiple' => false]
                );

            // Select first image
            $component->call('toggleImageSelection', $images[0]->id)
                ->assertSet('selectedImageIds', [$images[0]->id]);

            // Select second image should replace first
            $component->call('toggleImageSelection', $images[1]->id)
                ->assertSet('selectedImageIds', [$images[1]->id]);
        });
    });

    describe('Image Linking', function () {
        it('can confirm selection and link images to product', function () {
            $images = $this->unattachedImages->take(2);
            $imageIds = $images->pluck('id')->toArray();

            Livewire::test(ImageSelector::class)
                ->dispatch('open-image-selector', 
                    targetType: 'product', 
                    targetId: $this->product->id,
                    options: ['setPrimaryOnSingle' => true]
                )
                ->set('selectedImageIds', $imageIds)
                ->call('confirmSelection')
                ->assertDispatched('images-linked', function ($event) use ($imageIds) {
                    return $event['count'] === 2 && 
                           $event['targetType'] === 'product' &&
                           $event['targetId'] === $this->product->id;
                })
                ->assertDispatched('notify', function ($event) {
                    return $event['type'] === 'success' &&
                           str_contains($event['message'], '2 image(s) linked successfully');
                })
                ->assertSet('show', false);

            // Verify images are attached
            foreach ($images as $image) {
                $fresh = $image->fresh();
                expect($fresh->imageable_type)->toBe(Product::class)
                    ->and($fresh->imageable_id)->toBe($this->product->id);
            }

            // First image should be set as primary
            expect($images->first()->fresh()->is_primary)->toBeTrue();
        });

        it('can link images to variant', function () {
            $image = $this->unattachedImages->first();

            Livewire::test(ImageSelector::class)
                ->dispatch('open-image-selector', 
                    targetType: 'variant', 
                    targetId: $this->variant->id
                )
                ->call('toggleImageSelection', $image->id)
                ->call('confirmSelection')
                ->assertDispatched('images-linked')
                ->assertSet('show', false);

            // Verify image is attached to variant
            $fresh = $image->fresh();
            expect($fresh->imageable_type)->toBe(ProductVariant::class)
                ->and($fresh->imageable_id)->toBe($this->variant->id);
        });

        it('does not set primary if product already has primary image', function () {
            // Create existing primary image
            $existingPrimary = Image::factory()->create([
                'imageable_type' => Product::class,
                'imageable_id' => $this->product->id,
                'is_primary' => true,
                'created_by_user_id' => $this->user->id,
            ]);

            $newImage = $this->unattachedImages->first();

            Livewire::test(ImageSelector::class)
                ->dispatch('open-image-selector', 
                    targetType: 'product', 
                    targetId: $this->product->id,
                    options: ['setPrimaryOnSingle' => true]
                )
                ->call('toggleImageSelection', $newImage->id)
                ->call('confirmSelection');

            // Existing primary should remain primary
            expect($existingPrimary->fresh()->is_primary)->toBeTrue()
                ->and($newImage->fresh()->is_primary)->toBeFalse();
        });

        it('does nothing when no images selected', function () {
            Livewire::test(ImageSelector::class)
                ->dispatch('open-image-selector', 
                    targetType: 'product', 
                    targetId: $this->product->id
                )
                ->set('selectedImageIds', [])
                ->call('confirmSelection')
                ->assertNotDispatched('images-linked')
                ->assertSet('show', true); // Should stay open
        });
    });

    describe('Computed Properties', function () {
        it('calculates selection info correctly', function () {
            $component = Livewire::test(ImageSelector::class)
                ->dispatch('open-image-selector', 
                    targetType: 'product', 
                    targetId: $this->product->id,
                    options: ['maxSelection' => 5]
                )
                ->set('selectedImageIds', [1, 2, 3]);

            $selectionInfo = $component->get('this.selectionInfo');
            
            expect($selectionInfo['count'])->toBe(3)
                ->and($selectionInfo['max'])->toBe(5)
                ->and($selectionInfo['canSelectMore'])->toBeTrue()
                ->and($selectionInfo['hasSelection'])->toBeTrue();
        });

        it('returns available folders', function () {
            $component = Livewire::test(ImageSelector::class)
                ->dispatch('open-image-selector', 
                    targetType: 'product', 
                    targetId: $this->product->id
                );

            $folders = $component->get('this.folders');
            expect($folders)->toContain('products');
        });

        it('returns available tags', function () {
            $component = Livewire::test(ImageSelector::class)
                ->dispatch('open-image-selector', 
                    targetType: 'product', 
                    targetId: $this->product->id
                );

            $tags = $component->get('this.availableTags');
            expect($tags)->toContain('hero')
                ->and($tags)->toContain('gallery');
        });
    });

    describe('Pagination and Performance', function () {
        beforeEach(function () {
            // Create many unattached images for pagination testing
            Image::factory()->count(20)->unattached()->create([
                'created_by_user_id' => $this->user->id,
            ]);
        });

        it('paginates images correctly', function () {
            Livewire::test(ImageSelector::class)
                ->dispatch('open-image-selector', 
                    targetType: 'product', 
                    targetId: $this->product->id
                )
                ->assertSee('of')
                ->assertSee('images'); // Pagination info
        });

        it('resets page when search changes', function () {
            Livewire::test(ImageSelector::class)
                ->dispatch('open-image-selector', 
                    targetType: 'product', 
                    targetId: $this->product->id
                )
                ->call('nextPage')
                ->set('search', 'test')
                ->assertStatus(200);
        });
    });

    describe('Edge Cases and Error Handling', function () {
        it('handles empty image results gracefully', function () {
            // Delete all unattached images
            Image::unattached()->delete();

            Livewire::test(ImageSelector::class)
                ->dispatch('open-image-selector', 
                    targetType: 'product', 
                    targetId: $this->product->id
                )
                ->assertSee('No images found')
                ->assertSee('No images available to link');
        });

        it('handles invalid target model ID gracefully', function () {
            expect(fn() => 
                Livewire::test(ImageSelector::class)
                    ->dispatch('open-image-selector', 
                        targetType: 'product', 
                        targetId: 99999 // Non-existent ID
                    )
            )->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        });

        it('maintains selection when filter changes', function () {
            $image = $this->unattachedImages->first();

            Livewire::test(ImageSelector::class)
                ->dispatch('open-image-selector', 
                    targetType: 'product', 
                    targetId: $this->product->id
                )
                ->call('toggleImageSelection', $image->id)
                ->set('selectedFolder', 'different-folder')
                ->assertContains('selectedImageIds', $image->id);
        });
    });

    describe('User Experience Features', function () {
        it('shows correct modal title for different target types', function () {
            // Test product
            Livewire::test(ImageSelector::class)
                ->dispatch('open-image-selector', 
                    targetType: 'product', 
                    targetId: $this->product->id
                )
                ->assertSee('Link Images to Product');

            // Test variant
            Livewire::test(ImageSelector::class)
                ->dispatch('open-image-selector', 
                    targetType: 'variant', 
                    targetId: $this->variant->id
                )
                ->assertSee('Link Images to Variant');
        });

        it('shows selection limits in interface', function () {
            Livewire::test(ImageSelector::class)
                ->dispatch('open-image-selector', 
                    targetType: 'product', 
                    targetId: $this->product->id,
                    options: ['maxSelection' => 3, 'allowMultiple' => true]
                )
                ->assertSee('up to 3 images');

            Livewire::test(ImageSelector::class)
                ->dispatch('open-image-selector', 
                    targetType: 'product', 
                    targetId: $this->product->id,
                    options: ['allowMultiple' => false]
                )
                ->assertSee('one image');
        });

        it('provides clear selection status feedback', function () {
            $image = $this->unattachedImages->first();

            $component = Livewire::test(ImageSelector::class)
                ->dispatch('open-image-selector', 
                    targetType: 'product', 
                    targetId: $this->product->id,
                    options: ['maxSelection' => 5]
                )
                ->call('toggleImageSelection', $image->id);

            $selectionInfo = $component->get('this.selectionInfo');
            expect($selectionInfo['count'])->toBe(1)
                ->and($selectionInfo['max'])->toBe(5);
        });
    });
});