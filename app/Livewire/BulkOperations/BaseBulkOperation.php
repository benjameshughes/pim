<?php

namespace App\Livewire\BulkOperations;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

/**
 * 🚀 BASE BULK OPERATION
 *
 * Abstract base class for all bulk operation components.
 * Provides shared functionality for handling selected items, progress tracking,
 * validation, and navigation between bulk operations.
 */
abstract class BaseBulkOperation extends Component
{
    // URL Parameters
    public string $targetType; // 'products' or 'variants'

    public string $encryptedSelectedItems = ''; // Encrypted array of selected item IDs

    // Processed data (can be string from URL or array from tests)
    public mixed $selectedItems = [];

    // Processing state
    public bool $isProcessing = false;

    public float $processingProgress = 0;

    // UI state
    public ?string $errorMessage = null;

    public ?string $successMessage = null;

    /**
     * 🎯 Initialize the component with URL parameters
     */
    public function mount(string $targetType, mixed $selectedItems): void
    {
        try {
            // Set target type first
            $this->targetType = $targetType;

            // Validate target type
            if (! in_array($targetType, ['products', 'variants'])) {
                throw new \InvalidArgumentException('Invalid target type: '.$targetType);
            }

            // Process selectedItems - handle both encrypted strings and arrays
            if (is_string($selectedItems)) {
                $this->encryptedSelectedItems = $selectedItems;
                $this->selectedItems = decrypt($selectedItems);
            } elseif (is_array($selectedItems)) {
                // Direct array (from tests)
                $this->selectedItems = $selectedItems;
                $this->encryptedSelectedItems = encrypt($selectedItems);
            } else {
                throw new \InvalidArgumentException('Invalid selected items format');
            }

            // Validate selected items exist and are not empty
            if (empty($this->selectedItems) || ! is_array($this->selectedItems)) {
                throw new \InvalidArgumentException('No valid items selected');
            }

            // Verify items still exist in database
            $this->validateSelectedItems();

        } catch (\Exception $e) {
            Log::warning('BulkOperation mount failed: '.$e->getMessage(), [
                'targetType' => $targetType,
                'selectedItems' => is_string($selectedItems) ? 'encrypted_string' : gettype($selectedItems),
            ]);
            $this->redirectRoute('bulk.operations', navigate: true);
        }
    }

    /**
     * 🔍 Validate that selected items still exist in database
     */
    private function validateSelectedItems(): void
    {
        // Ensure selectedItems is an array
        if (! is_array($this->selectedItems)) {
            return;
        }

        $model = $this->targetType === 'products' ? Product::class : ProductVariant::class;
        $existingIds = $model::whereIn('id', $this->selectedItems)->pluck('id')->toArray();

        // Filter out items that no longer exist
        $this->selectedItems = array_intersect($this->selectedItems, $existingIds);

        if (empty($this->selectedItems)) {
            throw new \InvalidArgumentException('Selected items no longer exist');
        }
    }

    /**
     * 📋 Get selected items as collection
     */
    protected function getSelectedItemsCollection(): Collection
    {
        $items = is_array($this->selectedItems) ? $this->selectedItems : [];

        return $this->targetType === 'products'
            ? Product::whereIn('id', $items)->get()
            : ProductVariant::whereIn('id', $items)->get();
    }

    /**
     * 📊 Get count of selected items
     */
    public function getSelectedCountProperty(): int
    {
        return is_array($this->selectedItems) ? count($this->selectedItems) : 0;
    }

    /**
     * 🎨 Get display name for target type
     */
    public function getTargetDisplayNameProperty(): string
    {
        return $this->targetType === 'products' ? 'Products' : 'Variants';
    }

    /**
     * 🔙 Navigate back to bulk operations center
     */
    public function backToBulkOperations(): void
    {
        $this->redirectRoute('bulk.operations', navigate: true);
    }

    /**
     * ✅ Handle successful operation completion
     */
    protected function handleSuccess(string $message): void
    {
        session()->flash('bulk_operation_success', $message);
        $this->redirectRoute('bulk.operations', navigate: true);
    }

    /**
     * ❌ Handle operation error
     */
    protected function handleError(string $message, ?\Exception $exception = null): void
    {
        $this->isProcessing = false;
        $this->errorMessage = $message;

        if ($exception) {
            Log::error('Bulk operation failed', [
                'operation' => static::class,
                'target_type' => $this->targetType,
                'selected_items' => $this->selectedItems,
                'error' => $exception->getMessage(),
                'user_id' => auth()->id(),
            ]);
        }
    }

    /**
     * 🔄 Execute bulk operation with progress tracking and error handling
     *
     * @param  callable(\App\Models\Product|\App\Models\ProductVariant): void  $operation
     */
    protected function executeBulkOperation(callable $operation, string $operationType): void
    {
        $this->isProcessing = true;
        $this->processingProgress = 0;
        $this->errorMessage = null;

        try {
            DB::transaction(function () use ($operation, $operationType) {
                $items = $this->getSelectedItemsCollection();
                $total = $items->count();
                $processed = 0;

                foreach ($items->chunk(10) as $chunk) {
                    foreach ($chunk as $item) {
                        $operation($item);
                        $processed++;

                        $this->processingProgress = (float) round(($processed / $total) * 100);
                    }

                    // Allow UI to update
                    $this->dispatch('progress-updated');
                }

                // Log successful operation
                Log::info("Bulk {$operationType} completed", [
                    'operation' => static::class,
                    'target_type' => $this->targetType,
                    'items_updated' => $total,
                    'user_id' => auth()->id(),
                ]);
            });

            $this->isProcessing = false;
            $this->handleSuccess("Successfully {$operationType} {$this->getSelectedCountProperty()} {$this->getTargetDisplayNameProperty()}!");

        } catch (\Exception $e) {
            $this->handleError("Bulk {$operationType} failed: ".$e->getMessage(), $e);
        }
    }

    /**
     * 🧹 Clear error message
     */
    public function clearError(): void
    {
        $this->errorMessage = null;
    }

    /**
     * 🎨 Abstract method for rendering the component
     */
    abstract public function render(): \Illuminate\Contracts\View\View;
}
