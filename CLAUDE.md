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
- **Actions Pattern**: Single-responsibility business logic classes with transaction safety
- **Error Handling**: Custom exceptions with user-friendly messages and recovery suggestions
- **UI Components**: Toast notifications from App\UI
### Core Performance Metrics
- **7.92ms** - Complex variant creation with pricing + attributes (98% improvement)
- **0.64ms** - Cached queries (99% faster than database hits)
- **12.22ms** - Cache warmup for entire system
- **Sub-10ms** - All critical operations optimized


### Actions Pattern Implementation
Actions encapsulate single-responsibility business logic with transaction safety

## Coding Best Practices

### Error Handling
- Do not use try catches. Use laravel exceptions and make custom exceptions where possible

### Flux UI Memories
- Flux UI select dropdown is flux::select.option not flux::option

### Flux Icons
- Uses lucide dev for icons
- Icons should be in the flux tag as a directive

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
