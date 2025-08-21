<?php

namespace App\Livewire\ChannelMapping;

use App\Models\ChannelFieldDefinition;
use App\Models\ChannelFieldMapping;
use App\Models\Product;
use App\Models\SyncAccount;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * 🎛️ PRODUCT FIELD MAPPER
 *
 * Embedded component for product/variant edit pages that allows users to:
 * - View field requirements for selected marketplace channels
 * - Configure field mappings (global, product-specific, variant-specific)
 * - Test mappings with real product data
 * - Validate field requirements and mappings
 */
class ProductFieldMapper extends Component
{
    public Product $product;

    public string $selectedSyncAccount = '';

    public string $selectedCategory = '';

    public string $activeTab = 'mappings';

    public bool $showAddMappingModal = false;

    public bool $showTestModal = false;

    // Add/Edit mapping form
    public string $mappingFieldCode = '';

    public string $mappingType = 'pim_field';

    public string $sourceField = '';

    public string $staticValue = '';

    public string $mappingExpression = '';

    public string $mappingLevel = 'global';

    public string $variantScope = '';

    public array $transformationRules = [];

    public string $notes = '';

    public ?int $editingMappingId = null;

    // Test mapping
    public array $testContext = [];

    public array $testResults = [];

    protected $rules = [
        'mappingFieldCode' => 'required|string',
        'mappingType' => 'required|in:pim_field,static_value,expression,custom',
        'sourceField' => 'required_if:mappingType,pim_field|string|nullable',
        'staticValue' => 'required_if:mappingType,static_value|string|nullable',
        'mappingExpression' => 'required_if:mappingType,expression|string|nullable',
        'mappingLevel' => 'required|in:global,product,variant',
        'variantScope' => 'required_if:mappingLevel,variant|string|nullable',
    ];

    public function mount(Product $product): void
    {
        $this->product = $product;
        $this->loadTestContext();
    }

    /**
     * 📊 COMPUTED: Available Sync Accounts
     */
    #[Computed]
    public function syncAccounts()
    {
        return SyncAccount::where('is_active', true)
            ->orderBy('marketplace_type')
            ->orderBy('account_name')
            ->get();
    }

    /**
     * 📊 COMPUTED: Field Requirements for Selected Account
     */
    #[Computed]
    public function fieldRequirements()
    {
        if (! $this->selectedSyncAccount) {
            return ['required' => collect(), 'optional' => collect()];
        }

        $syncAccount = SyncAccount::find($this->selectedSyncAccount);
        if (! $syncAccount) {
            return ['required' => collect(), 'optional' => collect()];
        }

        return ChannelFieldDefinition::getFieldRequirements(
            $syncAccount->marketplace_type,
            $syncAccount->account_name,
            $this->selectedCategory ?: null
        );
    }

    /**
     * 📊 COMPUTED: Current Mappings for Selected Account
     */
    #[Computed]
    public function currentMappings()
    {
        if (! $this->selectedSyncAccount) {
            return collect();
        }

        return ChannelFieldMapping::getEffectiveMappings(
            syncAccountId: (int) $this->selectedSyncAccount,
            productId: $this->product->id,
            category: $this->selectedCategory ?: null
        );
    }

    /**
     * 📊 COMPUTED: Available PIM Fields
     */
    #[Computed]
    public function availablePimFields(): array
    {
        return [
            // Product fields
            'product.name' => 'Product Name',
            'product.description' => 'Product Description',
            'product.brand' => 'Product Brand',
            'product.category.name' => 'Category Name',
            'product.tags' => 'Product Tags',
            'product.features' => 'Product Features',

            // Variant fields
            'variant.sku' => 'Variant SKU',
            'variant.color' => 'Variant Color',
            'variant.size' => 'Variant Size',
            'variant.material' => 'Variant Material',
            'variant.weight' => 'Variant Weight',
            'variant.dimensions' => 'Variant Dimensions',

            // Pricing fields
            'pricing.retail_price' => 'Retail Price',
            'pricing.cost_price' => 'Cost Price',
            'pricing.wholesale_price' => 'Wholesale Price',

            // Barcode fields
            'barcode.code' => 'Barcode',
            'barcode.type' => 'Barcode Type',
        ];
    }

    /**
     * 🎯 Set Selected Sync Account
     */
    public function setSyncAccount(string $syncAccountId): void
    {
        $this->selectedSyncAccount = $syncAccountId;
        $this->selectedCategory = '';
        $this->resetAddMappingForm();
    }

    /**
     * 🎯 Set Selected Category
     */
    public function setCategory(string $category): void
    {
        $this->selectedCategory = $category;
    }

    /**
     * 🎯 Set Active Tab
     */
    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    /**
     * ➕ Show Add Mapping Modal
     */
    public function showAddMapping(?string $fieldCode = null): void
    {
        $this->resetAddMappingForm();
        if ($fieldCode) {
            $this->mappingFieldCode = $fieldCode;
        }
        $this->showAddMappingModal = true;
    }

    /**
     * ✏️ Edit Mapping
     */
    public function editMapping(int $mappingId): void
    {
        $mapping = ChannelFieldMapping::find($mappingId);
        if (! $mapping) {
            return;
        }

        $this->editingMappingId = $mappingId;
        $this->mappingFieldCode = $mapping->channel_field_code;
        $this->mappingType = $mapping->mapping_type;
        $this->sourceField = $mapping->source_field ?? '';
        $this->staticValue = $mapping->static_value ?? '';
        $this->mappingExpression = $mapping->mapping_expression ?? '';
        $this->mappingLevel = $mapping->mapping_level;
        $this->variantScope = $mapping->variant_scope ?? '';
        $this->transformationRules = $mapping->transformation_rules ?? [];
        $this->notes = $mapping->notes ?? '';

        $this->showAddMappingModal = true;
    }

    /**
     * 💾 Save Mapping
     */
    public function saveMapping(): void
    {
        $this->validate();

        if (! $this->selectedSyncAccount) {
            $this->addError('selectedSyncAccount', 'Please select a sync account first');

            return;
        }

        $data = [
            'sync_account_id' => $this->selectedSyncAccount,
            'channel_field_code' => $this->mappingFieldCode,
            'category' => $this->selectedCategory ?: null,
            'mapping_type' => $this->mappingType,
            'source_field' => $this->sourceField ?: null,
            'static_value' => $this->staticValue ?: null,
            'mapping_expression' => $this->mappingExpression ?: null,
            'transformation_rules' => $this->transformationRules ?: null,
            'mapping_level' => $this->mappingLevel,
            'product_id' => $this->mappingLevel === 'product' ? $this->product->id : null,
            'variant_scope' => $this->mappingLevel === 'variant' ? $this->variantScope : null,
            'notes' => $this->notes ?: null,
            'is_active' => true,
        ];

        if ($this->editingMappingId) {
            $mapping = ChannelFieldMapping::find($this->editingMappingId);
            $mapping->update($data);
            $message = 'Mapping updated successfully!';
        } else {
            ChannelFieldMapping::create($data);
            $message = 'Mapping created successfully!';
        }

        $this->dispatch('mapping-saved', ['message' => $message]);
        $this->closeAddMappingModal();
    }

    /**
     * 🗑️ Delete Mapping
     */
    public function deleteMapping(int $mappingId): void
    {
        $mapping = ChannelFieldMapping::find($mappingId);
        if ($mapping) {
            $mapping->delete();
            $this->dispatch('mapping-deleted', ['message' => 'Mapping deleted successfully!']);
        }
    }

    /**
     * 🧪 Test Mapping
     */
    public function testMapping(int $mappingId): void
    {
        $mapping = ChannelFieldMapping::find($mappingId);
        if (! $mapping) {
            return;
        }

        $this->testResults = $mapping->testMapping($this->testContext);
        $this->showTestModal = true;
    }

    /**
     * 🧪 Test All Mappings
     */
    public function testAllMappings(): void
    {
        $results = [];

        foreach ($this->currentMappings as $mapping) {
            $results[] = [
                'mapping' => $mapping,
                'result' => $mapping->testMapping($this->testContext),
            ];
        }

        $this->testResults = $results;
        $this->showTestModal = true;
    }

    /**
     * ✅ Validate Mappings
     */
    public function validateMappings(): void
    {
        $validationResults = [];

        foreach ($this->currentMappings as $mapping) {
            $validationResults[] = [
                'mapping' => $mapping,
                'validation' => $mapping->validateMapping(),
            ];
        }

        $this->dispatch('mappings-validated', [
            'results' => $validationResults,
            'message' => 'Validation completed!',
        ]);
    }

    /**
     * 🔄 Reset Add Mapping Form
     */
    protected function resetAddMappingForm(): void
    {
        $this->editingMappingId = null;
        $this->mappingFieldCode = '';
        $this->mappingType = 'pim_field';
        $this->sourceField = '';
        $this->staticValue = '';
        $this->mappingExpression = '';
        $this->mappingLevel = 'global';
        $this->variantScope = '';
        $this->transformationRules = [];
        $this->notes = '';
        $this->resetErrorBag();
    }

    /**
     * 🔄 Close Add Mapping Modal
     */
    public function closeAddMappingModal(): void
    {
        $this->showAddMappingModal = false;
        $this->resetAddMappingForm();
    }

    /**
     * 🔄 Close Test Modal
     */
    public function closeTestModal(): void
    {
        $this->showTestModal = false;
        $this->testResults = [];
    }

    /**
     * 🔄 Load Test Context
     */
    protected function loadTestContext(): void
    {
        $this->testContext = [
            'product' => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'description' => $this->product->description,
                'brand' => $this->product->brand,
                'tags' => $this->product->tags,
                'features' => $this->product->features,
            ],
            'variant' => [
                'sku' => 'SAMPLE-SKU-001',
                'color' => 'Blue',
                'size' => 'Large',
                'material' => 'Cotton',
                'weight' => 250,
                'dimensions' => '30x20x10',
            ],
            'pricing' => [
                'retail_price' => 29.99,
                'cost_price' => 15.00,
                'wholesale_price' => 22.50,
            ],
            'barcode' => [
                'code' => '1234567890123',
                'type' => 'EAN13',
            ],
        ];
    }

    /**
     * 📊 Get Mapping Coverage
     */
    public function getMappingCoverage(): array
    {
        if (! $this->selectedSyncAccount) {
            return ['mapped' => 0, 'required' => 0, 'optional' => 0, 'percentage' => 0];
        }

        $requirements = $this->fieldRequirements;
        $mappings = $this->currentMappings;

        $requiredFields = $requirements['required'];
        $optionalFields = $requirements['optional'];
        $mappedFields = $mappings->pluck('channel_field_code');

        $mappedRequired = $requiredFields->whereIn('field_code', $mappedFields)->count();
        $mappedOptional = $optionalFields->whereIn('field_code', $mappedFields)->count();

        $totalRequired = $requiredFields->count();
        $totalOptional = $optionalFields->count();

        $percentage = $totalRequired > 0 ? round(($mappedRequired / $totalRequired) * 100, 1) : 100;

        return [
            'mapped_required' => $mappedRequired,
            'total_required' => $totalRequired,
            'mapped_optional' => $mappedOptional,
            'total_optional' => $totalOptional,
            'percentage' => $percentage,
        ];
    }

    public function render()
    {
        return view('livewire.channel-mapping.product-field-mapper');
    }
}
