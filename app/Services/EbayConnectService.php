<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class EbayConnectService
{
    private Client $client;
    private array $config;
    private string $environment;
    private string $baseUrl;

    public function __construct()
    {
        $this->environment = config('services.ebay.environment', 'SANDBOX');
        $this->config = [
            'client_id' => config('services.ebay.client_id'),
            'client_secret' => config('services.ebay.client_secret'),
            'redirect_uri' => config('services.ebay.redirect_uri'),
            'dev_id' => config('services.ebay.dev_id'),
        ];
        
        $this->baseUrl = $this->environment === 'PRODUCTION' 
            ? 'https://api.ebay.com' 
            : 'https://api.sandbox.ebay.com';

        // Only throw exception when actually trying to make API calls
        // This allows the service to be instantiated for testing structure

        $this->client = new Client([
            'timeout' => 30,
            'verify' => true,
        ]);
    }

    /**
     * Get application access token using client credentials
     */
    public function getApplicationToken(): array
    {
        if (empty($this->config['client_id']) || empty($this->config['client_secret'])) {
            return [
                'success' => false,
                'error' => 'eBay client credentials are not configured. Please set EBAY_CLIENT_ID and EBAY_CLIENT_SECRET in your .env file.',
            ];
        }

        $cacheKey = "ebay_app_token_{$this->environment}";
        
        return Cache::remember($cacheKey, now()->addMinutes(50), function () {
            try {
                $response = $this->client->post("{$this->baseUrl}/identity/v1/oauth2/token", [
                    'headers' => [
                        'Content-Type' => 'application/x-www-form-urlencoded',
                        'Authorization' => 'Basic ' . base64_encode($this->config['client_id'] . ':' . $this->config['client_secret']),
                    ],
                    'form_params' => [
                        'grant_type' => 'client_credentials',
                        'scope' => 'https://api.ebay.com/oauth/api_scope/sell.inventory https://api.ebay.com/oauth/api_scope/sell.inventory.readonly',
                    ],
                ]);

                $data = json_decode($response->getBody()->getContents(), true);
                
                return [
                    'success' => true,
                    'access_token' => $data['access_token'],
                    'token_type' => $data['token_type'],
                    'expires_in' => $data['expires_in'],
                ];
                
            } catch (RequestException $e) {
                Log::error('eBay application token request failed', [
                    'error' => $e->getMessage(),
                    'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null,
                ]);
                
                return [
                    'success' => false,
                    'error' => 'Failed to get application token: ' . $e->getMessage(),
                ];
            }
        });
    }

    /**
     * Create an inventory item in eBay
     */
    public function createInventoryItem(string $sku, array $inventoryData): array
    {
        $tokenResult = $this->getApplicationToken();
        
        if (!$tokenResult['success']) {
            return $tokenResult;
        }

        try {
            Log::info('Creating eBay inventory item', [
                'sku' => $sku,
                'product_title' => $inventoryData['product']['title'] ?? 'Unknown',
            ]);

            $response = $this->client->put("{$this->baseUrl}/sell/inventory/v1/inventory_item/{$sku}", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $tokenResult['access_token'],
                    'Content-Type' => 'application/json',
                    'Content-Language' => 'en-US',
                ],
                'json' => $inventoryData,
            ]);

            $responseCode = $response->getStatusCode();
            
            return [
                'success' => in_array($responseCode, [200, 201, 204]),
                'sku' => $sku,
                'status_code' => $responseCode,
                'response' => json_decode($response->getBody()->getContents(), true),
            ];
            
        } catch (RequestException $e) {
            Log::error('eBay inventory item creation failed', [
                'sku' => $sku,
                'error' => $e->getMessage(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null,
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'response' => $e->hasResponse() ? json_decode($e->getResponse()->getBody()->getContents(), true) : null,
            ];
        }
    }

    /**
     * Get an inventory item from eBay
     */
    public function getInventoryItem(string $sku): array
    {
        $tokenResult = $this->getApplicationToken();
        
        if (!$tokenResult['success']) {
            return $tokenResult;
        }

        try {
            $response = $this->client->get("{$this->baseUrl}/sell/inventory/v1/inventory_item/{$sku}", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $tokenResult['access_token'],
                    'Content-Language' => 'en-US',
                ],
            ]);

            return [
                'success' => true,
                'data' => json_decode($response->getBody()->getContents(), true),
            ];
            
        } catch (RequestException $e) {
            if ($e->hasResponse() && $e->getResponse()->getStatusCode() === 404) {
                return [
                    'success' => false,
                    'error' => 'Inventory item not found',
                    'not_found' => true,
                ];
            }

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create an offer for an inventory item
     */
    public function createOffer(array $offerData): array
    {
        $tokenResult = $this->getApplicationToken();
        
        if (!$tokenResult['success']) {
            return $tokenResult;
        }

        try {
            Log::info('Creating eBay offer', [
                'sku' => $offerData['sku'] ?? 'Unknown',
                'marketplace_id' => $offerData['marketplaceId'] ?? 'Unknown',
            ]);

            $response = $this->client->post("{$this->baseUrl}/sell/inventory/v1/offer", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $tokenResult['access_token'],
                    'Content-Type' => 'application/json',
                    'Content-Language' => 'en-US',
                ],
                'json' => $offerData,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            return [
                'success' => true,
                'offer_id' => $data['offerId'] ?? null,
                'response' => $data,
            ];
            
        } catch (RequestException $e) {
            Log::error('eBay offer creation failed', [
                'error' => $e->getMessage(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null,
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'response' => $e->hasResponse() ? json_decode($e->getResponse()->getBody()->getContents(), true) : null,
            ];
        }
    }

    /**
     * Publish an offer to eBay marketplace
     */
    public function publishOffer(string $offerId): array
    {
        $tokenResult = $this->getApplicationToken();
        
        if (!$tokenResult['success']) {
            return $tokenResult;
        }

        try {
            Log::info('Publishing eBay offer', ['offer_id' => $offerId]);

            $response = $this->client->post("{$this->baseUrl}/sell/inventory/v1/offer/{$offerId}/publish", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $tokenResult['access_token'],
                    'Content-Type' => 'application/json',
                    'Content-Language' => 'en-US',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            return [
                'success' => true,
                'listing_id' => $data['listingId'] ?? null,
                'response' => $data,
            ];
            
        } catch (RequestException $e) {
            Log::error('eBay offer publishing failed', [
                'offer_id' => $offerId,
                'error' => $e->getMessage(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null,
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'response' => $e->hasResponse() ? json_decode($e->getResponse()->getBody()->getContents(), true) : null,
            ];
        }
    }

    /**
     * Get inventory locations
     */
    public function getInventoryLocations(): array
    {
        $tokenResult = $this->getApplicationToken();
        
        if (!$tokenResult['success']) {
            return $tokenResult;
        }

        try {
            $response = $this->client->get("{$this->baseUrl}/sell/inventory/v1/location", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $tokenResult['access_token'],
                    'Content-Language' => 'en-US',
                ],
            ]);

            return [
                'success' => true,
                'data' => json_decode($response->getBody()->getContents(), true),
            ];
            
        } catch (RequestException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test connection to eBay API
     */
    public function testConnection(): array
    {
        try {
            $tokenResult = $this->getApplicationToken();
            
            if (!$tokenResult['success']) {
                return [
                    'success' => false,
                    'message' => 'Failed to authenticate with eBay API',
                    'error' => $tokenResult['error'],
                ];
            }

            // Test by getting locations (lightweight call)
            $locationsResult = $this->getInventoryLocations();
            
            if ($locationsResult['success']) {
                return [
                    'success' => true,
                    'message' => 'Successfully connected to eBay API',
                    'environment' => $this->environment,
                    'response' => $locationsResult['data'],
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Connection failed: ' . $locationsResult['error'],
                    'error' => $locationsResult['error'],
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate eBay inventory item data from product variant
     */
    public function buildInventoryItemData(array $productData): array
    {
        return [
            'availability' => [
                'shipToLocationAvailability' => [
                    'quantity' => $productData['inventory_quantity'] ?? 0,
                ],
            ],
            'condition' => 'NEW',
            'product' => [
                'title' => $productData['title'],
                'description' => $productData['description'] ?? '',
                'imageUrls' => $productData['images'] ?? [],
                'aspects' => $this->buildProductAspects($productData),
            ],
            'packageWeightAndSize' => $this->buildPackageInfo($productData),
        ];
    }

    /**
     * Generate eBay offer data
     */
    public function buildOfferData(string $sku, array $productData, string $marketplaceId = 'EBAY_US'): array
    {
        return [
            'sku' => $sku,
            'marketplaceId' => $marketplaceId,
            'format' => 'FIXED_PRICE',
            'availableQuantity' => $productData['inventory_quantity'] ?? 0,
            'categoryId' => $productData['category_id'] ?? '11700', // Default to Home & Garden
            'pricingSummary' => [
                'price' => [
                    'currency' => 'USD',
                    'value' => $productData['price'] ?? '0.00',
                ],
            ],
            'listingDescription' => $productData['description'] ?? '',
            'listingPolicies' => [
                'fulfillmentPolicyId' => config('services.ebay.fulfillment_policy_id'),
                'paymentPolicyId' => config('services.ebay.payment_policy_id'),
                'returnPolicyId' => config('services.ebay.return_policy_id'),
            ],
            'merchantLocationKey' => config('services.ebay.location_key', 'default_location'),
        ];
    }

    /**
     * Build product aspects from attributes
     */
    private function buildProductAspects(array $productData): array
    {
        $aspects = [];
        
        if (!empty($productData['brand'])) {
            $aspects['Brand'] = [$productData['brand']];
        }
        
        if (!empty($productData['color'])) {
            $aspects['Color'] = [$productData['color']];
        }
        
        if (!empty($productData['material'])) {
            $aspects['Material'] = [$productData['material']];
        }

        // Add custom aspects from attributes
        if (!empty($productData['attributes'])) {
            foreach ($productData['attributes'] as $key => $value) {
                if (!empty($value) && !in_array($key, ['brand', 'color', 'material'])) {
                    $aspects[ucfirst($key)] = is_array($value) ? $value : [$value];
                }
            }
        }
        
        return $aspects;
    }

    /**
     * Build package information
     */
    private function buildPackageInfo(array $productData): ?array
    {
        $package = [];
        
        if (!empty($productData['weight'])) {
            $package['weight'] = [
                'unit' => 'POUND',
                'value' => $productData['weight'],
            ];
        }
        
        if (!empty($productData['length']) || !empty($productData['width']) || !empty($productData['height'])) {
            $package['dimensions'] = [
                'unit' => 'INCH',
                'length' => $productData['length'] ?? 0,
                'width' => $productData['width'] ?? 0,
                'height' => $productData['height'] ?? 0,
            ];
        }
        
        return !empty($package) ? $package : null;
    }
}