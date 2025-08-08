# Drop-in Atom Framework Usage

Your Atom framework is designed to be completely **non-invasive** - it respects your existing Blade views and layouts without taking them over.

## 🎯 Three Ways to Use Atom Framework

### Method 1: Dedicated Livewire Component (Easiest)

Drop this into any Blade view and it works with your existing layout:

```blade
{{-- In your existing Blade view --}}
@extends('layouts.app')

@section('content')
<div class="container">
    <h1>My Products</h1>
    
    {{-- Your custom content --}}
    <div class="my-custom-section">
        <p>This is your own content in your own layout.</p>
    </div>
    
    {{-- Drop in the Atom table - respects your layout --}}
    @livewire('resource-table', ['resource' => \App\Atom\Resources\ProductResource::class])
    
    {{-- More of your content --}}
</div>
@endsection
```

### Method 2: In Your Own Livewire Components

Add the trait to your existing Livewire components:

```php
<?php

namespace App\Livewire;

use App\Atom\Core\Support\Concerns\HasResourceTable;
use App\Atom\Resources\ProductResource;
use Livewire\Component;

class MyComponent extends Component
{
    use HasResourceTable;
    
    // This is all you need!
    public string $resource = ProductResource::class;
    
    // Your existing component code...
    public $myProperty = 'Hello World';
    
    public function myMethod() 
    {
        // Your existing methods...
    }
}
```

Then in your Blade view:

```blade
<div>
    {{-- Your existing component content --}}
    <h2>{{ $myProperty }}</h2>
    <button wire:click="myMethod">My Button</button>
    
    {{-- Drop in the table - powered by your resource --}}
    {{ $this->table }}
    
    {{-- More of your content --}}
</div>
```

### Method 3: Universal Elements in Any Component

Use any of these magic properties in your Livewire components that extend the ResourceAdapter:

```blade
{{-- In your Blade views --}}
<div>
    {{ $this->navigation }}      {{-- Auto-generated navigation --}}
    {{ $this->breadcrumbs }}     {{-- Smart breadcrumbs --}}
    {{ $this->table }}           {{-- Complete resource table --}}
    {{ $this->actions }}         {{-- Context-aware buttons --}}
    {{ $this->filters }}         {{-- Table filters --}}
    {{ $this->search }}          {{-- Global search --}}
    {{ $this->stats }}           {{-- Statistics cards --}}
    {{ $this->pagination }}      {{-- Smart pagination --}}
</div>
```

## ✨ Key Features

### 🔥 **Non-Invasive Design**
- Respects your existing layouts
- Works with any CSS framework (Tailwind, Bootstrap, custom)
- Doesn't override your Blade views
- Plays nicely with existing Livewire components

### 🚀 **Drop-in Ready**
- Add one line to get a complete table
- Auto-detects your styling framework
- Graceful fallbacks for missing views
- Works with any Laravel version

### 🎯 **Smart Auto-Detection**
- Detects your layout automatically
- Adapts to your CSS framework
- Falls back gracefully when views are missing
- Zero configuration required

## 🛠️ Setup

1. **Create a Resource** (one time setup):
```bash
php artisan atom:resource Product
```

2. **Configure your Resource** in `app/Atom/Resources/ProductResource.php`:
```php
public static function table(Table $table): Table
{
    return $table
        ->columns([
            Column::make('id')->label('ID')->sortable(),
            Column::make('name')->label('Name')->sortable()->searchable(),
            Column::make('created_at')->label('Created')->sortable(),
        ])
        ->actions([
            Action::make('edit')->label('Edit'),
            Action::make('delete')->label('Delete')->color('danger'),
        ]);
}
```

3. **Drop it into any view**:
```blade
@livewire('resource-table', ['resource' => \App\Atom\Resources\ProductResource::class])
```

## 🎨 Customization

### Custom Views
Override any view by creating your own:
```
resources/views/atom/components/resource-table.blade.php
```

### Custom Styling  
The framework auto-detects your CSS framework:
- **Tailwind CSS** → Uses Tailwind classes
- **Bootstrap** → Uses Bootstrap classes  
- **Custom/None** → Uses minimal HTML

### Custom Actions
Add custom actions in your Resource:
```php
Action::make('duplicate')
    ->label('Duplicate')
    ->icon('copy')
    ->action(function ($record) {
        // Your custom logic
    })
```

## 🔧 Advanced Usage

### Extend with Your Own Logic
```php
class ProductDashboard extends Component
{
    use HasResourceTable;
    
    public string $resource = ProductResource::class;
    
    // Add your own properties and methods
    public $customData = [];
    
    public function customAction()
    {
        // Your custom logic
    }
    
    // Override table actions
    protected function handleCustomTableAction(string $action, $record): void
    {
        match ($action) {
            'duplicate' => $this->duplicateRecord($record),
            'archive' => $this->archiveRecord($record),
            default => null,
        };
    }
}
```

## 🚨 The Magic

The beauty of this framework is that **it doesn't take over your app** - it enhances it:

- ✅ Your layouts stay yours
- ✅ Your CSS stays yours  
- ✅ Your components stay yours
- ✅ Just add powerful table functionality with one line

This is a true **drop-in framework** that respects your existing architecture while giving you FilamentPHP-level functionality! 🎯