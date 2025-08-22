<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Category;
use App\Models\Image;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Product Model', function () {
    it('has correct fillable attributes', function () {
        $product = new Product();
        
        $fillable = [
            'name', 'description', 'sku', 'status', 'category_id', 
            'brand', 'weight', 'dimensions', 'meta_title', 'meta_description'
        ];

        expect($product->getFillable())->toEqual($fillable);
    });

    it('belongs to category', function () {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        expect($product->category)->toBeInstanceOf(Category::class);
        expect($product->category->id)->toBe($category->id);
    });

    it('has many variants', function () {
        $product = Product::factory()->create();
        ProductVariant::factory()->count(3)->create(['product_id' => $product->id]);

        expect($product->variants)->toHaveCount(3);
        expect($product->variants->first())->toBeInstanceOf(ProductVariant::class);
    });

    it('has many images', function () {
        $product = Product::factory()->create();
        $images = Image::factory()->count(2)->create();
        $product->images()->attach($images->pluck('id'));

        expect($product->images)->toHaveCount(2);
        expect($product->images->first())->toBeInstanceOf(Image::class);
    });

    it('can get primary image', function () {
        $product = Product::factory()->create();
        $primaryImage = Image::factory()->create();
        $secondaryImage = Image::factory()->create();
        
        $product->images()->attach([
            $primaryImage->id => ['is_primary' => true],
            $secondaryImage->id => ['is_primary' => false],
        ]);

        expect($product->primaryImage->id)->toBe($primaryImage->id);
    });

    it('returns null when no primary image exists', function () {
        $product = Product::factory()->create();
        
        expect($product->primaryImage)->toBeNull();
    });

    it('can calculate total variants count', function () {
        $product = Product::factory()->create();
        ProductVariant::factory()->count(5)->create(['product_id' => $product->id]);

        expect($product->total_variants)->toBe(5);
    });

    it('can determine if product is active', function () {
        $activeProduct = Product::factory()->create(['status' => 'active']);
        $draftProduct = Product::factory()->create(['status' => 'draft']);

        expect($activeProduct->isActive())->toBeTrue();
        expect($draftProduct->isActive())->toBeFalse();
    });

    it('can scope by status', function () {
        Product::factory()->create(['status' => 'active']);
        Product::factory()->create(['status' => 'draft']);
        Product::factory()->create(['status' => 'archived']);

        $activeProducts = Product::active()->get();
        $draftProducts = Product::draft()->get();

        expect($activeProducts)->toHaveCount(1);
        expect($draftProducts)->toHaveCount(1);
    });

    it('can search by name', function () {
        Product::factory()->create(['name' => 'Red T-Shirt']);
        Product::factory()->create(['name' => 'Blue Jeans']);
        Product::factory()->create(['name' => 'Red Sweater']);

        $results = Product::search('Red')->get();

        expect($results)->toHaveCount(2);
    });

    it('generates slug from name', function () {
        $product = Product::factory()->create(['name' => 'Amazing Product Name']);
        
        expect($product->slug)->toBe('amazing-product-name');
    });
});