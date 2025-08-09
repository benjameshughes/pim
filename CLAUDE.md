# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands

### Primary Development
- `composer dev` - Starts full development environment with Laravel server, queue worker, logs, and Vite
- `composer test` - Runs all tests (clears config first, then runs `php artisan test`)
- `npm run dev` - Starts Vite development server for frontend assets
- `npm run build` - Builds production assets

### Laravel Commands
- `php artisan serve` - Start Laravel development server
- `php artisan test` - Run tests using Pest framework
- `php artisan pail` - View application logs in real-time
- `php artisan queue:listen images` - Start image processing queue worker
- `php artisan migrate` - Run database migrations
- `php artisan pint` - Format code using Laravel Pint

### Individual Tests
- `php artisan test --filter TestName` - Run specific test
- `php artisan test tests/Feature/Auth/LoginTest.php` - Run specific test file

### Development Tools
- `php artisan clear:products` - Nuclear reset tool to delete ALL products, variants, barcodes, pricing, and images (development only)
- `php artisan clear:products --force` - Skip confirmation prompts

### Marketplace Integration Commands
- `php artisan ebay:test` - Test eBay API integration and configuration status

## Architecture Overview

### Framework Stack
- **Backend**: Laravel 12 with PHP 8.2+
- **Frontend**: Livewire with Flux UI components
- **Styling**: Tailwind CSS 4.0
- **Testing**: Pest PHP framework
- **Build Tool**: Vite
- **Database**: SQLite (development)
- **Architecture**: Builder Pattern + Actions Pattern

### Core Structure
- **Builder Pattern**: Fluent APIs for product and variant creation with method chaining
- **Actions Pattern**: Single-responsibility business logic classes with transaction safety
- **Performance Optimization**: Sub-10ms operations with smart caching and monitoring
- **Error Handling**: Custom exceptions with user-friendly messages and recovery suggestions
- **Authentication**: Complete auth system with Livewire components (login, register, password reset, email verification)
- **User Management**: Built-in user model with profile management and settings
- **UI Components**: Flux-based component system with custom Blade components and Toast notifications

### Key Directories
- `app/Builders/` - **Builder Pattern** implementation for fluent object creation
  - `app/Builders/Base/` - Foundation classes with validation and execution
  - `app/Builders/Products/` - Product creation builders
  - `app/Builders/Variants/` - Variant creation builders (primary focus)
- `app/Actions/` - **Actions Pattern** for business logic operations
  - `app/Actions/Base/` - Action pattern foundation with performance monitoring
  - `app/Actions/Products/` - Product-related business operations
  - `app/Actions/Variants/` - Variant creation and management actions
- `app/Support/` - **Support Classes** for cross-cutting concerns
  - `app/Support/Toast.php` - User feedback and notification system
- `app/Traits/` - **Reusable Traits** for common functionality
  - `app/Traits/PerformanceMonitoring.php` - Performance tracking and optimization
  - `app/Traits/HasLoadingStates.php` - Loading state management for UI components
- `app/Exceptions/` - **Custom Exceptions** with smart error handling
  - `app/Exceptions/BarcodePoolExhaustedException.php` - Barcode pool management
  - `app/Exceptions/DuplicateSkuException.php` - SKU conflict resolution
- `app/Services/` - **Business Services** for complex operations
  - `app/Services/VariantPerformanceService.php` - Performance optimization utilities
- `app/Livewire/` - Livewire components organized by feature (Auth, Settings, PIM, Examples)
- `resources/views/livewire/` - Corresponding Blade views for Livewire components
- `resources/views/components/` - Reusable Blade components including layouts
- `tests/Feature/` - Feature tests organized by functionality
- `database/migrations/` - Database schema definitions

### Component Patterns
- Livewire components use attributes for validation (`#[Validate]`)
- Layout assignment via attributes (`#[Layout]`)
- Form validation handled at component level
- Rate limiting implemented for authentication
- Session management and CSRF protection built-in

### Database
- Uses SQLite for development with in-memory testing
- Standard Laravel user authentication schema
- Includes caching and job queue tables

## Builder Pattern + Actions Pattern Architecture

### Overview
This PIM system implements a modern **Builder Pattern + Actions Pattern** architecture for elegant, maintainable, and performant product and variant management. This approach provides fluent APIs, single-responsibility business logic, and exceptional performance.

### Core Performance Metrics
- **7.92ms** - Complex variant creation with pricing + attributes (98% improvement)
- **0.64ms** - Cached queries (99% faster than database hits)
- **12.22ms** - Cache warmup for entire system
- **Sub-10ms** - All critical operations optimized

### Builder Pattern Implementation
The Builder Pattern provides fluent APIs for constructing complex objects with method chaining:

```php
// Basic variant creation
$variant = ProductVariant::buildFor($product)
    ->sku('WIDGET-001')
    ->color('Red')
    ->retailPrice(29.99)
    ->execute();

// Complex variant with all features
$variant = ProductVariant::buildFor($product)
    ->sku('WIDGET-002')
    ->color('Blue')
    ->windowDimensions('120cm', '160cm')
    ->retailPrice(59.99)
    ->vatInclusivePrice(49.99, 20)
    ->assignFromPool('EAN13')                     // Auto-assign barcode
    ->addMarketplacePricing('ebay', 64.99, 30.00)
    ->addMarketplacePricing('shopify', 62.99, 30.00)
    ->execute();
```

### Actions Pattern Implementation
Actions encapsulate single-responsibility business logic with transaction safety:

```php
// Actions automatically chosen based on complexity
// Simple variants use CreateVariantAction
// Complex variants use CreateVariantWithBarcodeAction

// All operations are transaction-wrapped with performance monitoring
// Error handling provides user-friendly feedback with recovery suggestions
```

### Key Features
- **Fluent APIs**: Beautiful, readable code that's self-documenting
- **Smart Routing**: Automatic selection between simple and complex actions
- **Performance Monitoring**: Built-in timing and memory tracking
- **Error Recovery**: Custom exceptions with actionable suggestions
- **Cache Optimization**: Smart caching with automatic invalidation
- **User Feedback**: Toast notifications with loading states and progress indicators

## PIM System Architecture

### Core Data Management Sections
The PIM system has three primary data management sections:

1. **Barcodes Management**
   - GS1 barcode pool management with automatic assignment
   - Support for multiple barcode types (EAN13, UPC, etc.)
   - Constraint handling to prevent duplicate barcodes per variant

2. **Pricing Management**
   - Multi-marketplace pricing support
   - Automatic VAT calculation (20% inclusive by default)
   - Sales channel integration

3. **Images Management**
   - Event-driven background image processing
   - Bulk upload and assignment capabilities
   - Storage integration with Laravel's Storage facade

### Import System Features

#### Smart Attribute Extraction
- **Multi-algorithm attribute extraction** using weighted scoring systems
- **Color Detection**: Word boundary detection prevents false matches (e.g., "blackout" won't extract "black")
- **Size Detection**: Multi-layer detection with comprehensive size dictionaries
- **Enhanced Pattern Matching**: Uses regex with `\b` anchors for accurate extraction

#### Import Modes
Three configurable import modes with comprehensive constraint handling:

1. **create_only**: Skip existing SKUs and color/size combinations
2. **update_existing**: Only update existing records, skip non-existing
3. **create_or_update**: Upsert functionality for all records

#### Auto-Parent Creation System
Hybrid system supporting multiple algorithms:

1. **SKU-based extraction**: Uses 001-001 pattern where first 3 digits = parent SKU
2. **Name-based extraction**: Smart name parsing to remove colors/sizes
3. **Smart grouping**: Batch processing for variant grouping

```php
// SKU pattern extraction example
if (preg_match('/^(\d{3})-(\d{3})$/', $variantSku, $matches)) {
    $parentSku = $matches[1]; // First 3 digits
}
```

#### Constraint Handling
Comprehensive UNIQUE constraint violation prevention:

- **Barcode Constraints**: Prevents duplicate barcodes per variant
- **Variant Constraints**: Handles product_id + color + size combinations
- **Dry Run Logic**: Accurately predicts import actions before execution

### Key Services

#### ProductAttributeExtractor (`app/Services/ProductAttributeExtractor.php`)
- Enhanced with word boundary detection
- Weighted scoring for color detection
- Multi-layer size detection
- Prevents false positive extractions

```php
// Word boundary match - ensures we don't match "black" in "blackout"
if (preg_match('/\b' . preg_quote($variation, '/') . '\b/i', $text)) {
    $score += 8;
}
```

#### AutoParentCreator (`app/Services/AutoParentCreator.php`)
- Hybrid auto-parent creation system
- SKU-based extraction (001-001 pattern)
- Name-based extraction with smart cleaning
- Fallback to generic parent creation

### Testing Framework

#### Comprehensive Test Coverage
- **Import Modes**: `ImportModesTest.php` - Tests all three import modes
- **Constraint Handling**: `VariantConstraintTest.php` - Tests color/size constraints
- **Barcode Constraints**: `BarcodeConstraintTest.php` - Tests barcode uniqueness
- **Auto-Parent Creation**: `AutoParentCreationTest.php` - Tests hybrid parent system

#### Test Patterns
Uses Pest PHP framework with factories:
```php
test('handles duplicate color/size combination gracefully in create_only mode', function () {
    $product = Product::factory()->create(['name' => 'Test Product']);
    // Test implementation...
});
```

### Development Tools
 ### Pest and LaraStan
- Use Pest to run test to check if there are code errors
- Use LaraStan to check code types and code quality

```bash
php artisan clear:products          # With confirmations
php artisan clear:products --force  # Skip confirmations
```

## Marketplace Integration System

### Supported Marketplaces
The PIM system supports multiple marketplace integrations with dedicated service classes:

#### eBay Integration
- **Service**: `EbayConnectService` - Modern REST API integration using Guzzle HTTP
- **Export Service**: `EbayExportService` - Handles product exports to eBay
- **Authentication**: OAuth 2.0 client credentials flow
- **APIs Used**: eBay Inventory API, Sell API
- **Features**:
  - Inventory item creation and management
  - Offer creation and publishing
  - Real-time inventory updates
  - Product aspects (item specifics) mapping
  - Business policies integration

#### Shopify Integration  
- **Service**: `ShopifyConnectService` - Uses PHPShopify SDK
- **Export Service**: `ShopifyExportService` - Product grouping and export
- **Authentication**: Admin API access tokens
- **Features**:
  - Product and variant management
  - Metafields and taxonomy categories
  - GraphQL and REST API support

#### Mirakl Integration
- **Service**: Configured via services.php
- **Authentication**: JWT tokens with API key/secret

### Configuration

#### eBay Configuration (.env)
```
EBAY_ENVIRONMENT=SANDBOX          # or PRODUCTION
EBAY_CLIENT_ID=your_app_id
EBAY_CLIENT_SECRET=your_client_secret  
EBAY_DEV_ID=your_dev_id
EBAY_REDIRECT_URI=your_redirect_uri

# Business Policies (required for listings)
EBAY_FULFILLMENT_POLICY_ID=policy_id
EBAY_PAYMENT_POLICY_ID=policy_id
EBAY_RETURN_POLICY_ID=policy_id
EBAY_LOCATION_KEY=default_location
```

#### Shopify Configuration (.env)
```
SHOPIFY_STORE_URL=your-store.myshopify.com
SHOPIFY_ACCESS_TOKEN=your_admin_api_token
SHOPIFY_API_VERSION=2024-07
```

### Marketplace Data Structure
- **Marketplaces**: Base marketplace configuration (platform, codes, settings)
- **MarketplaceVariants**: Product listings on marketplaces with titles, descriptions
- **MarketplaceBarcodes**: Platform-specific identifiers (ASINs, listing IDs, etc.)

### eBay Integration Workflow
1. **Create Inventory Item**: Product details, condition, availability
2. **Create Offer**: Pricing, category, policies, quantity
3. **Publish Offer**: Convert to live eBay listing
4. **Store Marketplace Data**: Track offers and listings in database

### Testing Marketplace Integrations
- `php artisan ebay:test` - Comprehensive eBay integration testing
- Tests API connectivity, configuration status, data structure building
- Validates marketplace setup and credentials

## Coding Best Practices

### Error Handling
- Do not use try catches. Use laravel exceptions and make custom exceptions where possible

### Flux UI Memories
- Flux UI select dropdown is flux::select.option not flux::option

### Flux Icons
- Uses lucide dev for icons
- Icons should be in the flux tag as a directive

## Design Patterns & Architecture

### Builder Pattern / Fluent API
When creating services or utilities that require configuration, use the **Builder Pattern with Fluent API**:

### Alpine.js Integration with Livewire

**Alpine Store Pattern for Global State:**
```javascript
Alpine.store('storeName', {
    items: [],
    get computed() { return this.items.filter(...) },
    add(item) { ... },
    remove(id) { ... }
});
```

**Key Principles:**
- Use Alpine stores for client-side state that needs to be shared across components
- Access stores in templates with `$store.storeName`
- Use computed properties (getters) for derived state
- Handle Livewire events to sync server/client state
- Keep Alpine logic in Blade templates, not external JS files

**Livewire + Alpine Data Flow:**
1. Livewire manages server state (session, database)
2. Alpine store manages client state (UI interactions, animations)
3. Use browser events to communicate: `livewire:navigate`, custom events
4. Pass data from Livewire to Alpine using `@js()` directive: `x-data="{ data: @js($this->data) }"`

### Livewire Component Patterns

**Computed Properties:**
```php
#[Computed]
public function items(): Collection
{
    return $this->query()->get();
}
```
- Use for expensive operations that should be cached during request
- Access in Blade with `$this->items`
- Automatically refreshes when component re-renders

**Event Communication:**
```php
// Dispatch from Livewire
$this->dispatch('event-name', ['data' => $value]);

// Listen in Alpine
window.addEventListener('event-name', (event) => {
    // Handle event.detail.data
});
```

### File Organization

File organisation should be organised very well and not just have all files in a directory, for example all Services should be broken down by service and have subdirectories 

- **Livewire Components:** `app/Livewire/` organized by feature
- **Blade Views:** Mirror Livewire structure in `resources/views/livewire/`
- **Alpine Stores:** Inline in Blade components or partials
- **Services:** `app/Services/` for business logic
- **Traits:** `app/Traits/` or `app/Concerns/` for reusable component behavior
- **Tests:** `tests/Feature/` with descriptive names matching features