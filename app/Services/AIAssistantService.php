<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\MarketplaceVariant;
use App\Models\MarketplaceBarcode;
use App\Models\ProductAttribute;
use App\Models\VariantAttribute;
use App\Models\Marketplace;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class AIAssistantService
{
    private ?string $apiKey;
    private string $baseUrl;
    
    public function __construct()
    {
        $this->apiKey = config('services.anthropic.api_key', '');
        $this->baseUrl = config('services.anthropic.base_url', 'https://api.anthropic.com/v1/messages');
    }
    
    /**
     * Process AI request with context about the user's product data
     */
    public function processRequest(string $prompt, array $selectedVariants = []): string
    {
        // If no API key configured, return helpful placeholder
        if (empty($this->apiKey)) {
            return $this->getPlaceholderResponse($prompt, $selectedVariants);
        }
        
        try {
            // Build context about the user's data
            $context = $this->buildDataContext($selectedVariants);
            
            // Create the full prompt with context
            $fullPrompt = $this->buildPromptWithContext($prompt, $context);
            
            // Make API request to Claude
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01'
            ])->post($this->baseUrl, [
                'model' => 'claude-3-sonnet-20240229',
                'max_tokens' => 4000,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $fullPrompt
                    ]
                ]
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                return $data['content'][0]['text'] ?? 'No response generated.';
            } else {
                Log::error('Claude API error', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return "Sorry, I encountered an error communicating with the AI service. Please try again.";
            }
            
        } catch (\Exception $e) {
            Log::error('AI Assistant error', ['error' => $e->getMessage()]);
            return "I apologize, but I encountered an error processing your request. Please try again or contact support.";
        }
    }
    
    /**
     * Build context about the user's product data
     */
    private function buildDataContext(array $selectedVariants = []): array
    {
        $context = [
            'business_type' => 'Window Shades and Blinds Business',
            'total_products' => Product::count(),
            'total_variants' => ProductVariant::count(),
            'marketplace_variants' => MarketplaceVariant::count(),
            'marketplace_identifiers' => MarketplaceBarcode::count(),
            'product_attributes' => ProductAttribute::count(),
            'variant_attributes' => VariantAttribute::count(),
            'marketplaces' => Marketplace::active()->pluck('name')->toArray(),
        ];
        
        // Add data about selected variants if provided
        if (!empty($selectedVariants)) {
            $variants = ProductVariant::with(['product', 'marketplaceVariants', 'marketplaceBarcodes', 'attributes', 'product.attributes'])
                ->whereIn('id', $selectedVariants)
                ->limit(5) // Limit to first 5 for context
                ->get();
            
            $context['selected_variants'] = $variants->map(function ($variant) {
                return [
                    'sku' => $variant->sku,
                    'product_name' => $variant->product->name,
                    'color' => $variant->color,
                    'size' => $variant->size,
                    'marketplace_variants_count' => $variant->marketplaceVariants->count(),
                    'marketplace_identifiers_count' => $variant->marketplaceBarcodes->count(),
                    'variant_attributes_count' => $variant->attributes->count(),
                    'product_attributes_count' => $variant->product->attributes->count(),
                ];
            })->toArray();
        }
        
        // Add data quality insights
        $context['data_quality'] = [
            'variants_without_marketplace_variants' => ProductVariant::whereDoesntHave('marketplaceVariants')->count(),
            'variants_without_asin' => ProductVariant::whereDoesntHave('marketplaceBarcodes', function($query) {
                $query->where('identifier_type', 'asin');
            })->count(),
            'products_without_attributes' => Product::whereDoesntHave('attributes')->count(),
        ];
        
        return $context;
    }
    
    /**
     * Build the full prompt with business context
     */
    private function buildPromptWithContext(string $userPrompt, array $context): string
    {
        $contextString = "BUSINESS CONTEXT:\n";
        $contextString .= "You are helping manage a " . $context['business_type'] . " with:\n";
        $contextString .= "- " . $context['total_products'] . " products\n";
        $contextString .= "- " . $context['total_variants'] . " product variants\n";
        $contextString .= "- " . $context['marketplace_variants'] . " marketplace variants\n";
        $contextString .= "- " . $context['marketplace_identifiers'] . " marketplace identifiers (ASINs, Item IDs)\n";
        $contextString .= "- " . ($context['product_attributes'] + $context['variant_attributes']) . " total attributes\n";
        $contextString .= "- Active marketplaces: " . implode(', ', $context['marketplaces']) . "\n\n";
        
        if (isset($context['selected_variants']) && !empty($context['selected_variants'])) {
            $contextString .= "SELECTED VARIANTS FOR OPERATION:\n";
            foreach ($context['selected_variants'] as $variant) {
                $contextString .= "- {$variant['sku']}: {$variant['product_name']} ({$variant['color']} {$variant['size']})\n";
                $contextString .= "  Marketplace variants: {$variant['marketplace_variants_count']}, Identifiers: {$variant['marketplace_identifiers_count']}, Attributes: {$variant['variant_attributes_count']}\n";
            }
            $contextString .= "\n";
        }
        
        $contextString .= "DATA QUALITY INSIGHTS:\n";
        $contextString .= "- {$context['data_quality']['variants_without_marketplace_variants']} variants missing marketplace variants\n";
        $contextString .= "- {$context['data_quality']['variants_without_asin']} variants missing Amazon ASINs\n";
        $contextString .= "- {$context['data_quality']['products_without_attributes']} products missing attributes\n\n";
        
        $contextString .= "USER REQUEST:\n";
        $contextString .= $userPrompt . "\n\n";
        
        $contextString .= "Please provide specific, actionable advice for this window shades business. ";
        $contextString .= "If generating content like titles or descriptions, make them relevant to window coverings, blinds, and shades. ";
        $contextString .= "Focus on practical solutions that can be implemented through the bulk operations interface.";
        
        return $contextString;
    }
    
    /**
     * Get placeholder response when API is not configured
     */
    private function getPlaceholderResponse(string $prompt, array $selectedVariants = []): string
    {
        $response = "🤖 **AI Assistant Integration Ready!**\n\n";
        $response .= "**Your request:** \"{$prompt}\"\n\n";
        
        if (!empty($selectedVariants)) {
            $response .= "**Selected variants:** " . count($selectedVariants) . " variants\n\n";
        }
        
        $response .= "**What this AI assistant can do when fully integrated:**\n\n";
        $response .= "📝 **Content Generation:**\n";
        $response .= "- Generate SEO-optimized marketplace titles\n";
        $response .= "- Create compelling product descriptions\n";
        $response .= "- Suggest keyword-rich content for different platforms\n\n";
        
        $response .= "🔍 **Data Analysis:**\n";
        $response .= "- Identify missing marketplace variants\n";
        $response .= "- Suggest optimal pricing strategies\n";
        $response .= "- Recommend attribute improvements\n\n";
        
        $response .= "⚡ **Automation Suggestions:**\n";
        $response .= "- Template recommendations for bulk operations\n";
        $response .= "- Marketplace-specific optimization tips\n";
        $response .= "- Data quality improvement strategies\n\n";
        
        $response .= "🔧 **Integration Status:**\n";
        $response .= "To enable the AI assistant, add your Claude API key to the environment:\n";
        $response .= "```\n";
        $response .= "ANTHROPIC_API_KEY=your_api_key_here\n";
        $response .= "```\n\n";
        
        $response .= "Would you like me to help you set up the Claude API integration? I can provide the exact steps needed!";
        
        return $response;
    }
    
    /**
     * Generate marketplace titles using AI
     */
    public function generateMarketplaceTitles(array $variantIds, array $marketplaceIds): array
    {
        $variants = ProductVariant::with(['product', 'attributes', 'product.attributes'])
            ->whereIn('id', $variantIds)
            ->limit(10) // Limit for API efficiency
            ->get();
        
        $marketplaces = Marketplace::whereIn('id', $marketplaceIds)->get();
        
        $prompt = "Generate SEO-optimized product titles for these window shade variants across different marketplaces:\n\n";
        
        foreach ($variants as $variant) {
            $prompt .= "Variant: {$variant->sku} - {$variant->product->name} ({$variant->color} {$variant->size})\n";
        }
        
        $prompt .= "\nFor marketplaces: " . $marketplaces->pluck('name')->implode(', ') . "\n\n";
        $prompt .= "Requirements:\n";
        $prompt .= "- Include relevant keywords for window treatments\n";
        $prompt .= "- Optimize for each marketplace's search algorithm\n";
        $prompt .= "- Keep within character limits (typically 80-200 chars)\n";
        $prompt .= "- Highlight key features like material, size, style\n";
        $prompt .= "- Use compelling language that drives clicks\n\n";
        $prompt .= "Return as JSON with variant_sku and marketplace_name as keys.";
        
        return json_decode($this->processRequest($prompt, $variantIds), true) ?: [];
    }
    
    /**
     * Analyze data quality and provide recommendations
     */
    public function analyzeDataQuality(): string
    {
        $prompt = "Analyze the data quality of my window shades catalog and provide specific recommendations for improvement. ";
        $prompt .= "Focus on marketplace readiness, missing attributes, and optimization opportunities. ";
        $prompt .= "Provide actionable steps I can take using the bulk operations tools.";
        
        return $this->processRequest($prompt);
    }
    
    /**
     * Suggest missing attributes for products
     */
    public function suggestMissingAttributes(array $productIds = []): string
    {
        $context = [];
        if (!empty($productIds)) {
            $context = $productIds;
        }
        
        $prompt = "Based on my window shades and blinds catalog, suggest important product attributes that might be missing. ";
        $prompt .= "Consider attributes that would be valuable for customers (sizing, materials, functionality) ";
        $prompt .= "and for marketplace optimization (SEO, filtering, categorization). ";
        $prompt .= "Provide specific attribute names and example values.";
        
        return $this->processRequest($prompt, $context);
    }
}