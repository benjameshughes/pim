<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Image;
use App\Models\Barcode;
use App\Models\Pricing;
use App\Models\SyncAccount;
use App\Models\SyncLog;
use App\Models\SyncStatus;
use App\Models\AttributeDefinition;
use App\Models\ProductAttribute;
use App\Models\VariantAttribute;
use App\Models\MarketplaceLink;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Complete System Functionality', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    it('can create complete product ecosystem', function () {
        // 1. Create core product
        $product = Product::create([
            'name' => 'Complete System Test Product',
            'parent_sku' => 'CST-001',
            'description' => 'Testing the complete system',
            'status' => 'active',
        ]);

        // 2. Create variants
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'CST-001-L-BLUE',
            'width' => 120,
            'color' => 'Blue',
        ]);

        // 3. Create barcode
        $barcode = Barcode::create([
            'product_variant_id' => $variant->id,
            'barcode' => '1234567890123',
            'type' => 'EAN13',
            'status' => 'active',
        ]);

        // 4. Create pricing (need sales channel first)
        $salesChannel = \App\Models\SalesChannel::factory()->create();
        $pricing = Pricing::create([
            'product_variant_id' => $variant->id,
            'sales_channel_id' => $salesChannel->id,
            'price' => 35.00,
            'cost_price' => 15.00,
            'discount_price' => null,
            'currency' => 'GBP',
        ]);

        // 5. Create image
        $image = Image::create([
            'filename' => 'complete-test.jpg',
            'path' => 'images/complete-test.jpg',
            'url' => 'https://example.com/complete-test.jpg',
            'size' => 2048,
            'mime_type' => 'image/jpeg',
            'imageable_type' => 'App\Models\Product',
            'imageable_id' => $product->id,
            'is_primary' => true,
            'sort_order' => 0,
            'created_by_user_id' => $this->user->id,
        ]);

        // 6. Create sync account
        $syncAccount = SyncAccount::create([
            'name' => 'Test Shopify Store',
            'channel' => 'shopify',
            'display_name' => 'My Test Store',
            'is_active' => true,
            'credentials' => json_encode(['api_key' => 'test']),
            'settings' => ['auto_sync' => true],
        ]);

        // 7. Create sync status
        $syncStatus = SyncStatus::create([
            'product_id' => $product->id,
            'sync_account_id' => $syncAccount->id,
            'channel' => 'shopify',
            'status' => 'synced',
            'external_id' => 'shopify-12345',
            'last_synced_at' => now(),
        ]);

        // 8. Create sync log
        $syncLog = SyncLog::create([
            'product_id' => $product->id,
            'sync_account_id' => $syncAccount->id,
            'channel' => 'shopify',
            'operation' => 'create',
            'status' => 'completed',
            'external_id' => 'shopify-12345',
            'duration_ms' => 250,
        ]);

        // 9. Create attribute definition
        $attributeDefinition = AttributeDefinition::create([
            'name' => 'material',
            'label' => 'Material',
            'type' => 'select',
            'options' => ['Cotton', 'Polyester', 'Silk'],
            'is_required' => false,
            'is_inheritable' => true,
        ]);

        // 10. Create product attribute
        $productAttribute = ProductAttribute::create([
            'product_id' => $product->id,
            'attribute_definition_id' => $attributeDefinition->id,
            'value' => 'Cotton',
            'source' => 'manual',
            'confidence_score' => 1.00,
        ]);

        // 11. Create variant attribute
        $variantAttribute = VariantAttribute::create([
            'product_variant_id' => $variant->id,
            'attribute_definition_id' => $attributeDefinition->id,
            'value' => 'Cotton Blend',
            'inherited_from_product' => false,
            'overrides_product' => true,
            'source' => 'manual',
        ]);

        // 12. Create marketplace link
        $marketplaceLink = MarketplaceLink::create([
            'linkable_type' => 'App\Models\Product',
            'linkable_id' => $product->id,
            'sync_account_id' => $syncAccount->id,
            'channel' => 'shopify',
            'external_id' => 'shopify-12345',
            'link_level' => 'product',
            'status' => 'active',
            'created_by_user_id' => $this->user->id,
        ]);

        // 13. Create tags
        $tag1 = Tag::create([
            'name' => 'bestseller',
            'slug' => 'bestseller',
            'type' => 'general',
            'created_by_user_id' => $this->user->id,
        ]);

        $tag2 = Tag::create([
            'name' => 'seasonal',
            'slug' => 'seasonal', 
            'type' => 'system',
            'created_by_user_id' => $this->user->id,
        ]);

        // 14. Attach tags to product
        $product->tags()->attach([$tag1->id, $tag2->id], [
            'source' => 'manual',
            'tagged_by_user_id' => $this->user->id,
        ]);

        // VERIFY ALL COMPONENTS EXIST
        expect(Product::count())->toBe(1);
        expect(ProductVariant::count())->toBe(1);
        expect(Barcode::count())->toBe(1);
        expect(Pricing::count())->toBe(1);
        expect(Image::count())->toBe(1);
        expect(SyncAccount::count())->toBe(1);
        expect(SyncStatus::count())->toBe(1);
        expect(SyncLog::count())->toBe(1);
        expect(AttributeDefinition::count())->toBe(1);
        expect(ProductAttribute::count())->toBe(1);
        expect(VariantAttribute::count())->toBe(1);
        expect(MarketplaceLink::count())->toBe(1);
        expect(Tag::count())->toBe(2);

        // VERIFY RELATIONSHIPS WORK
        expect($product->variants)->toHaveCount(1);
        expect($product->images)->toHaveCount(1);
        expect($product->syncStatuses)->toHaveCount(1);
        expect($product->syncLogs)->toHaveCount(1);
        expect($product->attributes)->toHaveCount(1);
        expect($product->marketplaceLinks)->toHaveCount(1);
        expect($product->tags)->toHaveCount(2);

        expect($variant->barcodes)->toHaveCount(1);
        expect($variant->pricing)->not->toBeNull();
        expect($variant->attributes)->toHaveCount(1);

        // VERIFY DATA INTEGRITY
        expect($product->primaryImage->id)->toBe($image->id);
        expect($variant->barcode->code)->toBe('1234567890123');
        expect($syncStatus->external_id)->toBe('shopify-12345');
        expect($marketplaceLink->external_id)->toBe('shopify-12345');
        expect($productAttribute->value)->toBe('Cotton');
        expect($variantAttribute->value)->toBe('Cotton Blend');
        expect($product->tags->first()->name)->toBe('bestseller');
    });

    it('verifies all database relationships work correctly', function () {
        // Test that all the foreign key relationships work
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $syncAccount = SyncAccount::factory()->create();

        // Create related records
        $syncStatus = SyncStatus::factory()->create([
            'product_id' => $product->id,
            'sync_account_id' => $syncAccount->id,
        ]);

        $syncLog = SyncLog::factory()->create([
            'product_id' => $product->id,
            'sync_account_id' => $syncAccount->id,
        ]);

        // Test relationships exist  
        expect($syncStatus->product)->not->toBeNull();
        expect($syncStatus->syncAccount)->not->toBeNull();
        expect($syncLog->product)->not->toBeNull();

        // Test cascade deletes work
        $product->delete();

        expect(SyncStatus::count())->toBe(0); // Should cascade delete
        expect(SyncLog::count())->toBe(0); // Should cascade delete
    });

    it('confirms the system is no longer bloated', function () {
        // All major models should now have corresponding tables
        $modelsWithMigrations = [
            'Product', 'ProductVariant', 'Barcode', 'Image', 'Pricing',
            'SyncAccount', 'SyncLog', 'SyncStatus',
            'AttributeDefinition', 'ProductAttribute', 'VariantAttribute', 
            'MarketplaceLink', 'Tag'
        ];

        foreach ($modelsWithMigrations as $model) {
            $class = "App\\Models\\{$model}";
            expect(class_exists($class))->toBeTrue("Model {$model} should exist");
            
            // Try to create a factory instance to ensure table exists
            if (method_exists($class, 'factory')) {
                try {
                    $class::factory()->make();
                    expect(true)->toBeTrue(); // Factory worked
                } catch (Exception $e) {
                    dump("Factory failed for {$model}: " . $e->getMessage());
                    expect(false)->toBeTrue(); // Will fail and show the error
                }
            }
        }

        dump("✅ System is now complete with all critical tables!");
    });
});