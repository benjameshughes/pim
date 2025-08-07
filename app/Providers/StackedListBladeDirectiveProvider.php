<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use App\StackedList\StackedListDirectiveService;

class StackedListBladeDirectiveProvider extends ServiceProvider
{
    public function boot()
    {
        // Register @stackedList directive (FilamentPHP approach)
        Blade::directive('stackedList', function ($expression) {
            // Parse the expression: 'products', ['param' => 'value']
            $parts = explode(',', $expression, 2);
            $type = trim($parts[0], " '\"");
            $parameters = isset($parts[1]) ? trim($parts[1]) : '[]';

            // Get the component class at compile time
            $componentClass = StackedListDirectiveService::getComponentClass($type);
            
            // Return PHP code that renders the Livewire component (FilamentPHP style)
            return "<?php 
                echo \\Livewire\\Livewire::mount('{$componentClass}', {$parameters});
            ?>";
        });
    }
}