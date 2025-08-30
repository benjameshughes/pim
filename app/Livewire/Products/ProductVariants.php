<?php

namespace App\Livewire\Products;

use App\Models\Product;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * 💎 PRODUCT VARIANTS - LAZY LOADING WITH COLLECTIONS
 *
 * Clean lazy-loaded component for displaying product variants
 * Uses Laravel Collections for elegant data handling
 */
class ProductVariants extends Component
{
    public Product $product;

    /**
     * 🎯 Mount with product instance
     */
    public function mount(Product $product): void
    {
        // Authorize viewing variants
        $this->authorize('view-variants');

        $this->product = $product;
    }

    /**
     * ✨ Smart variants collection with efficient loading
     */
    #[Computed]
    public function variants(): Collection
    {
        return $this->product
            ->variants()
            ->with(['pricing:id,product_variant_id,retail_price'])
            ->withCount('barcodes')
            ->get()
            ->sortBy(['color', 'width', 'drop'])
            ->values();
    }

    /**
     * 📊 Quick stats for this product's variants
     */
    #[Computed]
    public function stats(): array
    {
        $variants = $this->variants;

        return [
            'total' => $variants->count(),
            'active' => $variants->where('status', 'active')->count(),
            'colors' => $variants->pluck('color')->unique()->count(),
            'avg_price' => $variants->avg('price') ?? 0,
            'total_stock' => $variants->sum('stock_level') ?? 0,
        ];
    }

    /**
     * Color mapping cache
     */
    private static array $colorMap = [
        'red' => '#FF0000',
        'blue' => '#0000FF',
        'green' => '#008000',
        'yellow' => '#FFFF00',
        'orange' => '#FFA500',
        'purple' => '#800080',
        'pink' => '#FFC0CB',
        'brown' => '#A52A2A',
        'gray' => '#808080',
        'grey' => '#808080',
        'black' => '#000000',
        'white' => '#FFFFFF',
        'navy' => '#000080',
        'lime' => '#00FF00',
        'cyan' => '#00FFFF',
        'magenta' => '#FF00FF',
        'silver' => '#C0C0C0',
        'maroon' => '#800000',
        'olive' => '#808000',
        'teal' => '#008080',
        'aqua' => '#00FFFF',
        'fuchsia' => '#FF00FF',
    ];

    /**
     * 🌈 Convert color name to hex value for color picker
     */
    public function getColorHex(string $color): string
    {
        // If it's already a hex color, return as-is
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            return $color;
        }

        // Return cached color map lookup
        return self::$colorMap[strtolower($color)] ?? '#808080';
    }

    /**
     * 🎨 Update variant color with real-time save
     */
    public function updateColor(int $variantId, string $color): void
    {
        // Authorize editing variants
        $this->authorize('edit-variants');

        try {
            // Find the variant and update the color
            $variant = $this->product->variants()->findOrFail($variantId);
            $variant->update(['color' => $color]);

            // Clear computed properties to refresh data
            unset($this->variants);
            unset($this->stats);

            // Show friendly message
            $displayColor = $this->getColorName($color) ?: $color;
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Color updated to {$displayColor}! 🎨",
            ]);

        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to update color: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * 🎨 Get friendly color name from hex value
     */
    private function getColorName(string $hex): ?string
    {
        $reversed = array_flip(self::$colorMap);

        return isset($reversed[strtolower($hex)]) ? ucfirst($reversed[strtolower($hex)]) : null;
    }

    /**
     * 🎨 Render the variant rows
     */
    public function render()
    {
        return view('livewire.products.product-variants');
    }
}
