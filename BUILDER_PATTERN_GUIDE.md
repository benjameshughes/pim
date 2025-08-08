# Builder Pattern + Actions Pattern Implementation Guide

This document provides a comprehensive guide for using the new Builder Pattern + Actions Pattern implementation for product management in the PIM system.

## 📋 Table of Contents
- [Overview](#overview)
- [Architecture](#architecture)
- [Basic Usage](#basic-usage)
- [Advanced Usage](#advanced-usage)
- [Available Methods](#available-methods)
- [Best Practices](#best-practices)
- [Examples](#examples)
- [Testing](#testing)

## 🔍 Overview

This implementation provides a clean, fluent API for creating and updating products using two complementary patterns:

- **Builder Pattern**: Provides a readable, fluent interface for constructing complex objects
- **Actions Pattern**: Encapsulates business logic into single-responsibility classes

### Key Benefits
- ✅ **Readable Code**: Fluent API reads like natural language
- ✅ **Type Safety**: Full PHP 8.2+ type hints and validation
- ✅ **Single Responsibility**: Each action does one thing well
- ✅ **Transaction Safety**: All operations wrapped in database transactions
- ✅ **Extensible**: Easy to add new functionality
- ✅ **Testable**: Clean separation makes testing straightforward

## 🏗️ Architecture

### Folder Structure
```
app/
├── Actions/
│   ├── Base/
│   │   └── BaseAction.php              # Abstract base for all actions
│   ├── Products/
│   │   ├── CreateProductAction.php     # Product creation logic
│   │   ├── UpdateProductAction.php     # Product update logic
│   │   └── DeleteProductAction.php     # Product deletion logic
│   └── Variants/
│       └── CreateVariantAction.php     # Variant creation logic
├── Builders/
│   ├── Base/
│   │   └── BaseBuilder.php             # Abstract base for all builders
│   ├── Products/
│   │   └── ProductBuilder.php          # Fluent product API
│   └── Variants/
│       └── VariantBuilder.php          # Fluent variant API
├── Http/Controllers/Products/
│   └── ProductController.php           # Clean RESTful controller
├── Livewire/Products/
│   ├── ProductIndex.php                # Product listing component
│   ├── ProductCreate.php               # Product creation component
│   ├── ProductEdit.php                 # Product editing component
│   └── ProductShow.php                 # Product detail component
└── Models/
    ├── Product.php                     # Enhanced model with scopes
    └── ProductVariant.php              # Variant model
```

### Pattern Flow
```
Controller/Livewire → Builder → Action → Model → Database
                        ↓         ↓
                   Validation  Business Logic
```

## 🚀 Basic Usage

### Creating a Product

#### Simple Creation
```php
use App\Builders\Products\ProductBuilder;

$product = ProductBuilder::create()
    ->name('Amazing Widget')
    ->sku('WIDGET001')
    ->description('The most amazing widget ever created')
    ->active()
    ->execute();
```

#### Using Model Helper
```php
use App\Models\Product;

$product = Product::build()
    ->name('Another Widget')
    ->sku('WIDGET002')
    ->draft()
    ->execute();
```

### Updating a Product

```php
$product = ProductBuilder::update($product)
    ->name('Updated Widget Name')
    ->description('Updated description')
    ->active()
    ->execute();

// Or using model helper
$product = $product->edit()
    ->name('Updated via Model')
    ->execute();
```

### Creating Variants

```php
use App\Builders\Variants\VariantBuilder;

$variant = VariantBuilder::for($product)
    ->sku('WIDGET001-RED-LARGE')
    ->color('Red')
    ->width('Large')
    ->stockLevel(100)
    ->retailPrice(29.99)
    ->primaryBarcode('1234567890123')
    ->execute();
```

## 🎯 Advanced Usage

### Complex Product Creation
```php
$product = ProductBuilder::create()
    ->name('Professional Widget Set')
    ->sku('WIDGETPRO001')
    ->description('Professional grade widget for business use')
    ->active()
    ->features([
        'Durable construction',
        'Weather resistant',
        'Easy installation',
        '5-year warranty'
    ])
    ->details([
        'Made from premium materials',
        'Tested for 10,000 hours',
        'Meets industry standards',
        'Available in multiple colors'
    ])
    ->categories([
        1 => ['is_primary' => true],   // Primary category
        2 => ['is_primary' => false],  // Secondary category
    ])
    ->attributes([
        'material' => 'Aluminum',
        'weight' => '2.5kg',
        'warranty_years' => 5
    ])
    ->execute();
```

### Batch Variant Creation
```php
$colors = ['Red', 'Blue', 'Green', 'Yellow'];
$sizes = ['Small', 'Medium', 'Large'];

foreach ($colors as $color) {
    foreach ($sizes as $size) {
        VariantBuilder::for($product)
            ->sku("WIDGET001-{$color}-{$size}")
            ->color($color)
            ->width($size)
            ->stockLevel(50)
            ->retailPrice(19.99)
            ->dimensions(10.0, 5.0, 3.0, 0.2)
            ->execute();
    }
}
```

### Using in Controllers
```php
class ProductController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'sku' => 'required|string|unique:products,parent_sku',
            'status' => 'required|in:draft,active',
        ]);
        
        try {
            $product = ProductBuilder::create()
                ->name($validated['name'])
                ->sku($validated['sku'])
                ->status($validated['status'])
                ->execute();
                
            return redirect()->route('products.show', $product)
                ->with('success', 'Product created successfully');
                
        } catch (\Exception $e) {
            return back()
                ->withErrors(['error' => $e->getMessage()])
                ->withInput();
        }
    }
}
```

### Using in Livewire Components
```php
class ProductCreate extends Component
{
    public string $name = '';
    public string $sku = '';
    public string $status = 'draft';
    
    public function save()
    {
        $this->validate([
            'name' => 'required|string',
            'sku' => 'required|string|unique:products,parent_sku',
        ]);
        
        $product = ProductBuilder::create()
            ->name($this->name)
            ->sku($this->sku)
            ->status($this->status)
            ->execute();
            
        $this->redirect(route('products.show', $product));
    }
}
```

## 📚 Available Methods

### ProductBuilder Methods

#### Basic Information
- `name(string $name)` - Set product name
- `slug(string $slug)` - Set URL slug
- `sku(string $sku)` - Set parent SKU
- `parentSku(string $sku)` - Alias for sku()
- `description(?string $description)` - Set description

#### Status Management
- `status(string $status)` - Set status (draft/active/inactive/archived)
- `draft()` - Set status to draft
- `active()` - Set status to active
- `inactive()` - Set status to inactive
- `archived()` - Set status to archived

#### Content
- `features(array $features)` - Set product features (max 5)
- `details(array $details)` - Set product details (max 5)
- `images(array $images)` - Set product images

#### Relationships
- `categories(array $categories)` - Set product categories
- `attributes(array $attributes)` - Set product attributes
- `metadata(array $metadata)` - Set product metadata

#### Flags
- `autoGenerated(bool $generated = true)` - Mark as auto-generated

#### Execution
- `execute()` - Create or update the product
- `save()` - Alias for execute()

### VariantBuilder Methods

#### Basic Information
- `product(Product $product)` - Set parent product
- `productId(int $productId)` - Set parent product by ID
- `sku(string $sku)` - Set variant SKU

#### Status and Inventory
- `status(string $status)` - Set status
- `draft()` / `active()` / `inactive()` - Status shortcuts
- `stockLevel(int $level)` - Set stock level

#### Physical Properties
- `dimensions(float $length, float $width, float $height, ?float $weight)` - Set package dimensions
- `weight(float $weight)` - Set weight only
- `images(array $images)` - Set variant images

#### Attributes
- `attributes(array $attributes)` - Set all attributes
- `color(string $color)` - Set color attribute
- `width(string $width)` - Set width attribute  
- `drop(string $drop)` - Set drop attribute

#### Pricing and Barcodes
- `pricing(float $retail, ?float $cost, ?float $sale, string $currency)` - Set pricing
- `retailPrice(float $price)` - Set retail price only
- `barcode(string $barcode, string $type, bool $isPrimary)` - Add barcode
- `primaryBarcode(string $barcode, string $type)` - Add primary barcode

### Model Enhancement Methods

#### Builder Helpers
- `Product::build()` - Create new ProductBuilder
- `$product->edit()` - Create ProductBuilder for updates

#### Status Checks
- `$product->isActive()` - Check if active
- `$product->isDraft()` - Check if draft
- `$product->isInactive()` - Check if inactive
- `$product->isArchived()` - Check if archived

#### Data Access
- `$product->getFeaturesArray()` - Get features as array
- `$product->getDetailsArray()` - Get details as array
- `$product->hasFeatures()` - Check if has features
- `$product->hasDetails()` - Check if has details

#### Query Scopes
- `Product::active()` - Get active products
- `Product::draft()` - Get draft products
- `Product::search($term)` - Search products
- `Product::withVariants()` - Products with variants
- `Product::autoGenerated()` - Auto-generated products

## ✅ Best Practices

### 1. Use Builders for Complex Operations
```php
// Good - Readable and maintainable
$product = ProductBuilder::create()
    ->name('Widget')
    ->sku('W001')
    ->active()
    ->features(['Durable', 'Lightweight'])
    ->execute();

// Avoid - Hard to read and error-prone
$product = Product::create([
    'name' => 'Widget',
    'parent_sku' => 'W001',
    'status' => 'active',
    'product_features_1' => 'Durable',
    'product_features_2' => 'Lightweight',
]);
```

### 2. Handle Exceptions Gracefully
```php
try {
    $product = ProductBuilder::create()
        ->name($name)
        ->sku($sku)
        ->execute();
} catch (\InvalidArgumentException $e) {
    // Handle validation errors
    return back()->withErrors(['validation' => $e->getMessage()]);
} catch (\Exception $e) {
    // Handle unexpected errors
    Log::error('Product creation failed', ['error' => $e->getMessage()]);
    return back()->withErrors(['error' => 'Something went wrong']);
}
```

### 3. Use Type-Safe Status Constants
```php
// Good - Type safe and IDE friendly
ProductBuilder::create()
    ->name('Widget')
    ->status(Product::STATUS_ACTIVE)
    ->execute();

// Or use convenience methods
ProductBuilder::create()
    ->name('Widget')
    ->active()
    ->execute();
```

### 4. Leverage Model Scopes
```php
// Efficient querying with scopes
$activeProducts = Product::active()
    ->withVariants()
    ->withCommonRelations()
    ->paginate(15);

$searchResults = Product::search($query)
    ->published()
    ->orderBy('name')
    ->get();
```

### 5. Use Actions Directly for Simple Operations
```php
// For simple operations, use actions directly
use App\Actions\Products\DeleteProductAction;

$deleteAction = new DeleteProductAction();
$deleteAction->execute($product);
```

## 🧪 Testing

### Testing Builders
```php
class ProductBuilderTest extends TestCase
{
    public function test_can_create_product_with_builder()
    {
        $product = ProductBuilder::create()
            ->name('Test Product')
            ->sku('TEST001')
            ->active()
            ->execute();
            
        $this->assertDatabaseHas('products', [
            'name' => 'Test Product',
            'parent_sku' => 'TEST001',
            'status' => 'active',
        ]);
        
        $this->assertTrue($product->isActive());
    }
    
    public function test_builder_validates_required_fields()
    {
        $this->expectException(\InvalidArgumentException::class);
        
        ProductBuilder::create()
            ->sku('TEST001')  // Missing name
            ->execute();
    }
}
```

### Testing Actions
```php
class CreateProductActionTest extends TestCase
{
    public function test_action_creates_product()
    {
        $action = new CreateProductAction();
        
        $product = $action->execute([
            'name' => 'Test Product',
            'parent_sku' => 'TEST001',
            'status' => 'active',
        ]);
        
        $this->assertInstanceOf(Product::class, $product);
        $this->assertEquals('Test Product', $product->name);
    }
}
```

## 🎯 Usage Examples

### E-commerce Product Setup
```php
// Create main product
$product = ProductBuilder::create()
    ->name('Premium Athletic Shoes')
    ->sku('SHOES001')
    ->description('High-performance athletic shoes for serious athletes')
    ->active()
    ->features([
        'Breathable mesh upper',
        'Responsive foam midsole',
        'Durable rubber outsole',
        'Lightweight design'
    ])
    ->details([
        'Weight: 280g',
        'Drop: 10mm',
        'Stack height: 32mm/22mm',
        'Made from recycled materials'
    ])
    ->execute();

// Create variants for different colors and sizes
$colors = ['Black', 'White', 'Red', 'Blue'];
$sizes = ['7', '8', '9', '10', '11', '12'];

foreach ($colors as $color) {
    foreach ($sizes as $size) {
        VariantBuilder::for($product)
            ->sku("SHOES001-{$color}-{$size}")
            ->color($color)
            ->attributes(['size' => $size])
            ->stockLevel(25)
            ->retailPrice(149.99)
            ->primaryBarcode($this->generateEAN13())
            ->execute();
    }
}
```

### Bulk Import Integration
```php
class ProductImportService
{
    public function importFromCsv(string $filePath): int
    {
        $imported = 0;
        $rows = $this->parseCsv($filePath);
        
        foreach ($rows as $row) {
            try {
                $builder = ProductBuilder::create()
                    ->name($row['name'])
                    ->sku($row['sku'])
                    ->description($row['description'])
                    ->status($row['status'] ?? 'draft');
                
                if (!empty($row['features'])) {
                    $builder->features(explode('|', $row['features']));
                }
                
                $product = $builder->execute();
                $imported++;
                
            } catch (\Exception $e) {
                Log::warning("Failed to import product: {$row['name']}", [
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $imported;
    }
}
```

This implementation provides a solid foundation for scalable product management with clean, readable code that follows Laravel best practices and modern PHP patterns.