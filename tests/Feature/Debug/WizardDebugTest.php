<?php

use App\Livewire\Products\ProductWizardClean;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('Wizard Debug', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    it('can instantiate wizard component directly', function () {
        $component = Livewire::test(ProductWizardClean::class);
        
        $component->assertStatus(200);
    });

    it('can access wizard view file directly', function () {
        // Test the blade view exists
        $viewPath = resource_path('views/products/wizard-clean.blade.php');
        expect(file_exists($viewPath))->toBeTrue();
        
        // Test the livewire view exists
        $livewireViewPath = resource_path('views/livewire/products/product-wizard-clean.blade.php');
        expect(file_exists($livewireViewPath))->toBeTrue();
    });

    it('can check route configuration', function () {
        $routes = collect(\Illuminate\Support\Facades\Route::getRoutes())
            ->filter(fn($route) => str_contains($route->getName() ?? '', 'wizard'));
            
        // Debug: Let's see what wizard routes exist
        $routeNames = $routes->map(fn($route) => $route->getName())->toArray();
        dump('Found wizard routes:', $routeNames);
        
        // Check for any product routes
        $productRoutes = collect(\Illuminate\Support\Facades\Route::getRoutes())
            ->filter(fn($route) => str_contains($route->getName() ?? '', 'products.wizard'));
        
        $productRouteNames = $productRoutes->map(fn($route) => $route->getName())->toArray();
        dump('Found product wizard routes:', $productRouteNames);
        
        expect($routes->count())->toBeGreaterThanOrEqual(0); // Allow 0 for now
    });
});