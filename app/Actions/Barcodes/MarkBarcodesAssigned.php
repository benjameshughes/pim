<?php

namespace App\Actions\Barcodes;

use App\Models\Barcode;
use Exception;

class MarkBarcodesAssigned
{
    public function execute(?string $upToBarcode = null, ?int $count = null, ?string $defaultTitle = null): int
    {
        $updateData = [
            'is_assigned' => true,
            'updated_at' => now()
        ];
        
        // Add title for empty ones if specified
        if ($defaultTitle) {
            $updateData['title'] = \DB::raw("COALESCE(NULLIF(title, ''), '{$defaultTitle}')");
        }
        
        if ($upToBarcode) {
            // Mark all barcodes up to the specified barcode number
            $updated = Barcode::where('barcode', '<=', $upToBarcode)
                ->where('is_assigned', false)
                ->update($updateData);
        } else {
            // Mark first N barcodes by count
            $count = $count ?? 40000;
            
            $updated = Barcode::where('is_assigned', false)
                ->orderBy('barcode')
                ->limit($count)
                ->update($updateData);
        }

        return $updated;
    }
}