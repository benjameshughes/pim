<?php

namespace App\Livewire\Pim\Products\Management;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class ProductForm extends Component
{
    use WithFileUploads;

    public ?Product $product = null;

    public $name = '';

    public $slug = '';

    public $description = '';

    public $status = 'active';

    public $product_features_1 = '';

    public $product_features_2 = '';

    public $product_features_3 = '';

    public $product_features_4 = '';

    public $product_features_5 = '';

    public $product_details_1 = '';

    public $product_details_2 = '';

    public $product_details_3 = '';

    public $product_details_4 = '';

    public $product_details_5 = '';

    public $newImages = [];

    public $existingImages = [];

    public $imageType = 'gallery';

    protected $rules = [
        'name' => 'required|string|max:255',
        'slug' => 'nullable|string|max:255|unique:products,slug',
        'description' => 'nullable|string',
        'status' => 'required|in:active,inactive,discontinued',
        'product_features_1' => 'nullable|string',
        'product_features_2' => 'nullable|string',
        'product_features_3' => 'nullable|string',
        'product_features_4' => 'nullable|string',
        'product_features_5' => 'nullable|string',
        'product_details_1' => 'nullable|string',
        'product_details_2' => 'nullable|string',
        'product_details_3' => 'nullable|string',
        'product_details_4' => 'nullable|string',
        'product_details_5' => 'nullable|string',
        'newImages.*' => 'nullable|image|max:2048',
        'imageType' => 'required|in:main,gallery,swatch',
    ];

    public function mount(?Product $product = null)
    {
        if ($product) {
            $this->product = $product;
            $this->name = $product->name;
            $this->slug = $product->slug;
            $this->description = $product->description;
            $this->status = $product->status;
            $this->product_features_1 = $product->product_features_1;
            $this->product_features_2 = $product->product_features_2;
            $this->product_features_3 = $product->product_features_3;
            $this->product_features_4 = $product->product_features_4;
            $this->product_features_5 = $product->product_features_5;
            $this->product_details_1 = $product->product_details_1;
            $this->product_details_2 = $product->product_details_2;
            $this->product_details_3 = $product->product_details_3;
            $this->product_details_4 = $product->product_details_4;
            $this->product_details_5 = $product->product_details_5;
            // Load existing ProductImage records
            $this->existingImages = $product->productImages->map(function ($image) {
                return [
                    'id' => $image->id,
                    'path' => $image->image_path,
                    'type' => $image->image_type,
                    'alt_text' => $image->alt_text,
                ];
            })->toArray();
        }
    }

    public function removeExistingImage($index)
    {
        if (isset($this->existingImages[$index])) {
            $imageData = $this->existingImages[$index];

            // Delete the ProductImage record and file
            if (isset($imageData['id'])) {
                $productImage = ProductImage::find($imageData['id']);
                if ($productImage) {
                    // Delete file from storage
                    if (Storage::disk('public')->exists($productImage->image_path)) {
                        Storage::disk('public')->delete($productImage->image_path);
                    }
                    $productImage->delete();
                }
            }

            unset($this->existingImages[$index]);
            $this->existingImages = array_values($this->existingImages);
        }
    }

    public function removeNewImage($index)
    {
        unset($this->newImages[$index]);
        $this->newImages = array_values($this->newImages);
    }

    public function updatedName()
    {
        if (! $this->slug || $this->slug === Str::slug($this->name)) {
            $this->slug = Str::slug($this->name);
        }
    }

    public function save()
    {
        try {
            // Update validation rules for existing product
            $rules = $this->rules;
            if ($this->product && $this->product->exists) {
                $rules['slug'] = 'nullable|string|max:255|unique:products,slug,'.$this->product->id;
            }

            $validated = $this->validate($rules);

            // Generate slug if empty
            if (empty($validated['slug'])) {
                $validated['slug'] = $this->generateUniqueSlug($validated['name']);
            }

            // Remove image-related fields from product data
            unset($validated['newImages'], $validated['imageType']);

            if ($this->product && $this->product->exists) {
                $this->product->update($validated);
                $product = $this->product;
                session()->flash('message', 'Product updated successfully.');
            } else {
                $product = Product::create($validated);
                session()->flash('message', 'Product created successfully.');
            }

            // Handle new image uploads as ProductImage records
            foreach ($this->newImages as $newImage) {
                $path = $newImage->store('product-images', 'public');

                ProductImage::create([
                    'product_id' => $product->id,
                    'variant_id' => null,
                    'image_path' => $path,
                    'image_type' => $this->imageType,
                    'alt_text' => null,
                    'sort_order' => ProductImage::where('product_id', $product->id)
                        ->whereNull('variant_id')
                        ->max('sort_order') + 1 ?? 1,
                ]);
            }

            return redirect()->route('products.index');
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Re-throw validation exceptions so they can be caught by Livewire
            throw $e;
        } catch (\Exception $e) {
            // Add error to session for debugging
            session()->flash('error', 'Error saving product: '.$e->getMessage());
            \Log::error('ProductForm save error: '.$e->getMessage(), ['exception' => $e]);
        }
    }

    private function generateUniqueSlug($name)
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (Product::where('slug', $slug)->when($this->product, function ($query) {
            return $query->where('id', '!=', $this->product->id);
        })->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    public function render()
    {
        return view('livewire.pim.products.management.product-form');
    }
}
