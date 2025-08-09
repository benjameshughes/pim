<?php

use App\Models\User;
use App\Models\Product;
use App\Models\ProductVariant;

/**
 * 🛣️ LEGENDARY SHOPIFY ROUTES TEST 🛣️
 * 
 * Making sure every sparkly route works PERFECTLY!
 * Because broken routes are SO not FABULOUS! 💅
 */
describe('Shopify Routes Accessibility', function () {
    
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        
        $this->product = Product::factory()
            ->has(ProductVariant::factory()->count(2), 'variants')
            ->create(['name' => 'Test Route Product']);
    });

    test('🏪 shopify dashboard route is accessible', function () {
        $response = $this->get('/shopify-dashboard');
        
        $response->assertStatus(200)
                ->assertSee('Shopify Sync Dashboard')
                ->assertSee('complete sync intelligence');
    });

    test('🔄 shopify sync route is accessible', function () {
        $response = $this->get('/sync/shopify');
        
        $response->assertStatus(200)
                ->assertSee('Shopify Sync')
                ->assertSee('Push your products to Shopify');
    });

    test('📦 product sync tab route is accessible', function () {
        $response = $this->get("/products/{$this->product->id}");
        
        $response->assertStatus(200);
        
        // Test sync tab specifically
        $response = $this->get("/products/{$this->product->id}/sync");
        $response->assertStatus(200)
                ->assertSee('Marketplace Sync Status');
    });

    test('🌐 all main routes return successful responses', function () {
        $routes = [
            '/' => 200,
            '/dashboard' => 200,
            '/products' => 200,
            '/sync/shopify' => 200,
            '/shopify-dashboard' => 200,
        ];

        foreach ($routes as $route => $expectedStatus) {
            $response = $this->get($route);
            expect($response->status())->toBe($expectedStatus, "Route {$route} should return {$expectedStatus}");
        }
    });

    test('🚫 invalid routes return 404', function () {
        $invalidRoutes = [
            '/shopify-unicorn-magic',
            '/sync/invalid-marketplace',
            '/products/999999999/sync',
        ];

        foreach ($invalidRoutes as $route) {
            $response = $this->get($route);
            expect($response->status())->toBe(404, "Route {$route} should return 404");
        }
    });

    test('🔐 auth protected routes redirect when not authenticated', function () {
        // Logout to test auth protection
        auth()->logout();
        
        $protectedRoutes = [
            '/dashboard',
            '/products',
            '/sync/shopify',
            '/shopify-dashboard',
        ];

        foreach ($protectedRoutes as $route) {
            $response = $this->get($route);
            expect($response->status())->toBe(302, "Protected route {$route} should redirect when not authenticated");
        }
    });
});

/**
 * ✨ SASSILLA'S ROUTE TESTING WISDOM ✨
 * 
 * "Every route needs to work flawlessly, darling!
 * Because a broken link is like a wardrobe malfunction - 
 * absolutely UNACCEPTABLE during a performance!" 💅
 */