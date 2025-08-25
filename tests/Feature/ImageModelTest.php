<?php

use App\Models\Image;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Image Model', function () {
    
    test('can create image with basic data', function () {
        $image = Image::create([
            'filename' => 'test-image.jpg',
            'url' => 'https://example.com/test-image.jpg',
            'size' => 1024,
            'mime_type' => 'image/jpeg',
        ]);
        
        expect($image)->toBeInstanceOf(Image::class);
        expect($image->filename)->toBe('test-image.jpg');
        expect($image->url)->toBe('https://example.com/test-image.jpg');
        expect($image->size)->toBe(1024);
        expect($image->mime_type)->toBe('image/jpeg');
    });
    
    test('can create image with metadata', function () {
        $image = Image::create([
            'filename' => 'test-image.jpg',
            'url' => 'https://example.com/test-image.jpg',
            'size' => 1024,
            'mime_type' => 'image/jpeg',
            'title' => 'Test Image',
            'alt_text' => 'A test image',
            'description' => 'This is a test image',
            'folder' => 'products',
            'tags' => ['test', 'sample'],
        ]);
        
        expect($image->title)->toBe('Test Image');
        expect($image->alt_text)->toBe('A test image');
        expect($image->description)->toBe('This is a test image');
        expect($image->folder)->toBe('products');
        expect($image->tags)->toBe(['test', 'sample']);
    });
    
});

describe('Image Scopes', function () {
    
    beforeEach(function () {
        $this->image1 = Image::factory()->create([
            'is_primary' => true,
            'sort_order' => 1,
            'folder' => 'products',
            'tags' => ['red', 'featured'],
            'title' => 'Red Product Image',
        ]);
        
        $this->image2 = Image::factory()->create([
            'is_primary' => false,
            'sort_order' => 2,
            'folder' => 'banners',
            'tags' => ['blue', 'sale'],
            'title' => 'Blue Banner Image',
        ]);
        
        $this->image3 = Image::factory()->create([
            'is_primary' => false,
            'sort_order' => 0,
            'folder' => 'products',
            'tags' => ['green'],
            'title' => 'Green Product',
            'description' => 'A beautiful green product',
        ]);
    });
    
    test('primary scope returns only primary images', function () {
        $primaryImages = Image::primary()->get();
        
        expect($primaryImages)->toHaveCount(1);
        expect($primaryImages->first()->id)->toBe($this->image1->id);
        expect($primaryImages->first()->is_primary)->toBeTrue();
    });
    
    test('ordered scope returns images in correct order', function () {
        $orderedImages = Image::ordered()->get();
        
        expect($orderedImages)->toHaveCount(3);
        expect($orderedImages->first()->id)->toBe($this->image3->id); // sort_order 0
        expect($orderedImages->get(1)->id)->toBe($this->image1->id); // sort_order 1
        expect($orderedImages->last()->id)->toBe($this->image2->id); // sort_order 2
    });
    
    test('inFolder scope filters by folder', function () {
        $productImages = Image::inFolder('products')->get();
        
        expect($productImages)->toHaveCount(2);
        expect($productImages->pluck('id'))->toContain($this->image1->id, $this->image3->id);
    });
    
    test('withTag scope filters by single tag', function () {
        $redImages = Image::withTag('red')->get();
        
        expect($redImages)->toHaveCount(1);
        expect($redImages->first()->id)->toBe($this->image1->id);
    });
    
    test('withAnyTag scope filters by multiple tags', function () {
        $colorImages = Image::withAnyTag(['red', 'blue'])->get();
        
        expect($colorImages)->toHaveCount(2);
        expect($colorImages->pluck('id'))->toContain($this->image1->id, $this->image2->id);
    });
    
    test('search scope finds images by title', function () {
        $searchResults = Image::search('Red Product')->get();
        
        expect($searchResults)->toHaveCount(1);
        expect($searchResults->first()->id)->toBe($this->image1->id);
    });
    
    test('search scope finds images by description', function () {
        $searchResults = Image::search('beautiful green')->get();
        
        expect($searchResults)->toHaveCount(1);
        expect($searchResults->first()->id)->toBe($this->image3->id);
    });
    
});

describe('Image Relationships', function () {
    
    beforeEach(function () {
        $this->image = Image::factory()->create();
        $this->product = Product::factory()->create();
        $this->variant = ProductVariant::factory()->create(['product_id' => $this->product->id]);
    });
    
    test('can attach to products via pivot table', function () {
        $this->image->products()->attach($this->product);
        
        expect($this->image->products)->toHaveCount(1);
        expect($this->image->products->first()->id)->toBe($this->product->id);
    });
    
    test('can attach to variants via pivot table', function () {
        $this->image->variants()->attach($this->variant);
        
        expect($this->image->variants)->toHaveCount(1);
        expect($this->image->variants->first()->id)->toBe($this->variant->id);
    });
    
    test('isAttached method works correctly', function () {
        expect($this->image->isAttached())->toBeFalse();
        
        $this->image->products()->attach($this->product);
        $this->image->refresh();
        
        expect($this->image->isAttached())->toBeTrue();
    });
    
    test('unattached scope finds images not linked to anything', function () {
        $attachedImage = Image::factory()->create();
        $attachedImage->products()->attach($this->product);
        
        $unattachedImages = Image::unattached()->get();
        
        expect($unattachedImages->pluck('id'))->toContain($this->image->id);
        expect($unattachedImages->pluck('id'))->not->toContain($attachedImage->id);
    });
    
    test('attached scope finds images linked to models', function () {
        $this->image->products()->attach($this->product);
        
        $attachedImages = Image::attached()->get();
        
        expect($attachedImages->pluck('id'))->toContain($this->image->id);
    });
    
});

describe('Image Helper Methods', function () {
    
    test('can add tags to image', function () {
        $image = Image::factory()->create(['tags' => ['existing']]);
        
        $image->addTag('new-tag');
        
        expect($image->fresh()->tags)->toBe(['existing', 'new-tag']);
    });
    
    test('addTag does not duplicate existing tags', function () {
        $image = Image::factory()->create(['tags' => ['existing']]);
        
        $image->addTag('existing');
        
        expect($image->fresh()->tags)->toBe(['existing']);
    });
    
    test('can remove tags from image', function () {
        $image = Image::factory()->create(['tags' => ['tag1', 'tag2', 'tag3']]);
        
        $image->removeTag('tag2');
        
        expect($image->fresh()->tags)->toBe(['tag1', 'tag3']);
    });
    
    test('hasTag method works correctly', function () {
        $image = Image::factory()->create(['tags' => ['featured', 'sale']]);
        
        expect($image->hasTag('featured'))->toBeTrue();
        expect($image->hasTag('new'))->toBeFalse();
    });
    
    test('can move image to different folder', function () {
        $image = Image::factory()->create(['folder' => 'old-folder']);
        
        $image->moveToFolder('new-folder');
        
        expect($image->fresh()->folder)->toBe('new-folder');
    });
    
    test('getDisplayTitleAttribute uses title or falls back to filename', function () {
        $imageWithTitle = Image::factory()->create([
            'title' => 'Custom Title',
            'filename' => 'test-image.jpg'
        ]);
        
        $imageWithoutTitle = Image::factory()->create([
            'title' => null,
            'filename' => 'another-image.png'
        ]);
        
        expect($imageWithTitle->display_title)->toBe('Custom Title');
        expect($imageWithoutTitle->display_title)->toBe('another-image'); // without extension
    });
    
    test('getFormattedSizeAttribute formats bytes correctly', function () {
        $smallImage = Image::factory()->create(['size' => 500]);
        $mediumImage = Image::factory()->create(['size' => 1024 * 500]); // 500KB
        $largeImage = Image::factory()->create(['size' => 1024 * 1024 * 2]); // 2MB
        
        expect($smallImage->formatted_size)->toBe('500 bytes');
        expect($mediumImage->formatted_size)->toBe('500 KB');
        expect($largeImage->formatted_size)->toBe('2 MB');
    });
    
});