<?php

namespace App\Livewire\Operations;

use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductVariant;
use App\Models\VariantAttribute;
use App\Traits\HasRouteTabs;
use App\Traits\SharesBulkOperationsState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class BulkOperationsAttributes extends Component
{
    use HasRouteTabs, SharesBulkOperationsState;

    // URL-tracked state
    #[Url(except: '', as: 'attr_key')]
    public $bulkAttributeKey = '';

    #[Url(except: '', as: 'attr_value')]
    public $bulkAttributeValue = '';

    #[Url(except: 'product', as: 'attr_type')]
    public $bulkAttributeType = 'product';

    #[Url(except: 'string', as: 'data_type')]
    public $bulkAttributeDataType = 'string';

    #[Url(except: 'general', as: 'category')]
    public $bulkAttributeCategory = 'general';

    #[Url(except: '', as: 'existing_attr')]
    public $selectedExistingAttribute = '';

    #[Url(except: '', as: 'update_value')]
    public $updateAttributeValue = '';

    // Local state
    public $existingAttributes = [];

    protected $baseRoute = 'operations.bulk';
    
    protected $tabConfig = [
        'tabs' => [
            [
                'key' => 'overview',
                'label' => 'Overview',
                'icon' => 'chart-bar',
            ],
            [
                'key' => 'templates',
                'label' => 'Title Templates',
                'icon' => 'layout-grid',
            ],
            [
                'key' => 'attributes',
                'label' => 'Bulk Attributes',
                'icon' => 'tag',
            ],
            [
                'key' => 'quality',
                'label' => 'Data Quality',
                'icon' => 'shield-check',
            ],
            [
                'key' => 'recommendations',
                'label' => 'Smart Recommendations',
                'icon' => 'lightbulb',
            ],
            [
                'key' => 'ai',
                'label' => 'AI Assistant',
                'icon' => 'zap',
            ],
        ],
    ];

    protected $queryString = [
        'bulkAttributeKey' => ['except' => '', 'as' => 'attr_key'],
        'bulkAttributeValue' => ['except' => '', 'as' => 'attr_value'],
        'bulkAttributeType' => ['except' => 'product', 'as' => 'attr_type'],
        'bulkAttributeDataType' => ['except' => 'string', 'as' => 'data_type'],
        'bulkAttributeCategory' => ['except' => 'general', 'as' => 'category'],
        'selectedExistingAttribute' => ['except' => '', 'as' => 'existing_attr'],
        'updateAttributeValue' => ['except' => '', 'as' => 'update_value'],
    ];

    public function mount()
    {
        $this->getExistingAttributes();
    }


    public function getSelectedVariantsCountProperty()
    {
        return count($this->getSelectedVariants());
    }

    public function getSelectedVariantsProperty()
    {
        return $this->getSelectedVariants();
    }

    public function applyBulkAttribute()
    {
        $selectedVariants = $this->getSelectedVariants();

        if (empty($selectedVariants)) {
            session()->flash('error', 'Please select variants from the Overview tab first.');
            return;
        }
        
        if (empty($this->bulkAttributeKey) || empty($this->bulkAttributeValue)) {
            session()->flash('error', 'Please provide both attribute key and value.');
            return;
        }
        
        DB::beginTransaction();
        
        try {
            $applied = 0;
            $variants = ProductVariant::with('product')->whereIn('id', $selectedVariants)->get();
            
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
            $message = "Applied '{$this->bulkAttributeKey}' attribute to {$applied} " . ($this->bulkAttributeType === 'product' ? 'products' : 'variants') . " successfully!";
            session()->flash('message', $message);
            $this->resetBulkAttributeForm();
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Bulk attribute application failed', ['error' => $e->getMessage()]);
            session()->flash('error', 'Failed to apply attributes: ' . $e->getMessage());
        }
    }

    public function updateExistingAttribute()
    {
        $selectedVariants = $this->getSelectedVariants();

        if (empty($selectedVariants) || empty($this->selectedExistingAttribute) || empty($this->updateAttributeValue)) {
            session()->flash('error', 'Please select variants, an attribute, and provide a new value.');
            return;
        }
        
        // Parse the selected attribute (format: "type:key")
        [$type, $key] = explode(':', $this->selectedExistingAttribute, 2);
        
        DB::beginTransaction();
        
        try {
            $updated = 0;
            $variants = ProductVariant::with('product')->whereIn('id', $selectedVariants)->get();
            
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
            session()->flash('message', "Updated '{$key}' attribute for {$updated} " . ($type === 'product' ? 'products' : 'variants') . " successfully!");
            
            // Reset form and refresh existing attributes
            $this->selectedExistingAttribute = '';
            $this->updateAttributeValue = '';
            $this->getExistingAttributes();
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Bulk attribute update failed', ['error' => $e->getMessage()]);
            session()->flash('error', 'Failed to update attribute: ' . $e->getMessage());
        }
    }

    public function getExistingAttributes()
    {
        $selectedVariants = $this->getSelectedVariants();

        if (empty($selectedVariants)) {
            $this->existingAttributes = [];
            return [];
        }
        
        $variants = ProductVariant::with(['product.attributes', 'attributes'])
            ->whereIn('id', $selectedVariants)
            ->get();
        
        $productAttributes = [];
        $variantAttributes = [];
        
        foreach ($variants as $variant) {
            // Collect product attributes
            foreach ($variant->product->attributes as $attr) {
                $key = $attr->attribute_key;
                if (!isset($productAttributes[$key])) {
                    $productAttributes[$key] = [
                        'key' => $key,
                        'values' => [],
                        'data_type' => $attr->data_type,
                        'category' => $attr->category,
                        'type' => 'product',
                        'count' => 0
                    ];
                }
                $productAttributes[$key]['values'][] = $attr->attribute_value;
                $productAttributes[$key]['count']++;
            }
            
            // Collect variant attributes
            foreach ($variant->attributes as $attr) {
                $key = $attr->attribute_key;
                if (!isset($variantAttributes[$key])) {
                    $variantAttributes[$key] = [
                        'key' => $key,
                        'values' => [],
                        'data_type' => $attr->data_type,
                        'category' => $attr->category,
                        'type' => 'variant',
                        'count' => 0
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
                : count($uniqueValues) . ' different values';
        }
        
        foreach ($variantAttributes as $key => $data) {
            $uniqueValues = array_unique($data['values']);
            $variantAttributes[$key]['unique_values'] = $uniqueValues;
            $variantAttributes[$key]['is_consistent'] = count($uniqueValues) === 1;
            $variantAttributes[$key]['summary'] = count($uniqueValues) === 1 
                ? $uniqueValues[0] 
                : count($uniqueValues) . ' different values';
        }
        
        $this->existingAttributes = [
            'product' => $productAttributes,
            'variant' => $variantAttributes
        ];
        
        return $this->existingAttributes;
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
        $this->getExistingAttributes();
    }

    public function render()
    {
        return view('livewire.operations.bulk-operations-attributes', [
            'tabs' => $this->getTabsForNavigation(),
            'selectedVariants' => $this->selectedVariants,
            'selectedVariantsCount' => $this->selectedVariantsCount,
            'existingAttributes' => $this->existingAttributes,
        ]);
    }
}