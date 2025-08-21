<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('Product Routes Authentication', function () {
    it('redirects unauthenticated users to login for create route', function () {
        $response = $this->get(route('products.create'));

        $response->assertRedirect(route('login'));
    });

    it('redirects unauthenticated users to login for edit route', function () {
        $product = Product::factory()->create();

        $response = $this->get(route('products.edit', $product));

        $response->assertRedirect(route('login'));
    });

    it('allows authenticated users to access create route', function () {
        $response = $this->actingAs($this->user)
            ->get(route('products.create'));

        $response->assertOk();
    });

    it('allows authenticated users to access edit route', function () {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('products.edit', $product));

        $response->assertOk();
    });
});

describe('Product Create Route (/products/create)', function () {
    beforeEach(function () {
        $this->actingAs($this->user);
    });

    it('returns 200 status for authenticated users', function () {
        $response = $this->get(route('products.create'));

        $response->assertOk();
    });

    it('uses correct view template', function () {
        $response = $this->get(route('products.create'));

        $response->assertViewIs('products.create');
    });

    it('contains expected page elements', function () {
        $response = $this->get(route('products.create'));

        $response->assertSee('✨ Create New Product')
            ->assertSee('Use our magical product wizard to create amazing products')
            ->assertSee('Dashboard')
            ->assertSee('Products')
            ->assertSee('Create');
    });

    it('loads ProductWizardClean component', function () {
        $response = $this->get(route('products.create'));

        $response->assertSeeLivewire('products.product-wizard-clean');
    });

    it('has proper breadcrumb navigation', function () {
        $response = $this->get(route('products.create'));

        $response->assertSee('Dashboard')
            ->assertSee('Products')
            ->assertSee('Create');
    });

    it('sets correct page title', function () {
        $response = $this->get(route('products.create'));

        $response->assertSee('<title>Create Product</title>', false);
    });

    it('uses app layout', function () {
        $response = $this->get(route('products.create'));

        // Should contain layout structure
        $response->assertSee('container max-w-7xl');
    });
});

describe('Product Edit Route (/products/{product}/edit)', function () {
    beforeEach(function () {
        $this->actingAs($this->user);
        $this->product = Product::factory()->create([
            'name' => 'Test Product for Editing',
            'parent_sku' => 'TEST-EDIT-001',
            'status' => 'active',
        ]);
    });

    it('returns 200 status for authenticated users with valid product', function () {
        $response = $this->get(route('products.edit', $this->product));

        $response->assertOk();
    });

    it('returns 404 for non-existent product', function () {
        $response = $this->get('/products/99999/edit');

        $response->assertNotFound();
    });

    it('uses correct view template', function () {
        $response = $this->get(route('products.edit', $this->product));

        $response->assertViewIs('products.wizard-clean');
    });

    it('passes product data to view', function () {
        $response = $this->get(route('products.edit', $this->product));

        $response->assertViewHas('product', $this->product);
    });

    it('loads ProductWizardClean component in edit mode', function () {
        $response = $this->get(route('products.edit', $this->product));

        $response->assertSeeLivewire('products.product-wizard-clean');
    });

    it('handles products with special characters in name', function () {
        $specialProduct = Product::factory()->create([
            'name' => 'Special Product with "Quotes" & Symbols!',
        ]);

        $response = $this->get(route('products.edit', $specialProduct));

        $response->assertOk();
    });

    it('works with different product statuses', function () {
        $statuses = ['active', 'inactive', 'draft', 'archived'];

        foreach ($statuses as $status) {
            $product = Product::factory()->create(['status' => $status]);

            $response = $this->get(route('products.edit', $product));

            $response->assertOk();
        }
    });

    it('handles products with null optional fields', function () {
        $minimalProduct = Product::factory()->create([
            'name' => 'Minimal Product',
            'parent_sku' => 'MIN-001',
            'description' => null,
        ]);

        $response = $this->get(route('products.edit', $minimalProduct));

        $response->assertOk();
    });
});

describe('Route Performance', function () {
    beforeEach(function () {
        $this->actingAs($this->user);
    });

    it('create route performs within acceptable time', function () {
        $startTime = microtime(true);

        $this->get(route('products.create'));

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        expect($executionTime)->toBeLessThan(1000); // Should complete within 1 second
    });

    it('edit route performs within acceptable time', function () {
        $product = Product::factory()->create();

        $startTime = microtime(true);

        $this->get(route('products.edit', $product));

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        expect($executionTime)->toBeLessThan(1000);
    });
});

describe('Route Security', function () {
    it('create route requires authentication middleware', function () {
        $routeCollection = app('router')->getRoutes();
        $createRoute = $routeCollection->getByName('products.create');

        expect($createRoute)->not->toBeNull();
        expect($createRoute->middleware())->toContain('auth');
    });

    it('edit route requires authentication middleware', function () {
        $routeCollection = app('router')->getRoutes();
        $editRoute = $routeCollection->getByName('products.edit');

        expect($editRoute)->not->toBeNull();
        expect($editRoute->middleware())->toContain('auth');
    });

    it('edit route validates product parameter binding', function () {
        $this->actingAs($this->user);

        // Test with invalid product ID
        $response = $this->get('/products/invalid-id/edit');

        $response->assertNotFound();
    });
});

describe('Content Security', function () {
    beforeEach(function () {
        $this->actingAs($this->user);
    });

    it('create route escapes user content properly', function () {
        $response = $this->get(route('products.create'));

        // Should not contain unescaped script tags or HTML
        expect($response->getContent())->not->toContain('<script>');
        expect($response->getContent())->not->toContain('javascript:');
    });

    it('edit route escapes product data properly', function () {
        $xssProduct = Product::factory()->create([
            'name' => '<script>alert("xss")</script>Product',
            'description' => '<img src=x onerror=alert("xss")>Description',
        ]);

        $response = $this->get(route('products.edit', $xssProduct));

        // Should not contain unescaped script content
        expect($response->getContent())->not->toContain('<script>alert("xss")</script>');
        expect($response->getContent())->not->toContain('<img src=x onerror=alert("xss")>');
    });
});
