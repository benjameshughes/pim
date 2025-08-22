<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Image;
use App\Models\Barcode;
use App\Models\Pricing;
use App\Models\SyncAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

describe('Comprehensive System Tests', function () {
    beforeEach(function () {
        Storage::fake('public');
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    describe('Product Management System', function () {
        it('can create product with variants', function () {
            $product = Product::create([
                'name' => 'Roller Blind',
                'parent_sku' => 'RB-001',
                'description' => 'Premium blackout roller blind',
                'status' => 'active',
            ]);

            $variant = ProductVariant::factory()->create([
                'product_id' => $product->id,
                'sku' => 'RB-001-120-WHITE',
                'width' => 120,
                'color' => 'White',
            ]);

            expect(Product::count())->toBe(1);
            expect(ProductVariant::count())->toBe(1);
            expect($product->variants()->count())->toBe(1);
        });

        it('can manage product images', function () {
            $product = Product::create([
                'name' => 'Test Product',
                'parent_sku' => 'TP-001',
            ]);

            $image = Image::factory()->create();
            $product->images()->save($image);

            expect($product->images()->count())->toBe(1);
        });

        it('can handle product status changes', function () {
            $product = Product::create([
                'name' => 'Test Product',
                'parent_sku' => 'TP-002',
                'status' => 'active',
            ]);

            expect(Product::where('status', 'active')->count())->toBe(1);

            $product->update(['status' => 'inactive']);
            expect(Product::where('status', 'inactive')->count())->toBe(1);
            expect(Product::where('status', 'active')->count())->toBe(0);
        });
    });

    describe('User Authentication System', function () {
        it('can register and authenticate users', function () {
            expect(User::count())->toBe(1); // User from beforeEach

            $newUser = User::factory()->create([
                'email' => 'new@example.com',
            ]);

            expect(User::count())->toBe(2);
            expect(User::where('email', 'new@example.com')->exists())->toBeTrue();
        });

        it('can generate user initials', function () {
            $user = User::factory()->create(['name' => 'John Smith']);
            expect($user->initials())->toBe('JS');
        });
    });

    describe('Image Management (DAM) System', function () {
        it('can create and manage images', function () {
            $image = Image::factory()->create([
                'filename' => 'test-image.jpg',
                'path' => 'images/test-image.jpg',
                'mime_type' => 'image/jpeg',
            ]);

            expect(Image::count())->toBe(1);
            expect($image->filename)->toBe('test-image.jpg');
        });

        it('can associate images with products', function () {
            $product = Product::create([
                'name' => 'Product with Image',
                'parent_sku' => 'PWI-001',
            ]);

            $image = Image::factory()->create();
            $product->images()->save($image);

            expect($product->images()->count())->toBe(1);
        });
    });

    describe('Barcode Management System', function () {
        it('can create and assign barcodes', function () {
            $barcode = Barcode::factory()->create([
                'code' => '1234567890123',
                'status' => 'available',
            ]);

            expect(Barcode::count())->toBe(1);
            expect($barcode->code)->toBe('1234567890123');
        });

        it('can assign barcode to variant', function () {
            $variant = ProductVariant::factory()->create();
            $barcode = Barcode::factory()->create(['status' => 'available']);

            $variant->update(['barcode_id' => $barcode->id]);
            $barcode->update(['status' => 'assigned']);

            $variant->refresh();
            expect($variant->barcode_id)->toBe($barcode->id);
            expect($barcode->fresh()->status)->toBe('assigned');
        });
    });

    describe('Pricing System', function () {
        it('can create pricing for variants', function () {
            $variant = ProductVariant::factory()->create();
            
            $pricing = Pricing::factory()->create([
                'product_variant_id' => $variant->id,
                'cost_price' => 10.00,
                'retail_price' => 25.00,
            ]);

            expect(Pricing::count())->toBe(1);
            expect($pricing->cost_price)->toBe(10.00);
        });

        it('can calculate profit margins', function () {
            $pricing = Pricing::factory()->create([
                'cost_price' => 10.00,
                'retail_price' => 25.00,
            ]);

            $margin = ($pricing->retail_price - $pricing->cost_price) / $pricing->retail_price * 100;
            expect($margin)->toBe(60.0);
        });
    });

    describe('API Integration System', function () {
        it('can create sync accounts for marketplaces', function () {
            $syncAccount = SyncAccount::factory()->create([
                'channel' => 'shopify',
                'name' => 'Test Shopify Store',
            ]);

            expect(SyncAccount::count())->toBe(1);
            expect($syncAccount->channel)->toBe('shopify');
        });

        it('can manage multiple marketplace integrations', function () {
            $shopifyAccount = SyncAccount::factory()->create(['channel' => 'shopify']);
            $ebayAccount = SyncAccount::factory()->create(['channel' => 'ebay']);
            $amazonAccount = SyncAccount::factory()->create(['channel' => 'amazon']);

            expect(SyncAccount::count())->toBe(3);
            expect(SyncAccount::where('channel', 'shopify')->count())->toBe(1);
            expect(SyncAccount::where('channel', 'ebay')->count())->toBe(1);
        });
    });

    describe('Full System Integration', function () {
        it('can create complete product with all related data', function () {
            // Create product
            $product = Product::create([
                'name' => 'Complete Product',
                'parent_sku' => 'CP-001',
                'description' => 'A complete product with all features',
            ]);

            // Add variant
            $variant = ProductVariant::factory()->create([
                'product_id' => $product->id,
                'sku' => 'CP-001-150-BLUE',
            ]);

            // Add barcode
            $barcode = Barcode::factory()->create();
            $variant->update(['barcode_id' => $barcode->id]);

            // Add pricing
            $pricing = Pricing::factory()->create([
                'product_variant_id' => $variant->id,
            ]);

            // Add image
            $image = Image::factory()->create();
            $product->images()->save($image);

            // Add sync account
            $syncAccount = SyncAccount::factory()->create();

            // Verify everything is created
            expect(Product::count())->toBe(1);
            expect(ProductVariant::count())->toBe(1);
            expect(Barcode::count())->toBe(1);
            expect(Pricing::count())->toBe(1);
            expect(Image::count())->toBe(1);
            expect(SyncAccount::count())->toBe(1);

            // Verify relationships
            expect($product->variants()->count())->toBe(1);
            expect($product->images()->count())->toBe(1);
            expect($variant->barcode)->not->toBeNull();
            expect($variant->pricing)->not->toBeNull();
        });
    });
});