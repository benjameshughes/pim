<?php

use App\Models\Image;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Image Model - DAM Features', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        
        $this->product = Product::factory()->create();
        $this->variant = ProductVariant::factory()->create(['product_id' => $this->product->id]);
    });

    describe('Basic Model Operations', function () {
        it('can create an image with DAM metadata', function () {
            $image = Image::create([
                'filename' => 'test-image.jpg',
                'path' => 'images/test-image.jpg',
                'url' => 'https://example.com/test-image.jpg',
                'size' => 1024,
                'mime_type' => 'image/jpeg',
                'is_primary' => false,
                'sort_order' => 0,
                'title' => 'Test Image',
                'alt_text' => 'A test image',
                'description' => 'This is a test image for the DAM system',
                'folder' => 'test-folder',
                'tags' => ['test', 'sample', 'dam'],
                'created_by_user_id' => $this->user->id,
            ]);

            expect($image->title)->toBe('Test Image')
                ->and($image->alt_text)->toBe('A test image')
                ->and($image->description)->toBe('This is a test image for the DAM system')
                ->and($image->folder)->toBe('test-folder')
                ->and($image->tags)->toBe(['test', 'sample', 'dam'])
                ->and($image->created_by_user_id)->toBe($this->user->id);
        });

        it('casts tags as array', function () {
            $image = Image::factory()->create([
                'tags' => ['tag1', 'tag2', 'tag3'],
            ]);

            expect($image->tags)->toBeArray()
                ->and($image->tags)->toBe(['tag1', 'tag2', 'tag3']);
        });

        it('has relationship with user who created it', function () {
            $image = Image::factory()->create([
                'created_by_user_id' => $this->user->id,
            ]);

            expect($image->createdBy)->toBeInstanceOf(User::class)
                ->and($image->createdBy->id)->toBe($this->user->id);
        });
    });

    describe('DAM Scopes', function () {
        beforeEach(function () {
            // Create unattached images
            $this->unattachedImages = Image::factory()->count(3)->create([
                'imageable_type' => null,
                'imageable_id' => null,
                'created_by_user_id' => $this->user->id,
            ]);

            // Create attached images
            $this->attachedImages = Image::factory()->count(2)->create([
                'imageable_type' => Product::class,
                'imageable_id' => $this->product->id,
                'created_by_user_id' => $this->user->id,
            ]);

            // Create images in specific folder
            $this->folderImages = Image::factory()->count(2)->create([
                'folder' => 'test-folder',
                'created_by_user_id' => $this->user->id,
            ]);

            // Create images with specific tags
            $this->taggedImages = Image::factory()->count(2)->create([
                'tags' => ['product', 'hero'],
                'created_by_user_id' => $this->user->id,
            ]);
        });

        it('can filter unattached images', function () {
            $unattached = Image::unattached()->get();
            
            expect($unattached)->toHaveCount(7) // 3 + 2 + 2 unattached images
                ->and($unattached->every(fn($img) => is_null($img->imageable_type)))->toBeTrue();
        });

        it('can filter attached images', function () {
            $attached = Image::attached()->get();
            
            expect($attached)->toHaveCount(2)
                ->and($attached->every(fn($img) => !is_null($img->imageable_type)))->toBeTrue();
        });

        it('can filter images in specific folder', function () {
            $folderImages = Image::inFolder('test-folder')->get();
            
            expect($folderImages)->toHaveCount(2)
                ->and($folderImages->every(fn($img) => $img->folder === 'test-folder'))->toBeTrue();
        });

        it('can filter images with specific tag', function () {
            $taggedImages = Image::withTag('product')->get();
            
            expect($taggedImages)->toHaveCount(2)
                ->and($taggedImages->every(fn($img) => in_array('product', $img->tags ?? [])))->toBeTrue();
        });

        it('can filter images with any of specified tags', function () {
            Image::factory()->create(['tags' => ['banner']]);
            Image::factory()->create(['tags' => ['product', 'banner']]);
            
            $taggedImages = Image::withAnyTag(['product', 'banner'])->get();
            
            expect($taggedImages)->toHaveCount(4); // 2 existing + 2 new
        });

        it('can search images by title, filename, or description', function () {
            Image::factory()->create([
                'title' => 'Searchable Title',
                'filename' => 'normal-file.jpg',
                'description' => 'Normal description',
            ]);
            
            Image::factory()->create([
                'title' => 'Normal Title',
                'filename' => 'searchable-filename.jpg',
                'description' => 'Normal description',
            ]);
            
            Image::factory()->create([
                'title' => 'Normal Title',
                'filename' => 'normal-file.jpg',
                'description' => 'This contains searchable content',
            ]);

            $searchResults = Image::search('searchable')->get();
            
            expect($searchResults)->toHaveCount(3);
        });

        it('can filter images by user', function () {
            $otherUser = User::factory()->create();
            Image::factory()->count(2)->create(['created_by_user_id' => $otherUser->id]);
            
            $userImages = Image::byUser($this->user->id)->get();
            
            expect($userImages)->toHaveCount(9) // All images created in beforeEach
                ->and($userImages->every(fn($img) => $img->created_by_user_id === $this->user->id))->toBeTrue();
        });
    });

    describe('DAM Helper Methods', function () {
        beforeEach(function () {
            $this->image = Image::factory()->create([
                'tags' => ['initial', 'test'],
                'folder' => 'initial-folder',
                'imageable_type' => null,
                'imageable_id' => null,
            ]);
        });

        it('can add tags to image', function () {
            $this->image->addTag('new-tag');
            
            expect($this->image->fresh()->tags)->toContain('new-tag')
                ->and($this->image->fresh()->tags)->toHaveCount(3);
        });

        it('does not duplicate tags when adding existing tag', function () {
            $this->image->addTag('initial');
            
            expect($this->image->fresh()->tags)->toHaveCount(2);
        });

        it('can remove tags from image', function () {
            $this->image->removeTag('initial');
            
            expect($this->image->fresh()->tags)->not->toContain('initial')
                ->and($this->image->fresh()->tags)->toHaveCount(1);
        });

        it('can check if image has specific tag', function () {
            expect($this->image->hasTag('initial'))->toBeTrue()
                ->and($this->image->hasTag('nonexistent'))->toBeFalse();
        });

        it('can move image to different folder', function () {
            $this->image->moveToFolder('new-folder');
            
            expect($this->image->fresh()->folder)->toBe('new-folder');
        });

        it('can attach image to model', function () {
            $this->image->attachTo($this->product);
            
            $fresh = $this->image->fresh();
            expect($fresh->imageable_type)->toBe(Product::class)
                ->and($fresh->imageable_id)->toBe($this->product->id);
        });

        it('can detach image from model', function () {
            // First attach
            $this->image->update([
                'imageable_type' => Product::class,
                'imageable_id' => $this->product->id,
                'is_primary' => true,
            ]);
            
            // Then detach
            $this->image->detach();
            
            $fresh = $this->image->fresh();
            expect($fresh->imageable_type)->toBeNull()
                ->and($fresh->imageable_id)->toBeNull()
                ->and($fresh->is_primary)->toBeFalse();
        });

        it('can check if image is attached', function () {
            expect($this->image->isAttached())->toBeFalse();
            
            $this->image->attachTo($this->product);
            
            expect($this->image->fresh()->isAttached())->toBeTrue();
        });
    });

    describe('Display Attributes', function () {
        it('returns title as display title when title exists', function () {
            $image = Image::factory()->create([
                'title' => 'Custom Title',
                'filename' => 'test-file.jpg',
            ]);
            
            expect($image->display_title)->toBe('Custom Title');
        });

        it('returns filename without extension as display title when no title', function () {
            $image = Image::factory()->create([
                'title' => null,
                'filename' => 'test-file.jpg',
            ]);
            
            expect($image->display_title)->toBe('test-file');
        });

        it('formats file size correctly', function () {
            $image = Image::factory()->create(['size' => 1536]); // 1.5 KB
            expect($image->formatted_size)->toBe('1.5 KB');
            
            $imageMB = Image::factory()->create(['size' => 1572864]); // 1.5 MB
            expect($imageMB->formatted_size)->toBe('1.5 MB');
            
            $imageBytes = Image::factory()->create(['size' => 512]); // 512 bytes
            expect($imageBytes->formatted_size)->toBe('512 bytes');
        });
    });

    describe('Polymorphic Relationships', function () {
        it('can be attached to products', function () {
            $image = Image::factory()->create();
            $image->attachTo($this->product);
            
            expect($this->product->images)->toHaveCount(1)
                ->and($this->product->images->first()->id)->toBe($image->id);
        });

        it('can be attached to product variants', function () {
            $image = Image::factory()->create();
            $image->attachTo($this->variant);
            
            expect($this->variant->images)->toHaveCount(1)
                ->and($this->variant->images->first()->id)->toBe($image->id);
        });
    });

    describe('Factory and Edge Cases', function () {
        it('can handle null tags gracefully', function () {
            $image = Image::factory()->create(['tags' => null]);
            
            expect($image->tags)->toBeNull()
                ->and($image->hasTag('any'))->toBeFalse();
            
            $image->addTag('first-tag');
            expect($image->fresh()->tags)->toBe(['first-tag']);
        });

        it('can handle empty folder names', function () {
            $image = Image::factory()->create(['folder' => '']);
            
            expect($image->folder)->toBe('')
                ->and($image->moveToFolder('new-folder')->fresh()->folder)->toBe('new-folder');
        });
    });
});