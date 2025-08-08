<?php

namespace App\Repositories;

use App\Models\ProductVariant;
use App\Models\VariantPricing;
use Illuminate\Database\Eloquent\Collection;

class VariantPricingRepository
{
    public function findByVariant(ProductVariant $variant): Collection
    {
        return VariantPricing::where('variant_id', $variant->id)->get();
    }

    public function findByVariantAndMarketplace(ProductVariant $variant, string $marketplace): ?VariantPricing
    {
        return VariantPricing::where('variant_id', $variant->id)
            ->where('marketplace', $marketplace)
            ->first();
    }

    public function findActiveByVariant(ProductVariant $variant): Collection
    {
        return VariantPricing::where('variant_id', $variant->id)
            ->where('is_active', true)
            ->get();
    }

    public function findDefaultByVariant(ProductVariant $variant): ?VariantPricing
    {
        return VariantPricing::where('variant_id', $variant->id)
            ->where('marketplace', 'default')
            ->where('is_active', true)
            ->first();
    }

    public function create(array $data): VariantPricing
    {
        return VariantPricing::create($data);
    }

    public function updateOrCreate(array $conditions, array $data): VariantPricing
    {
        return VariantPricing::updateOrCreate($conditions, $data);
    }

    public function getByMarketplace(string $marketplace): Collection
    {
        return VariantPricing::where('marketplace', $marketplace)->get();
    }

    public function getPriceRange(): array
    {
        $pricing = VariantPricing::selectRaw('MIN(price_including_vat) as min_price, MAX(price_including_vat) as max_price')
            ->where('is_active', true)
            ->first();

        return [
            'min' => $pricing->min_price ?? 0,
            'max' => $pricing->max_price ?? 0,
        ];
    }

    public function getAveragePrice(): float
    {
        return VariantPricing::where('is_active', true)
            ->avg('price_including_vat') ?? 0;
    }

    public function update(VariantPricing $pricing, array $data): bool
    {
        return $pricing->update($data);
    }

    public function delete(VariantPricing $pricing): bool
    {
        return $pricing->delete();
    }

    public function deleteByVariant(ProductVariant $variant): int
    {
        return VariantPricing::where('variant_id', $variant->id)->delete();
    }
}
