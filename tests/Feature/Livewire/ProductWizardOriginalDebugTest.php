<?php

use App\Livewire\Pim\Products\Management\ProductWizard;
use App\Models\AttributeDefinition;
use App\Models\BarcodePool;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('ProductWizard Original Debug - Exact Replica 🔍', function () {

    beforeEach(function () {
        Storage::fake('public');
        
        // Create attribute definitions (exact same as original test)
        AttributeDefinition::factory()->color()->forProducts()->required()->create([
            'is_active' => true,
        ]);
        
        AttributeDefinition::factory()->material()->forProducts()->create([
            'is_required' => false,
            'is_active' => true,
        ]);

        // Create barcodes (exact same as original)
        BarcodePool::factory()->ean13()->count(50)->create();
    });

    it('replicates the original failing test exactly with debug', function () {
        Log::info('=== REPLICATING ORIGINAL FAILING TEST ===');
        
        $image = UploadedFile::fake()->image('product.jpg');
        
        $component = Livewire::test(ProductWizard::class)
            ->set('form.name', 'Ultimate Window Treatment System')
            ->set('form.description', 'The crème de la crème of window coverings')
            ->set('form.status', 'active')
            ->set('form.product_features_1', 'Whisper-quiet motor')
            ->set('form.product_features_2', 'Smart home integration')
            ->set('form.product_details_1', 'Premium aluminum construction')
            ->set('parentSku', '001')
            ->set('newImages.0', $image)
            ->set('imageType', 'gallery')
            ->set('attributeValues.color', 'Charcoal Grey')  // Exact same value
            ->set('selectedColors', ['Black', 'White', 'Grey'])
            ->set('selectedWidths', ['120cm', '160cm'])
            ->set('selectedDrops', ['200cm'])
            ->set('barcodeAssignmentMethod', 'auto');

        Log::info('Component setup completed with exact original values');
        
        // Generate variant matrix
        $component->call('generateVariantMatrix');
        $variantMatrix = $component->get('variantMatrix');
        
        Log::info('Generated variant matrix count: ' . count($variantMatrix));
        Log::info('Expected: 6 variants (3 colors × 2 widths × 1 drop)');
        
        // Check barcode assignment
        $variantBarcodes = $component->get('variantBarcodes');
        Log::info('Assigned barcodes count: ' . count($variantBarcodes));
        
        // Check initial database state
        expect(Product::count())->toBe(0);
        expect(ProductVariant::count())->toBe(0);
        
        // Try to create the product
        Log::info('About to call createProduct()');
        $component->call('createProduct');
        
        // Check results
        $productCount = Product::count();
        $variantCount = ProductVariant::count();
        
        Log::info("After original replica createProduct call:");
        Log::info("Products count: {$productCount}");
        Log::info("Variants count: {$variantCount}");
        
        // Check flash messages
        $flashMessage = session('message');
        $flashError = session('error');
        
        Log::info('Flash message: ' . ($flashMessage ?? 'none'));
        Log::info('Flash error: ' . ($flashError ?? 'none'));
        
        // This should reveal the exact issue
        expect($productCount)->toBe(1);
        expect($variantCount)->toBe(6); // 3 colors × 2 widths × 1 drop
    });
});