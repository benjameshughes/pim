<?php

namespace App\Livewire\Components;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * 🔍✨ PRODUCT/VARIANT COMBOBOX - SEARCH & SELECT
 *
 * Shadcn-inspired combobox for searching and selecting products or variants
 * Supports both products and variants with intelligent search
 */
class ProductVariantCombobox extends Component
{
    // Search and selection
    public string $search = '';

    public string $selectedType = ''; // 'product' or 'variant'

    public int $selectedId = 0;

    public string $placeholder = 'Search products and variants...';

    public bool $isOpen = false;

    // Configuration
    public bool $allowProducts = true;

    public bool $allowVariants = true;

    public int $maxResults = 10;

    // Current selection display
    public string $displayValue = '';

    /**
     * 🔍 GET SEARCH RESULTS
     *
     * @return Collection<int, array{id: int, type: string, name: string, sku: string, description: string}>
     */
    #[Computed]
    public function searchResults(): Collection
    {
        if (strlen($this->search) < 2) {
            return collect();
        }

        $results = collect();

        // Search products
        if ($this->allowProducts) {
            $products = Product::query()
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('parent_sku', 'like', "%{$this->search}%")
                ->orWhere('description', 'like', "%{$this->search}%")
                ->limit($this->maxResults)
                ->get()
                ->map(function (Product $product) {
                    return [
                        'id' => $product->id,
                        'type' => 'product',
                        'name' => $product->name,
                        'sku' => $product->parent_sku,
                        'description' => $product->description ?? '',
                        'variants_count' => $product->variants()->count(),
                    ];
                });

            $results = $results->concat($products);
        }

        // Search variants
        if ($this->allowVariants) {
            $variants = ProductVariant::query()
                ->with('product')
                ->where('sku', 'like', "%{$this->search}%")
                ->orWhere('name', 'like', "%{$this->search}%")
                ->orWhereHas('product', function ($query) {
                    $query->where('name', 'like', "%{$this->search}%");
                })
                ->limit($this->maxResults)
                ->get()
                ->map(function (ProductVariant $variant) {
                    return [
                        'id' => $variant->id,
                        'type' => 'variant',
                        'name' => $variant->name ?? $variant->product->name,
                        'sku' => $variant->sku,
                        'description' => $variant->product->name,
                        'product_name' => $variant->product->name,
                    ];
                });

            $results = $results->concat($variants);
        }

        return $results->take($this->maxResults);
    }

    /**
     * ✅ SELECT ITEM
     */
    public function selectItem(string $type, int $id): void
    {
        $this->selectedType = $type;
        $this->selectedId = $id;
        $this->isOpen = false;

        // Update display value
        if ($type === 'product') {
            $product = Product::find($id);
            $this->displayValue = $product ? "{$product->name} (SKU: {$product->parent_sku})" : '';
        } elseif ($type === 'variant') {
            $variant = ProductVariant::with('product')->find($id);
            $this->displayValue = $variant
                ? "{$variant->product->name} - {$variant->name} (SKU: {$variant->sku})"
                : '';
        }

        $this->search = $this->displayValue;

        // Emit selection event to parent
        $this->dispatch('item-selected', [
            'type' => $type,
            'id' => $id,
            'display' => $this->displayValue,
        ]);
    }

    /**
     * ❌ CLEAR SELECTION
     */
    public function clear(): void
    {
        $this->reset(['selectedType', 'selectedId', 'displayValue', 'search']);
        $this->dispatch('item-cleared');
    }

    /**
     * 🔍 HANDLE SEARCH INPUT
     */
    public function updatedSearch(): void
    {
        $this->isOpen = strlen($this->search) >= 2;

        // Clear selection if search doesn't match display value
        if ($this->search !== $this->displayValue) {
            $this->selectedType = '';
            $this->selectedId = 0;
            $this->displayValue = '';
        }
    }

    /**
     * 📂 OPEN DROPDOWN
     */
    public function openDropdown(): void
    {
        $this->isOpen = true;
    }

    /**
     * 📂 CLOSE DROPDOWN
     */
    public function closeDropdown(): void
    {
        $this->isOpen = false;
    }

    /**
     * 🎨 RENDER COMPONENT
     */
    public function render(): View
    {
        return view('livewire.components.product-variant-combobox');
    }
}
