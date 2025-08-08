<?php

namespace App\Actions\Import;

class GenerateParentName
{
    public function execute(string $productName, ?string $variantSku = null): string
    {
        // First clean the product name
        $parentName = app(CleanProductName::class)->execute($productName);

        // Remove obvious variant-specific information
        $parentName = $this->removeVariantSpecificTerms($parentName);

        // If the result is too short or empty, fall back to the original name
        if (strlen($parentName) < 5) {
            $parentName = $productName;
        }

        // Remove variant-specific dimensions and colors more conservatively
        $parentName = $this->removeConservatively($parentName);

        return trim($parentName);
    }

    private function removeVariantSpecificTerms(string $name): string
    {
        // Remove dimensions in brackets like [45cm x 120cm]
        $cleaned = preg_replace('/\[\d+cm?\s*x\s*\d+cm?\]/i', '', $name);

        // Remove standalone dimensions like "45cm x 120cm"
        $cleaned = preg_replace('/\b\d+cm?\s*x\s*\d+cm?\b/i', '', $cleaned);

        // Remove color names in brackets like [White], [Dark Grey]
        $colors = ['white', 'black', 'grey', 'gray', 'dark grey', 'light grey', 'blue', 'red', 'green', 'yellow', 'brown', 'cream', 'cappuccino'];
        foreach ($colors as $color) {
            $cleaned = preg_replace('/\['.preg_quote($color, '/').'\]/i', '', $cleaned);
        }

        // Clean up extra spaces
        $cleaned = preg_replace('/\s+/', ' ', trim($cleaned));

        return $cleaned;
    }

    private function removeConservatively(string $name): string
    {
        // Only remove very obvious variant markers, keep the core product identity

        // Remove size indicators like "C02", "C04/6" at the end
        $cleaned = preg_replace('/\s+C\d{2}(?:\/\d)?(?:\s+|$)/i', ' ', $name);

        // Remove trailing color words only if they're clearly separated
        $colors = ['white', 'black', 'grey', 'gray', 'dark grey', 'light grey', 'blue', 'red', 'green', 'yellow', 'brown', 'cream', 'cappuccino'];
        foreach ($colors as $color) {
            $cleaned = preg_replace('/\s+'.preg_quote($color, '/').'\s*$/i', '', $cleaned);
        }

        return trim($cleaned);
    }
}
