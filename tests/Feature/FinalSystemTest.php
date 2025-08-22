<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Image;
use App\Models\Barcode;
use App\Models\Pricing;
use App\Models\SyncAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Final System Tests', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    describe('Core Product System', function () {
        it('can create and manage products', function () {
            $product = Product::create([
                'name' => 'Blackout Roller Blind',
                'parent_sku' => 'BRB-001',
                'description' => 'Premium blackout roller blind',
                'status' => 'active',
            ]);

            expect(Product::count())->toBe(1);
            expect($product->name)->toBe('Blackout Roller Blind');
            expect($product->parent_sku)->toBe('BRB-001');
        });

        it('can create product variants', function () {
            $product = Product::create([
                'name' => 'Test Product',
                'parent_sku' => 'TP-001',
            ]);

            $variant = ProductVariant::factory()->create([
                'product_id' => $product->id,
                'sku' => 'TP-001-120-WHITE',
                'width' => 120,
                'color' => 'White',
            ]);

            expect(ProductVariant::count())->toBe(1);
            expect($product->variants()->count())->toBe(1);
        });
    });

    describe('User System', function () {
        it('can manage users', function () {
            expect(User::count())->toBe(1); // From beforeEach

            $newUser = User::factory()->create([
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
            ]);

            expect(User::count())->toBe(2);
            expect($newUser->initials())->toBe('JS');
        });
    });

    describe('Image System (DAM)', function () {
        it('can create images with correct schema', function () {
            $image = Image::create([
                'filename' => 'test-image.jpg',
                'url' => 'https://example.com/test-image.jpg',
                'mime_type' => 'image/jpeg',
                'file_size' => 1024,
                'title' => 'Test Image',
                'user_id' => $this->user->id,
            ]);

            expect(Image::count())->toBe(1);
            expect($image->filename)->toBe('test-image.jpg');
        });

        it('can attach images to products', function () {
            $product = Product::create([
                'name' => 'Product with Image',
                'parent_sku' => 'PWI-001',
            ]);

            $image = Image::create([
                'filename' => 'product-image.jpg',
                'url' => 'https://example.com/product-image.jpg',
                'mime_type' => 'image/jpeg',
                'file_size' => 2048,
                'attachable_type' => 'App\Models\Product',
                'attachable_id' => $product->id,
                'user_id' => $this->user->id,
            ]);

            expect($product->images()->count())->toBe(1);
        });
    });

    describe('Barcode System', function () {
        it('can create barcodes for variants', function () {
            $product = Product::create([
                'name' => 'Product with Barcode',
                'parent_sku' => 'PWB-001',
            ]);

            $variant = ProductVariant::factory()->create([
                'product_id' => $product->id,
            ]);

            $barcode = Barcode::create([
                'product_variant_id' => $variant->id,
                'barcode' => '1234567890123',
                'type' => 'EAN13',
                'status' => 'active',
            ]);

            expect(Barcode::count())->toBe(1);
            expect($barcode->barcode)->toBe('1234567890123');
        });
    });

    describe('Pricing System', function () {
        it('can create pricing for variants', function () {
            $product = Product::create([
                'name' => 'Priced Product',
                'parent_sku' => 'PP-001',
            ]);

            $variant = ProductVariant::factory()->create([
                'product_id' => $product->id,
            ]);

            $pricing = Pricing::factory()->create([
                'product_variant_id' => $variant->id,
                'cost_price' => 10.00,
                'retail_price' => 25.00,
            ]);

            expect(Pricing::count())->toBe(1);
            expect($pricing->retail_price)->toBe(25.00);
        });
    });

    describe('API Integration System', function () {
        it('can create marketplace sync accounts', function () {
            $syncAccount = SyncAccount::factory()->create([
                'channel' => 'shopify',
                'name' => 'Test Store',
            ]);

            expect(SyncAccount::count())->toBe(1);
            expect($syncAccount->channel)->toBe('shopify');
        });
    });

    describe('Complete Integration Test', function () {
        it('can create a fully integrated product ecosystem', function () {
            // 1. Create Product
            $product = Product::create([
                'name' => 'Complete Integration Product',
                'parent_sku' => 'CIP-001',
                'description' => 'Fully integrated test product',
                'status' => 'active',
            ]);

            // 2. Create Variant
            $variant = ProductVariant::factory()->create([
                'product_id' => $product->id,
                'sku' => 'CIP-001-100-BLUE',
                'width' => 100,
                'color' => 'Blue',
            ]);

            // 3. Create Barcode
            $barcode = Barcode::create([
                'product_variant_id' => $variant->id,
                'barcode' => '9876543210987',
                'type' => 'EAN13',
                'status' => 'active',
            ]);

            // 4. Create Pricing
            $pricing = Pricing::factory()->create([
                'product_variant_id' => $variant->id,
                'cost_price' => 15.00,
                'retail_price' => 35.00,
            ]);

            // 5. Create Image
            $image = Image::create([
                'filename' => 'complete-product.jpg',
                'url' => 'https://example.com/complete-product.jpg',
                'mime_type' => 'image/jpeg',
                'file_size' => 3072,
                'attachable_type' => 'App\Models\Product',
                'attachable_id' => $product->id,
                'user_id' => $this->user->id,
            ]);

            // 6. Create Sync Account
            $syncAccount = SyncAccount::factory()->create([
                'channel' => 'shopify',
                'name' => 'Integration Test Store',
            ]);

            // Verify all components
            expect(Product::count())->toBe(1);
            expect(ProductVariant::count())->toBe(1);
            expect(Barcode::count())->toBe(1);
            expect(Pricing::count())->toBe(1);
            expect(Image::count())->toBe(1);
            expect(SyncAccount::count())->toBe(1);

            // Verify relationships
            expect($product->variants()->count())->toBe(1);
            expect($product->images()->count())->toBe(1);
            expect($variant->barcodes()->count())->toBe(1);
            expect($variant->pricing)->not->toBeNull();

            // Verify data integrity
            expect($variant->barcodes->first()->barcode)->toBe('9876543210987');
            expect($pricing->retail_price)->toBe(35.00);
            expect($image->attachable_id)->toBe($product->id);
        });
    });
});