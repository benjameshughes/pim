<?php

namespace App\Services;

use PHPShopify\ShopifySDK;
use Illuminate\Support\Facades\Log;
use Exception;

class ShopifyConnectService
{
    private ShopifySDK $shopify;
    private array $config;

    public function __construct()
    {
        $this->config = [
            'ShopUrl' => config('services.shopify.store_url'),
            'AccessToken' => config('services.shopify.access_token'),
            'ApiVersion' => config('services.shopify.api_version', '2024-07'),
        ];
        
        if (empty($this->config['ShopUrl'])) {
            throw new Exception('Shopify store URL is not configured');
        }
        
        if (empty($this->config['AccessToken'])) {
            throw new Exception('Shopify access token is not configured');
        }

        $this->shopify = new ShopifySDK($this->config);
    }

    /**
     * Create a product in Shopify using the SDK
     */
    public function createProduct(array $productData): array
    {
        Log::info('Creating Shopify product', [
            'title' => $productData['product']['title'] ?? 'Unknown',
            'variants_count' => count($productData['product']['variants'] ?? []),
            'payload' => $productData
        ]);

        try {
            // Use the SDK to create the product
            $product = $this->shopify->Product->post($productData['product']);
            
            return [
                'success' => true,
                'product_id' => $product['id'] ?? null,
                'response' => ['product' => $product]
            ];
        } catch (Exception $e) {
            Log::error('Shopify product creation failed', [
                'error' => $e->getMessage(),
                'payload' => $productData
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Update a product in Shopify using the SDK
     */
    public function updateProduct(int $productId, array $productData): array
    {
        try {
            $product = $this->shopify->Product($productId)->put($productData['product']);
            
            return [
                'success' => true,
                'product_id' => $product['id'] ?? null,
                'response' => ['product' => $product]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete a product from Shopify using the SDK
     */
    public function deleteProduct(int $productId): array
    {
        try {
            $result = $this->shopify->Product($productId)->delete();
            
            return [
                'success' => true,
                'response' => $result
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get products from Shopify using the SDK
     */
    public function getProducts(int $limit = 50, ?string $pageInfo = null): array
    {
        try {
            $params = ['limit' => $limit];
            
            if ($pageInfo) {
                parse_str($pageInfo, $pageParams);
                $params = array_merge($params, $pageParams);
            }

            $products = $this->shopify->Product->get($params);
            
            return [
                'success' => true,
                'data' => ['products' => $products],
                'pagination' => null // SDK handles pagination differently
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get a single product from Shopify using the SDK
     */
    public function getProduct(int $productId): array
    {
        try {
            $product = $this->shopify->Product($productId)->get();
            
            return [
                'success' => true,
                'data' => ['product' => $product]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test connection to Shopify using the SDK
     */
    public function testConnection(): array
    {
        try {
            $shop = $this->shopify->Shop->get();
            
            return [
                'success' => true,
                'message' => 'Successfully connected to Shopify store',
                'response' => ['shop' => $shop]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get Shopify taxonomy categories using GraphQL
     */
    public function getTaxonomyCategories(int $first = 50): array
    {
        $graphQL = <<<Query
query {
  taxonomy {
    categories(first: $first) {
      edges {
        node {
          id
          name
          fullName
          level
          isLeaf
          parentId
          childrenIds
        }
      }
    }
  }
}
Query;

        try {
            $response = $this->shopify->GraphQL->post($graphQL);
            
            return [
                'success' => true,
                'data' => $response['data'] ?? []
            ];
        } catch (Exception $e) {
            Log::error('Failed to get taxonomy categories', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Search taxonomy categories by name using GraphQL
     */
    public function searchTaxonomyCategories(string $query): array
    {
        $graphQL = <<<Query
query {
  taxonomy {
    categories(first: 100, query: "$query") {
      edges {
        node {
          id
          name
          fullName
          level
          isLeaf
          parentId
        }
      }
    }
  }
}
Query;

        try {
            $response = $this->shopify->GraphQL->post($graphQL);
            
            return [
                'success' => true,
                'data' => $response['data'] ?? []
            ];
        } catch (Exception $e) {
            Log::error('Failed to search taxonomy categories', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create metafields for a product using REST API
     */
    public function createProductMetafields(int $productId, array $metafields): array
    {
        try {
            $results = [];
            
            foreach ($metafields as $metafield) {
                $result = $this->shopify->Product($productId)->Metafield->post($metafield);
                $results[] = $result;
            }
            
            return [
                'success' => true,
                'data' => $results
            ];
        } catch (Exception $e) {
            Log::error('Failed to create product metafields', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Update product with category using GraphQL
     */
    public function updateProductWithCategory(string $productGid, string $categoryId): array
    {
        $graphQL = <<<Query
mutation {
  productUpdate(input: {
    id: "$productGid"
    category: "$categoryId"
  }) {
    product {
      id
      title
      category {
        id
        name
        fullName
      }
    }
    userErrors {
      field
      message
    }
  }
}
Query;

        try {
            $response = $this->shopify->GraphQL->post($graphQL);
            
            return [
                'success' => true,
                'data' => $response['data'] ?? []
            ];
        } catch (Exception $e) {
            Log::error('Failed to update product category', [
                'product_gid' => $productGid,
                'category_id' => $categoryId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create product with category and metafields using GraphQL
     */
    public function createProductWithCategoryAndMetafields(array $productInput): array
    {
        // Build the metafields array for GraphQL
        $metafieldsGraphQL = '';
        if (!empty($productInput['metafields'])) {
            $metafields = [];
            foreach ($productInput['metafields'] as $metafield) {
                $metafields[] = '{
                    namespace: "' . $metafield['namespace'] . '"
                    key: "' . $metafield['key'] . '"
                    value: "' . addslashes($metafield['value']) . '"
                    type: "' . $metafield['type'] . '"
                }';
            }
            $metafieldsGraphQL = 'metafields: [' . implode(',', $metafields) . ']';
        }

        // Build category input
        $categoryInput = !empty($productInput['category']) ? 'category: "' . $productInput['category'] . '"' : '';

        // Build variants array
        $variantsGraphQL = '';
        if (!empty($productInput['variants'])) {
            $variants = [];
            foreach ($productInput['variants'] as $variant) {
                $variantFields = [];
                $variantFields[] = 'sku: "' . addslashes($variant['sku']) . '"';
                $variantFields[] = 'price: "' . $variant['price'] . '"';
                
                if (!empty($variant['barcode'])) {
                    $variantFields[] = 'barcode: "' . $variant['barcode'] . '"';
                }
                
                if (!empty($variant['inventory_quantity'])) {
                    $variantFields[] = 'inventoryQuantities: [{availableQuantity: ' . $variant['inventory_quantity'] . ', locationId: "gid://shopify/Location/1"}]';
                }

                // Add option values
                for ($i = 1; $i <= 3; $i++) {
                    if (!empty($variant["option{$i}"])) {
                        $variantFields[] = "option{$i}: \"" . addslashes($variant["option{$i}"]) . '"';
                    }
                }

                $variants[] = '{' . implode(', ', $variantFields) . '}';
            }
            $variantsGraphQL = 'variants: [' . implode(',', $variants) . ']';
        }

        // Build options array with proper GraphQL structure
        $optionsGraphQL = '';
        if (!empty($productInput['options'])) {
            $options = [];
            foreach ($productInput['options'] as $option) {
                $values = array_map(function($value) {
                    return '{name: "' . addslashes($value) . '"}';
                }, $option['values']);
                $options[] = '{
                    name: "' . addslashes($option['name']) . '"
                    values: [' . implode(',', $values) . ']
                }';
            }
            $optionsGraphQL = 'productOptions: [' . implode(',', $options) . ']';
        }

        $graphQL = <<<Query
mutation {
  productCreate(input: {
    title: "{$productInput['title']}"
    descriptionHtml: "{$productInput['body_html']}"
    vendor: "{$productInput['vendor']}"
    productType: "{$productInput['product_type']}"
    status: ACTIVE
    $categoryInput
    $optionsGraphQL
    $metafieldsGraphQL
  }) {
    product {
      id
      title
      handle
      category {
        id
        name
        fullName
      }
      metafields(first: 10) {
        edges {
          node {
            id
            namespace
            key
            value
            type
          }
        }
      }
    }
    userErrors {
      field
      message
    }
  }
}
Query;

        try {
            Log::info('Creating product with category and metafields via GraphQL', [
                'title' => $productInput['title'],
                'category' => $productInput['category'] ?? null,
                'metafields_count' => count($productInput['metafields'] ?? [])
            ]);

            $response = $this->shopify->GraphQL->post($graphQL);
            
            return [
                'success' => true,
                'data' => $response['data'] ?? []
            ];
        } catch (Exception $e) {
            Log::error('Failed to create product with category and metafields', [
                'error' => $e->getMessage(),
                'graphql' => $graphQL
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}