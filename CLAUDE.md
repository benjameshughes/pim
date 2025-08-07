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
- **Custom Framework**: Atom Framework (`App\Atom\`)

### Core Structure
- **Livewire Components**: Primary UI interaction layer using Livewire components for auth, settings, and actions
- **Authentication**: Complete auth system with Livewire components (login, register, password reset, email verification)
- **User Management**: Built-in user model with profile management and settings
- **UI Components**: Flux-based component system with custom Blade components
- **Testing**: Comprehensive test suite covering authentication flows and features

### Key Directories
- `app/Atom/` - **Atom Framework** (ResourceManager, NavigationManager, Tables, Forms)
  - `app/Atom/Resources/` - Resource definitions and management
  - `app/Atom/Navigation/` - Unified navigation system
  - `app/Atom/Tables/` - Dynamic table generation
  - `app/Atom/Adapters/` - Livewire/API/Blade adapters
  - `app/Atom/Providers/` - AtomServiceProvider
- `app/Livewire/` - Livewire components organized by feature (Auth, Settings, Actions)
- `resources/views/livewire/` - Corresponding Blade views for Livewire components
- `resources/views/components/` - Reusable Blade components including layouts
- `resources/views/flux/` - Custom Flux UI components
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

## Atom Framework

### Overview
The Atom Framework is a custom FilamentPHP-inspired resource management system built specifically for this Laravel application. It provides automatic CRUD operations, navigation generation, and a unified interface for managing resources across different presentation layers.

### Core Components
- **ResourceManager** (`App\Atom\Resources\ResourceManager`) - Central hub for resource discovery and management
- **NavigationManager** (`App\Atom\Navigation\NavigationManager`) - Unified navigation system
- **LivewireResourceAdapter** (`App\Atom\Adapters\LivewireResourceAdapter`) - Dynamic Livewire component adapter
- **AtomServiceProvider** (`App\Atom\Providers\AtomServiceProvider`) - Framework service provider

### Usage Examples

#### Creating a New Resource
```bash
# Generate resource class
php artisan make:resource OrderResource
```

```php
// app/Atom/Resources/OrderResource.php
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    
    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('order_number')->sortable(),
            TextColumn::make('customer.name'),
            TextColumn::make('total')->money('GBP'),
        ]);
    }
    
    public static function getNavigationGroup(): ?string
    {
        return 'Sales';
    }
}
```

#### Adding Custom Navigation
```php
// In AppServiceProvider::boot()
Navigation::make()
    ->label('Analytics')
    ->route('analytics.dashboard')
    ->icon('chart-bar')
    ->group('Reports')
    ->register();
```

### Auto-Generated Features
- **Routes**: Automatically registers CRUD routes for all resources
- **Navigation**: Dynamic sidebar navigation based on resources and custom items
- **Tables**: Interactive tables with sorting, searching, and actions
- **Forms**: Dynamic form generation (planned feature)

### Framework Benefits
- **Zero Boilerplate**: No need to create controllers, views, or routes manually
- **Consistent UI**: All resources use the same design patterns
- **Extensible**: Easy to customize and extend functionality
- **Type-Safe**: Full PHP type safety with IDE support

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

### Import Data Component (`app/Livewire/Products/ImportData.php`)

#### Features
- **Column Auto-mapping**: Intelligent field detection using pattern matching
- **Dry Run Validation**: Comprehensive constraint checking before import
- **Event-driven Processing**: ProductImported events for background tasks
- **Multi-mode Support**: All three import modes with proper constraint handling

#### Key Methods
- `predictVariantAction()`: Predicts create/update/skip actions
- `handleVariantImport()`: Core variant import logic with constraint handling
- `runDryRun()`: Enhanced dry run with color/size constraint detection

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

#### Nuclear Reset Command (`app/Console/Commands/ClearAllProductData.php`)
- Safely deletes all product data with confirmation prompts
- Resets barcode pool usage and auto-increment counters
- Clears product images from storage
- Development-only tool for clean testing iterations

```bash
php artisan clear:products          # With confirmations
php artisan clear:products --force  # Skip confirmations
```

### Recent Improvements

#### Session Achievements
1. **Complete PIM Integration**: All three data management sections (Barcodes, Pricing, Images) properly integrated
2. **Smart Import System**: Enhanced column mapping and attribute extraction
3. **Constraint Resolution**: Fixed all UNIQUE constraint violations
4. **Auto-Parent Creation**: Implemented lazy programmer solution for parent generation
5. **Comprehensive Testing**: Full test coverage with Pest framework
6. **Development Tooling**: Nuclear reset tool for clean iterations

#### Technical Debt Removed
- Completely removed style/subcategory references from entire codebase
- Fixed color extraction false positives ("blackout" → "black")
- Resolved barcode and variant constraint violations
- Enhanced dry run logic to match actual import behavior

### Best Practices
- Always use constraint-aware import logic
- Test with nuclear reset tool between iterations
- Use smart attribute extraction for missing color/size data
- Leverage auto-parent creation for variant-heavy imports
- Run comprehensive tests before deployment

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

## Atom Framework - Universal Laravel UI Toolkit

The Atom framework is now a complete, universal Laravel UI toolkit that works with any Laravel project out of the box. It provides a drop-in resource management system with intelligent layout and CSS framework detection.

### Universal Element System 🚀

The framework provides magic `{{ $this->element }}` properties that work in any Blade template:

```blade
{{-- Drop into ANY Laravel project --}}
<div>
    {{ $this->navigation }}      {{-- Auto-detects layout & styling --}}
    {{ $this->breadcrumbs }}     {{-- Smart breadcrumbs --}}
    {{ $this->search }}          {{-- Global search --}}
    {{ $this->actions }}         {{-- Context buttons --}}
    {{ $this->filters }}         {{-- Table filters --}}
    {{ $this->table }}           {{-- Resource table --}}
    {{ $this->pagination }}      {{-- Smart pagination --}}
    {{ $this->stats }}           {{-- Stats cards --}}
    {{ $this->subNavigation }}   {{-- Tabs/sub menus --}}
    {{ $this->userMenu }}        {{-- Profile dropdown --}}
    {{ $this->notifications }}   {{-- Toast notifications --}}
</div>
```

### Smart Auto-Detection

Each element automatically:
- **Detects Layout**: Tries common Laravel layouts (`components.layouts.app`, `layouts.app`, `app`, etc.)
- **Detects CSS Framework**: Auto-detects Tailwind, Bootstrap, or falls back to minimal HTML
- **Graceful Fallbacks**: Multiple view layers ensure compatibility
- **Zero Configuration**: Works immediately after installation

### Element Rendering Chain

1. `atom.elements.navigation.main` (user's custom view)
2. `atom::elements.navigation.main` (framework default)
3. `atom::elements.tailwind.navigation.main` (framework + detected CSS)
4. Simple HTML fallback

### Framework Architecture

- **`App\Atom\Adapters\LivewireResourceAdapter`** - Core dynamic component that handles all resource pages
- **`App\Atom\Resources\`** - Resource definitions and table configurations
- **`App\Atom\Navigation\`** - Auto-discovery navigation system
- **`App\Atom\Tables\`** - Intelligent table rendering system

### Drop-In Compatibility

The framework works with:
- ✅ Any Laravel version (11+, 10+, etc.)
- ✅ Any starter kit (Breeze, Jetstream, custom)
- ✅ Any CSS framework (Tailwind, Bootstrap, custom)
- ✅ Any UI library (Flux, Blade UI, Livewire UI, custom)

### Console Commands

The Atom framework includes powerful console commands for resource generation:

#### `php artisan atom:resource {name}`

Generate new Atom resources with intelligent naming:

```bash
# Simple usage - automatically appends "Resource"
php artisan atom:resource Product    # → ProductResource.php  
php artisan atom:resource User       # → UserResource.php
php artisan atom:resource Order      # → OrderResource.php

# Still works with full names (no duplication)
php artisan atom:resource ProductResource  # → ProductResource.php
```

**Command Features:**
- ✅ **Smart Naming**: Auto-appends "Resource" suffix if not provided
- ✅ **Correct Namespace**: Creates in `App\Atom\Resources` with proper imports
- ✅ **Model Detection**: Automatically infers model name and creates proper references
- ✅ **Full Template**: Generates complete resource with table configuration, actions, and bulk operations
- ✅ **No Conflicts**: Uses `atom:resource` to avoid Laravel's default `make:resource`

**Options:**
- `--model=ModelName` - Specify different model name
- `--model-namespace=Namespace` - Custom model namespace (default: App\Models)
- `--force` - Overwrite existing resource
- `--simple` - Generate minimal configuration
- `--generate` - Auto-generate based on model (coming soon)

**Generated Structure:**
```php
<?php

namespace App\Atom\Resources;

use \App\Models\Product;
use App\Atom\Resources\Resource;
// ... other imports

class ProductResource extends Resource
{
    protected static ?string $model = \App\Models\Product::class;
    protected static ?string $navigationLabel = 'Products';
    protected static ?string $navigationIcon = 'cube';
    
    public static function table(Table $table): Table
    {
        return $table
            ->columns([...])
            ->actions([...])
            ->bulkActions([...]);
    }
}
```

### Configuration

Customize the framework through dedicated configuration classes:

#### Navigation Configuration (`app/Atom/Config/NavigationConfig.php`)
```php
// Add your application's navigation items
Navigation::make()
    ->label('Products')
    ->route('products.index')
    ->icon('package')
    ->group('Catalog')
    ->sort(10)
    ->register();
```

#### Framework Configuration (`app/Atom/Config/AtomConfig.php`)
```php
// Customize framework behavior
public static function getDefaultLayouts(): array
{
    return [
        'components.layouts.app',    // Try these layouts in order
        'layouts.app',
        'app',
    ];
}
```

## Design Patterns & Architecture

### Builder Pattern / Fluent API
When creating services or utilities that require configuration, use the **Builder Pattern with Fluent API**:

```php
// Example: Toast notification system
Toast::success('Title', 'Message')
    ->position('top-right')
    ->duration(5000)
    ->persist()      // Method chaining
    ->persistent()   // Each method returns $this
    ->action(ToastAction::make('Undo')->url('/undo'))
    ->send();
```

**Key Principles:**
- Each configuration method returns `$this` for chaining
- Use descriptive method names that read naturally
- Provide sensible defaults in constructor
- Terminal methods (like `send()`) execute the action
- Static factory methods for common presets (`::success()`, `::error()`)

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

### Navigation Persistence (wire:navigate)

For elements that should persist across SPA navigation:
```blade
@persist('unique-name')
    <!-- Element that survives page changes -->
@endpersist
```

**Toast Persistence Pattern:**
- Use `->persist()` for navigation persistence
- Use `->persistent()` for auto-dismiss control
- Listen for `livewire:navigate` events to filter non-persistent items
- Wrap container in `@persist` directive

### Testing with Pest

**Test Structure:**
```php
beforeEach(function () {
    // Setup for each test
});

it('describes what it tests', function () {
    // Arrange
    $model = Model::factory()->create();
    
    // Act
    $result = $model->doSomething();
    
    // Assert
    expect($result)->toBe($expected);
});

describe('Feature Group', function () {
    it('tests specific feature', function () {
        // Grouped related tests
    });
});
```

**Key Principles:**
- Use descriptive test names that explain the behavior
- Group related tests with `describe()`
- Use `beforeEach()` for common setup
- Prefer `expect()` syntax over traditional assertions
- Test uses in-memory SQLite database (`:memory:`)
- Tests are non-destructive to development database

### State Management Best Practices

**Session State (Server):**
- Use for data that must persist across requests
- Store minimal data (IDs, not full objects)
- Reconstruct objects from session data when needed

**Alpine State (Client):**
- Use for UI state (open/closed, animations, timers)
- Keep reactive data in Alpine stores for sharing
- Use component `x-data` for isolated state

**Livewire State (Hybrid):**
- Public properties are reactive and persist across requests
- Use computed properties for derived state
- Don't store Eloquent Collections as public properties
- Use `->values()->toArray()` to convert Collections for Alpine

### Component Communication Patterns

1. **Parent → Child:** Props/attributes
2. **Child → Parent:** Events (`$dispatch`, `$emit`)
3. **Sibling → Sibling:** Alpine store or browser events
4. **Cross-Page (SPA):** Navigation persistence with `@persist`

### File Organization

- **Livewire Components:** `app/Livewire/` organized by feature
- **Blade Views:** Mirror Livewire structure in `resources/views/livewire/`
- **Alpine Stores:** Inline in Blade components or partials
- **Services:** `app/Services/` for business logic
- **Traits:** `app/Traits/` or `app/Concerns/` for reusable component behavior
- **Tests:** `tests/Feature/` with descriptive names matching features