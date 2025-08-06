<?php

namespace App\Livewire\Products;

use App\Models\Barcode;
use App\Models\BarcodePool;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesChannel;
use App\Models\Pricing;
use App\Models\Marketplace;
use App\Models\MarketplaceVariant;
use App\Models\MarketplaceBarcode;
use App\Models\AttributeDefinition;
use App\Models\ProductAttribute;
use App\Models\VariantAttribute;
use App\Services\BarcodeDetector;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Layout('components.layouts.app')]
class VariantEdit extends Component
{
    use WithFileUploads;
    
    public ?ProductVariant $variant = null;
    public $isEditing = false;
    
    // Basic Information
    #[Validate('required|exists:products,id')]
    public $product_id = '';
    
    #[Validate('required|string|max:255')]
    public $color = '';
    
    #[Validate('required|string|max:255')]
    public $size = '';
    
    #[Validate('required|string|max:255')]
    public $sku = '';
    
    #[Validate('required|in:active,inactive,out_of_stock')]
    public $status = 'active';
    
    #[Validate('nullable|integer|min:0')]
    public $stock_level = 0;
    
    // Package Information
    #[Validate('nullable|numeric|min:0')]
    public $package_length = '';
    
    #[Validate('nullable|numeric|min:0')]
    public $package_width = '';
    
    #[Validate('nullable|numeric|min:0')]
    public $package_height = '';
    
    #[Validate('nullable|numeric|min:0')]
    public $package_weight = '';
    
    // Images
    #[Validate(['newImages.*' => 'image|max:2048'])]
    public $newImages = [];
    public $existingImages = [];
    
    // Barcode Management
    public $barcodes = [];
    public $newBarcode = '';
    public $newBarcodeType = 'EAN13';
    
    // Pricing Management
    public $pricing = [];
    public $newPricing = [
        'sales_channel_id' => '',
        'retail_price' => '',
        'cost_price' => ''
    ];
    
    // Marketplace Management
    public $marketplaceVariants = [];
    public $newMarketplaceVariant = [
        'marketplace_id' => '',
        'title' => '',
        'description' => '',
        'price_override' => '',
        'marketplace_data' => []
    ];
    
    // Marketplace Barcodes
    public $marketplaceBarcodes = [];
    public $newMarketplaceBarcode = [
        'marketplace_id' => '',
        'identifier_type' => 'asin',
        'identifier_value' => ''
    ];
    
    // Product/Variant Attributes
    public $productAttributes = [];
    public $variantAttributes = [];
    public $newProductAttribute = [
        'attribute_key' => '',
        'attribute_value' => '',
        'data_type' => 'string',
        'category' => ''
    ];
    public $newVariantAttribute = [
        'attribute_key' => '',
        'attribute_value' => '',
        'data_type' => 'string', 
        'category' => ''
    ];
    
    // UI State
    public $activeTab = 'basic';
    public $showBarcodeModal = false;
    public $showPricingModal = false;
    public $showMarketplaceVariantModal = false;
    public $showMarketplaceBarcodeModal = false;
    public $showProductAttributeModal = false;
    public $showVariantAttributeModal = false;
    public $showDeleteConfirmation = false;
    
    public function mount(?ProductVariant $variant = null)
    {
        if ($variant && $variant->exists) {
            $this->variant = $variant->load([
                'product', 
                'barcodes', 
                'pricing.salesChannel',
                'marketplaceVariants.marketplace',
                'marketplaceBarcodes.marketplace',
                'attributes',
                'product.attributes'
            ]);
            $this->isEditing = true;
            
            // Load basic information
            $this->product_id = $variant->product_id;
            $this->color = $variant->color;
            $this->size = $variant->size;
            $this->sku = $variant->sku;
            $this->status = $variant->status;
            $this->stock_level = $variant->stock_level ?? 0;
            
            // Load package information
            $this->package_length = $variant->package_length;
            $this->package_width = $variant->package_width;
            $this->package_height = $variant->package_height;
            $this->package_weight = $variant->package_weight;
            
            // Load images
            $this->existingImages = $variant->images ?? [];
            
            // Load barcodes
            $this->barcodes = $variant->barcodes->map(function ($barcode) {
                return [
                    'id' => $barcode->id,
                    'barcode' => $barcode->barcode,
                    'barcode_type' => $barcode->barcode_type,
                    'is_primary' => $barcode->is_primary,
                ];
            })->toArray();
            
            // Load pricing
            $this->pricing = $variant->pricing->map(function ($price) {
                return [
                    'id' => $price->id,
                    'sales_channel_id' => $price->sales_channel_id,
                    'retail_price' => $price->retail_price,
                    'cost_price' => $price->cost_price,
                    'channel_name' => $price->salesChannel->name ?? 'Default'
                ];
            })->toArray();
            
            // Load marketplace variants
            $this->marketplaceVariants = $variant->marketplaceVariants->map(function ($mv) {
                return [
                    'id' => $mv->id,
                    'marketplace_id' => $mv->marketplace_id,
                    'marketplace_name' => $mv->marketplace->name,
                    'title' => $mv->title,
                    'description' => $mv->description,
                    'price_override' => $mv->price_override,
                    'status' => $mv->status,
                    'marketplace_data' => $mv->marketplace_data ?? []
                ];
            })->toArray();
            
            // Load marketplace barcodes
            $this->marketplaceBarcodes = $variant->marketplaceBarcodes->map(function ($mb) {
                return [
                    'id' => $mb->id,
                    'marketplace_id' => $mb->marketplace_id,
                    'marketplace_name' => $mb->marketplace->name,
                    'identifier_type' => $mb->identifier_type,
                    'identifier_value' => $mb->identifier_value,
                    'is_active' => $mb->is_active
                ];
            })->toArray();
            
            // Load variant attributes
            $this->variantAttributes = $variant->attributes->map(function ($attr) {
                return [
                    'id' => $attr->id,
                    'attribute_key' => $attr->attribute_key,
                    'attribute_value' => $attr->attribute_value,
                    'data_type' => $attr->data_type,
                    'category' => $attr->category
                ];
            })->toArray();
            
            // Load product attributes
            $this->productAttributes = $variant->product->attributes->map(function ($attr) {
                return [
                    'id' => $attr->id,
                    'attribute_key' => $attr->attribute_key,
                    'attribute_value' => $attr->attribute_value,
                    'data_type' => $attr->data_type,
                    'category' => $attr->category
                ];
            })->toArray();
        } else {
            $this->variant = new ProductVariant();
            $this->isEditing = false;
            
            // Pre-fill product_id if provided
            if (request()->has('product')) {
                $this->product_id = request()->get('product');
            }
        }
    }
    
    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
    }
    
    // Image Management
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
    
    // Barcode Management
    public function addBarcode()
    {
        $this->validate([
            'newBarcode' => 'required|string|max:255',
            'newBarcodeType' => 'required|string'
        ]);
        
        // Auto-detect barcode type if not manually set
        $detectedType = BarcodeDetector::detectBarcodeType($this->newBarcode);
        $barcodeInfo = BarcodeDetector::getBarcodeInfo($this->newBarcode);
        
        $this->barcodes[] = [
            'id' => null,
            'barcode' => $this->newBarcode,
            'barcode_type' => $this->newBarcodeType ?: $detectedType,
            'is_primary' => count($this->barcodes) === 0,
            'is_valid' => $barcodeInfo['is_valid']
        ];
        
        $this->newBarcode = '';
        $this->newBarcodeType = 'EAN13';
        $this->showBarcodeModal = false;
        
        session()->flash('message', 'Barcode added successfully.');
    }
    
    public function removeBarcode($index)
    {
        unset($this->barcodes[$index]);
        $this->barcodes = array_values($this->barcodes);
        
        // Ensure we have a primary barcode
        if (count($this->barcodes) > 0 && !collect($this->barcodes)->where('is_primary', true)->count()) {
            $this->barcodes[0]['is_primary'] = true;
        }
    }
    
    public function setPrimaryBarcode($index)
    {
        foreach ($this->barcodes as $key => $barcode) {
            $this->barcodes[$key]['is_primary'] = ($key === $index);
        }
    }
    
    public function generateBarcode()
    {
        $this->newBarcode = Barcode::generateRandomBarcode($this->newBarcodeType);
    }
    
    public function assignFromPool()
    {
        $poolBarcode = BarcodePool::getNextAvailable($this->newBarcodeType);
        
        if ($poolBarcode) {
            $this->newBarcode = $poolBarcode->barcode;
            session()->flash('message', 'Barcode assigned from GS1 pool.');
        } else {
            session()->flash('error', "No available {$this->newBarcodeType} barcodes in pool.");
        }
    }
    
    // Pricing Management
    public function addPricing()
    {
        $this->validate([
            'newPricing.retail_price' => 'required|numeric|min:0',
            'newPricing.cost_price' => 'nullable|numeric|min:0'
        ]);
        
        $salesChannel = null;
        if ($this->newPricing['sales_channel_id']) {
            $salesChannel = SalesChannel::find($this->newPricing['sales_channel_id']);
        }
        
        $this->pricing[] = [
            'id' => null,
            'sales_channel_id' => $this->newPricing['sales_channel_id'] ?: null,
            'retail_price' => $this->newPricing['retail_price'],
            'cost_price' => $this->newPricing['cost_price'] ?: null,
            'channel_name' => $salesChannel->name ?? 'Default'
        ];
        
        $this->newPricing = [
            'sales_channel_id' => '',
            'retail_price' => '',
            'cost_price' => ''
        ];
        
        $this->showPricingModal = false;
        session()->flash('message', 'Pricing added successfully.');
    }
    
    public function removePricing($index)
    {
        unset($this->pricing[$index]);
        $this->pricing = array_values($this->pricing);
    }
    
    // Marketplace Variant Management
    public function addMarketplaceVariant()
    {
        $this->validate([
            'newMarketplaceVariant.marketplace_id' => 'required|exists:marketplaces,id',
            'newMarketplaceVariant.title' => 'required|string|max:255',
            'newMarketplaceVariant.price_override' => 'nullable|numeric|min:0'
        ]);
        
        $marketplace = Marketplace::find($this->newMarketplaceVariant['marketplace_id']);
        
        $this->marketplaceVariants[] = [
            'id' => null,
            'marketplace_id' => $this->newMarketplaceVariant['marketplace_id'],
            'marketplace_name' => $marketplace->name,
            'title' => $this->newMarketplaceVariant['title'],
            'description' => $this->newMarketplaceVariant['description'],
            'price_override' => $this->newMarketplaceVariant['price_override'] ?: null,
            'status' => 'active',
            'marketplace_data' => $this->newMarketplaceVariant['marketplace_data'] ?? []
        ];
        
        $this->newMarketplaceVariant = [
            'marketplace_id' => '',
            'title' => '',
            'description' => '',
            'price_override' => '',
            'marketplace_data' => []
        ];
        
        $this->showMarketplaceVariantModal = false;
        session()->flash('message', 'Marketplace variant added successfully.');
    }
    
    public function removeMarketplaceVariant($index)
    {
        unset($this->marketplaceVariants[$index]);
        $this->marketplaceVariants = array_values($this->marketplaceVariants);
    }
    
    // Marketplace Barcode Management
    public function addMarketplaceBarcode()
    {
        $this->validate([
            'newMarketplaceBarcode.marketplace_id' => 'required|exists:marketplaces,id',
            'newMarketplaceBarcode.identifier_type' => 'required|string',
            'newMarketplaceBarcode.identifier_value' => 'required|string|max:255'
        ]);
        
        $marketplace = Marketplace::find($this->newMarketplaceBarcode['marketplace_id']);
        
        $this->marketplaceBarcodes[] = [
            'id' => null,
            'marketplace_id' => $this->newMarketplaceBarcode['marketplace_id'],
            'marketplace_name' => $marketplace->name,
            'identifier_type' => $this->newMarketplaceBarcode['identifier_type'],
            'identifier_value' => $this->newMarketplaceBarcode['identifier_value'],
            'is_active' => true
        ];
        
        $this->newMarketplaceBarcode = [
            'marketplace_id' => '',
            'identifier_type' => 'asin',
            'identifier_value' => ''
        ];
        
        $this->showMarketplaceBarcodeModal = false;
        session()->flash('message', 'Marketplace identifier added successfully.');
    }
    
    public function removeMarketplaceBarcode($index)
    {
        unset($this->marketplaceBarcodes[$index]);
        $this->marketplaceBarcodes = array_values($this->marketplaceBarcodes);
    }
    
    // Attribute Management
    public function addProductAttribute()
    {
        $this->validate([
            'newProductAttribute.attribute_key' => 'required|string|max:255',
            'newProductAttribute.attribute_value' => 'required|string',
            'newProductAttribute.data_type' => 'required|in:string,number,boolean,json'
        ]);
        
        $this->productAttributes[] = [
            'id' => null,
            'attribute_key' => $this->newProductAttribute['attribute_key'],
            'attribute_value' => $this->newProductAttribute['attribute_value'],
            'data_type' => $this->newProductAttribute['data_type'],
            'category' => $this->newProductAttribute['category']
        ];
        
        $this->newProductAttribute = [
            'attribute_key' => '',
            'attribute_value' => '',
            'data_type' => 'string',
            'category' => ''
        ];
        
        $this->showProductAttributeModal = false;
        session()->flash('message', 'Product attribute added successfully.');
    }
    
    public function removeProductAttribute($index)
    {
        unset($this->productAttributes[$index]);
        $this->productAttributes = array_values($this->productAttributes);
    }
    
    public function addVariantAttribute()
    {
        $this->validate([
            'newVariantAttribute.attribute_key' => 'required|string|max:255',
            'newVariantAttribute.attribute_value' => 'required|string',
            'newVariantAttribute.data_type' => 'required|in:string,number,boolean,json'
        ]);
        
        $this->variantAttributes[] = [
            'id' => null,
            'attribute_key' => $this->newVariantAttribute['attribute_key'],
            'attribute_value' => $this->newVariantAttribute['attribute_value'],
            'data_type' => $this->newVariantAttribute['data_type'],
            'category' => $this->newVariantAttribute['category']
        ];
        
        $this->newVariantAttribute = [
            'attribute_key' => '',
            'attribute_value' => '',
            'data_type' => 'string',
            'category' => ''
        ];
        
        $this->showVariantAttributeModal = false;
        session()->flash('message', 'Variant attribute added successfully.');
    }
    
    public function removeVariantAttribute($index)
    {
        unset($this->variantAttributes[$index]);
        $this->variantAttributes = array_values($this->variantAttributes);
    }
    
    // Main Save Function
    public function save()
    {
        // Validate basic fields
        $validatedData = $this->validate([
            'product_id' => 'required|exists:products,id',
            'color' => 'required|string|max:255',
            'size' => 'required|string|max:255',
            'sku' => $this->isEditing 
                ? 'required|string|max:255|unique:product_variants,sku,' . $this->variant->id
                : 'required|string|max:255|unique:product_variants,sku',
            'status' => 'required|in:active,inactive,out_of_stock',
            'stock_level' => 'nullable|integer|min:0',
            'package_length' => 'nullable|numeric|min:0',
            'package_width' => 'nullable|numeric|min:0',
            'package_height' => 'nullable|numeric|min:0',
            'package_weight' => 'nullable|numeric|min:0',
        ]);
        
        // Handle image uploads
        $allImages = $this->existingImages;
        foreach ($this->newImages as $newImage) {
            $path = $newImage->store('variant-images', 'public');
            $allImages[] = $path;
        }
        $validatedData['images'] = $allImages;
        
        // Save or update variant
        if ($this->isEditing) {
            $this->variant->update($validatedData);
        } else {
            $this->variant = ProductVariant::create($validatedData);
        }
        
        // Handle barcodes
        $this->saveBarcodes();
        
        // Handle pricing
        $this->savePricing();
        
        // Handle marketplace variants
        $this->saveMarketplaceVariants();
        
        // Handle marketplace barcodes
        $this->saveMarketplaceBarcodes();
        
        // Handle attributes
        $this->saveAttributes();
        
        $message = $this->isEditing ? 'Variant updated successfully.' : 'Variant created successfully.';
        session()->flash('message', $message);
        
        return $this->redirect(route('products.variants.view', $this->variant));
    }
    
    private function saveBarcodes()
    {
        if ($this->isEditing) {
            // Delete existing barcodes not in the current list
            $currentBarcodeIds = collect($this->barcodes)->pluck('id')->filter();
            $this->variant->barcodes()->whereNotIn('id', $currentBarcodeIds)->delete();
        }
        
        foreach ($this->barcodes as $barcodeData) {
            if ($barcodeData['id']) {
                // Update existing barcode
                Barcode::where('id', $barcodeData['id'])->update([
                    'barcode' => $barcodeData['barcode'],
                    'barcode_type' => $barcodeData['barcode_type'],
                    'is_primary' => $barcodeData['is_primary'],
                ]);
            } else {
                // Create new barcode
                Barcode::create([
                    'product_variant_id' => $this->variant->id,
                    'barcode' => $barcodeData['barcode'],
                    'barcode_type' => $barcodeData['barcode_type'],
                    'is_primary' => $barcodeData['is_primary'],
                ]);
            }
        }
    }
    
    private function savePricing()
    {
        if ($this->isEditing) {
            // Delete existing pricing not in the current list
            $currentPricingIds = collect($this->pricing)->pluck('id')->filter();
            $this->variant->pricing()->whereNotIn('id', $currentPricingIds)->delete();
        }
        
        foreach ($this->pricing as $pricingData) {
            if ($pricingData['id']) {
                // Update existing pricing
                Pricing::where('id', $pricingData['id'])->update([
                    'sales_channel_id' => $pricingData['sales_channel_id'],
                    'retail_price' => $pricingData['retail_price'],
                    'cost_price' => $pricingData['cost_price'],
                ]);
            } else {
                // Create new pricing
                Pricing::create([
                    'product_variant_id' => $this->variant->id,
                    'sales_channel_id' => $pricingData['sales_channel_id'],
                    'retail_price' => $pricingData['retail_price'],
                    'cost_price' => $pricingData['cost_price'],
                ]);
            }
        }
    }
    
    private function saveMarketplaceVariants()
    {
        if ($this->isEditing) {
            // Delete existing marketplace variants not in the current list
            $currentIds = collect($this->marketplaceVariants)->pluck('id')->filter();
            $this->variant->marketplaceVariants()->whereNotIn('id', $currentIds)->delete();
        }
        
        foreach ($this->marketplaceVariants as $mvData) {
            if ($mvData['id']) {
                // Update existing marketplace variant
                MarketplaceVariant::where('id', $mvData['id'])->update([
                    'marketplace_id' => $mvData['marketplace_id'],
                    'title' => $mvData['title'],
                    'description' => $mvData['description'],
                    'price_override' => $mvData['price_override'],
                    'status' => $mvData['status'],
                    'marketplace_data' => $mvData['marketplace_data'],
                ]);
            } else {
                // Create new marketplace variant
                MarketplaceVariant::create([
                    'variant_id' => $this->variant->id,
                    'marketplace_id' => $mvData['marketplace_id'],
                    'title' => $mvData['title'],
                    'description' => $mvData['description'],
                    'price_override' => $mvData['price_override'],
                    'status' => $mvData['status'],
                    'marketplace_data' => $mvData['marketplace_data'],
                ]);
            }
        }
    }
    
    private function saveMarketplaceBarcodes()
    {
        if ($this->isEditing) {
            // Delete existing marketplace barcodes not in the current list
            $currentIds = collect($this->marketplaceBarcodes)->pluck('id')->filter();
            $this->variant->marketplaceBarcodes()->whereNotIn('id', $currentIds)->delete();
        }
        
        foreach ($this->marketplaceBarcodes as $mbData) {
            if ($mbData['id']) {
                // Update existing marketplace barcode
                MarketplaceBarcode::where('id', $mbData['id'])->update([
                    'marketplace_id' => $mbData['marketplace_id'],
                    'identifier_type' => $mbData['identifier_type'],
                    'identifier_value' => $mbData['identifier_value'],
                    'is_active' => $mbData['is_active'],
                ]);
            } else {
                // Create new marketplace barcode
                MarketplaceBarcode::create([
                    'variant_id' => $this->variant->id,
                    'marketplace_id' => $mbData['marketplace_id'],
                    'identifier_type' => $mbData['identifier_type'],
                    'identifier_value' => $mbData['identifier_value'],
                    'is_active' => $mbData['is_active'],
                ]);
            }
        }
    }
    
    private function saveAttributes()
    {
        // Save product attributes
        if ($this->isEditing) {
            $currentProductAttrIds = collect($this->productAttributes)->pluck('id')->filter();
            ProductAttribute::where('product_id', $this->variant->product_id)
                ->whereNotIn('id', $currentProductAttrIds)
                ->delete();
        }
        
        foreach ($this->productAttributes as $attrData) {
            if ($attrData['id']) {
                // Update existing product attribute
                ProductAttribute::where('id', $attrData['id'])->update([
                    'attribute_key' => $attrData['attribute_key'],
                    'attribute_value' => $attrData['attribute_value'],
                    'data_type' => $attrData['data_type'],
                    'category' => $attrData['category'],
                ]);
            } else {
                // Create new product attribute
                ProductAttribute::create([
                    'product_id' => $this->variant->product_id,
                    'attribute_key' => $attrData['attribute_key'],
                    'attribute_value' => $attrData['attribute_value'],
                    'data_type' => $attrData['data_type'],
                    'category' => $attrData['category'],
                ]);
            }
        }
        
        // Save variant attributes
        if ($this->isEditing) {
            $currentVariantAttrIds = collect($this->variantAttributes)->pluck('id')->filter();
            $this->variant->attributes()->whereNotIn('id', $currentVariantAttrIds)->delete();
        }
        
        foreach ($this->variantAttributes as $attrData) {
            if ($attrData['id']) {
                // Update existing variant attribute
                VariantAttribute::where('id', $attrData['id'])->update([
                    'attribute_key' => $attrData['attribute_key'],
                    'attribute_value' => $attrData['attribute_value'],
                    'data_type' => $attrData['data_type'],
                    'category' => $attrData['category'],
                ]);
            } else {
                // Create new variant attribute
                VariantAttribute::create([
                    'variant_id' => $this->variant->id,
                    'attribute_key' => $attrData['attribute_key'],
                    'attribute_value' => $attrData['attribute_value'],
                    'data_type' => $attrData['data_type'],
                    'category' => $attrData['category'],
                ]);
            }
        }
    }
    
    public function cancel()
    {
        if ($this->isEditing) {
            return $this->redirect(route('products.variants.view', $this->variant));
        } else {
            return $this->redirect(route('products.variants.index'));
        }
    }
    
    public function render()
    {
        $products = Product::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
            
        $salesChannels = SalesChannel::orderBy('name')->get();
        $barcodeTypes = Barcode::BARCODE_TYPES;
        $poolStats = BarcodePool::getStats();
        
        $marketplaces = Marketplace::active()->orderBy('name')->get();
        $identifierTypes = [
            'asin' => 'Amazon ASIN',
            'item_id' => 'eBay Item ID',
            'listing_id' => 'Listing ID',
            'sku' => 'Marketplace SKU',
            'product_id' => 'Product ID'
        ];
        
        $attributeDefinitions = AttributeDefinition::active()->ordered()->get();
        $dataTypes = [
            'string' => 'Text',
            'number' => 'Number',
            'boolean' => 'Yes/No',
            'json' => 'JSON Data'
        ];
        
        $categories = [
            'physical' => 'Physical',
            'functional' => 'Functional',
            'compliance' => 'Compliance'
        ];
        
        return view('livewire.products.variant-edit', [
            'products' => $products,
            'salesChannels' => $salesChannels,
            'barcodeTypes' => $barcodeTypes,
            'poolStats' => $poolStats,
            'marketplaces' => $marketplaces,
            'identifierTypes' => $identifierTypes,
            'attributeDefinitions' => $attributeDefinitions,
            'dataTypes' => $dataTypes,
            'categories' => $categories,
        ]);
    }
}