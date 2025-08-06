<?php

namespace App\Repositories;

use App\Models\Barcode;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection;

class BarcodeRepository
{
    public function findByBarcode(string $barcode): ?Barcode
    {
        return Barcode::where('barcode', $barcode)->first();
    }
    
    public function findByVariant(ProductVariant $variant): Collection
    {
        return Barcode::where('variant_id', $variant->id)->get();
    }
    
    public function findPrimaryByVariant(ProductVariant $variant): ?Barcode
    {
        return Barcode::where('variant_id', $variant->id)
                     ->where('is_primary', true)
                     ->first();
    }
    
    public function create(array $data): Barcode
    {
        return Barcode::create($data);
    }
    
    public function updateOrCreate(array $conditions, array $data): Barcode
    {
        return Barcode::updateOrCreate($conditions, $data);
    }
    
    public function existsForVariant(ProductVariant $variant, string $barcode): bool
    {
        return Barcode::where('variant_id', $variant->id)
                     ->where('barcode', $barcode)
                     ->exists();
    }
    
    public function getDuplicates(): Collection
    {
        return Barcode::select('barcode')
                     ->groupBy('barcode')
                     ->havingRaw('COUNT(*) > 1')
                     ->get();
    }
    
    public function getByType(string $type): Collection
    {
        return Barcode::where('type', $type)->get();
    }
    
    public function getUnassigned(): Collection
    {
        return Barcode::whereNull('variant_id')->get();
    }
    
    public function delete(Barcode $barcode): bool
    {
        return $barcode->delete();
    }
    
    public function deleteByVariant(ProductVariant $variant): int
    {
        return Barcode::where('variant_id', $variant->id)->delete();
    }
}