<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('Route Debug Tests', function () {
    it('can access dashboard route for comparison', function () {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard'));

        $response->assertOk();
    });

    it('can access products index route for comparison', function () {
        $response = $this->actingAs($this->user)
            ->get(route('products.index'));

        $response->assertOk();
    });

    it('tests direct URL access to products create', function () {
        $response = $this->actingAs($this->user)
            ->get('/products/create');

        if ($response->status() !== 200) {
            echo 'Direct URL status: '.$response->status()."\n";
            echo 'Headers: '.json_encode($response->headers->all())."\n";
        }

        $response->assertOk();
    });

    it('tests route name resolution', function () {
        $url = route('products.create');
        expect($url)->toContain('products/create');

        $response = $this->actingAs($this->user)->get($url);

        if ($response->status() !== 200) {
            echo "Named route URL: $url\n";
            echo 'Status: '.$response->status()."\n";
        }

        $response->assertOk();
    });
});
