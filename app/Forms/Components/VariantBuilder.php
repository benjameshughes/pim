<?php

namespace App\Forms\Components;

/**
 * 💎 VARIANT BUILDER COMPONENT
 *
 * Interactive variant generation like the old wizard
 * Handles collections, crossJoin, and real-time updates
 */
class VariantBuilder extends FormField
{
    protected array $colors = [];

    protected array $widths = [];

    protected array $drops = [];

    protected array $generatedVariants = [];

    protected bool $enableSkuGrouping = true;

    protected string $parentSku = '';

    public function colors(array $colors): static
    {
        $this->colors = $colors;

        return $this;
    }

    public function widths(array $widths): static
    {
        $this->widths = $widths;

        return $this;
    }

    public function drops(array $drops): static
    {
        $this->drops = $drops;

        return $this;
    }

    public function parentSku(string $parentSku): static
    {
        $this->parentSku = $parentSku;

        return $this;
    }

    public function enableSkuGrouping(bool $enable = true): static
    {
        $this->enableSkuGrouping = $enable;

        return $this;
    }

    public function render(): string
    {
        return view('forms.components.variant-builder', [
            'field' => $this,
            'colors' => $this->colors,
            'widths' => $this->widths,
            'drops' => $this->drops,
            'enableSkuGrouping' => $this->enableSkuGrouping,
            'parentSku' => $this->parentSku,
        ])->render();
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'colors' => $this->colors,
            'widths' => $this->widths,
            'drops' => $this->drops,
            'parent_sku' => $this->parentSku,
            'enable_sku_grouping' => $this->enableSkuGrouping,
        ]);
    }
}
