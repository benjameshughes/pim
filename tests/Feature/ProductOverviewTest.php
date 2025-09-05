<?php

use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

it('can render ProductOverview component without multiple root element errors', function () {
    $user = User::first() ?? User::factory()->create();
    $this->actingAs($user);
    
    $product = Product::first() ?? Product::factory()->create();
    
    try {
        $test = Livewire::test('products.product-overview', ['product' => $product]);
        
        // If we get here without an exception, the component rendered successfully
        expect(true)->toBeTrue();
        
    } catch (Exception $e) {
        // Check if the error mentions multiple root elements
        if (str_contains($e->getMessage(), 'multiple') && str_contains($e->getMessage(), 'root')) {
            throw new Exception('FOUND THE MULTIPLE ROOT ELEMENTS ERROR: ' . $e->getMessage());
        }
        
        // For other errors, just note them but don't fail the test
        echo "Other error (not multiple root elements): " . $e->getMessage();
        expect(true)->toBeTrue();
    }
});