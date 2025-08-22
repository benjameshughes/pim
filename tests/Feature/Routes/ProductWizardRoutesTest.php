<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Product Wizard Routes', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    it('can access product wizard create route', function () {
        // Check route exists
        expect(route('products.wizard-clean'))->toContain('wizard-clean');
        
        // Try to access the working products index first
        $indexResponse = $this->actingAs($this->user)->get(route('products.index'));
        expect($indexResponse->status())->toBe(200);
        
        // Now try the wizard route
        $response = $this->actingAs($this->user)->get('/products/wizard-clean');
        
        // Debug the response
        if ($response->status() !== 200) {
            dump('Response status:', $response->status());
            dump('URL being tested:', '/products/wizard-clean');
            
            // Let's also check what routes are available during test
            $routes = collect(\Illuminate\Support\Facades\Route::getRoutes())
                ->map(function($route) {
                    return [
                        'uri' => $route->uri(),
                        'name' => $route->getName(),
                        'methods' => $route->methods()
                    ];
                })
                ->filter(fn($route) => str_contains($route['uri'], 'wizard'))
                ->toArray();
            dump('Available wizard routes during test:', $routes);
        }
        
        $response->assertStatus(200);
        
        // Check if it's the view or the livewire component
        $content = $response->getContent();
        if (str_contains($content, 'View error:')) {
            dump('View error found:', $content);
        }
        
        // The view should render
        expect($content)->not->toContain('View error:');
    });

    it('can generate product wizard route correctly', function () {
        $url = route('products.wizard-clean');
        
        expect($url)->toContain('/products/wizard-clean');
    });

    it('wizard route is properly named', function () {
        expect(route('products.wizard-clean'))->toBeString();
        expect(route('products.wizard-clean'))->toContain('wizard-clean');
    });

    it('product index page references correct wizard route', function () {
        // Visit products index to ensure the wizard link works
        $response = $this->get(route('products.index'));
        
        $response->assertStatus(200);
        // Should contain the corrected route reference
        $response->assertSee('products/wizard-clean');
    });
});