<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Blade Component Tests', function () {

    it('can render page-template component with no props', function () {
        $html = view('components.page-template')->with('slot', 'Test content')->render();
        expect($html)->toBeString();
        expect($html)->toContain('Test content');
    });

    it('can render page-template component with basic props', function () {
        $props = [
            'title' => 'Test Page',
            'breadcrumbs' => [
                ['name' => 'Home', 'url' => '/'],
                ['name' => 'Test'],
            ],
        ];

        $view = view('components.page-template', $props)->with('slot', 'Test content');
        $html = $view->render();

        expect($html)->toContain('Test Page');
    });

    it('can render page-template component with simple actions', function () {
        $props = [
            'title' => 'Test Page',
            'actions' => [
                [
                    'type' => 'button',
                    'label' => 'Simple Button',
                    'variant' => 'primary',
                ],
            ],
        ];

        $view = view('components.page-template', $props)->with('slot', 'Test content');
        $html = $view->render();

        expect($html)->toContain('Simple Button');
    });

    it('can render page-template component with link actions', function () {
        $props = [
            'title' => 'Test Page',
            'actions' => [
                [
                    'type' => 'link',
                    'href' => '/test-link',
                    'label' => 'Test Link',
                    'variant' => 'outline',
                ],
            ],
        ];

        $view = view('components.page-template', $props)->with('slot', 'Test content');
        $html = $view->render();

        expect($html)->toContain('Test Link');
        expect($html)->toContain('/test-link');
    });

    it('can render page-template component with mixed actions', function () {
        $props = [
            'title' => 'Test Page',
            'actions' => [
                [
                    'type' => 'link',
                    'href' => '/link',
                    'label' => 'Link Action',
                    'wire:navigate' => true,
                ],
                [
                    'type' => 'button',
                    'label' => 'Button Action',
                    'wire:click' => 'doSomething',
                    'icon' => 'plus',
                ],
            ],
        ];

        $view = view('components.page-template', $props)->with('slot', 'Test content');

        $html = $view->render();
        expect($html)->toBeString();
    });

    it('can render data-table component', function () {
        $props = [
            'data' => collect([
                ['name' => 'Item 1', 'status' => 'active'],
                ['name' => 'Item 2', 'status' => 'inactive'],
            ]),
            'columns' => [
                ['key' => 'name', 'label' => 'Name'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
            ],
        ];

        $view = view('components.data-table', $props)->with('slot', '');

        $html = $view->render();
        expect($html)->toBeString();
    });

    it('can render stats-card component', function () {
        $props = [
            'title' => 'Total Products',
            'value' => 1234,
            'icon' => 'cube',
            // Remove trend props that use invalid icons
        ];

        $view = view('components.stats-card', $props)->with('slot', '');
        $html = $view->render();

        expect($html)->toContain('Total Products');
        expect($html)->toContain('1234');
    });

    it('can render form-layout component', function () {
        $props = [
            'title' => 'Test Form',
            'columns' => 2,
        ];

        $view = view('components.form-layout', $props)->with('slot', '');

        $html = $view->render();
        expect($html)->toBeString();
    });

    it('handles empty actions array gracefully', function () {
        $props = [
            'title' => 'Test Page',
            'actions' => [],
        ];

        $view = view('components.page-template', $props)->with('slot', 'Test content');

        $html = $view->render();
        expect($html)->toBeString();
    });

    it('handles null actions gracefully', function () {
        $props = [
            'title' => 'Test Page',
            'actions' => null,
        ];

        $view = view('components.page-template', $props)->with('slot', 'Test content');

        $html = $view->render();
        expect($html)->toBeString();
    });

    it('handles invisible actions correctly', function () {
        $props = [
            'title' => 'Test Page',
            'actions' => [
                [
                    'type' => 'button',
                    'label' => 'Visible Button',
                    'visible' => true,
                ],
                [
                    'type' => 'button',
                    'label' => 'Hidden Button',
                    'visible' => false,
                ],
            ],
        ];

        $view = view('components.page-template', $props)->with('slot', 'Test content');
        $html = $view->render();

        expect($html)->toContain('Visible Button');
        expect($html)->not->toContain('Hidden Button');
    });

    it('can render breadcrumb component with valid items', function () {
        $props = [
            'items' => [
                ['name' => 'Home', 'url' => '/'],
                ['name' => 'Products', 'url' => '/products'],
                ['name' => 'Details'],
            ],
        ];

        $view = view('components.breadcrumb', $props);
        $html = $view->render();

        expect($html)->toContain('Home');
        expect($html)->toContain('Products');
        expect($html)->toContain('Details');
    });

    it('handles breadcrumb items with missing name key gracefully', function () {
        $props = [
            'items' => [
                ['name' => 'Home', 'url' => '/'],
                ['url' => '/products'], // Missing name key
                ['name' => 'Details'],
            ],
        ];

        $view = view('components.breadcrumb', $props);
        $html = $view->render();

        expect($html)->toBeString();
        expect($html)->toContain('Home');
        expect($html)->toContain('Details');
    });

    it('handles empty breadcrumb items array', function () {
        $props = ['items' => []];

        $view = view('components.breadcrumb', $props);
        $html = $view->render();

        expect($html)->toBeString();
    });

    it('can render products.index template with mock data', function () {
        // Create and authenticate a user
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        // Share global view variables that Laravel middleware normally provides
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);

        // Mock products collection for testing
        $mockProducts = collect([
            (object) [
                'id' => 1,
                'name' => 'Test Product',
                'parent_sku' => 'TEST-001',
                'status' => 'active',
                'variants_count' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Create a paginated mock that mimics Laravel's LengthAwarePaginator
        $paginatedProducts = new \Illuminate\Pagination\LengthAwarePaginator(
            $mockProducts,
            1,
            10,
            1,
            ['path' => '/products']
        );

        $view = view('products.index', ['products' => $paginatedProducts]);
        $html = $view->render();

        expect($html)->toBeString();
        expect($html)->toContain('Products');
        expect($html)->toContain('Test Product');
    });

});
