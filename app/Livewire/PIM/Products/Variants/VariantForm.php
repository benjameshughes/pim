<?php

namespace App\Livewire\Pim\Products\Variants;

use App\Models\Barcode;
use App\Models\BarcodePool;
use App\Models\Product;
use App\Models\ProductVariant;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class VariantForm extends Component
{
    use WithFileUploads;

    public ?ProductVariant $variant = null;

    #[Validate('required|exists:products,id')]
    public $product_id = '';

    #[Validate('required|string|max:255')]
    public $color = '';

    #[Validate('nullable|numeric|min:0')]
    public $width = '';

    #[Validate('nullable|numeric|min:0')]
    public $drop = '';

    #[Validate('required|string|max:255|unique:product_variants,sku')]
    public $sku = '';

    #[Validate('required|in:active,inactive,out_of_stock')]
    public $status = 'active';

    #[Validate('nullable|integer|min:0')]
    public $stock_level = 0;

    #[Validate('nullable|numeric|min:0')]
    public $package_length = '';

    #[Validate('nullable|numeric|min:0')]
    public $package_width = '';

    #[Validate('nullable|numeric|min:0')]
    public $package_height = '';

    #[Validate('nullable|numeric|min:0')]
    public $package_weight = '';

    #[Validate(['newImages.*' => 'image|max:2048'])]
    public $newImages = [];

    public $existingImages = [];

    // Barcode fields
    #[Validate('nullable|string|max:255')]
    public $barcode = '';

    #[Validate('nullable|string|in:EAN13,EAN8,UPC,CODE128,CODE39,CODABAR,QRCODE')]
    public $barcode_type = 'CODE128';

    public $generateBarcodeAutomatically = false;

    public $useGS1Pool = false;

    public function mount(?ProductVariant $variant = null)
    {
        if ($variant) {
            $this->variant = $variant;
            $this->product_id = $variant->product_id;
            $this->color = $variant->color; // Uses accessor method for attribute
            $this->width = $variant->width; // Uses accessor method for attribute
            $this->drop = $variant->drop; // Uses accessor method for attribute
            $this->sku = $variant->sku;
            $this->status = $variant->status;
            $this->stock_level = $variant->stock_level;
            $this->package_length = $variant->package_length;
            $this->package_width = $variant->package_width;
            $this->package_height = $variant->package_height;
            $this->package_weight = $variant->package_weight;
            $this->existingImages = $variant->images ?? [];

            // Load primary barcode if exists
            $primaryBarcode = $variant->primaryBarcode();
            if ($primaryBarcode) {
                $this->barcode = $primaryBarcode->barcode;
                $this->barcode_type = $primaryBarcode->barcode_type;
            }
        }

        // If coming from product page, pre-fill product_id
        if (request()->has('product')) {
            $this->product_id = request()->get('product');
        }
    }

    public function removeExistingImage($index)
    {
        unset($this->existingImages[$index]);
        $this->existingImages = array_values($this->existingImages);
    }

    public function removeNewImage($index)
    {
        unset($this->newImages[$index]);
        $this->newImages = array_values($this->newImages);
    }

    public function generateBarcode()
    {
        $this->barcode = Barcode::generateRandomBarcode($this->barcode_type);
    }

    public function assignFromPool()
    {
        $poolBarcode = BarcodePool::getNextAvailable($this->barcode_type);

        if ($poolBarcode) {
            $this->barcode = $poolBarcode->barcode;
            session()->flash('message', 'Barcode assigned from GS1 pool.');
        } else {
            session()->flash('error', "No available {$this->barcode_type} barcodes in pool.");
        }
    }

    private function handleBarcodeUpdate(ProductVariant $variant)
    {
        // Auto-assign from GS1 pool if requested
        if ($this->useGS1Pool && ! $this->barcode) {
            $poolBarcode = BarcodePool::getNextAvailable($this->barcode_type);
            if ($poolBarcode) {
                $poolBarcode->assignToVariant($variant);

                return; // Pool assignment handles barcode creation
            }
        }

        // Auto-generate if requested
        if ($this->generateBarcodeAutomatically && ! $this->barcode) {
            $this->barcode = Barcode::generateRandomBarcode($this->barcode_type);
        }

        if ($this->barcode) {
            // Check if this barcode is from the pool and needs assignment
            $poolBarcode = BarcodePool::where('barcode', $this->barcode)
                ->where('status', 'available')
                ->first();

            if ($poolBarcode) {
                // This is a pool barcode, use the pool assignment method
                $poolBarcode->assignToVariant($variant);
            } else {
                // Regular barcode handling
                $primaryBarcode = $variant->primaryBarcode();

                if ($primaryBarcode) {
                    // Update existing barcode
                    $primaryBarcode->update([
                        'barcode' => $this->barcode,
                        'barcode_type' => $this->barcode_type,
                    ]);
                } else {
                    // Create new barcode
                    Barcode::create([
                        'product_variant_id' => $variant->id,
                        'barcode' => $this->barcode,
                        'barcode_type' => $this->barcode_type,
                        'is_primary' => true,
                    ]);
                }
            }
        }
    }

    public function save()
    {
        if ($this->variant) {
            $this->validate([
                'product_id' => 'required|exists:products,id',
                'color' => 'required|string|max:255',
                'width' => 'nullable|numeric|min:0',
                'drop' => 'nullable|numeric|min:0',
                'sku' => 'required|string|max:255|unique:product_variants,sku,'.$this->variant->id,
                'status' => 'required|in:active,inactive,out_of_stock',
            ]);

            // Handle image uploads
            $allImages = $this->existingImages;

            foreach ($this->newImages as $newImage) {
                $path = $newImage->store('variant-images', 'public');
                $allImages[] = $path;
            }

            // Update basic variant info (no color/width/drop as they're now attributes)
            $this->variant->update([
                'product_id' => $this->product_id,
                'sku' => $this->sku,
                'status' => $this->status,
                'stock_level' => $this->stock_level,
                'package_length' => $this->package_length,
                'package_width' => $this->package_width,
                'package_height' => $this->package_height,
                'package_weight' => $this->package_weight,
                'images' => $allImages,
            ]);

            // Update attributes using the attribute system
            if ($this->color) {
                $this->variant->setVariantAttributeValue('color', $this->color, 'string', 'core');
            }
            if ($this->width) {
                $this->variant->setVariantAttributeValue('width', $this->width, 'number', 'core');
            }
            if ($this->drop) {
                $this->variant->setVariantAttributeValue('drop', $this->drop, 'number', 'core');
            }

            // Handle barcode
            $this->handleBarcodeUpdate($this->variant);

            session()->flash('message', 'Variant updated successfully.');
        } else {
            $this->validate();

            // Handle image uploads for new variant
            $allImages = [];

            foreach ($this->newImages as $newImage) {
                $path = $newImage->store('variant-images', 'public');
                $allImages[] = $path;
            }

            // Create variant with basic data (no color/width/drop as they're now attributes)
            $variant = ProductVariant::create([
                'product_id' => $this->product_id,
                'sku' => $this->sku,
                'status' => $this->status,
                'stock_level' => $this->stock_level,
                'package_length' => $this->package_length,
                'package_width' => $this->package_width,
                'package_height' => $this->package_height,
                'package_weight' => $this->package_weight,
                'images' => $allImages,
            ]);

            // Set attributes using the attribute system
            if ($this->color) {
                $variant->setVariantAttributeValue('color', $this->color, 'string', 'core');
            }
            if ($this->width) {
                $variant->setVariantAttributeValue('width', $this->width, 'number', 'core');
            }
            if ($this->drop) {
                $variant->setVariantAttributeValue('drop', $this->drop, 'number', 'core');
            }

            // Handle barcode for new variant
            $this->handleBarcodeUpdate($variant);

            session()->flash('message', 'Variant created successfully.');
        }

        return redirect()->route('products.variants.index');
    }

    public function render()
    {
        $products = Product::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('livewire.pim.products.variants.variant-form', [
            'products' => $products,
            'barcodeTypes' => Barcode::BARCODE_TYPES,
            'poolStats' => BarcodePool::getStats(),
        ]);
    }
}
