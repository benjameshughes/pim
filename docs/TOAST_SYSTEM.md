# Toast Notification System Documentation

## Overview

This toast notification system provides a FilamentPHP-style fluent API for creating and managing toast notifications in Laravel applications with Livewire and Flux UI integration. The system follows Laravel conventions and provides a clean, extensible architecture.

## Features

- **Fluent API**: Similar to FilamentPHP's notification system
- **Multiple Types**: Success, error, warning, and info toasts with automatic styling
- **Configurable Positioning**: Six different positions (top/bottom × left/center/right)
- **Alpine.js Integration**: Smooth animations and interactive features
- **Action Support**: Add clickable actions to toast notifications
- **Session Persistence**: Toasts persist across redirects
- **Customizable Styling**: Full Tailwind CSS and Flux UI integration
- **Auto-dismiss**: Configurable auto-dismiss timers with progress bars
- **Global Helper Functions**: Easy-to-use helper functions for quick toast creation

## Installation

The toast system is already integrated into your application. The following components are included:

### Core Components

1. **Configuration**: `/config/toasts.php`
2. **Toast Class**: `/app/Toasts/Toast.php`
3. **ToastManager**: `/app/Toasts/ToastManager.php`
4. **Livewire Component**: `/app/Livewire/Components/ToastContainer.php`
5. **Service Provider**: `/app/Providers/ToastServiceProvider.php`
6. **Facade**: `/app/Toasts/Facades/Toast.php`

### Helper Functions

The system provides global helper functions:
- `toast()` - Create a toast or get the toast manager
- `toast_success()` - Create a success toast
- `toast_error()` - Create an error toast  
- `toast_warning()` - Create a warning toast
- `toast_info()` - Create an info toast

## Usage Examples

### Basic Usage

```php
use App\Toasts\Facades\Toast;

// Simple success toast
Toast::success('Operation Successful!', 'Your changes have been saved.')
    ->send();

// Error toast with persistence
Toast::error('Operation Failed!', 'Please try again.')
    ->persistent()
    ->send();

// Custom positioned toast
Toast::info('Welcome!', 'Thanks for joining our platform.')
    ->position('top-center')
    ->duration(6000)
    ->send();
```

### Using Helper Functions

```php
// Quick success toast
toast_success('Saved!', 'Your changes have been saved.')->send();

// Get toast manager
toast()->success('Success!', 'Operation completed.')->send();

// Info toast with default settings
toast('Information', 'Here is some helpful info.')->send();
```

### Advanced Features

```php
use App\Toasts\Toast;
use App\Toasts\ToastAction;

// Toast with actions
Toast::info('Confirmation Required', 'Do you want to proceed?')
    ->persistent()
    ->action(
        ToastAction::make('Confirm')
            ->url('/confirm-action')
            ->icon('check')
    )
    ->action(
        ToastAction::make('Cancel')
            ->shouldCloseToast()
    )
    ->send();

// Custom styled toast
Toast::make()
    ->title('Custom Toast')
    ->body('This toast has custom styling.')
    ->type('warning')
    ->icon('star')
    ->position('bottom-right')
    ->duration(8000)
    ->class(['custom-toast-class'])
    ->send();
```

### In Livewire Components

```php
use App\Toasts\Facades\Toast;

class MyLivewireComponent extends Component
{
    public function save()
    {
        // Validation and save logic...
        
        Toast::success('Record Saved', 'The record has been successfully saved.')
            ->duration(5000)
            ->send();
    }

    public function delete()
    {
        // Delete logic...
        
        Toast::error('Record Deleted', 'The record has been permanently deleted.')
            ->persistent()
            ->send();
    }
}
```

### In Controllers

```php
use App\Toasts\Facades\Toast;

class ProductController extends Controller
{
    public function store(Request $request)
    {
        // Create product logic...
        
        toast_success('Product Created', 'Your new product has been added to the catalog.')
            ->send();
            
        return redirect()->route('products.index');
    }
}
```

## Configuration Options

Edit `/config/toasts.php` to customize:

- **Default Settings**: Duration, position, type, closable behavior
- **Positions**: Available positioning options with CSS classes
- **Toast Types**: Styling for success, error, warning, and info types
- **Animations**: Enter and exit animation settings
- **Limits**: Maximum number of simultaneous toasts
- **Session Key**: Session storage key for toast persistence

### Available Positions

- `top-left`
- `top-center` 
- `top-right`
- `bottom-left`
- `bottom-center`
- `bottom-right`

### Toast Types

Each type includes automatic styling:
- **Success**: Green theme with check-circle icon
- **Error**: Red theme with x-circle icon
- **Warning**: Amber theme with exclamation-triangle icon
- **Info**: Blue theme with information-circle icon

## Fluent API Reference

### Toast Creation Methods

```php
Toast::make()              // Create generic toast
Toast::success()           // Create success toast
Toast::error()             // Create error toast
Toast::warning()           // Create warning toast
Toast::info()              // Create info toast
```

### Configuration Methods

```php
->title(string $title)                    // Set toast title
->body(?string $body)                     // Set toast body content
->type(string $type)                      // Set toast type
->position(string $position)              // Set display position
->duration(int $milliseconds)             // Set auto-dismiss duration
->delay(int $milliseconds)                // Set display delay
->icon(?string $icon)                     // Set custom icon
->closable(bool $closable = true)         // Make closable/non-closable
->persistent(bool $persistent = true)     // Disable auto-dismiss
->class(string|array $classes)            // Add CSS classes
->style(array $styles)                    // Add inline styles
->data(array $data)                       // Add custom data
->action(ToastAction $action)             // Add action button
->actions(array $actions)                 // Add multiple actions
->send()                                  // Display the toast
```

### ToastAction Methods

```php
ToastAction::make(string $label)          // Create action
->url(string $url)                        // Set action URL
->action(Closure $callback)               // Set callback (not used in frontend)
->icon(string $icon)                      // Set action icon
->class(string|array $classes)            // Add CSS classes
->shouldCloseToast(bool $close = true)    // Close toast on click
```

## Demo Page

Visit `/examples/toast-demo` to see the complete toast system in action with:
- Basic toast type demonstrations
- Advanced features like actions and positioning
- Custom toast builder interface
- Code examples and usage patterns
- Configuration information

## Integration with Existing Application

The toast container is automatically included in your application layout. Toasts will appear based on their configured position and will:

- Automatically dismiss after the specified duration (unless persistent)
- Show a progress bar indicating remaining time
- Support manual dismissal if closable
- Handle multiple toasts with proper stacking
- Animate smoothly using Alpine.js transitions
- Integrate seamlessly with Flux UI design system

## Troubleshooting

### Common Issues

1. **Toasts not appearing**: Ensure `<x-toast-container />` is included in your layout
2. **Styling issues**: Check that Flux UI and Tailwind CSS are properly loaded
3. **Helper functions not working**: Clear configuration cache with `php artisan config:clear`
4. **JavaScript errors**: Ensure Alpine.js is loaded and @fluxScripts is included

### Cache Clearing

If you make configuration changes, clear the cache:

```bash
php artisan config:clear
php artisan view:clear
```

## Extending the System

The toast system is designed to be extensible:

1. **Custom Toast Types**: Add new types in the configuration file
2. **Custom Positions**: Define new positioning options
3. **Custom Styling**: Override default styles through configuration
4. **Custom Actions**: Create complex action handlers
5. **Event Integration**: Listen for toast events in Alpine.js components

## Best Practices

1. Use appropriate toast types for different scenarios
2. Keep toast messages concise and actionable
3. Use persistent toasts sparingly (only for critical messages)
4. Provide clear actions when user interaction is required
5. Consider the user's attention span when setting durations
6. Test toast positioning on different screen sizes
7. Use semantic icons that match your message type