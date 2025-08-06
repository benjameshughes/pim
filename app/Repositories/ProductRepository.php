<?php

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class ProductRepository
{
    public function findByName(string $name): ?Product
    {
        return Product::where('name', $name)->first();
    }
    
    public function findParentByName(string $name): ?Product
    {
        return Product::where('name', $name)
                     ->where('is_parent', true)
                     ->first();
    }
    
    public function findByNameLike(string $pattern): Collection
    {
        return Product::where('name', 'LIKE', $pattern)->get();
    }
    
    public function findParentByNameLike(string $pattern): ?Product
    {
        return Product::where('name', 'LIKE', $pattern)
                     ->where('is_parent', true)
                     ->first();
    }
    
    public function create(array $data): Product
    {
        return Product::create($data);
    }
    
    public function updateOrCreate(array $conditions, array $data): Product
    {
        return Product::updateOrCreate($conditions, $data);
    }
    
    public function getAllParents(): Collection
    {
        return Product::where('is_parent', true)->get();
    }
    
    public function getParentsWithVariantCount(): Collection
    {
        return Product::where('is_parent', true)
                     ->withCount('variants')
                     ->get();
    }
    
    public function findById(int $id): ?Product
    {
        return Product::find($id);
    }
    
    public function delete(Product $product): bool
    {
        return $product->delete();
    }
}