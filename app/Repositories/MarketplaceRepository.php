<?php

namespace App\Repositories;

use App\Models\Marketplace;
use App\Models\MarketplaceVariant;
use App\Models\MarketplaceBarcode;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection;

class MarketplaceRepository
{
    public function findByName(string $name): ?Marketplace
    {
        return Marketplace::where('name', $name)->first();
    }
    
    public function getAll(): Collection
    {
        return Marketplace::all();
    }
    
    public function getActive(): Collection
    {
        return Marketplace::where('is_active', true)->get();
    }
    
    public function create(array $data): Marketplace
    {
        return Marketplace::create($data);
    }
    
    public function findVariantByProductId(ProductVariant $variant, Marketplace $marketplace): ?MarketplaceVariant
    {
        return MarketplaceVariant::where('variant_id', $variant->id)
                                ->where('marketplace_id', $marketplace->id)
                                ->first();
    }
    
    public function createVariant(array $data): MarketplaceVariant
    {
        return MarketplaceVariant::create($data);
    }
    
    public function updateOrCreateVariant(array $conditions, array $data): MarketplaceVariant
    {
        return MarketplaceVariant::updateOrCreate($conditions, $data);
    }
    
    public function findBarcodeByVariant(ProductVariant $variant, Marketplace $marketplace): ?MarketplaceBarcode
    {
        return MarketplaceBarcode::where('variant_id', $variant->id)
                                ->where('marketplace_id', $marketplace->id)
                                ->first();
    }
    
    public function createBarcode(array $data): MarketplaceBarcode
    {
        return MarketplaceBarcode::create($data);
    }
    
    public function updateOrCreateBarcode(array $conditions, array $data): MarketplaceBarcode
    {
        return MarketplaceBarcode::updateOrCreate($conditions, $data);
    }
    
    public function getVariantsByMarketplace(Marketplace $marketplace): Collection
    {
        return MarketplaceVariant::where('marketplace_id', $marketplace->id)->get();
    }
    
    public function getBarcodesByMarketplace(Marketplace $marketplace): Collection
    {
        return MarketplaceBarcode::where('marketplace_id', $marketplace->id)->get();
    }
    
    public function deleteVariantsByVariant(ProductVariant $variant): int
    {
        return MarketplaceVariant::where('variant_id', $variant->id)->delete();
    }
    
    public function deleteBarcodesByVariant(ProductVariant $variant): int
    {
        return MarketplaceBarcode::where('variant_id', $variant->id)->delete();
    }
}