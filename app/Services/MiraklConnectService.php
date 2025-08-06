<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class MiraklConnectService
{
    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;
    private string $audience;
    private string $sellerId;
    private ?string $accessToken = null;

    public function __construct()
    {
        $this->baseUrl = config('services.mirakl.base_url', 'https://miraklconnect.com/api');
        $this->clientId = config('services.mirakl.client_id');
        $this->clientSecret = config('services.mirakl.client_secret');
        $this->audience = config('services.mirakl.audience');
        $this->sellerId = config('services.mirakl.seller_id');
        
        if (empty($this->clientId)) {
            throw new Exception('Mirakl Connect client ID is not configured');
        }
        
        if (empty($this->clientSecret)) {
            throw new Exception('Mirakl Connect client secret is not configured');
        }
        
        if (empty($this->audience)) {
            throw new Exception('Mirakl Connect audience (company ID) is not configured');
        }
        
        if (empty($this->sellerId)) {
            throw new Exception('Mirakl Connect seller ID is not configured');
        }
    }

    /**
     * Get or refresh access token from Mirakl Connect
     */
    private function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $response = Http::asForm()->post('https://auth.mirakl.net/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'audience' => $this->audience
        ]);

        if (!$response->successful()) {
            throw new Exception('Failed to get access token: ' . $response->body());
        }

        $data = $response->json();
        $this->accessToken = $data['access_token'] ?? null;

        if (!$this->accessToken) {
            throw new Exception('No access token received from Mirakl Connect');
        }

        return $this->accessToken;
    }

    /**
     * Get authenticated headers for API requests
     */
    private function getAuthHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->getAccessToken(),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Seller-Id' => $this->sellerId
        ];
    }

    /**
     * Push a single product with all its variants to Mirakl Connect
     */
    public function pushProduct(Product $product): array
    {
        $results = [];
        
        Log::info("Starting Mirakl push for product: {$product->name}", [
            'product_id' => $product->id,
            'variants_count' => $product->variants->count()
        ]);

        foreach ($product->variants as $variant) {
            try {
                $productData = $this->mapProductData($product, $variant);
                $response = $this->sendProductToMirakl($productData);
                
                $results[] = [
                    'variant_id' => $variant->id,
                    'variant_sku' => $variant->sku,
                    'success' => $response->successful(),
                    'status_code' => $response->status(),
                    'response' => $response->json()
                ];

                if ($response->successful()) {
                    Log::info("Successfully pushed variant to Mirakl", [
                        'variant_sku' => $variant->sku,
                        'response' => $response->json()
                    ]);
                } else {
                    Log::error("Failed to push variant to Mirakl", [
                        'variant_sku' => $variant->sku,
                        'status' => $response->status(),
                        'error' => $response->body()
                    ]);
                    
                    // If rate limited, add delay before next request
                    if ($response->status() === 429) {
                        Log::warning("Rate limited by Mirakl, waiting 2 seconds before next request");
                        sleep(2);
                    }
                }

                // Add small delay between requests to avoid rate limiting
                usleep(200000); // 200ms delay

            } catch (Exception $e) {
                Log::error("Exception pushing variant to Mirakl", [
                    'variant_sku' => $variant->sku,
                    'error' => $e->getMessage()
                ]);
                
                $results[] = [
                    'variant_id' => $variant->id,
                    'variant_sku' => $variant->sku,
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Push multiple products to Mirakl Connect
     */
    public function pushProducts($products): array
    {
        $allResults = [];
        
        foreach ($products as $product) {
            $productResults = $this->pushProduct($product);
            $allResults[$product->id] = [
                'product_name' => $product->name,
                'results' => $productResults
            ];
        }

        return $allResults;
    }

    /**
     * Map Laravel product/variant data to Mirakl Connect format
     */
    private function mapProductData(Product $product, ProductVariant $variant): array
    {
        // Get color, width, drop from attributes system
        $color = $variant->attributes()->byKey('color')->first()?->attribute_value;
        $width = $variant->attributes()->byKey('width')->first()?->attribute_value;
        $drop = $variant->attributes()->byKey('drop')->first()?->attribute_value;

        // Get primary barcode
        $primaryBarcode = $variant->barcodes()->where('is_primary', true)->first();

        // Build product data for Mirakl (matching their exact API format)
        $productData = [
            'id' => $variant->sku,
            'titles' => [
                [
                    'locale' => 'en_US',
                    'value' => $this->generateProductTitle($product, $variant, $color, $width, $drop)
                ]
            ],
            'descriptions' => [
                [
                    'locale' => 'en_US', 
                    'value' => $this->generateDescription($product, $variant, $color, $width, $drop)
                ]
            ],
            'quantities' => [
                [
                    'available_quantity' => $variant->stock_level ?? 0
                ]
            ],
            'brand' => 'BLINDS_OUTLET'
        ];

        // Add pricing if available
        $price = $this->getVariantPrice($variant);
        if ($price) {
            $productData['standard_prices'] = [
                [
                    'scope' => null,
                    'price' => [
                        'amount' => $price['amount'],
                        'currency' => $price['currency'] ?? 'GBP'
                    ]
                ]
            ];
        }

        // Add barcode if available  
        if ($primaryBarcode) {
            $productData['gtins'] = [
                [
                    'value' => $primaryBarcode->barcode
                ]
            ];
        }

        // Add images if available
        $images = $this->getVariantImages($variant, $product);
        if (!empty($images)) {
            $productData['images'] = array_map(function($image) {
                return ['url' => $image['url']];
            }, $images);
        }

        // Add attributes (color, width, drop)
        $attributes = [];
        if ($color) {
            $attributes[] = [
                'id' => 'color',
                'name' => 'color', 
                'type' => 'STRING',
                'value' => $color
            ];
        }
        if ($width) {
            // Extract numeric value from width (e.g., "60cm" -> 60)
            $widthValue = (float)preg_replace('/[^0-9.]/', '', $width);
            $attributes[] = [
                'id' => 'width',
                'name' => 'width',
                'type' => 'NUMERIC', 
                'value' => $widthValue
            ];
        }
        if ($drop) {
            // Extract numeric value from drop (e.g., "120cm" -> 120)
            $dropValue = (float)preg_replace('/[^0-9.]/', '', $drop);
            $attributes[] = [
                'id' => 'drop',
                'name' => 'drop',
                'type' => 'NUMERIC',
                'value' => $dropValue  
            ];
        }
        
        if (!empty($attributes)) {
            $productData['attributes'] = $attributes;
        }

        // Remove null values to keep the payload clean
        return $this->removeNullValues($productData);
    }

    /**
     * Send product data to Mirakl Connect API
     */
    private function sendProductToMirakl(array $productData): Response
    {
        // Wrap in products array as Mirakl expects
        $payload = [
            'products' => [$productData]
        ];

        // Debug: Log the title being sent
        Log::info('Sending product to Mirakl', [
            'sku' => $productData['id'],
            'title' => $productData['titles'][0]['value'] ?? 'No title',
            'full_payload' => $payload
        ]);

        return Http::withHeaders($this->getAuthHeaders())
            ->timeout(30)
            ->post('https://miraklconnect.com/api/products', $payload);
    }

    /**
     * Update existing product in Mirakl Connect
     */
    public function updateProduct(ProductVariant $variant): Response
    {
        $productData = $this->mapProductData($variant->product, $variant);
        
        return Http::withHeaders($this->getAuthHeaders())
            ->timeout(30)
            ->put($this->baseUrl . '/products/' . $variant->sku, array_merge($productData, [
                'seller_id' => $this->sellerId
            ]));
    }

    /**
     * Delete product from Mirakl Connect
     */
    public function deleteProduct(string $sku): Response
    {
        return Http::withHeaders($this->getAuthHeaders())
            ->timeout(30)
            ->delete($this->baseUrl . '/products/' . $sku . '?seller_id=' . $this->sellerId);
    }

    /**
     * Get products from Mirakl Connect
     */
    public function getProducts(int $limit = 100, ?string $pageToken = null): Response
    {
        $params = [
            'limit' => $limit,
            'seller_id' => $this->sellerId
        ];
        if ($pageToken) {
            $params['page_token'] = $pageToken;
        }

        return Http::withHeaders($this->getAuthHeaders())
            ->timeout(30)
            ->get('https://miraklconnect.com/api/products', $params);
    }

    /**
     * Map Laravel status to Mirakl status
     */
    private function mapStatus(string $status): string
    {
        return match($status) {
            'active' => 'ACTIVE',
            'inactive' => 'INACTIVE',
            'discontinued' => 'DISCONTINUED',
            default => 'ACTIVE'
        };
    }

    /**
     * Generate a descriptive product title with attributes
     */
    private function generateProductTitle(Product $product, ProductVariant $variant, ?string $color, ?string $width, ?string $drop): string
    {
        $title = $product->name;
        
        // Add color, width, and drop to title for better marketplace visibility
        $attributes = [];
        if ($color) $attributes[] = $color;
        
        // Handle width - check if it already contains 'cm'
        if ($width) {
            if (str_contains($width, 'cm')) {
                $attributes[] = $width; // Already has cm
            } else {
                $attributes[] = $width . 'cm'; // Add cm
            }
        }
        
        // Handle drop - check if it already contains 'cm'  
        if ($drop) {
            if (str_contains($drop, 'cm')) {
                $attributes[] = 'Drop: ' . $drop; // Already has cm
            } else {
                $attributes[] = 'Drop: ' . $drop . 'cm'; // Add cm
            }
        }
        
        if (!empty($attributes)) {
            $title .= ' - ' . implode(' ', $attributes);
        }
        
        return $title;
    }

    /**
     * Generate a descriptive product description
     */
    private function generateDescription(Product $product, ProductVariant $variant, ?string $color, ?string $width, ?string $drop): string
    {
        if ($product->description) {
            return $product->description;
        }

        // Generate description from product name and attributes
        $description = $product->name;
        
        $attributes = [];
        if ($color) $attributes[] = "Color: {$color}";
        if ($width) $attributes[] = "Width: {$width}cm";
        if ($drop) $attributes[] = "Drop: {$drop}cm";
        
        if (!empty($attributes)) {
            $description .= " - " . implode(', ', $attributes);
        }
        
        $description .= ". High-quality blind manufactured to order. SKU: {$variant->sku}";
        
        return $description;
    }

    /**
     * Get variant pricing information
     */
    private function getVariantPrice(ProductVariant $variant): ?array
    {
        // Check if variant has pricing relationships
        $pricing = $variant->pricing()->first();
        
        if ($pricing && $pricing->retail_price > 0) {
            return [
                'amount' => (float)$pricing->retail_price,
                'currency' => $pricing->currency ?? 'GBP'
            ];
        }

        // Fallback: generate a default price based on product name/attributes
        $basePrice = 25.99; // Default base price
        
        // Adjust price based on width if available
        $width = $variant->attributes()->byKey('width')->first()?->attribute_value;
        if ($width) {
            // Extract number from width (e.g., "60cm" -> 60)
            $widthValue = (float)preg_replace('/[^0-9.]/', '', $width);
            if ($widthValue > 0) {
                $basePrice += ($widthValue * 0.15); // Add £0.15 per cm width
            }
        }

        return [
            'amount' => round($basePrice, 2),
            'currency' => 'GBP'
        ];
    }

    /**
     * Get variant images with fallback to product images
     */
    private function getVariantImages(ProductVariant $variant, Product $product): array
    {
        $images = [];

        // Try variant images first
        if ($variant->images && count($variant->images) > 0) {
            foreach ($variant->images as $image) {
                $images[] = [
                    'url' => url(\Storage::url($image)),
                    'alt' => $variant->sku
                ];
            }
        } 
        // Fallback to product images
        elseif ($product->images && count($product->images) > 0) {
            foreach ($product->images as $image) {
                $images[] = [
                    'url' => url(\Storage::url($image)),
                    'alt' => $product->name
                ];
            }
        }

        return $images;
    }

    /**
     * Remove null values from array recursively
     */
    private function removeNullValues(array $data): array
    {
        return array_filter($data, function ($value) {
            if (is_array($value)) {
                return !empty($this->removeNullValues($value));
            }
            return $value !== null && $value !== '';
        });
    }

    /**
     * Test connection to Mirakl Connect
     */
    public function testConnection(): array
    {
        try {
            $response = $this->getProducts(1);
            
            return [
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'message' => $response->successful() 
                    ? 'Successfully connected to Mirakl Connect'
                    : 'Failed to connect to Mirakl Connect',
                'response' => $response->json()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }
}