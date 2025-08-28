<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\SalesChannel;

beforeEach(function () {
    // Create and authenticate user
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    
    // Create required sales channel for pricing tests
    $this->salesChannel = SalesChannel::factory()->create([
        'name' => 'Default'
    ]);
    
    // Create test data
    $this->product = Product::factory()->create([
        'name' => 'Test Product',
        'parent_sku' => 'TEST-001',
        'status' => 'active'
    ]);
    
    $this->variant = ProductVariant::factory()->create([
        'product_id' => $this->product->id,
        'sku' => 'TEST-001-White',
        'title' => 'Test Product White 120cm',
        'color' => 'White',
        'width' => 120,
        'drop' => 180,
        'status' => 'active'
    ]);
});

describe('Variant Routes', function () {
    test('variants index redirects to products', function () {
        $response = $this->get(route('variants.index'));
        
        $response->assertRedirect(route('products.index'));
    });
    
    test('variants create page loads successfully', function () {
        $response = $this->get(route('variants.create'));
        
        $response->assertOk();
        $response->assertViewIs('variants.create');
    });
    
    test('variants show page loads successfully', function () {
        $response = $this->get(route('variants.show', $this->variant));
        
        $response->assertOk();
        $response->assertViewIs('variants.show');
        $response->assertViewHas('variant', $this->variant);
    });
    
    test('variants show page displays variant information', function () {
        $response = $this->get(route('variants.show', $this->variant));
        
        $response->assertSee($this->variant->sku);
        $response->assertSee($this->variant->title);
        $response->assertSee($this->variant->color);
        $response->assertSee($this->product->name);
    });
    
    test('variants edit page loads successfully', function () {
        $response = $this->get(route('variants.edit', $this->variant));
        
        $response->assertOk();
        $response->assertViewIs('variants.edit');
        $response->assertViewHas('variant', $this->variant);
    });
    
    test('variants edit page displays edit form', function () {
        $response = $this->get(route('variants.edit', $this->variant));
        
        // Check for Livewire wire:model attributes and form content
        $response->assertSee('wire:model="sku"', false);
        $response->assertSee('wire:model="title"', false);
        $response->assertSee('wire:model="color"', false);
        $response->assertSee($this->variant->sku);  // Should be displayed somewhere in the form
        $response->assertSee($this->variant->color);
    });
    
    test('nonexistent variant returns 404', function () {
        $response = $this->get(route('variants.show', 999999));
        
        $response->assertNotFound();
    });
});

describe('Variant View Components', function () {
    test('variant show page includes product relationship data', function () {
        $response = $this->get(route('variants.show', $this->variant));
        
        // Should show parent product information
        $response->assertSee($this->product->name);
        $response->assertSee($this->product->parent_sku);
    });
    
    test('variant edit page has required form fields', function () {
        $response = $this->get(route('variants.edit', $this->variant));
        
        // Check for key form elements
        $response->assertSee('name="sku"', false);
        $response->assertSee('name="title"', false);
        $response->assertSee('name="color"', false);
        $response->assertSee('name="width"', false);
        $response->assertSee('name="drop"', false);
    });
    
    test('variant create page has form fields', function () {
        $response = $this->get(route('variants.create'));
        
        // Should have create form
        $response->assertSee('form', false);
        $response->assertSee('Create', false);
    });
});

describe('Variant Data Integrity', function () {
    test('variant show handles missing pricing gracefully', function () {
        // Variant without pricing should not error
        $response = $this->get(route('variants.show', $this->variant));
        
        $response->assertOk();
        // Should handle null pricing gracefully
    });
    
    test('variant show handles missing barcode gracefully', function () {
        // Variant without barcode should not error
        $response = $this->get(route('variants.show', $this->variant));
        
        $response->assertOk();
        // Should handle null barcode gracefully
    });
    
    test('variant with pricing shows price information', function () {
        // Create pricing record
        \App\Models\Pricing::create([
            'product_variant_id' => $this->variant->id,
            'sales_channel_id' => $this->salesChannel->id,
            'price' => 29.99,
            'currency' => 'GBP'
        ]);
        
        $response = $this->get(route('variants.show', $this->variant));
        
        $response->assertOk();
        // Should display price information
        $response->assertSee('29.99');
    });
    
    test('variant with barcode shows barcode information', function () {
        // Create barcode record
        \App\Models\Barcode::create([
            'barcode' => '1234567890123',
            'sku' => $this->variant->sku,
            'title' => $this->variant->title,
            'product_variant_id' => $this->variant->id,
            'is_assigned' => true
        ]);
        
        $response = $this->get(route('variants.show', $this->variant));
        
        $response->assertOk();
        // Should display barcode information
        $response->assertSee('1234567890123');
    });
});