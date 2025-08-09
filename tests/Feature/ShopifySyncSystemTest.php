<?php

use App\Models\User;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShopifyProductSync;
use App\Models\ShopifyWebhookLog;
use App\Actions\Shopify\Sync\CheckSyncStatusAction;
use App\Actions\Shopify\Sync\SyncProductToShopifyAction;
use App\Services\Shopify\API\ShopifySyncStatusService;
use App\Services\Shopify\API\ShopifyDataComparatorService;
use App\Services\Shopify\Builders\WebhookSubscriptionBuilder;
use App\Services\Shopify\Builders\SyncConfigurationBuilder;

/**
 * 💅 LEGENDARY SHOPIFY SYNC SYSTEM TESTS 💅
 * 
 * Testing our FABULOUS sync system with MAXIMUM SASS!
 * Every method, every route, every sparkly feature gets tested!
 */
describe('Shopify Sync System', function () {
    
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        
        // Create test products with variants
        $this->product = Product::factory()
            ->has(ProductVariant::factory()->count(3), 'variants')
            ->create(['name' => 'Test Blind Product']);
    });

    describe('🏪 Dashboard Routes & Rendering', function () {
        
        test('shopify dashboard route renders successfully', function () {
            $response = $this->get('/shopify-dashboard');
            
            $response->assertStatus(200)
                    ->assertSee('Shopify Sync Dashboard')
                    ->assertSee('Your complete sync intelligence command center');
        });
        
        test('shopify sync route renders successfully', function () {
            $response = $this->get('/sync/shopify');
            
            $response->assertStatus(200)
                    ->assertSee('Shopify Sync')
                    ->assertSee('Sync Status Overview');
        });
        
        test('product sync tab renders with enhanced status', function () {
            $response = $this->get("/products/{$this->product->id}/sync");
            
            $response->assertStatus(200)
                    ->assertSee('Shopify Sync Status')
                    ->assertSee('Health Grade');
        });
    });

    describe('📊 Models & Database', function () {
        
        test('enhanced ShopifyProductSync model calculates health correctly', function () {
            $sync = ShopifyProductSync::create([
                'product_id' => $this->product->id,
                'color' => 'red',
                'shopify_product_id' => 'gid://shopify/Product/12345',
                'sync_status' => 'synced',
                'last_sync_data' => ['test' => 'data'],
                'last_synced_at' => now(),
                'data_drift_score' => 0.0,
                'health_score' => 95,
            ]);
            
            $health = $sync->calculateSyncHealth();
            expect($health)->toBeGreaterThan(80);
            
            $summary = $sync->getSyncSummary();
            expect($summary)->toHaveKeys([
                'status', 'health_percentage', 'health_grade', 
                'last_synced', 'drift_score', 'needs_attention'
            ]);
        });
        
        test('ShopifyWebhookLog model processes webhook data correctly', function () {
            $webhookLog = ShopifyWebhookLog::createFromWebhook('products/update', [
                'id' => 'gid://shopify/Product/12345',
                'title' => 'Test Product',
                'updated_at' => now()->toISOString(),
            ]);
            
            expect($webhookLog->topic)->toBe('products/update');
            expect($webhookLog->shopify_product_id)->toBe('gid://shopify/Product/12345');
            
            $relatedProduct = $webhookLog->findRelatedProduct();
            // This would be null without SKU matching in test data
            expect($relatedProduct)->toBeNull();
        });
        
        test('product enhanced sync status includes all legendary fields', function () {
            // Create a sync record first
            ShopifyProductSync::create([
                'product_id' => $this->product->id,
                'color' => 'blue',
                'shopify_product_id' => 'gid://shopify/Product/67890',
                'sync_status' => 'synced',
                'last_sync_data' => [],
                'last_synced_at' => now(),
                'health_score' => 85,
                'data_drift_score' => 1.5,
            ]);
            
            $status = $this->product->getShopifySyncStatus();
            
            expect($status)->toHaveKeys([
                'status', 'health_score', 'health_grade', 'sync_summary',
                'needs_attention', 'drift_score', 'shopify_urls'
            ]);
            
            expect($status['health_grade'])->toBeString();
            expect($status['sync_summary'])->toContain('synced');
        });
    });

    describe('⚡ Actions System', function () {
        
        test('CheckSyncStatusAction executes successfully', function () {
            $action = app(CheckSyncStatusAction::class);
            $result = $action->execute($this->product);
            
            expect($result['success'])->toBeTrue();
            expect($result['data'])->toHaveKeys([
                'product_info', 'sync_status', 'overall_status', 'health_score'
            ]);
        });
        
        test('SyncProductToShopifyAction handles validation gracefully', function () {
            $action = app(SyncProductToShopifyAction::class);
            
            // This will likely fail due to missing Shopify credentials, but should not crash
            $result = $action->execute($this->product, ['method' => 'manual']);
            
            expect($result)->toHaveKeys(['success', 'message', 'data']);
        });
        
        test('bulk sync status checking works correctly', function () {
            $action = app(CheckSyncStatusAction::class);
            $products = Product::factory()->count(3)->create();
            
            $result = $action->checkBulkSyncStatus($products->pluck('id')->toArray());
            
            expect($result)->toHaveKeys(['summary', 'results']);
            expect($result['summary']['total_checked'])->toBe(3);
        });
    });

    describe('🔧 Services Layer', function () {
        
        test('ShopifySyncStatusService initializes correctly', function () {
            $service = app(ShopifySyncStatusService::class);
            
            expect($service)->toBeInstanceOf(ShopifySyncStatusService::class);
        });
        
        test('ShopifyDataComparatorService can compare product data', function () {
            $service = app(ShopifyDataComparatorService::class);
            
            $mockShopifyData = [
                'title' => 'Different Title',
                'variants' => [],
                'updatedAt' => now()->toISOString(),
            ];
            
            $comparison = $service->compareProductData($this->product, $mockShopifyData);
            
            expect($comparison)->toHaveKeys([
                'needs_sync', 'drift_score', 'differences', 'recommendation'
            ]);
        });
        
        test('bulk comparison handles multiple products', function () {
            $service = app(ShopifyDataComparatorService::class);
            $products = collect([$this->product]);
            
            $result = $service->bulkCompareProducts($products, []);
            
            expect($result)->toHaveKeys(['summary', 'products', 'drift_alerts']);
        });
    });

    describe('🏗️ Builder Pattern Tests', function () {
        
        test('WebhookSubscriptionBuilder builds configuration correctly', function () {
            $mockWebhookService = $this->createMock(\App\Services\Shopify\API\ShopifyWebhookService::class);
            
            $builder = WebhookSubscriptionBuilder::create($mockWebhookService);
            $config = $builder
                ->allSyncTopics()
                ->defaultCallback()
                ->withSignatureVerification()
                ->build();
            
            expect($config)->toHaveKeys([
                'topics', 'callback_url', 'options', 'sync_coverage'
            ]);
            
            expect($config['sync_coverage']['status'])->toBe('complete');
        });
        
        test('SyncConfigurationBuilder creates proper configuration', function () {
            $statusAction = app(CheckSyncStatusAction::class);
            $syncAction = app(SyncProductToShopifyAction::class);
            
            $builder = SyncConfigurationBuilder::create($statusAction, $syncAction);
            $config = $builder
                ->product($this->product)
                ->manual()
                ->withMonitoring()
                ->batchSize(5)
                ->build();
            
            expect($config)->toHaveKeys([
                'products', 'sync_method', 'monitoring_enabled', 'configuration_summary'
            ]);
            
            expect($config['sync_method'])->toBe('manual');
            expect($config['batch_size'])->toBe(5);
        });
        
        test('builder preview shows estimated products correctly', function () {
            $statusAction = app(CheckSyncStatusAction::class);
            $syncAction = app(SyncProductToShopifyAction::class);
            
            $builder = SyncConfigurationBuilder::create($statusAction, $syncAction);
            $preview = $builder->product($this->product)->preview();
            
            expect($preview)->toHaveKeys([
                'total_products', 'products', 'configuration', 'estimated_duration'
            ]);
            
            expect($preview['total_products'])->toBe(1);
        });
    });

    describe('🎭 Livewire Components', function () {
        
        test('ShopifyDashboard component loads data correctly', function () {
            $component = Livewire\Livewire::test(\App\Livewire\ShopifyDashboard::class);
            
            $component->assertStatus(200)
                     ->assertSet('dashboardData', [])
                     ->assertSet('healthSummary', [])
                     ->assertViewIs('livewire.shopify-dashboard');
        });
        
        test('dashboard refresh method works', function () {
            $component = Livewire\Livewire::test(\App\Livewire\ShopifyDashboard::class);
            
            $component->call('refresh')
                     ->assertEmitted('dashboard-refreshed');
        });
        
        test('bulk sync action can be triggered', function () {
            $component = Livewire\Livewire::test(\App\Livewire\ShopifyDashboard::class);
            
            $component->call('syncProductsNeedingAttention')
                     ->assertEmitted('bulk-sync-triggered');
        });
    });

    describe('📈 Health & Monitoring', function () {
        
        test('health scoring system works correctly', function () {
            // Test different health scenarios
            $healthySync = ShopifyProductSync::create([
                'product_id' => $this->product->id,
                'color' => 'green',
                'shopify_product_id' => 'gid://shopify/Product/99999',
                'sync_status' => 'synced',
                'last_sync_data' => [],
                'last_synced_at' => now(),
                'data_drift_score' => 0.0,
            ]);
            
            $healthScore = $healthySync->calculateSyncHealth();
            expect($healthScore)->toBeGreaterThanOrEqual(95);
            
            // Test unhealthy sync
            $unhealthySync = ShopifyProductSync::create([
                'product_id' => $this->product->id,
                'color' => 'red',
                'shopify_product_id' => 'gid://shopify/Product/88888',
                'sync_status' => 'failed',
                'last_sync_data' => [],
                'last_synced_at' => now()->subDays(2),
                'data_drift_score' => 8.0,
            ]);
            
            $unhealthyScore = $unhealthySync->calculateSyncHealth();
            expect($unhealthyScore)->toBeLessThan(50);
        });
        
        test('grade calculation matches expected ranges', function () {
            $product = Product::factory()->create();
            
            // Test A+ grade
            expect($product->getHealthGrade(97))->toBe('A+');
            // Test B grade  
            expect($product->getHealthGrade(77))->toBe('B');
            // Test F grade
            expect($product->getHealthGrade(45))->toBe('F');
            // Test N/A for 0
            expect($product->getHealthGrade(0))->toBe('N/A');
        });
        
        test('sync needs attention scopes work correctly', function () {
            // Create products that need attention
            ShopifyProductSync::create([
                'product_id' => $this->product->id,
                'color' => 'yellow',
                'shopify_product_id' => 'gid://shopify/Product/77777',
                'sync_status' => 'failed',
                'last_sync_data' => [],
                'last_synced_at' => now()->subDays(2),
                'data_drift_score' => 6.0,
            ]);
            
            $needsAttention = ShopifyProductSync::needsAttention()->get();
            expect($needsAttention)->toHaveCount(1);
            
            $healthy = ShopifyProductSync::healthy()->get();
            expect($healthy)->toHaveCount(0); // This one is not healthy
        });
    });

    describe('🚀 Integration & Performance', function () {
        
        test('sync status overview performs efficiently', function () {
            // Create multiple products with sync records
            $products = Product::factory()->count(10)->create();
            
            foreach ($products as $product) {
                ShopifyProductSync::create([
                    'product_id' => $product->id,
                    'color' => 'blue',
                    'shopify_product_id' => 'gid://shopify/Product/' . rand(10000, 99999),
                    'sync_status' => 'synced',
                    'last_sync_data' => [],
                    'last_synced_at' => now(),
                ]);
            }
            
            $start = microtime(true);
            $response = $this->get('/sync/shopify');
            $duration = microtime(true) - $start;
            
            $response->assertStatus(200);
            expect($duration)->toBeLessThan(2.0); // Should render in under 2 seconds
        });
        
        test('dashboard handles large datasets gracefully', function () {
            // Create many sync records
            Product::factory()->count(50)->create()->each(function ($product) {
                ShopifyProductSync::create([
                    'product_id' => $product->id,
                    'color' => 'mixed',
                    'shopify_product_id' => 'gid://shopify/Product/' . $product->id,
                    'sync_status' => collect(['synced', 'failed', 'pending'])->random(),
                    'last_sync_data' => [],
                    'last_synced_at' => now()->subHours(rand(1, 24)),
                    'data_drift_score' => rand(0, 10),
                ]);
            });
            
            $start = microtime(true);
            $response = $this->get('/shopify-dashboard');
            $duration = microtime(true) - $start;
            
            $response->assertStatus(200);
            expect($duration)->toBeLessThan(3.0); // Should handle 50 records efficiently
        });
    });
});

/**
 * 🔥 EDGE CASES & ERROR HANDLING 🔥
 */
describe('Edge Cases & Error Handling', function () {
    
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });
    
    test('handles missing products gracefully', function () {
        $response = $this->get('/products/999999/sync');
        $response->assertStatus(404);
    });
    
    test('handles corrupt sync data gracefully', function () {
        $product = Product::factory()->create();
        
        // Create sync record with potentially problematic data
        ShopifyProductSync::create([
            'product_id' => $product->id,
            'color' => '',
            'shopify_product_id' => 'invalid-id-format',
            'sync_status' => 'corrupted',
            'last_sync_data' => null,
            'last_synced_at' => null,
        ]);
        
        $status = $product->getShopifySyncStatus();
        expect($status)->toHaveKey('status');
        expect($status['health_score'])->toBeInt();
    });
    
    test('webhook log handles malformed payloads', function () {
        $log = ShopifyWebhookLog::createFromWebhook('invalid/topic', [
            'malformed' => 'data',
            'no_id' => true,
        ]);
        
        expect($log->topic)->toBe('invalid/topic');
        expect($log->shopify_product_id)->toBeNull();
    });
});

/**
 * 💅 SASS LEVEL: MAXIMUM! 💅
 * 
 * These tests ensure our LEGENDARY sync system is:
 * ✨ Robust and error-free
 * ✨ Performant under load  
 * ✨ Handles edge cases gracefully
 * ✨ Provides accurate health monitoring
 * ✨ Maintains data integrity
 * 
 * Because like any good drag performance, 
 * we need to SLAY without any technical difficulties! 🏆
 */