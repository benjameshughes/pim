<?php

namespace App\View\Components;

use Illuminate\View\Component;
use App\StackedList\StackedListDirectiveService;
use Livewire\Livewire;

class StackedList extends Component
{
    public string $type;
    public array $parameters;

    public function __construct($type = '', $parameters = [])
    {
        $this->type = $type;
        $this->parameters = is_array($parameters) ? $parameters : [];
    }

    public function render()
    {
        // Validate type
        if (empty($this->type)) {
            throw new \Exception("StackedList component requires 'type' attribute. Available types: " . implode(', ', array_keys(StackedListDirectiveService::getAvailableTypes())));
        }
        
        // Get the component class
        $componentClass = StackedListDirectiveService::getComponentClass($this->type);
        
        // Mount and render the Livewire component directly as HTML
        $component = Livewire::mount($componentClass, $this->parameters);
        
        // Return the HTML directly (not a view)
        return $component;
    }
}