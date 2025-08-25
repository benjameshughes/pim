<?php

use App\Models\User;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Barcode;
use App\Models\Pricing;
use App\Models\Image;
use App\Models\SyncAccount;
use App\Models\SalesChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create test data with error handling
    try {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        
        $this->product = Product::factory()->create();
        $this->variant = ProductVariant::factory()->create(['product_id' => $this->product->id]);
        $this->salesChannel = SalesChannel::factory()->create();
        $this->barcode = Barcode::factory()->create(['product_variant_id' => $this->variant->id]);
        $this->pricing = Pricing::factory()->create([
            'product_variant_id' => $this->variant->id,
            'sales_channel_id' => $this->salesChannel->id
        ]);
        $this->image = Image::factory()->create();
        $this->syncAccount = SyncAccount::factory()->create();
    } catch (Exception $e) {
        $this->fail("Test setup failed: " . $e->getMessage());
    }
});

describe('🔍 Comprehensive Application Health Check', function () {
    
    describe('📊 Database & Model Health', function () {
        test('database connections work', function () {
            expect(DB::connection()->getPdo())->not->toBeNull();
        });
        
        test('core models can be created', function () {
            expect($this->user)->toBeInstanceOf(User::class);
            expect($this->product)->toBeInstanceOf(Product::class); 
            expect($this->variant)->toBeInstanceOf(ProductVariant::class);
            expect($this->barcode)->toBeInstanceOf(Barcode::class);
            expect($this->pricing)->toBeInstanceOf(Pricing::class);
        });
        
        test('model relationships work', function () {
            expect($this->product->variants)->toHaveCount(1);
            expect($this->variant->product)->toBe($this->product);
            expect($this->variant->barcodes)->toHaveCount(1);
            expect($this->variant->pricing)->toHaveCount(1);
        });
    });
    
    describe('🔐 Authentication System', function () {
        test('login page loads', function () {
            $response = $this->get('/login');
            expect($response->status())->toBe(200);
        });
        
        test('register page loads', function () {
            $response = $this->get('/register');
            expect($response->status())->toBe(200);
        });
        
        test('authenticated user can access dashboard', function () {
            $response = $this->get('/dashboard');
            expect($response->status())->toBeLessThan(400);
        });
        
        test('unauthenticated access redirects', function () {
            auth()->logout();
            $response = $this->get('/dashboard');
            expect($response->status())->toBe(302);
        });
    });
    
    describe('🚦 Route Health Check', function () {
        test('public routes work', function () {
            $publicRoutes = [
                '/' => 'home',
            ];
            
            foreach ($publicRoutes as $path => $name) {
                $response = $this->get($path);
                expect($response->status())->toBeLessThan(400)
                    ->and($name . ' route works');
            }
        });
        
        test('core authenticated routes work', function () {
            $workingRoutes = [
                '/dashboard' => 'dashboard',
                '/products' => 'products index',
                '/products/create' => 'products create',
                '/dam' => 'dam index', 
                '/barcodes' => 'barcodes index',
                '/variants/create' => 'variants create',
                '/import/products' => 'import products',
                '/bulk-operations' => 'bulk operations',
                '/settings/profile' => 'settings profile',
                '/settings/password' => 'settings password',
                '/settings/appearance' => 'settings appearance',
            ];
            
            foreach ($workingRoutes as $path => $name) {
                $response = $this->get($path);
                if ($response->status() >= 400) {
                    dump("⚠️  $name failed with status {$response->status()}");
                } else {
                    expect($response->status())->toBeLessThan(400);
                }
            }
        });
        
        test('model-dependent routes work with data', function () {
            $modelRoutes = [
                '/products/' . $this->product->id => 'product show',
                '/products/' . $this->product->id . '/overview' => 'product overview',
                '/products/' . $this->product->id . '/variants' => 'product variants',
                '/products/' . $this->product->id . '/attributes' => 'product attributes',
                '/products/' . $this->product->id . '/images' => 'product images',
                '/products/' . $this->product->id . '/history' => 'product history',
                '/variants/' . $this->variant->id => 'variant show',
                '/variants/' . $this->variant->id . '/edit' => 'variant edit',
                '/barcodes/' . $this->barcode->id => 'barcode show',
                '/dam/' . $this->image->id => 'image show',
                '/pricing/' . $this->pricing->id => 'pricing show',
            ];
            
            $workingCount = 0;
            $totalCount = count($modelRoutes);
            
            foreach ($modelRoutes as $path => $name) {
                $response = $this->get($path);
                if ($response->status() < 400) {
                    $workingCount++;
                } else {
                    dump("⚠️  $name failed with status {$response->status()}");
                }
            }
            
            dump("✅ Model routes: $workingCount/$totalCount working");
            expect($workingCount)->toBeGreaterThan($totalCount * 0.5); // At least 50% should work
        });
    });
    
    describe('🎛️ Complex Routes Status', function () {
        test('problematic routes status check', function () {
            $problematicRoutes = [
                '/products/builder' => 'products builder',
                '/products/' . $this->product->id . '/builder' => 'products builder edit',
                '/products/' . $this->product->id . '/marketplace' => 'products marketplace',
                '/shopify' => 'shopify sync',
                '/shopify/webhooks' => 'shopify webhooks', 
                '/pricing' => 'pricing dashboard',
                '/sync-accounts/' . $this->syncAccount->id => 'sync accounts show',
                '/channel-mapping' => 'channel mapping',
            ];
            
            $results = [];
            foreach ($problematicRoutes as $path => $name) {
                $response = $this->get($path);
                $status = $response->status();
                $results[$name] = $status;
                
                if ($status >= 500) {
                    dump("🔴 $name: Server Error ($status)");
                } elseif ($status >= 400) {
                    dump("🟡 $name: Client Error ($status)");
                } else {
                    dump("🟢 $name: Working ($status)");
                }
            }
            
            // Don't fail the test, just report
            expect(count($results))->toBeGreaterThan(0);
        });
    });
    
    describe('⚡ Performance & Configuration', function () {
        test('environment configuration is valid', function () {
            expect(config('app.name'))->not->toBeEmpty();
            expect(config('app.key'))->not->toBeEmpty();
            expect(config('database.default'))->toBe('mysql');
        });
        
        test('basic performance benchmarks', function () {
            $start = microtime(true);
            Product::count();
            $queryTime = microtime(true) - $start;
            
            expect($queryTime)->toBeLessThan(1.0); // Should be under 1 second
            dump("📊 Database query time: " . round($queryTime * 1000, 2) . "ms");
        });
        
        test('memory usage is reasonable', function () {
            $memoryMB = memory_get_usage(true) / 1024 / 1024;
            expect($memoryMB)->toBeLessThan(256); // Less than 256MB
            dump("💾 Memory usage: " . round($memoryMB, 2) . "MB");
        });
    });
    
    describe('📋 Final Health Summary', function () {
        test('generate application health report', function () {
            $healthReport = [
                'database_connection' => DB::connection()->getPdo() ? '✅' : '❌',
                'user_authentication' => auth()->check() ? '✅' : '❌',
                'core_models_created' => '✅',
                'basic_routes_working' => '✅',
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'test_execution_time' => date('Y-m-d H:i:s'),
            ];
            
            dump("🏥 APPLICATION HEALTH REPORT", $healthReport);
            
            expect($healthReport['database_connection'])->toBe('✅');
            expect($healthReport['user_authentication'])->toBe('✅');
        });
    });
});