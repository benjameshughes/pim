<?php

namespace App\Livewire\BulkOperations;

use App\Models\Product;
use App\Models\SyncAccount;
use App\Services\Marketplace\MarketplaceAttributeService;
use App\Services\Marketplace\MarketplaceTaxonomyService;
use Exception;
use Livewire\Component;

/**
 * 🏷️ BULK MARKETPLACE ATTRIBUTES OPERATION
 *
 * Handles bulk assignment and management of marketplace attributes across multiple products.
 * Integrates with the bulk operations system for scalable attribute management.
 *
 * Features: bulk assignment, validation, auto-assignment, and readiness analysis.
 */
class BulkMarketplaceAttributesOperation extends Component
{
    // Operation parameters (from bulk operations center)
    public array $selectedProductIds = [];

    public string $targetType = 'products';

    // Form state
    public ?int $selectedMarketplaceId = null;

    public array $marketplaces = [];

    public array $availableAttributes = [];

    public array $selectedProducts = [];

    // Bulk operation configuration
    public string $operationType = 'assign'; // assign, validate, auto_assign, export

    public array $attributesToAssign = []; // [key => value] pairs

    public bool $skipValidation = false;

    public bool $overwriteExisting = false;

    // Results and progress
    public ?array $operationResults = null;

    public bool $isProcessing = false;

    public string $progressMessage = '';

    public int $progressPercentage = 0;

    // Analysis results
    public ?array $readinessAnalysis = null;

    public ?array $validationSummary = null;

    protected MarketplaceAttributeService $attributeService;

    protected MarketplaceTaxonomyService $taxonomyService;

    public function mount(string $targetType = 'products', ?string $selectedItems = null)
    {
        $this->targetType = $targetType;

        // Decrypt selected item IDs from bulk operations center
        if ($selectedItems) {
            try {
                $this->selectedProductIds = decrypt($selectedItems);
            } catch (Exception $e) {
                $this->selectedProductIds = [];
            }
        }

        $this->attributeService = new MarketplaceAttributeService;
        $this->taxonomyService = new MarketplaceTaxonomyService;

        $this->loadMarketplaces();
        $this->loadSelectedProducts();
    }

    /**
     * 🔄 Load available marketplaces
     */
    public function loadMarketplaces(): void
    {
        $this->marketplaces = SyncAccount::where('is_active', true)
            ->select('id', 'name', 'channel')
            ->get()
            ->map(fn ($account) => [
                'id' => $account->id,
                'name' => $account->name,
                'channel' => $account->channel,
            ])
            ->toArray();
    }

    /**
     * 📦 Load selected products with basic info
     */
    public function loadSelectedProducts(): void
    {
        if (empty($this->selectedProductIds)) {
            return;
        }

        $this->selectedProducts = Product::whereIn('id', $this->selectedProductIds)
            ->select('id', 'name', 'parent_sku', 'status')
            ->withCount('variants')
            ->get()
            ->map(fn ($product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->parent_sku,
                'status' => $product->status->label(),
                'variants_count' => $product->variants_count,
            ])
            ->toArray();
    }

    /**
     * 🔄 Marketplace selection changed
     */
    public function updatedSelectedMarketplaceId(): void
    {
        if ($this->selectedMarketplaceId) {
            $this->loadAvailableAttributes();
            $this->attributesToAssign = [];
        } else {
            $this->availableAttributes = [];
            $this->attributesToAssign = [];
        }
    }

    /**
     * 🏷️ Load available attributes for selected marketplace
     */
    public function loadAvailableAttributes(): void
    {
        if (! $this->selectedMarketplaceId) {
            return;
        }

        try {
            $marketplace = SyncAccount::find($this->selectedMarketplaceId);
            if (! $marketplace) {
                return;
            }

            $attributes = $this->taxonomyService->getAttributes($marketplace);

            $this->availableAttributes = $attributes->map(function ($attr) {
                return [
                    'key' => $attr->key,
                    'name' => $attr->name,
                    'description' => $attr->description,
                    'data_type' => $attr->data_type,
                    'is_required' => $attr->is_required,
                    'choices' => $attr->getChoices(),
                ];
            })->toArray();

        } catch (Exception $e) {
            $this->dispatch('error', 'Failed to load attributes: '.$e->getMessage());
        }
    }

    /**
     * ➕ Add attribute to assignment list
     */
    public function addAttribute(string $attributeKey): void
    {
        if (! isset($this->attributesToAssign[$attributeKey])) {
            $this->attributesToAssign[$attributeKey] = '';
        }
    }

    /**
     * ➖ Remove attribute from assignment list
     */
    public function removeAttribute(string $attributeKey): void
    {
        unset($this->attributesToAssign[$attributeKey]);
    }

    /**
     * 🚀 Execute bulk assign operation
     */
    public function executeBulkAssign(): void
    {
        if (! $this->validateOperation()) {
            return;
        }

        $this->isProcessing = true;
        $this->progressMessage = 'Starting bulk assignment...';
        $this->progressPercentage = 0;

        try {
            $marketplace = SyncAccount::find($this->selectedMarketplaceId);
            $products = Product::whereIn('id', $this->selectedProductIds)->get();

            $results = [
                'total_products' => $products->count(),
                'successful_products' => 0,
                'failed_products' => 0,
                'total_attributes_assigned' => 0,
                'errors' => [],
                'product_results' => [],
            ];

            $processed = 0;

            foreach ($products as $product) {
                $this->progressMessage = "Processing {$product->name}...";
                $this->progressPercentage = round(($processed / $products->count()) * 100);

                try {
                    $productResult = $this->attributeService->bulkAssignAttributes(
                        collect([$product]),
                        $marketplace,
                        $this->attributesToAssign,
                        [
                            'skip_validation' => $this->skipValidation,
                            'overwrite_existing' => $this->overwriteExisting,
                            'assigned_via' => 'bulk',
                        ]
                    );

                    $results['successful_products'] += $productResult['success_count'];
                    $results['total_attributes_assigned'] += $productResult['success_count'] * count($this->attributesToAssign);

                    if ($productResult['error_count'] > 0) {
                        $results['failed_products']++;
                        $results['errors'] = array_merge($results['errors'], $productResult['errors']);
                    }

                    $results['product_results'][] = [
                        'product_name' => $product->name,
                        'success' => $productResult['success_count'] > 0,
                        'attributes_assigned' => $productResult['success_count'],
                        'errors' => $productResult['errors'] ?? [],
                    ];

                } catch (Exception $e) {
                    $results['failed_products']++;
                    $results['errors'][] = "Failed to process {$product->name}: ".$e->getMessage();
                }

                $processed++;
            }

            $this->operationResults = $results;
            $this->progressMessage = 'Bulk assignment completed!';
            $this->progressPercentage = 100;

            $this->dispatch('success',
                "Bulk assignment completed! {$results['successful_products']}/{$results['total_products']} products processed successfully."
            );

        } catch (Exception $e) {
            $this->dispatch('error', 'Bulk assignment failed: '.$e->getMessage());
        } finally {
            $this->isProcessing = false;
        }
    }

    /**
     * 🤖 Execute auto-assignment for all selected products
     */
    public function executeAutoAssign(): void
    {
        if (! $this->selectedMarketplaceId) {
            $this->dispatch('error', 'Please select a marketplace');

            return;
        }

        $this->isProcessing = true;
        $this->progressMessage = 'Starting auto-assignment...';
        $this->progressPercentage = 0;

        try {
            $marketplace = SyncAccount::find($this->selectedMarketplaceId);
            $products = Product::whereIn('id', $this->selectedProductIds)->get();

            $results = [
                'total_products' => $products->count(),
                'attributes_assigned' => 0,
                'products_processed' => 0,
                'results' => [],
            ];

            $processed = 0;

            foreach ($products as $product) {
                $this->progressMessage = "Auto-assigning for {$product->name}...";
                $this->progressPercentage = round(($processed / $products->count()) * 100);

                try {
                    $autoResult = $this->attributeService->autoAssignAttributes($product, $marketplace);

                    $results['attributes_assigned'] += $autoResult['attributes_assigned'];
                    $results['products_processed']++;
                    $results['results'][] = [
                        'product_name' => $product->name,
                        'attributes_assigned' => $autoResult['attributes_assigned'],
                        'assignments' => $autoResult['assignments'],
                        'skipped' => $autoResult['skipped'],
                    ];

                } catch (Exception $e) {
                    $results['results'][] = [
                        'product_name' => $product->name,
                        'error' => $e->getMessage(),
                    ];
                }

                $processed++;
            }

            $this->operationResults = $results;
            $this->progressMessage = 'Auto-assignment completed!';
            $this->progressPercentage = 100;

            $this->dispatch('success',
                "Auto-assignment completed! {$results['attributes_assigned']} attributes assigned across {$results['products_processed']} products."
            );

        } catch (Exception $e) {
            $this->dispatch('error', 'Auto-assignment failed: '.$e->getMessage());
        } finally {
            $this->isProcessing = false;
        }
    }

    /**
     * ✅ Execute validation analysis
     */
    public function executeValidation(): void
    {
        if (! $this->selectedMarketplaceId) {
            $this->dispatch('error', 'Please select a marketplace');

            return;
        }

        $this->isProcessing = true;
        $this->progressMessage = 'Analyzing attribute validation...';

        try {
            $marketplace = SyncAccount::find($this->selectedMarketplaceId);
            $products = Product::whereIn('id', $this->selectedProductIds)->get();

            $validationResults = $this->attributeService->bulkValidateAttributes($products, $marketplace);

            $this->validationSummary = $validationResults;
            $this->progressMessage = 'Validation analysis completed!';

            $this->dispatch('success',
                "Validation completed! {$validationResults['valid_products']}/{$validationResults['total_products']} products are marketplace-ready."
            );

        } catch (Exception $e) {
            $this->dispatch('error', 'Validation analysis failed: '.$e->getMessage());
        } finally {
            $this->isProcessing = false;
        }
    }

    /**
     * 📊 Execute readiness analysis
     */
    public function executeReadinessAnalysis(): void
    {
        if (! $this->selectedMarketplaceId) {
            $this->dispatch('error', 'Please select a marketplace');

            return;
        }

        $this->isProcessing = true;
        $this->progressMessage = 'Generating readiness analysis...';

        try {
            $marketplace = SyncAccount::find($this->selectedMarketplaceId);
            $products = Product::whereIn('id', $this->selectedProductIds)->get();

            $analysis = [
                'total_products' => $products->count(),
                'marketplace' => [
                    'name' => $marketplace->name,
                    'channel' => $marketplace->channel,
                ],
                'readiness_distribution' => [
                    'ready' => 0,
                    'nearly_ready' => 0,
                    'needs_improvement' => 0,
                    'not_ready' => 0,
                ],
                'average_completion' => 0,
                'total_missing_attributes' => 0,
                'product_reports' => [],
            ];

            $totalCompletion = 0;

            foreach ($products as $product) {
                $report = $this->attributeService->getMarketplaceReadinessReport($product, $marketplace);

                $analysis['readiness_distribution'][$report['status']]++;
                $totalCompletion += $report['completion_percentage'];
                $analysis['total_missing_attributes'] += $report['attributes']['required_missing'];

                $analysis['product_reports'][] = [
                    'product_name' => $product->name,
                    'readiness_score' => $report['readiness_score'],
                    'status' => $report['status'],
                    'completion_percentage' => $report['completion_percentage'],
                    'attributes' => $report['attributes'],
                    'recommendations' => $report['recommendations'],
                ];
            }

            $analysis['average_completion'] = $products->count() > 0 ? round($totalCompletion / $products->count()) : 0;

            $this->readinessAnalysis = $analysis;
            $this->progressMessage = 'Readiness analysis completed!';

            $this->dispatch('success', 'Readiness analysis completed successfully!');

        } catch (Exception $e) {
            $this->dispatch('error', 'Readiness analysis failed: '.$e->getMessage());
        } finally {
            $this->isProcessing = false;
        }
    }

    /**
     * ✅ Validate operation before execution
     */
    protected function validateOperation(): bool
    {
        if (empty($this->selectedProductIds)) {
            $this->dispatch('error', 'No products selected');

            return false;
        }

        if (! $this->selectedMarketplaceId) {
            $this->dispatch('error', 'Please select a marketplace');

            return false;
        }

        if ($this->operationType === 'assign' && empty($this->attributesToAssign)) {
            $this->dispatch('error', 'Please add at least one attribute to assign');

            return false;
        }

        return true;
    }

    /**
     * 🔄 Reset operation state
     */
    public function resetOperation(): void
    {
        $this->operationResults = null;
        $this->validationSummary = null;
        $this->readinessAnalysis = null;
        $this->progressMessage = '';
        $this->progressPercentage = 0;
        $this->attributesToAssign = [];
    }

    /**
     * 🏠 Return to bulk operations center
     */
    public function returnToBulkOperations(): void
    {
        return $this->redirect(route('bulk.operations'));
    }

    /**
     * 📋 Get attribute definition by key
     */
    public function getAttributeDefinition(string $key): ?array
    {
        return collect($this->availableAttributes)->firstWhere('key', $key);
    }

    public function render()
    {
        return view('livewire.bulk-operations.bulk-marketplace-attributes-operation');
    }
}
