# Shopify Service Architecture - Usage Examples

## Overview
The refactored Shopify services follow a clean architecture with separation of concerns:
- **Client Layer**: Handles API connections (REST/GraphQL)
- **Repository Layer**: Implements data access patterns
- **Builder Layer**: Provides fluent APIs for data construction
- **Action Layer**: Encapsulates business logic

## Basic Usage

### 1. Using Dependency Injection (Recommended)

```php
use App\Services\Shopify\Repositories\ShopifyProductRepository;

class MyController extends Controller
{
    public function __construct(
        private ShopifyProductRepository $shopifyProducts
    ) {}

    public function syncProduct(Product $product)
    {
        $result = $this->shopifyProducts->push($product);
        
        if ($result['success']) {
            return response()->json([
                'message' => 'Product synced',
                'shopify_id' => $result['product_id']
            ]);
        }
    }
}
```

### 2. Direct Client Usage

```php
use App\Services\Shopify\API\Client\ShopifyClient;

// Create client from environment config
$shopify = ShopifyClient::fromEnv();

// Test connection
$result = $shopify->testConnection();

// Use REST API
$products = $shopify->rest()->getProducts(['limit' => 10]);

// Use GraphQL API
$categories = $shopify->graphql()->getTaxonomyCategories();
```

### 3. Using the Builder Pattern

```php
use App\Services\Shopify\API\Credentials\ShopifyCredentialsBuilder;
use App\Services\Shopify\API\Client\ShopifyClient;

// Build custom credentials
$credentials = ShopifyCredentialsBuilder::create()
    ->storeUrl('my-store.myshopify.com')
    ->accessToken('shpat_xxxxx')
    ->apiVersion('2024-10')
    ->withWebhooks('https://myapp.com/webhooks')
    ->withAutoRetry(3, 1000)
    ->build();

// Create client with custom config
$client = new ShopifyClient($credentials);
```

## Repository Pattern Examples

### Push/Pull Operations

```php
use App\Services\Shopify\Repositories\ShopifyProductRepository;

$repo = app(ShopifyProductRepository::class);

// Push single product
$product = Product::find(1);
$result = $repo->push($product);

// Pull product from Shopify
$product = $repo->pull('shopify_product_id_123');

// Push multiple products
$products = Product::where('status', 'active')->get();
$results = $repo->pushBulk($products);

// Pull all products from Shopify
$products = $repo->pullAll(['vendor' => 'MyBrand']);

// Check if product exists
if ($repo->exists('shopify_product_id_123')) {
    // Product exists in Shopify
}

// Bidirectional sync
$result = $repo->sync($product, 'push'); // or 'pull'
```

## Product Builder Examples

### Building Product Data

```php
use App\Services\Shopify\Builders\Products\ShopifyProductBuilder;

// Build from existing product
$data = ShopifyProductBuilder::forProduct($product)
    ->status('active')
    ->category('gid://shopify/TaxonomyCategory/123')
    ->seoTitle('Amazing Product - Buy Now')
    ->seoDescription('The best product description')
    ->build();

// Build custom product
$data = ShopifyProductBuilder::create()
    ->title('Custom Product')
    ->description('Product description')
    ->vendor('My Brand')
    ->productType('Widget')
    ->variant([
        'sku' => 'WIDGET-001',
        'price' => '29.99',
        'option1' => 'Red',
        'option2' => 'Large',
    ])
    ->variant([
        'sku' => 'WIDGET-002',
        'price' => '29.99',
        'option1' => 'Blue',
        'option2' => 'Large',
    ])
    ->option('Color', ['Red', 'Blue'])
    ->option('Size', ['Large'])
    ->metafield('custom', 'field1', 'value1')
    ->image('https://example.com/image.jpg', 'Product Image')
    ->tags(['new', 'featured', 'sale'])
    ->buildForRest(); // Returns with 'product' wrapper for REST API
```

## Advanced GraphQL Usage

```php
$graphql = $shopify->graphql();

// Custom GraphQL query
$query = '
    query {
        products(first: 10) {
            edges {
                node {
                    id
                    title
                    variants(first: 5) {
                        edges {
                            node {
                                id
                                sku
                                price
                            }
                        }
                    }
                }
            }
        }
    }
';

$result = $graphql->query($query);

// Query with variables
$mutation = '
    mutation CreateProduct($input: ProductInput!) {
        productCreate(input: $input) {
            product {
                id
                title
            }
            userErrors {
                field
                message
            }
        }
    }
';

$variables = [
    'input' => [
        'title' => 'New Product',
        'descriptionHtml' => '<p>Description</p>',
    ]
];

$result = $graphql->query($mutation, $variables);
```

## Fluent API Examples

### Repository with Builder

```php
use App\Services\Shopify\Repositories\ShopifyProductRepository;
use App\Services\Shopify\Builders\Products\ShopifyProductBuilder;

$repo = app(ShopifyProductRepository::class);

// Use custom builder with repository
$builder = ShopifyProductBuilder::forProduct($product)
    ->status('active')
    ->category('gid://shopify/TaxonomyCategory/123');

$repo->withBuilder($builder)->push($product);
```

### Chained Operations

```php
// Get Shopify service
$shopify = app('shopify');

// Chain operations
$result = $shopify
    ->rest()
    ->getProducts(['limit' => 50, 'vendor' => 'MyBrand']);

// Work with products repository
$products = app('shopify.products')
    ->pullAll(['status' => 'active']);
```

## Error Handling

```php
$repo = app(ShopifyProductRepository::class);

$result = $repo->push($product);

if (!$result['success']) {
    // Handle error
    Log::error('Shopify sync failed', [
        'error' => $result['error'],
        'product_id' => $product->id,
    ]);
    
    // User-friendly error message
    session()->flash('error', 'Failed to sync product: ' . $result['error']);
}
```

## Testing

```php
// In tests, you can mock the client
use App\Services\Shopify\API\Client\ShopifyClient;

$this->mock(ShopifyClient::class, function ($mock) {
    $mock->shouldReceive('testConnection')
        ->once()
        ->andReturn(['success' => true]);
});

// Or mock the repository
$this->mock(ShopifyProductRepository::class, function ($mock) {
    $mock->shouldReceive('push')
        ->once()
        ->andReturn(['success' => true, 'product_id' => '123']);
});
```

## Migration from Old Service

The old `ShopifyConnectService` is still supported through backward compatibility:

```php
// Old code still works
$shopify = app(ShopifyConnectService::class);
$result = $shopify->createProduct($productData);

// But prefer new approach
$repo = app(ShopifyProductRepository::class);
$result = $repo->push($product);
```

## Service Provider Registration

The `ShopifyServiceProvider` is automatically registered and provides:
- Singleton instances for optimal performance
- Dependency injection support
- Facade aliases (`shopify`, `shopify.products`)
- Backward compatibility wrapper

## Configuration

Set these in your `.env` file:

```env
SHOPIFY_STORE_URL=your-store.myshopify.com
SHOPIFY_ACCESS_TOKEN=shpat_xxxxxxxxxxxxx
SHOPIFY_API_VERSION=2024-10
SHOPIFY_WEBHOOKS_ENABLED=true
SHOPIFY_WEBHOOK_URL=https://yourapp.com/webhooks/shopify
```