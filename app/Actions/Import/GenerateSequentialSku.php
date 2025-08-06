<?php

namespace App\Actions\Import;

use App\Models\ProductVariant;
use Illuminate\Support\Facades\Log;

class GenerateSequentialSku
{
    public function execute(): ?string
    {
        $prefix = $this->detectSkuPrefix();
        
        if (!$prefix) {
            Log::info("No SKU prefix detected, starting with '001'");
            $prefix = '001';
            $nextNumber = 1;
        } else {
            $nextNumber = $this->findHighestSkuNumber($prefix) + 1;
        }
        
        $newSku = sprintf('%s-%03d', $prefix, $nextNumber);
        
        Log::info("Generated sequential SKU", [
            'prefix' => $prefix,
            'next_number' => $nextNumber,
            'new_sku' => $newSku
        ]);
        
        return $newSku;
    }
    
    private function detectSkuPrefix(): ?string
    {
        $latestVariant = ProductVariant::whereNotNull('sku')
                                     ->where('sku', '!=', '')
                                     ->orderBy('created_at', 'desc')
                                     ->first();
        
        if (!$latestVariant || !$latestVariant->sku) {
            return null;
        }
        
        if (preg_match('/^(\d{3})-\d{3}$/', $latestVariant->sku, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    private function findHighestSkuNumber(string $prefix): int
    {
        $variants = ProductVariant::whereNotNull('sku')
                                 ->where('sku', 'LIKE', "{$prefix}-%")
                                 ->get();
        
        $highestNumber = 0;
        
        foreach ($variants as $variant) {
            if (preg_match('/^' . preg_quote($prefix) . '-(\d{3})$/', $variant->sku, $matches)) {
                $number = (int) $matches[1];
                if ($number > $highestNumber) {
                    $highestNumber = $number;
                }
            }
        }
        
        return $highestNumber;
    }
}