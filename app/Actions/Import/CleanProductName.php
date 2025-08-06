<?php

namespace App\Actions\Import;

class CleanProductName
{
    public function execute(string $rawName): string
    {
        // Remove extra whitespace
        $cleaned = trim($rawName);
        
        // Remove multiple spaces
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        
        // Remove trailing punctuation that might interfere with grouping
        $cleaned = rtrim($cleaned, '.,;:-');
        
        return $cleaned;
    }
}