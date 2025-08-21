<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('Component Debug Tests', function () {
    it('can mount ProductWizardClean with no parameters', function () {
        try {
            $component = Livewire::test('products.product-wizard-clean');
            expect($component)->not->toBeNull();
        } catch (\Exception $e) {
            echo 'Component error: '.$e->getMessage()."\n";
            echo 'Stack trace: '.$e->getTraceAsString()."\n";
            throw $e;
        }
    });

    it('can mount ProductWizardClean with existing product', function () {
        $product = Product::factory()->create();

        try {
            $component = Livewire::test('products.product-wizard-clean', ['product' => $product]);
            expect($component)->not->toBeNull();
        } catch (\Exception $e) {
            echo 'Component with product error: '.$e->getMessage()."\n";
            throw $e;
        }
    });

    it('can test if the view file renders', function () {
        try {
            $view = view('livewire.products.product-wizard-clean');
            $content = $view->render();
            expect($content)->toContain('max-w-4xl');
        } catch (\Exception $e) {
            echo 'View render error: '.$e->getMessage()."\n";
            throw $e;
        }
    });
});
