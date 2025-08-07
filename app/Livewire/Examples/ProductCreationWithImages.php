<?php

namespace App\Livewire\Examples;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Traits\HasImageUpload;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ProductCreationWithImages extends Component
{
    use HasImageUpload;

    // Product Data
    #[Validate('required|string|max:255')]
    public string $name = '';
    
    #[Validate('required|string|max:10')]
    public string $sku = '';
    
    #[Validate('nullable|string')]
    public string $description = '';
    
    #[Validate('required|numeric|min:0')]
    public float $price = 0;
    
    // Variant Data
    #[Validate('nullable|string|max:50')]
    public string $color = '';
    
    #[Validate('nullable|string|max:20')]
    public string $size = '';

    // State
    public Product $product;
    public ProductVariant $variant;
    public bool $productCreated = false;
    public bool $variantCreated = false;
    public string $currentStep = 'product'; // 'product', 'variant', 'images'
    public array $uploadedImages = [];

    public function createProduct(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:10|unique:products,sku',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
        ]);

        try {
            $this->product = Product::create([
                'name' => $this->name,
                'sku' => $this->sku,
                'description' => $this->description,
                'price' => $this->price,
                'status' => 'active',
            ]);

            $this->productCreated = true;
            $this->currentStep = 'variant';
            
            session()->flash('success', 'Product created successfully!');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to create product: ' . $e->getMessage());
        }
    }

    public function createVariant(): void
    {
        $this->validate([
            'color' => 'required|string|max:50',
            'size' => 'required|string|max:20',
        ]);

        try {
            // Check for duplicate variant
            $existing = ProductVariant::where('product_id', $this->product->id)
                ->where('color', $this->color)
                ->where('size', $this->size)
                ->first();
                
            if ($existing) {
                session()->flash('error', 'A variant with this color and size already exists.');
                return;
            }

            $this->variant = ProductVariant::create([
                'product_id' => $this->product->id,
                'color' => $this->color,
                'size' => $this->size,
                'price_adjustment' => 0,
                'stock_quantity' => 0,
            ]);

            $this->variantCreated = true;
            $this->currentStep = 'images';
            
            session()->flash('success', 'Variant created successfully! Now add some images.');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to create variant: ' . $e->getMessage());
        }
    }

    public function skipVariantCreation(): void
    {
        $this->currentStep = 'images';
        session()->flash('info', 'Skipped variant creation. Adding images to product.');
    }

    public function skipImageUpload(): void
    {
        $this->redirectToProductView();
    }

    #[On('images-uploaded')]
    public function handleImageUpload(array $data): void
    {
        $this->uploadedImages[] = $data;
        
        session()->flash('success', 
            "Uploaded {$data['count']} images successfully! " . 
            "Total images uploaded: " . collect($this->uploadedImages)->sum('count')
        );
    }

    public function completeCreation(): void
    {
        $totalImages = collect($this->uploadedImages)->sum('count');
        
        session()->flash('success', 
            "Product creation completed! Created product with " . 
            ($this->variantCreated ? "1 variant and " : "") . 
            "{$totalImages} images."
        );

        $this->redirectToProductView();
    }

    private function redirectToProductView(): void
    {
        $this->redirect(route('pim.products.view', $this->product));
    }

    public function render()
    {
        $model = null;
        $modelType = null;

        if ($this->currentStep === 'images') {
            if ($this->variantCreated) {
                $model = $this->variant;
                $modelType = 'variant';
            } elseif ($this->productCreated) {
                $model = $this->product;
                $modelType = 'product';
            }
        }

        $uploaderConfig = [];
        if ($model) {
            $uploaderConfig = $this->getImageUploaderConfig('main', $model);
            $uploaderConfig['upload_text'] = 'Upload main product images to get started';
            $uploaderConfig['max_files'] = 5;
        }

        return view('livewire.examples.product-creation-with-images', [
            'model' => $model,
            'modelType' => $modelType,
            'uploaderConfig' => $uploaderConfig,
        ]);
    }
}