<?php

namespace App\Livewire\DataExchange\Import;

use App\Models\Marketplace;
use App\Models\MarketplaceBarcode;
use App\Models\MarketplaceVariant;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductVariant;
use App\Models\VariantAttribute;
use App\Services\AIAssistantService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class BulkOperations extends Component
{
    use WithPagination;

    public $activeTab = 'overview';

    public $selectedVariants = [];

    public $selectAll = false;

    // Search functionality
    public $search = '';

    public $searchFilter = 'all'; // 'all', 'parent_sku', 'variant_sku', 'barcode'

    // Expandable view
    public $expandedProducts = [];

    public $selectedProducts = []; // For selecting entire products (all variants)

    // Template Generation
    public $titleTemplate = '';

    public $descriptionTemplate = '';

    public $selectedMarketplaces = [];

    public $previewVariant = null;

    public $showTitlePreview = false;

    // Bulk Attribute Operations
    public $bulkAttributeKey = '';

    public $bulkAttributeValue = '';

    public $bulkAttributeType = 'product'; // 'product' or 'variant'

    public $bulkAttributeDataType = 'string';

    public $bulkAttributeCategory = 'general';

    // Data Quality
    public $qualityResults = [];

    public $qualityScanning = false;

    // AI Integration
    public $aiPrompt = '';

    public $aiResponse = '';

    public $aiProcessing = false;

    // Existing attributes management
    public $existingAttributes = [];

    public $selectedExistingAttribute = '';

    public $updateAttributeValue = '';

    // Smart recommendations
    public $recommendations = [];

    public $recommendationsLoaded = false;

    public function mount()
    {
        $this->titleTemplate = '[Brand] [ProductName] - [Color] [Size] | Premium Quality [Material]';
        $this->descriptionTemplate = 'High-quality [Material] [ProductName] in [Color]. Perfect for [RoomType]. [Features]';
        $this->selectedMarketplaces = Marketplace::active()->pluck('id')->toArray();
    }

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function toggleSelectAll()
    {
        if ($this->selectAll) {
            $this->selectedVariants = ProductVariant::pluck('id')->toArray();
        } else {
            $this->selectedVariants = [];
        }
    }

    private function updateSelectAll()
    {
        $totalVariants = ProductVariant::count();
        $this->selectAll = count($this->selectedVariants) === $totalVariants;
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingSearchFilter()
    {
        $this->resetPage();
    }

    public function toggleProductExpansion($productId)
    {
        if (in_array($productId, $this->expandedProducts)) {
            $this->expandedProducts = array_diff($this->expandedProducts, [$productId]);
        } else {
            $this->expandedProducts[] = $productId;
        }
    }

    public function toggleProduct($productId)
    {
        $product = Product::with('variants')->find($productId);

        if (in_array($productId, $this->selectedProducts)) {
            // Unselect product - remove from products and remove all its variants
            $this->selectedProducts = array_diff($this->selectedProducts, [$productId]);
            $variantIds = $product->variants->pluck('id')->toArray();
            $this->selectedVariants = array_diff($this->selectedVariants, $variantIds);
        } else {
            // Select product - add to products and add all its variants
            $this->selectedProducts[] = $productId;
            $variantIds = $product->variants->pluck('id')->toArray();
            $this->selectedVariants = array_unique(array_merge($this->selectedVariants, $variantIds));
        }

        $this->updateSelectAll();
    }

    public function toggleVariant($variantId)
    {
        $variant = ProductVariant::with('product.variants')->find($variantId);

        if (in_array($variantId, $this->selectedVariants)) {
            $this->selectedVariants = array_diff($this->selectedVariants, [$variantId]);
        } else {
            $this->selectedVariants[] = $variantId;
        }

        // Check if all variants of this product are now selected
        $productVariantIds = $variant->product->variants->pluck('id')->toArray();
        $selectedProductVariantIds = array_intersect($this->selectedVariants, $productVariantIds);

        if (count($selectedProductVariantIds) === count($productVariantIds)) {
            // All variants selected, add product to selectedProducts
            if (! in_array($variant->product_id, $this->selectedProducts)) {
                $this->selectedProducts[] = $variant->product_id;
            }
        } else {
            // Not all variants selected, remove product from selectedProducts
            $this->selectedProducts = array_diff($this->selectedProducts, [$variant->product_id]);
        }

        $this->updateSelectAll();
    }

    private function resetSelection()
    {
        $this->selectedVariants = [];
        $this->selectedProducts = [];
        $this->expandedProducts = [];
        $this->selectAll = false;
    }

    // Template-Based Title Generation
    public function generateTitles()
    {
        if (empty($this->selectedVariants)) {
            session()->flash('error', 'Please select variants to generate titles for.');

            return;
        }

        if (empty($this->selectedMarketplaces)) {
            session()->flash('error', 'Please select at least one marketplace.');

            return;
        }

        DB::beginTransaction();

        try {
            $generated = 0;
            $variants = ProductVariant::with(['product', 'attributes', 'product.attributes'])
                ->whereIn('id', $this->selectedVariants)
                ->get();

            foreach ($variants as $variant) {
                foreach ($this->selectedMarketplaces as $marketplaceId) {
                    $marketplace = Marketplace::find($marketplaceId);
                    if (! $marketplace) {
                        continue;
                    }

                    $title = $this->processTemplate($this->titleTemplate, $variant, $marketplace);
                    $description = $this->processTemplate($this->descriptionTemplate, $variant, $marketplace);

                    MarketplaceVariant::updateOrCreate(
                        [
                            'variant_id' => $variant->id,
                            'marketplace_id' => $marketplace->id,
                        ],
                        [
                            'title' => $title,
                            'description' => $description,
                            'status' => 'active',
                            'marketplace_data' => json_encode([
                                'generated_by' => 'bulk_template',
                                'template_used' => $this->titleTemplate,
                                'generated_at' => now()->toISOString(),
                            ]),
                        ]
                    );

                    $generated++;
                }
            }

            DB::commit();
            session()->flash('message', "Generated {$generated} marketplace titles successfully!");
            $this->resetSelection();

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Bulk title generation failed', ['error' => $e->getMessage()]);
            session()->flash('error', 'Failed to generate titles: '.$e->getMessage());
        }
    }

    public function previewTitle($variantId)
    {
        $this->previewVariant = ProductVariant::with(['product', 'attributes', 'product.attributes'])
            ->find($variantId);
        $this->showTitlePreview = true;
    }

    public function getPreviewTitles()
    {
        if (! $this->previewVariant) {
            return [];
        }

        $previews = [];
        foreach ($this->selectedMarketplaces as $marketplaceId) {
            $marketplace = Marketplace::find($marketplaceId);
            if ($marketplace) {
                $previews[] = [
                    'marketplace' => $marketplace->name,
                    'title' => $this->processTemplate($this->titleTemplate, $this->previewVariant, $marketplace),
                    'description' => $this->processTemplate($this->descriptionTemplate, $this->previewVariant, $marketplace),
                ];
            }
        }

        return $previews;
    }

    private function processTemplate($template, $variant, $marketplace)
    {
        $replacements = [
            '[Brand]' => 'Premium',
            '[ProductName]' => $variant->product->name,
            '[Color]' => $variant->color,
            '[Size]' => $variant->size,
            '[SKU]' => $variant->sku,
            '[Marketplace]' => $marketplace->name,
            '[Platform]' => ucfirst($marketplace->platform),
        ];

        // Add product attributes
        foreach ($variant->product->attributes as $attr) {
            $key = '['.ucfirst(str_replace('_', '', $attr->attribute_key)).']';
            $replacements[$key] = $attr->attribute_value;
        }

        // Add variant attributes
        foreach ($variant->attributes as $attr) {
            $key = '['.ucfirst(str_replace('_', '', $attr->attribute_key)).']';
            $replacements[$key] = $attr->attribute_value;
        }

        // Add common defaults
        $defaults = [
            '[Material]' => 'Premium Material',
            '[RoomType]' => 'any room',
            '[Features]' => 'Easy installation and premium quality.',
        ];

        $replacements = array_merge($defaults, $replacements);

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    // Bulk Attribute Operations
    public function applyBulkAttribute()
    {
        if (empty($this->selectedVariants)) {
            session()->flash('error', 'Please select variants to apply attributes to.');

            return;
        }

        if (empty($this->bulkAttributeKey) || empty($this->bulkAttributeValue)) {
            session()->flash('error', 'Please provide both attribute key and value.');

            return;
        }

        DB::beginTransaction();

        try {
            $applied = 0;
            $variants = ProductVariant::with('product')->whereIn('id', $this->selectedVariants)->get();

            foreach ($variants as $variant) {
                if ($this->bulkAttributeType === 'product') {
                    ProductAttribute::updateOrCreate(
                        [
                            'product_id' => $variant->product_id,
                            'attribute_key' => $this->bulkAttributeKey,
                        ],
                        [
                            'attribute_value' => $this->bulkAttributeValue,
                            'data_type' => $this->bulkAttributeDataType,
                            'category' => $this->bulkAttributeCategory,
                        ]
                    );
                } else {
                    VariantAttribute::updateOrCreate(
                        [
                            'variant_id' => $variant->id,
                            'attribute_key' => $this->bulkAttributeKey,
                        ],
                        [
                            'attribute_value' => $this->bulkAttributeValue,
                            'data_type' => $this->bulkAttributeDataType,
                            'category' => $this->bulkAttributeCategory,
                        ]
                    );
                }
                $applied++;
            }

            DB::commit();
            session()->flash('message', "Applied attribute to {$applied} ".($this->bulkAttributeType === 'product' ? 'products' : 'variants').' successfully!');
            $this->resetBulkAttributeForm();

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Bulk attribute application failed', ['error' => $e->getMessage()]);
            session()->flash('error', 'Failed to apply attributes: '.$e->getMessage());
        }
    }

    private function resetBulkAttributeForm()
    {
        $this->bulkAttributeKey = '';
        $this->bulkAttributeValue = '';
        $this->bulkAttributeType = 'product';
        $this->bulkAttributeDataType = 'string';
        $this->bulkAttributeCategory = 'general';
        $this->selectedExistingAttribute = '';
        $this->updateAttributeValue = '';
    }

    // Get existing attributes for selected variants
    public function getExistingAttributes()
    {
        if (empty($this->selectedVariants)) {
            $this->existingAttributes = [];

            return [];
        }

        $variants = ProductVariant::with(['product.attributes', 'attributes'])
            ->whereIn('id', $this->selectedVariants)
            ->get();

        $productAttributes = [];
        $variantAttributes = [];

        foreach ($variants as $variant) {
            // Collect product attributes
            foreach ($variant->product->attributes as $attr) {
                $key = $attr->attribute_key;
                if (! isset($productAttributes[$key])) {
                    $productAttributes[$key] = [
                        'key' => $key,
                        'values' => [],
                        'data_type' => $attr->data_type,
                        'category' => $attr->category,
                        'type' => 'product',
                        'count' => 0,
                    ];
                }
                $productAttributes[$key]['values'][] = $attr->attribute_value;
                $productAttributes[$key]['count']++;
            }

            // Collect variant attributes
            foreach ($variant->attributes as $attr) {
                $key = $attr->attribute_key;
                if (! isset($variantAttributes[$key])) {
                    $variantAttributes[$key] = [
                        'key' => $key,
                        'values' => [],
                        'data_type' => $attr->data_type,
                        'category' => $attr->category,
                        'type' => 'variant',
                        'count' => 0,
                    ];
                }
                $variantAttributes[$key]['values'][] = $attr->attribute_value;
                $variantAttributes[$key]['count']++;
            }
        }

        // Process unique values and add summary info
        foreach ($productAttributes as $key => $data) {
            $uniqueValues = array_unique($data['values']);
            $productAttributes[$key]['unique_values'] = $uniqueValues;
            $productAttributes[$key]['is_consistent'] = count($uniqueValues) === 1;
            $productAttributes[$key]['summary'] = count($uniqueValues) === 1
                ? $uniqueValues[0]
                : count($uniqueValues).' different values';
        }

        foreach ($variantAttributes as $key => $data) {
            $uniqueValues = array_unique($data['values']);
            $variantAttributes[$key]['unique_values'] = $uniqueValues;
            $variantAttributes[$key]['is_consistent'] = count($uniqueValues) === 1;
            $variantAttributes[$key]['summary'] = count($uniqueValues) === 1
                ? $uniqueValues[0]
                : count($uniqueValues).' different values';
        }

        $this->existingAttributes = [
            'product' => $productAttributes,
            'variant' => $variantAttributes,
        ];

        return $this->existingAttributes;
    }

    // Update existing attribute
    public function updateExistingAttribute()
    {
        if (empty($this->selectedVariants) || empty($this->selectedExistingAttribute) || empty($this->updateAttributeValue)) {
            session()->flash('error', 'Please select variants, an attribute, and provide a new value.');

            return;
        }

        // Parse the selected attribute (format: "type:key")
        [$type, $key] = explode(':', $this->selectedExistingAttribute, 2);

        DB::beginTransaction();

        try {
            $updated = 0;
            $variants = ProductVariant::with('product')->whereIn('id', $this->selectedVariants)->get();

            foreach ($variants as $variant) {
                if ($type === 'product') {
                    // Find existing product attribute to get its data type and category
                    $existingAttr = ProductAttribute::where('product_id', $variant->product_id)
                        ->where('attribute_key', $key)
                        ->first();

                    ProductAttribute::updateOrCreate(
                        [
                            'product_id' => $variant->product_id,
                            'attribute_key' => $key,
                        ],
                        [
                            'attribute_value' => $this->updateAttributeValue,
                            'data_type' => $existingAttr?->data_type ?? 'string',
                            'category' => $existingAttr?->category ?? 'general',
                        ]
                    );
                } else {
                    // Find existing variant attribute to get its data type and category
                    $existingAttr = VariantAttribute::where('variant_id', $variant->id)
                        ->where('attribute_key', $key)
                        ->first();

                    VariantAttribute::updateOrCreate(
                        [
                            'variant_id' => $variant->id,
                            'attribute_key' => $key,
                        ],
                        [
                            'attribute_value' => $this->updateAttributeValue,
                            'data_type' => $existingAttr?->data_type ?? 'string',
                            'category' => $existingAttr?->category ?? 'general',
                        ]
                    );
                }
                $updated++;
            }

            DB::commit();
            session()->flash('message', "Updated '{$key}' attribute for {$updated} ".($type === 'product' ? 'products' : 'variants').' successfully!');

            // Reset form and refresh existing attributes
            $this->selectedExistingAttribute = '';
            $this->updateAttributeValue = '';
            $this->getExistingAttributes();

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Bulk attribute update failed', ['error' => $e->getMessage()]);
            session()->flash('error', 'Failed to update attribute: '.$e->getMessage());
        }
    }

    // Data Quality Scanner
    public function scanDataQuality()
    {
        $this->qualityScanning = true;
        $this->qualityResults = [];

        try {
            // Check missing marketplace variants
            $variantsWithoutMarketplace = ProductVariant::whereDoesntHave('marketplaceVariants')->count();

            // Check missing attributes
            $productsWithoutAttributes = Product::whereDoesntHave('attributes')->count();
            $variantsWithoutAttributes = ProductVariant::whereDoesntHave('attributes')->count();

            // Check missing marketplace identifiers
            $variantsWithoutASIN = ProductVariant::whereDoesntHave('marketplaceBarcodes', function ($query) {
                $query->where('identifier_type', 'asin');
            })->count();

            // Check duplicate ASINs
            $duplicateASINs = MarketplaceBarcode::where('identifier_type', 'asin')
                ->select('identifier_value')
                ->groupBy('identifier_value')
                ->havingRaw('COUNT(*) > 1')
                ->count();

            // Check incomplete titles
            $incompleteTitles = MarketplaceVariant::where('title', 'LIKE', '%[%]%')->count();

            $this->qualityResults = [
                'missing_marketplace_variants' => $variantsWithoutMarketplace,
                'products_without_attributes' => $productsWithoutAttributes,
                'variants_without_attributes' => $variantsWithoutAttributes,
                'variants_without_asin' => $variantsWithoutASIN,
                'duplicate_asins' => $duplicateASINs,
                'incomplete_titles' => $incompleteTitles,
                'total_variants' => ProductVariant::count(),
                'total_products' => Product::count(),
            ];

        } catch (\Exception $e) {
            Log::error('Data quality scan failed', ['error' => $e->getMessage()]);
            session()->flash('error', 'Quality scan failed: '.$e->getMessage());
        }

        $this->qualityScanning = false;
    }

    // AI Integration
    public function processAIRequest()
    {
        if (empty($this->aiPrompt)) {
            session()->flash('error', 'Please enter a prompt for the AI assistant.');

            return;
        }

        $this->aiProcessing = true;

        try {
            $aiService = new AIAssistantService;
            $this->aiResponse = $aiService->processRequest($this->aiPrompt, $this->selectedVariants);
        } catch (\Exception $e) {
            Log::error('AI Assistant request failed', ['error' => $e->getMessage()]);
            $this->aiResponse = 'I apologize, but I encountered an error processing your request. Please try again.';
        }

        $this->aiProcessing = false;
    }

    public function generateAITitles()
    {
        if (empty($this->selectedVariants)) {
            session()->flash('error', 'Please select variants to generate AI titles for.');

            return;
        }

        if (empty($this->selectedMarketplaces)) {
            session()->flash('error', 'Please select at least one marketplace.');

            return;
        }

        $this->aiProcessing = true;

        try {
            $aiService = new AIAssistantService;
            $this->aiResponse = "🤖 **AI Title Generation**\n\n";
            $this->aiResponse .= 'Generating optimized titles for '.count($this->selectedVariants).' variants across '.count($this->selectedMarketplaces)." marketplaces...\n\n";

            $titles = $aiService->generateMarketplaceTitles($this->selectedVariants, $this->selectedMarketplaces);

            if (! empty($titles)) {
                $this->aiResponse .= "**Generated Titles:**\n\n";
                foreach ($titles as $sku => $marketplaceTitles) {
                    $this->aiResponse .= "**{$sku}:**\n";
                    foreach ($marketplaceTitles as $marketplace => $title) {
                        $this->aiResponse .= "- {$marketplace}: {$title}\n";
                    }
                    $this->aiResponse .= "\n";
                }
            } else {
                $this->aiResponse .= "I've analyzed your variants and can help generate optimized titles. ";
                $this->aiResponse .= 'The AI system will create marketplace-specific titles that include relevant keywords, sizing information, and compelling features for your window shades products.';
            }

        } catch (\Exception $e) {
            Log::error('AI title generation failed', ['error' => $e->getMessage()]);
            $this->aiResponse = 'I encountered an error generating titles. Please try again or use the template system instead.';
        }

        $this->aiProcessing = false;
    }

    public function analyzeDataQuality()
    {
        $this->aiProcessing = true;

        try {
            $aiService = new AIAssistantService;
            $this->aiResponse = $aiService->analyzeDataQuality();
        } catch (\Exception $e) {
            Log::error('AI data quality analysis failed', ['error' => $e->getMessage()]);
            $this->aiResponse = 'I encountered an error analyzing your data quality. Please try the manual quality scan instead.';
        }

        $this->aiProcessing = false;
    }

    // Smart Recommendations Methods
    public function loadSmartRecommendations()
    {
        try {
            $recommendationsService = app(\App\Services\SmartRecommendations\SmartRecommendationsService::class);
            $recommendationCollection = $recommendationsService->getRecommendations($this->selectedVariants);

            $this->recommendations = $recommendationCollection->toArray();
            $this->recommendationsLoaded = true;

        } catch (\Exception $e) {
            Log::error('Smart recommendations loading failed', ['error' => $e->getMessage()]);
            session()->flash('error', 'Failed to load smart recommendations: '.$e->getMessage());
        }
    }

    public function executeRecommendation($recommendationId)
    {
        try {
            $recommendationsService = app(\App\Services\SmartRecommendations\SmartRecommendationsService::class);
            $success = $recommendationsService->executeRecommendation($recommendationId, $this->selectedVariants);

            if ($success) {
                session()->flash('message', 'Recommendation executed successfully!');
                // Reload recommendations to reflect changes
                $this->loadSmartRecommendations();
            } else {
                session()->flash('error', 'Failed to execute recommendation.');
            }

        } catch (\Exception $e) {
            Log::error('Recommendation execution failed', ['error' => $e->getMessage()]);
            session()->flash('error', 'Error executing recommendation: '.$e->getMessage());
        }
    }

    public function getDataOverview()
    {
        return [
            'total_products' => Product::count(),
            'total_variants' => ProductVariant::count(),
            'marketplace_variants' => MarketplaceVariant::count(),
            'marketplace_identifiers' => MarketplaceBarcode::count(),
            'product_attributes' => ProductAttribute::count(),
            'variant_attributes' => VariantAttribute::count(),
            'active_marketplaces' => Marketplace::active()->count(),
        ];
    }

    public function render()
    {
        $isSearching = ! empty($this->search);

        if ($isSearching) {
            // Search mode: show matching variants with their products
            $variants = ProductVariant::with(['product', 'marketplaceVariants', 'marketplaceBarcodes', 'attributes', 'barcodes'])
                ->when($this->search, function ($query) {
                    $search = $this->search;

                    $query->where(function ($q) use ($search) {
                        switch ($this->searchFilter) {
                            case 'parent_sku':
                                $q->whereHas('product', function ($productQuery) use ($search) {
                                    $productQuery->where('parent_sku', 'like', '%'.$search.'%');
                                });
                                break;

                            case 'variant_sku':
                                $q->where('sku', 'like', '%'.$search.'%');
                                break;

                            case 'barcode':
                                $q->whereHas('barcodes', function ($barcodeQuery) use ($search) {
                                    $barcodeQuery->where('barcode', 'like', '%'.$search.'%');
                                });
                                break;

                            default: // 'all'
                                $q->where('sku', 'like', '%'.$search.'%')
                                    ->orWhereHas('product', function ($productQuery) use ($search) {
                                        $productQuery->where('parent_sku', 'like', '%'.$search.'%')
                                            ->orWhere('name', 'like', '%'.$search.'%');
                                    })
                                    ->orWhereHas('barcodes', function ($barcodeQuery) use ($search) {
                                        $barcodeQuery->where('barcode', 'like', '%'.$search.'%');
                                    });
                                break;
                        }
                    });
                })
                ->latest()
                ->paginate(20);

            $products = collect(); // Empty in search mode, we show variants

        } else {
            // Default mode: show products with variants only for expanded ones
            $products = Product::withCount('variants')
                ->with(['variants' => function ($query) {
                    // Only load variants for expanded products or if we need them for selection state
                    $query->when(! empty($this->expandedProducts), function ($q) {
                        $q->whereIn('product_id', $this->expandedProducts);
                    });
                    $query->with(['marketplaceVariants', 'marketplaceBarcodes', 'attributes', 'barcodes']);
                }])
                ->latest()
                ->paginate(20);

            // Load expanded variants separately for better control
            $variants = collect();
            if (! empty($this->expandedProducts)) {
                $expandedVariants = ProductVariant::with(['product', 'marketplaceVariants', 'marketplaceBarcodes', 'attributes', 'barcodes'])
                    ->whereIn('product_id', $this->expandedProducts)
                    ->get()
                    ->groupBy('product_id');

                // Attach variants to their products
                $products->each(function ($product) use ($expandedVariants) {
                    if (isset($expandedVariants[$product->id])) {
                        $product->expandedVariants = $expandedVariants[$product->id];
                    } else {
                        $product->expandedVariants = collect();
                    }
                });
            }
        }

        $marketplaces = Marketplace::active()->orderBy('name')->get();
        $dataOverview = $this->getDataOverview();

        return view('livewire.data-exchange.import.bulk-operations', [
            'products' => $products,
            'variants' => $variants ?? collect(),
            'marketplaces' => $marketplaces,
            'dataOverview' => $dataOverview,
            'isSearching' => $isSearching,
        ]);
    }
}
