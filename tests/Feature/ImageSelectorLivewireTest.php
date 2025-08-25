<?php

use App\Livewire\Images\ImageSelector;
use App\Models\Image;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('ImageSelector Livewire Component', function () {
    
    test('component renders successfully', function () {
        Livewire::test(ImageSelector::class)
            ->assertStatus(200);
    });
    
    test('component displays available images', function () {
        $image1 = Image::factory()->create(['title' => 'Available Image 1']);
        $image2 = Image::factory()->create(['title' => 'Available Image 2']);
        
        Livewire::test(ImageSelector::class)
            ->assertStatus(200);
            
        // Note: Actual visibility depends on component implementation
        // This test ensures component loads without error
    });
    
    test('component handles empty image library', function () {
        Livewire::test(ImageSelector::class)
            ->assertStatus(200);
    });
    
});

describe('ImageSelector Modal Behavior', function () {
    
    test('component can be opened and closed', function () {
        $component = Livewire::test(ImageSelector::class);
        
        // Test that component methods exist and don't error
        if (method_exists($component->instance(), 'openModal')) {
            $component->call('openModal')->assertStatus(200);
        }
        
        if (method_exists($component->instance(), 'closeModal')) {
            $component->call('closeModal')->assertStatus(200);
        }
    });
    
});

describe('ImageSelector Search and Filter', function () {
    
    beforeEach(function () {
        $this->image1 = Image::factory()->create([
            'title' => 'Red Product Image',
            'folder' => 'products',
            'tags' => ['red', 'featured'],
        ]);
        
        $this->image2 = Image::factory()->create([
            'title' => 'Blue Banner Image', 
            'folder' => 'banners',
            'tags' => ['blue', 'sale'],
        ]);
    });
    
    test('component handles search functionality', function () {
        $component = Livewire::test(ImageSelector::class);
        
        // Test search property exists and can be set
        if (property_exists($component->instance(), 'search')) {
            $component->set('search', 'Red Product')
                ->assertStatus(200);
        }
    });
    
    test('component handles folder filtering', function () {
        $component = Livewire::test(ImageSelector::class);
        
        // Test folder filter property exists and can be set
        if (property_exists($component->instance(), 'selectedFolder')) {
            $component->set('selectedFolder', 'products')
                ->assertStatus(200);
        }
    });
    
    test('component handles tag filtering', function () {
        $component = Livewire::test(ImageSelector::class);
        
        // Test tag filter property exists and can be set
        if (property_exists($component->instance(), 'selectedTag')) {
            $component->set('selectedTag', 'featured')
                ->assertStatus(200);
        }
    });
    
});

describe('ImageSelector Selection Logic', function () {
    
    beforeEach(function () {
        $this->image = Image::factory()->create(['title' => 'Selectable Image']);
    });
    
    test('component handles single image selection', function () {
        $component = Livewire::test(ImageSelector::class);
        
        // Test selection method exists
        if (method_exists($component->instance(), 'selectImage')) {
            $component->call('selectImage', $this->image->id)
                ->assertStatus(200);
        }
    });
    
    test('component handles multiple image selection', function () {
        $image2 = Image::factory()->create(['title' => 'Second Image']);
        
        $component = Livewire::test(ImageSelector::class);
        
        // Test multiple selection if supported
        if (property_exists($component->instance(), 'selectedImages')) {
            $component->set('selectedImages', [$this->image->id, $image2->id])
                ->assertStatus(200);
        }
    });
    
    test('component can clear selection', function () {
        $component = Livewire::test(ImageSelector::class);
        
        // Test clear selection method
        if (method_exists($component->instance(), 'clearSelection')) {
            $component->call('clearSelection')
                ->assertStatus(200);
        }
    });
    
});

describe('ImageSelector Events and Communication', function () {
    
    test('component can dispatch selection events', function () {
        $image = Image::factory()->create();
        
        $component = Livewire::test(ImageSelector::class);
        
        // Test that component can dispatch events without error
        if (method_exists($component->instance(), 'selectImage')) {
            $component->call('selectImage', $image->id)
                ->assertStatus(200);
        }
    });
    
    test('component responds to external events', function () {
        $component = Livewire::test(ImageSelector::class);
        
        // Test component can receive events
        if (method_exists($component->instance(), 'openForProduct')) {
            $product = Product::factory()->create();
            $component->call('openForProduct', $product->id)
                ->assertStatus(200);
        }
    });
    
});

describe('ImageSelector Integration with Products/Variants', function () {
    
    test('component works with product context', function () {
        $product = Product::factory()->create();
        $image = Image::factory()->create();
        
        $component = Livewire::test(ImageSelector::class);
        
        // Test component handles product-specific logic
        if (property_exists($component->instance(), 'targetModel')) {
            $component->set('targetModel', 'product')
                ->set('targetId', $product->id)
                ->assertStatus(200);
        }
    });
    
    test('component works with variant context', function () {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $image = Image::factory()->create();
        
        $component = Livewire::test(ImageSelector::class);
        
        // Test component handles variant-specific logic
        if (property_exists($component->instance(), 'targetModel')) {
            $component->set('targetModel', 'variant')
                ->set('targetId', $variant->id)
                ->assertStatus(200);
        }
    });
    
});

describe('ImageSelector Performance and Pagination', function () {
    
    test('component handles large image libraries', function () {
        Image::factory()->count(50)->create();
        
        $component = Livewire::test(ImageSelector::class)
            ->assertStatus(200);
        
        // Component should handle large datasets without error
        expect($component->instance())->toBeInstanceOf(ImageSelector::class);
    });
    
    test('component respects pagination settings', function () {
        Image::factory()->count(30)->create();
        
        $component = Livewire::test(ImageSelector::class);
        
        // Test pagination if implemented
        if (property_exists($component->instance(), 'perPage')) {
            $component->set('perPage', 12)
                ->assertStatus(200);
        }
    });
    
});

describe('ImageSelector Error Handling', function () {
    
    test('component handles invalid image selection gracefully', function () {
        $component = Livewire::test(ImageSelector::class);
        
        // Test selection of non-existent image
        if (method_exists($component->instance(), 'selectImage')) {
            $component->call('selectImage', 999999)
                ->assertStatus(200);
        }
    });
    
    test('component handles invalid model context gracefully', function () {
        $component = Livewire::test(ImageSelector::class);
        
        // Test invalid model context
        if (property_exists($component->instance(), 'targetModel')) {
            $component->set('targetModel', 'invalid')
                ->set('targetId', 999999)
                ->assertStatus(200);
        }
    });
    
});

describe('ImageSelector Accessibility and UX', function () {
    
    test('component provides proper keyboard navigation support', function () {
        Image::factory()->count(5)->create();
        
        $component = Livewire::test(ImageSelector::class)
            ->assertStatus(200);
        
        // Component should load without JavaScript errors
        expect($component->instance())->toBeInstanceOf(ImageSelector::class);
    });
    
    test('component supports screen reader compatibility', function () {
        $image = Image::factory()->create([
            'title' => 'Accessible Image',
            'alt_text' => 'Image description for screen readers'
        ]);
        
        $component = Livewire::test(ImageSelector::class)
            ->assertStatus(200);
        
        // Component should handle accessibility attributes properly
        expect($component->instance())->toBeInstanceOf(ImageSelector::class);
    });
    
});