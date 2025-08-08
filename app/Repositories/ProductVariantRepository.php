<?php

namespace App\Repositories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection;

class ProductVariantRepository
{
    public function findBySku(string $sku): ?ProductVariant
    {
        return ProductVariant::where('sku', $sku)->first();
    }

    public function findByProductAndColor(Product $product, string $color): ?ProductVariant
    {
        return ProductVariant::where('product_id', $product->id)
            ->where('color', $color)
            ->first();
    }

    public function findByProductAndSize(Product $product, string $size): ?ProductVariant
    {
        return ProductVariant::where('product_id', $product->id)
            ->where('size', $size)
            ->first();
    }

    public function findByProductColorAndSize(Product $product, ?string $color, ?string $size): ?ProductVariant
    {
        $query = ProductVariant::where('product_id', $product->id);

        if ($color) {
            $query->where('color', $color);
        }

        if ($size) {
            $query->where('size', $size);
        }

        return $query->first();
    }

    public function findByProduct(Product $product): Collection
    {
        return ProductVariant::where('product_id', $product->id)->get();
    }

    public function create(array $data): ProductVariant
    {
        return ProductVariant::create($data);
    }

    public function updateOrCreate(array $conditions, array $data): ProductVariant
    {
        return ProductVariant::updateOrCreate($conditions, $data);
    }

    public function getLatestWithSku(): ?ProductVariant
    {
        return ProductVariant::whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('created_at', 'desc')
            ->first();
    }

    public function getBySkuPrefix(string $prefix): Collection
    {
        return ProductVariant::where('sku', 'LIKE', "{$prefix}-%")->get();
    }

    public function getSkuCount(): int
    {
        return ProductVariant::whereNotNull('sku')
            ->where('sku', '!=', '')
            ->count();
    }

    public function findById(int $id): ?ProductVariant
    {
        return ProductVariant::find($id);
    }

    public function update(ProductVariant $variant, array $data): bool
    {
        return $variant->update($data);
    }

    public function delete(ProductVariant $variant): bool
    {
        return $variant->delete();
    }
}
