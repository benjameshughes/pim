<?php

use App\Models\Image;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Image-Product Relationships', function () {

    beforeEach(function () {
        $this->product = Product::factory()->create();
        $this->image1 = Image::factory()->create(['title' => 'Product Image 1']);
        $this->image2 = Image::factory()->create(['title' => 'Product Image 2']);
        $this->image3 = Image::factory()->create(['title' => 'Unattached Image']);
    });

    test('can attach images to product', function () {
        $this->product->images()->attach([$this->image1->id, $this->image2->id]);

        expect($this->product->images)->toHaveCount(2);
        expect($this->product->images->pluck('id'))->toContain($this->image1->id, $this->image2->id);
    });

    test('can attach product to image', function () {
        $this->image1->products()->attach($this->product);

        expect($this->image1->products)->toHaveCount(1);
        expect($this->image1->products->first()->id)->toBe($this->product->id);
    });

    test('can detach images from product', function () {
        $this->product->images()->attach([$this->image1->id, $this->image2->id]);

        $this->product->images()->detach($this->image1->id);

        expect($this->product->images)->toHaveCount(1);
        expect($this->product->images->first()->id)->toBe($this->image2->id);
    });

    test('can sync images with product', function () {
        $this->product->images()->attach($this->image1->id);

        // Sync should replace existing attachments
        $this->product->images()->sync([$this->image2->id, $this->image3->id]);

        expect($this->product->images)->toHaveCount(2);
        expect($this->product->images->pluck('id'))->toContain($this->image2->id, $this->image3->id);
        expect($this->product->images->pluck('id'))->not->toContain($this->image1->id);
    });

    test('image knows which products it belongs to', function () {
        $product2 = Product::factory()->create();

        $this->image1->products()->attach([$this->product->id, $product2->id]);

        expect($this->image1->products)->toHaveCount(2);
        expect($this->image1->products->pluck('id'))->toContain($this->product->id, $product2->id);
    });

});

describe('Image-ProductVariant Relationships', function () {

    beforeEach(function () {
        $this->product = Product::factory()->create();
        $this->variant1 = ProductVariant::factory()->create(['product_id' => $this->product->id]);
        $this->variant2 = ProductVariant::factory()->create(['product_id' => $this->product->id]);
        $this->image1 = Image::factory()->create(['title' => 'Variant Image 1']);
        $this->image2 = Image::factory()->create(['title' => 'Variant Image 2']);
    });

    test('can attach images to variant', function () {
        $this->variant1->images()->attach([$this->image1->id, $this->image2->id]);

        expect($this->variant1->images)->toHaveCount(2);
        expect($this->variant1->images->pluck('id'))->toContain($this->image1->id, $this->image2->id);
    });

    test('can attach variant to image', function () {
        $this->image1->variants()->attach($this->variant1);

        expect($this->image1->variants)->toHaveCount(1);
        expect($this->image1->variants->first()->id)->toBe($this->variant1->id);
    });

    test('can detach images from variant', function () {
        $this->variant1->images()->attach([$this->image1->id, $this->image2->id]);

        $this->variant1->images()->detach($this->image1->id);

        expect($this->variant1->images)->toHaveCount(1);
        expect($this->variant1->images->first()->id)->toBe($this->image2->id);
    });

    test('image can belong to multiple variants', function () {
        $this->image1->variants()->attach([$this->variant1->id, $this->variant2->id]);

        expect($this->image1->variants)->toHaveCount(2);
        expect($this->image1->variants->pluck('id'))->toContain($this->variant1->id, $this->variant2->id);
    });

    test('variant can have images from different sources', function () {
        $productImage = Image::factory()->create(['title' => 'Product Level Image']);
        $variantImage = Image::factory()->create(['title' => 'Variant Specific Image']);

        // Attach same image to both product and variant
        $this->product->images()->attach($productImage);
        $this->variant1->images()->attach([$productImage, $variantImage]);

        expect($this->variant1->images)->toHaveCount(2);
        expect($this->product->images)->toHaveCount(1);

        // Image should show it belongs to both product and variant
        expect($productImage->products)->toHaveCount(1);
        expect($productImage->variants)->toHaveCount(1);
    });

});

describe('Image Attachment Detection', function () {

    beforeEach(function () {
        $this->product = Product::factory()->create();
        $this->variant = ProductVariant::factory()->create(['product_id' => $this->product->id]);
        $this->attachedImage = Image::factory()->create(['title' => 'Attached Image']);
        $this->unattachedImage = Image::factory()->create(['title' => 'Unattached Image']);
    });

    test('isAttached method detects product attachment', function () {
        expect($this->attachedImage->isAttached())->toBeFalse();

        $this->attachedImage->products()->attach($this->product);
        $this->attachedImage->refresh();

        expect($this->attachedImage->isAttached())->toBeTrue();
    });

    test('isAttached method detects variant attachment', function () {
        expect($this->attachedImage->isAttached())->toBeFalse();

        $this->attachedImage->variants()->attach($this->variant);
        $this->attachedImage->refresh();

        expect($this->attachedImage->isAttached())->toBeTrue();
    });

    test('isAttached method detects mixed attachments', function () {
        $this->attachedImage->products()->attach($this->product);
        $this->attachedImage->variants()->attach($this->variant);
        $this->attachedImage->refresh();

        expect($this->attachedImage->isAttached())->toBeTrue();
    });

    test('unattached scope finds images not linked to anything', function () {
        $this->attachedImage->products()->attach($this->product);

        $unattachedImages = Image::unattached()->get();

        expect($unattachedImages->pluck('id'))->toContain($this->unattachedImage->id);
        expect($unattachedImages->pluck('id'))->not->toContain($this->attachedImage->id);
    });

    test('attached scope finds images linked to models', function () {
        $this->attachedImage->variants()->attach($this->variant);

        $attachedImages = Image::attached()->get();

        expect($attachedImages->pluck('id'))->toContain($this->attachedImage->id);
        expect($attachedImages->pluck('id'))->not->toContain($this->unattachedImage->id);
    });

});

describe('Image Primary and Ordering for Products', function () {

    beforeEach(function () {
        $this->product = Product::factory()->create();
        $this->image1 = Image::factory()->create(['is_primary' => true, 'sort_order' => 1]);
        $this->image2 = Image::factory()->create(['is_primary' => false, 'sort_order' => 2]);
        $this->image3 = Image::factory()->create(['is_primary' => false, 'sort_order' => 0]);

        $this->product->images()->attach([
            $this->image1->id,
            $this->image2->id,
            $this->image3->id,
        ]);
    });

    test('can get primary image for product', function () {
        $primaryImage = $this->product->images()->primary()->first();

        expect($primaryImage->id)->toBe($this->image1->id);
        expect($primaryImage->is_primary)->toBeTrue();
    });

    test('can get ordered images for product', function () {
        $orderedImages = $this->product->images()->ordered()->get();

        expect($orderedImages)->toHaveCount(3);
        expect($orderedImages->first()->id)->toBe($this->image3->id); // sort_order 0
        expect($orderedImages->get(1)->id)->toBe($this->image1->id); // sort_order 1
        expect($orderedImages->last()->id)->toBe($this->image2->id); // sort_order 2
    });

    test('can change primary image for product', function () {
        // Make image2 primary instead
        $this->image1->update(['is_primary' => false]);
        $this->image2->update(['is_primary' => true]);

        $primaryImage = $this->product->images()->primary()->first();
        expect($primaryImage->id)->toBe($this->image2->id);
    });

});

describe('Image Primary and Ordering for Variants', function () {

    beforeEach(function () {
        $this->product = Product::factory()->create();
        $this->variant = ProductVariant::factory()->create(['product_id' => $this->product->id]);
        $this->image1 = Image::factory()->create(['is_primary' => false, 'sort_order' => 2]);
        $this->image2 = Image::factory()->create(['is_primary' => true, 'sort_order' => 1]);
        $this->image3 = Image::factory()->create(['is_primary' => false, 'sort_order' => 0]);

        $this->variant->images()->attach([
            $this->image1->id,
            $this->image2->id,
            $this->image3->id,
        ]);
    });

    test('can get primary image for variant', function () {
        $primaryImage = $this->variant->images()->primary()->first();

        expect($primaryImage->id)->toBe($this->image2->id);
        expect($primaryImage->is_primary)->toBeTrue();
    });

    test('can get ordered images for variant', function () {
        $orderedImages = $this->variant->images()->ordered()->get();

        expect($orderedImages)->toHaveCount(3);
        expect($orderedImages->first()->id)->toBe($this->image3->id); // sort_order 0
        expect($orderedImages->get(1)->id)->toBe($this->image2->id); // sort_order 1
        expect($orderedImages->last()->id)->toBe($this->image1->id); // sort_order 2
    });

});

describe('Complex Image Relationships', function () {

    test('image can belong to product and its variants simultaneously', function () {
        $product = Product::factory()->create();
        $variant1 = ProductVariant::factory()->create(['product_id' => $product->id]);
        $variant2 = ProductVariant::factory()->create(['product_id' => $product->id]);

        $sharedImage = Image::factory()->create(['title' => 'Shared Image']);

        // Attach to product and both variants
        $sharedImage->products()->attach($product);
        $sharedImage->variants()->attach([$variant1, $variant2]);

        expect($sharedImage->products)->toHaveCount(1);
        expect($sharedImage->variants)->toHaveCount(2);
        expect($sharedImage->isAttached())->toBeTrue();
    });

    test('image can be shared across multiple products', function () {
        $product1 = Product::factory()->create(['name' => 'Product 1']);
        $product2 = Product::factory()->create(['name' => 'Product 2']);

        $sharedImage = Image::factory()->create(['title' => 'Brand Logo']);

        $sharedImage->products()->attach([$product1, $product2]);

        expect($sharedImage->products)->toHaveCount(2);
        expect($product1->images->pluck('id'))->toContain($sharedImage->id);
        expect($product2->images->pluck('id'))->toContain($sharedImage->id);
    });

    test('deleting product does not delete shared images', function () {
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();
        $image = Image::factory()->create();

        $image->products()->attach([$product1, $product2]);

        // Delete one product
        $product1->delete();

        // Image should still exist and be attached to remaining product
        expect(Image::find($image->id))->not->toBeNull();
        expect($image->fresh()->products)->toHaveCount(1);
        expect($image->fresh()->products->first()->id)->toBe($product2->id);
    });

    test('deleting variant does not delete shared images', function () {
        $product = Product::factory()->create();
        $variant1 = ProductVariant::factory()->create(['product_id' => $product->id]);
        $variant2 = ProductVariant::factory()->create(['product_id' => $product->id]);
        $image = Image::factory()->create();

        $image->variants()->attach([$variant1, $variant2]);

        // Delete one variant
        $variant1->delete();

        // Image should still exist and be attached to remaining variant
        expect(Image::find($image->id))->not->toBeNull();
        expect($image->fresh()->variants)->toHaveCount(1);
        expect($image->fresh()->variants->first()->id)->toBe($variant2->id);
    });

});
