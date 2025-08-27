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
- **Database**: MySQL (used in production so needs to match)
- **Architecture**: FluentAPI + Actions Pattern

### Core Structure
- **Actions Pattern**: Single-responsibility business logic classes with transaction safety
- **Error Handling**: Custom exceptions with user-friendly messages and recovery suggestions
- **UI Components**: Toast notifications from custom Flux toast and free Flux UI components

### Actions Pattern Implementation
Actions encapsulate single-responsibility business logic with transaction safety and organised into \App\Actions

## Coding Best Practices

### Organisation
Everything needs to be organised into their respective folders and subfolders. Example: App\Services\API or App\Actions\Products\CreateProduct.php. Livewire components and blade templates follow this pattern as well

### Error Handling
- Do not use try catches. Use laravel exceptions and make custom exceptions where possible

### Flux UI Memories
- Flux Free components
- Custom Flux toast component
- Flux UI select dropdown is flux::select.option not flux::option

### Flux Icons
- Uses lucide dev for icons
- Icons should be in the flux tag as a directive

## Keyboard Navigation

The ProductWizard includes intelligent keyboard shortcuts that work even when form elements are focused:

### Global Shortcuts (work while typing):
- `⌘/Ctrl + →` - Next Step
- `⌘/Ctrl + ←` - Previous Step  
- `⌘/Ctrl + S` - Save Product
- `Esc` - Clear Draft (respects inline editing context)

### Quick Navigation (only when not typing):
- `1-4` - Jump to specific step

### Tab Order Hierarchy:
1. **📝 Inputs** - Data entry fields (tabindex 1-5)
2. **🔘 Action Buttons** - Add/Upload buttons (tabindex 4-6)  
3. **⬅️➡️ Navigation** - Previous/Next buttons (tabindex 7-8)
4. **💾 Save** - Final save button (tabindex 9)
5. **✂️ CRUD Operations** - Edit/delete badges (tabindex 20+)

### Auto-Focus Behavior:
- **Step 1**: Auto-focus Product Name input
- **Step 2**: Auto-focus "Add color" input
- **Step 3**: Auto-focus file upload input
- **Step 4**: Auto-focus first variant's retail price

### Implementation Details:
- Uses `event.preventDefault()` and `event.stopPropagation()` for modifier key combinations
- Allows normal form interaction while preserving global shortcuts
- Smart Escape handling that doesn't interfere with inline editing
- Visual hints show automatically and indicate "Works while typing!"
- Dynamic tabindex for CRUD operations to maintain logical flow

## Replies
- Be concise in your explanations - focus on what's important rather than explaining obvious details.


## Laravel Boost
- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Flux UI Free

- This project is using the free edition of Flux UI. It has full access to the free components and variants, but does not have access to the Pro components.
- Flux UI is a component library for Livewire. Flux is a robust, hand-crafted, UI component library for your Livewire applications. It's built using Tailwind CSS and provides a set of components that are easy to use and customize.
- You should use Flux UI components when available.
- Fallback to standard Blade components if Flux is unavailable.
- If available, use Laravel Boost's `search-docs` tool to get the exact documentation and code snippets available for this project.
