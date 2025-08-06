<?php

namespace App\Casts\Import;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Safe SKU casting for import data
 * Handles SKU validation, formatting, and uniqueness preparation
 */
class SafeSkuCast implements CastsAttributes
{
    private int $maxLength;
    private bool $allowHyphens;
    private bool $allowUnderscores;
    private bool $forceUppercase;
    
    public function __construct(
        int $maxLength = 50,
        bool $allowHyphens = true,
        bool $allowUnderscores = true,
        bool $forceUppercase = false
    ) {
        $this->maxLength = $maxLength;
        $this->allowHyphens = $allowHyphens;
        $this->allowUnderscores = $allowUnderscores;
        $this->forceUppercase = $forceUppercase;
    }
    
    /**
     * Cast the given value when retrieving from database
     */
    public function get(Model $model, string $key, $value, array $attributes): ?string
    {
        return $value;
    }
    
    /**
     * Cast the given value when storing to database
     */
    public function set(Model $model, string $key, $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        $originalValue = (string) $value;
        $sku = trim($originalValue);
        
        // Remove null bytes and control characters
        $sku = preg_replace('/[\x00-\x1F\x7F]/', '', $sku);
        
        // Build allowed character pattern
        $allowedChars = 'a-zA-Z0-9';
        if ($this->allowHyphens) {
            $allowedChars .= '\-';
        }
        if ($this->allowUnderscores) {
            $allowedChars .= '_';
        }
        
        // Remove disallowed characters
        $cleanSku = preg_replace('/[^' . $allowedChars . ']/', '', $sku);
        
        // Apply case transformation
        if ($this->forceUppercase) {
            $cleanSku = strtoupper($cleanSku);
        }
        
        // Remove multiple consecutive hyphens/underscores
        if ($this->allowHyphens) {
            $cleanSku = preg_replace('/\-+/', '-', $cleanSku);
        }
        if ($this->allowUnderscores) {
            $cleanSku = preg_replace('/_+/', '_', $cleanSku);
        }
        
        // Trim leading/trailing separators
        $cleanSku = trim($cleanSku, '-_');
        
        // Ensure minimum length
        if (strlen($cleanSku) === 0) {
            Log::warning('Empty SKU after cleaning, generating fallback', [
                'model' => get_class($model),
                'key' => $key,
                'original_value' => $originalValue
            ]);
            
            // Generate fallback SKU
            $cleanSku = 'SKU_' . Str::random(8);
        }
        
        // Truncate if too long
        if (strlen($cleanSku) > $this->maxLength) {
            Log::info('SKU truncated due to length limit', [
                'model' => get_class($model),
                'key' => $key,
                'original_length' => strlen($cleanSku),
                'max_length' => $this->maxLength
            ]);
            $cleanSku = substr($cleanSku, 0, $this->maxLength);
        }
        
        // Log transformation if value changed significantly
        if ($originalValue !== $cleanSku) {
            Log::info('SKU transformed during casting', [
                'model' => get_class($model),
                'key' => $key,
                'original' => $originalValue,
                'transformed' => $cleanSku
            ]);
        }
        
        return $cleanSku;
    }
}