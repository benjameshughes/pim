<?php

namespace App\Actions\Import;

use App\Models\Product;
use App\Repositories\ProductRepository;
use App\Services\AutoParentCreator;
use Illuminate\Support\Facades\Log;

class FindParentForVariant
{
    public function __construct(
        private ProductRepository $productRepository
    ) {}

    public function execute(array $data, bool $autoCreateParents = true): ?Product
    {
        // 1. Check if parent_name is explicitly provided
        if (!empty($data['parent_name'])) {
            $parent = $this->productRepository->findByName($data['parent_name']);
            if ($parent) {
                Log::info("Found parent by explicit name", [
                    'parent_name' => $data['parent_name'],
                    'parent_id' => $parent->id
                ]);
                return $parent;
            }
        }
        
        // 2. Check if exact product_name match exists as parent
        if (!empty($data['product_name'])) {
            $parent = $this->productRepository->findParentByName($data['product_name']);
            if ($parent) {
                Log::info("Found parent by exact product name match", [
                    'product_name' => $data['product_name'],
                    'parent_id' => $parent->id
                ]);
                return $parent;
            }
        }
        
        // 3. Use AutoParentCreator service for intelligent parent finding/creation
        if ($autoCreateParents) {
            try {
                $autoParentCreator = app(AutoParentCreator::class);
                $parent = $autoParentCreator->findOrCreateParent($data);
                
                if ($parent) {
                    Log::info("Auto-created/found parent", [
                        'parent_id' => $parent->id,
                        'parent_name' => $parent->name,
                        'original_name' => $data['product_name'] ?? 'N/A'
                    ]);
                    return $parent;
                }
            } catch (\Exception $e) {
                Log::error("Auto parent creation failed", [
                    'error' => $e->getMessage(),
                    'data' => $data
                ]);
            }
        }
        
        Log::warning("No parent found for variant", [
            'product_name' => $data['product_name'] ?? 'N/A',
            'parent_name' => $data['parent_name'] ?? 'N/A'
        ]);
        
        return null;
    }
}