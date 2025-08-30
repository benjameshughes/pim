<?php

namespace App\Livewire\Products;

use App\Exceptions\BarcodePoolExhaustedException;
use App\Exceptions\DuplicateSkuException;
use App\Models\BarcodePool;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Variant Create Livewire Component
 *
 * Modern variant creation using the enhanced VariantBuilder pattern.
 * Demonstrates barcode pool integration, pricing, and attribute handling.
 */
#[Layout('components.layouts.app')]
#[Title('Create Product Variant')]
class VariantCreate extends Component
{
    use WithFileUploads;

    /**
     * Parent product (passed via route parameter)
     */
    public ?Product $product = null;

    /**
     * Variant being edited (null for create mode)
     */
    public ?ProductVariant $variant = null;

    /**
     * Variant SKU
     */
    public string $sku = '';

    /**
     * Variant status
     */
    #[Validate('required|in:draft,active,inactive,archived')]
    public string $status = 'active';

    /**
     * Stock level
     */
    #[Validate('required|integer|min:0')]
    public int $stockLevel = 0;

    /**
     * Variant color
     */
    #[Validate('nullable|string|max:50')]
    public string $color = '';

    /**
     * Variant width (for window shades)
     */
    #[Validate('nullable|string|max:20')]
    public string $width = '';

    /**
     * Variant drop (for window shades)
     */
    #[Validate('nullable|string|max:20')]
    public string $drop = '';

    /**
     * Retail price
     */
    #[Validate('nullable|numeric|min:0')]
    public ?float $retailPrice = null;

    /**
     * Cost price
     */
    #[Validate('nullable|numeric|min:0')]
    public ?float $costPrice = null;

    /**
     * Package dimensions
     */
    #[Validate('package.length', 'nullable|numeric|min:0')]
    #[Validate('package.width', 'nullable|numeric|min:0')]
    #[Validate('package.height', 'nullable|numeric|min:0')]
    #[Validate('package.weight', 'nullable|numeric|min:0')]
    public array $package = [
        'length' => null,
        'width' => null,
        'height' => null,
        'weight' => null,
    ];

    /**
     * Barcode assignment settings
     */
    public array $barcode = [
        'assign' => false,
        'type' => 'EAN13',
        'custom' => '',
    ];

    /**
     * Available barcode types
     */
    public array $barcodeTypes = [
        'EAN13' => 'EAN-13',
        'UPC' => 'UPC',
        'CODE128' => 'Code 128',
    ];

    /**
     * Available status options
     */
    public array $statusOptions = [
        'draft' => 'Draft',
        'active' => 'Active',
        'inactive' => 'Inactive',
        'archived' => 'Archived',
    ];

    /**
     * Common colors for quick selection
     */
    public array $commonColors = [
        'Black', 'White', 'Red', 'Blue', 'Green', 'Yellow',
        'Orange', 'Purple', 'Pink', 'Brown', 'Grey', 'Navy',
    ];

    /**
     * Common widths for window shades
     */
    public array $commonWidths = [
        '60cm', '80cm', '100cm', '120cm', '140cm', '160cm', '180cm', '200cm',
    ];

    /**
     * Common drops for window shades
     */
    public array $commonDrops = [
        '120cm', '140cm', '160cm', '180cm', '200cm', '220cm', '240cm', '260cm',
    ];

    /**
     * Available barcodes count
     */
    public int $availableBarcodesCount = 0;

    /**
     * Form processing state
     */
    public bool $processing = false;

    /**
     * Current processing step for user feedback
     */
    public string $processingStep = '';

    /**
     * Processing progress (0-100)
     */
    public int $processingProgress = 0;

    /**
     * Component mount
     */
    public function mount(?Product $product = null, ?ProductVariant $variant = null): void
    {
        // Authorize creating variants
        $this->authorize('create-variants');
        
        $this->product = $product;
        $this->variant = $variant;

        if ($product) {
            $this->generateSkuFromProduct();
        }

        $this->loadBarcodeStats();
    }

    /**
     * Get dynamic validation rules that handle both create and edit modes
     */
    protected function rules()
    {
        return [
            'sku' => [
                'required',
                'string',
                'max:100',
                Rule::unique('product_variants', 'sku')->ignore($this->variant?->id),
            ],
            'status' => 'required|in:draft,active,inactive,archived',
            'stockLevel' => 'required|integer|min:0',
            'color' => 'nullable|string|max:50',
            'width' => 'nullable|string|max:20',
            'drop' => 'nullable|string|max:20',
            'retailPrice' => 'nullable|numeric|min:0',
            'costPrice' => 'nullable|numeric|min:0',
            'package.length' => 'nullable|numeric|min:0',
            'package.width' => 'nullable|numeric|min:0',
            'package.height' => 'nullable|numeric|min:0',
            'package.weight' => 'nullable|numeric|min:0',
        ];
    }

    /**
     * Generate SKU based on product parent SKU
     */
    protected function generateSkuFromProduct(): void
    {
        if (! $this->product || ! $this->product->parent_sku) {
            return;
        }

        // Find next sequential number for this product
        $existingVariants = ProductVariant::where('product_id', $this->product->id)
            ->pluck('sku')
            ->map(function ($sku) {
                // Extract number from SKU pattern (e.g., "001-123" -> 123)
                if (preg_match('/\-(\d+)$/', $sku, $matches)) {
                    return (int) $matches[1];
                }

                return 0;
            })
            ->filter()
            ->sort()
            ->values();

        $nextNumber = $existingVariants->isEmpty() ? 1 : $existingVariants->last() + 1;
        $this->sku = $this->product->parent_sku.'-'.str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Load barcode statistics with caching
     */
    protected function loadBarcodeStats(): void
    {
        // Cache barcode counts for 30 seconds to reduce database load
        $cacheKey = "barcode_count_{$this->barcode['type']}";

        $this->availableBarcodesCount = Cache::remember($cacheKey, 30, function () {
            return BarcodePool::where('status', 'available')
                ->where('barcode_type', $this->barcode['type'])
                ->count();
        });
    }

    /**
     * Update barcode stats when type changes
     */
    public function updatedBarcodeType(string $value): void
    {
        $this->barcode['type'] = $value;
        $this->loadBarcodeStats();
    }

    /**
     * Set color from quick selection
     */
    public function selectColor(string $color): void
    {
        $this->color = $color;
    }

    /**
     * Set width from quick selection
     */
    public function selectWidth(string $width): void
    {
        $this->width = $width;
    }

    /**
     * Set drop from quick selection
     */
    public function selectDrop(string $drop): void
    {
        $this->drop = $drop;
    }

    /**
     * Create the variant using our beautiful VariantBuilder! 🚀
     */
    public function save(): void
    {
        $this->processing = true;
        $this->processingStep = 'Validating form data...';
        $this->processingProgress = 10;

        // Enhanced loading states - simplified

        try {
            $this->validate();

            $this->processingStep = 'Building variant configuration...';
            $this->processingProgress = 25;

            // Create variant using the enhanced VariantBuilder pattern
            $builder = ProductVariant::buildFor($this->product)
                ->sku($this->sku)
                ->status($this->status)
                ->stockLevel($this->stockLevel);

            // Add variant attributes
            if (! empty($this->color)) {
                $builder->color($this->color);
            }

            if (! empty($this->width) || ! empty($this->drop)) {
                $builder->windowDimensions($this->width, $this->drop);
            }

            // Add pricing if provided
            if ($this->retailPrice !== null) {
                $builder->retailPrice($this->retailPrice);
            }

            if ($this->costPrice !== null) {
                $builder->costPrice($this->costPrice);
            }

            // Add package dimensions if provided
            if ($this->hasPackageDimensions()) {
                $builder->dimensions(
                    (float) $this->package['length'],
                    (float) $this->package['width'],
                    (float) $this->package['height'],
                    (float) $this->package['weight']
                );
            }

            // Handle barcode assignment
            if ($this->barcode['assign']) {
                $this->processingStep = 'Assigning barcode...';
                $this->processingProgress = 60;

                if (! empty($this->barcode['custom'])) {
                    // Use custom barcode
                    $builder->primaryBarcode($this->barcode['custom'], $this->barcode['type']);
                } else {
                    // Auto-assign from pool
                    $builder->assignFromPool($this->barcode['type']);
                }
            }

            $this->processingStep = 'Creating variant in database...';
            $this->processingProgress = 80;

            // Execute the builder to create the variant! ✨
            $variant = $builder->execute();

            $this->processingStep = 'Success!';
            $this->processingProgress = 100;

            // Dispatch success event
            $this->dispatch('variant-created', [
                'variantId' => $variant->id,
                'variantSku' => $variant->sku,
                'productName' => $this->product->name,
            ]);

            // Success notification
            $this->dispatch('success', "Variant '{$variant->sku}' created successfully!");

            // Redirect to variant view
            $this->redirect(route('variants.show', $variant), navigate: true);

        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->resetProcessing();
            throw $e;
        } catch (BarcodePoolExhaustedException $e) {
            $this->resetProcessing();

            // Enhanced notification for barcode pool exhaustion
            $this->dispatch('error', 'Barcodes Exhausted: '.$e->getUserMessage());

            // Keep original dispatch for any JavaScript listeners
            $this->dispatch('barcode-pool-exhausted', [
                'barcodeType' => $this->barcode['type'],
                'userMessage' => $e->getUserMessage(),
                'suggestedActions' => $e->getSuggestedActions(),
            ]);

        } catch (DuplicateSkuException $e) {
            $this->resetProcessing();

            // Enhanced notification for duplicate SKU
            $this->dispatch('error', 'Duplicate SKU: '.$e->getUserMessage());

            // Add validation error to the sku field
            $this->addError('sku', $e->getUserMessage());

            // Dispatch for JavaScript listeners
            $this->dispatch('duplicate-sku-error', [
                'sku' => $e->getSku(),
                'userMessage' => $e->getUserMessage(),
                'suggestedSkus' => $e->getSuggestedSkus(),
            ]);

        } catch (\Exception $e) {
            $this->resetProcessing();

            // Generic error
            $this->dispatch('error', 'Variant Creation Failed: '.$e->getMessage());

            $this->dispatch('variant-create-failed', error: $e->getMessage());
        }
    }

    /**
     * Check if package dimensions are provided
     */
    protected function hasPackageDimensions(): bool
    {
        return ! empty($this->package['length']) &&
               ! empty($this->package['width']) &&
               ! empty($this->package['height']);
    }

    /**
     * Cancel and go back to product
     */
    public function cancel(): void
    {
        $routeName = $this->product ? 'products.view' : 'products.variants.index';
        $routeParams = $this->product ? [$this->product] : [];

        $this->redirect(route($routeName, $routeParams), navigate: true);
    }

    /**
     * Get display title for the page
     */
    public function getTitle(): string
    {
        return $this->product
            ? "Create Variant for {$this->product->name}"
            : 'Create Product Variant';
    }

    /**
     * Reset processing state
     */
    protected function resetProcessing(): void
    {
        $this->processing = false;
        $this->processingStep = '';
        $this->processingProgress = 0;
    }

    /**
     * Render the component
     *
     * @return \Illuminate\View\View
     */
    public function render()
    {
        return view('livewire.products.variant-create', [
            'title' => $this->getTitle(),
        ]);
    }
}
