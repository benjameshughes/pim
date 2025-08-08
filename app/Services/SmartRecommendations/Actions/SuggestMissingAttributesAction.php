<?php

namespace App\Services\SmartRecommendations\Actions;

use App\Models\ProductVariant;

class SuggestMissingAttributesAction extends BaseRecommendationAction
{
    public function __construct(
        protected string $attributeType
    ) {}

    public function getType(): string
    {
        return 'suggest_missing_'.$this->attributeType;
    }

    public function getName(): string
    {
        return 'Suggest Missing '.ucfirst($this->attributeType);
    }

    public function getPreview(array $variantIds): array
    {
        $variants = ProductVariant::whereIn('id', $variantIds)
            ->with(['product', 'attributes'])
            ->take(5)
            ->get();

        return [
            'action' => "Provide suggestions for missing {$this->attributeType}",
            'affected_variants' => count($variantIds),
            'type' => $this->attributeType,
            'note' => 'This action provides suggestions - you decide what to implement',
            'sample_variants' => $variants->map(fn ($variant) => [
                'sku' => $variant->sku,
                'product' => $variant->product->name,
                'suggestion' => $this->generateSuggestion($variant),
            ])->toArray(),
        ];
    }

    public function canExecute(array $variantIds): bool
    {
        return ! empty($variantIds);
    }

    protected function performAction(array $variantIds): bool
    {
        // This is a suggestion-only action
        // It doesn't actually modify data, just provides guidance
        return true;
    }

    protected function generateSuggestion($variant): string
    {
        return match ($this->attributeType) {
            'pricing' => 'Manual pricing required - check similar products',
            'images' => 'Upload product images to improve conversion',
            'color' => $this->extractColorFromName($variant->product->name) ?? 'Add color attribute',
            'width' => $this->extractDimensionFromName($variant->product->name, 'width') ?? 'Add width dimension',
            'drop' => $this->extractDimensionFromName($variant->product->name, 'drop') ?? 'Add drop dimension',
            default => "Consider adding {$this->attributeType} attribute",
        };
    }

    protected function extractColorFromName(string $name): ?string
    {
        $colors = ['black', 'white', 'grey', 'gray', 'brown', 'beige', 'cream', 'blue', 'red', 'green'];

        foreach ($colors as $color) {
            if (str_contains(strtolower($name), $color)) {
                return ucfirst($color);
            }
        }

        return null;
    }

    protected function extractDimensionFromName(string $name, string $type): ?string
    {
        // Look for patterns like "120cm", "150 cm", "1200mm"
        if (preg_match('/(\d+)\s*(cm|mm)/', $name, $matches)) {
            $value = $matches[1];
            $unit = $matches[2];

            if ($unit === 'mm') {
                $value = $value / 10; // Convert mm to cm
                $unit = 'cm';
            }

            return $value.$unit;
        }

        return null;
    }
}
