<?php

namespace App\Adapters;

use App\Resources\Resource;
use App\Resources\ResourceManager;
use App\Table\Concerns\InteractsWithTable;
use App\Table\Table;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Livewire Resource Adapter
 * 
 * FilamentPHP-inspired adapter that bridges pure Resource classes
 * to Livewire components with our table system. This enables you to write
 * pure PHP resource classes and automatically get working Livewire tables!
 */
#[Layout('components.layouts.app')]
class LivewireResourceAdapter extends Component
{
    use InteractsWithTable, WithPagination;
    
    /**
     * The resource class being displayed.
     */
    public string $resource;
    
    /**
     * The current page type (index, create, edit, view).
     */
    public string $page = 'index';
    
    /**
     * The current record ID (for edit/view pages).
     */
    public ?string $record = null;
    
    /**
     * Cached resource configuration.
     */
    protected ?array $resourceConfig = null;
    
    /**
     * Component mount.
     */
    public function mount(string $resource, string $page = 'index', ?string $record = null): void
    {
        // Validate resource exists and is registered
        if (!ResourceManager::hasResource($resource)) {
            abort(404, "Resource [{$resource}] not found.");
        }
        
        $this->resource = $resource;
        $this->page = $page;
        $this->record = $record;
        
        // If we have a record ID, validate it exists
        if ($this->record && in_array($this->page, ['edit', 'view'])) {
            $this->getRecord(); // This will throw 404 if not found
        }
    }
    
    /**
     * Configure the table using the resource.
     */
    public function table(Table $table): Table
    {
        // Only configure table for index page
        if ($this->page !== 'index') {
            return $table;
        }
        
        return ResourceManager::getTable($this->resource);
    }
    
    /**
     * Get the current record (for edit/view pages).
     */
    public function getRecord(): Model
    {
        if (!$this->record) {
            abort(404, 'Record not specified.');
        }
        
        return ResourceManager::resolveRecord($this->resource, $this->record);
    }
    
    /**
     * Get resource configuration.
     */
    public function getResourceConfig(): array
    {
        if ($this->resourceConfig === null) {
            $this->resourceConfig = ResourceManager::getResourceConfig($this->resource);
        }
        
        return $this->resourceConfig;
    }
    
    /**
     * Get page title.
     */
    public function getTitle(): string
    {
        $config = $this->getResourceConfig();
        
        return match ($this->page) {
            'index' => $config['pluralModelLabel'],
            'create' => "Create {$config['modelLabel']}",
            'edit' => "Edit {$config['modelLabel']}",
            'view' => "View {$config['modelLabel']}",
            default => $config['pluralModelLabel'],
        };
    }
    
    /**
     * Navigation breadcrumbs.
     */
    public function getBreadcrumbs(): array
    {
        $config = $this->getResourceConfig();
        $breadcrumbs = [];
        
        // Always start with the index page
        $breadcrumbs[] = [
            'label' => $config['pluralModelLabel'],
            'url' => $this->resource::getUrl('index'),
        ];
        
        // Add current page if not index
        if ($this->page !== 'index') {
            $breadcrumbs[] = [
                'label' => $this->getTitle(),
                'url' => null, // Current page, no URL
            ];
        }
        
        return $breadcrumbs;
    }
    
    /**
     * Handle create action.
     */
    public function create(): void
    {
        $this->redirect($this->resource::getUrl('create'));
    }
    
    /**
     * Handle view action.
     */
    public function view(mixed $recordId): void
    {
        $this->redirect($this->resource::getUrl('view', ['record' => $recordId]));
    }
    
    /**
     * Handle edit action.
     */
    public function edit(mixed $recordId): void
    {
        $this->redirect($this->resource::getUrl('edit', ['record' => $recordId]));
    }
    
    /**
     * Handle delete action.
     */
    public function delete(mixed $recordId): void
    {
        $record = ResourceManager::resolveRecord($this->resource, $recordId);
        $record->delete();
        
        // Redirect to index with success message
        session()->flash('success', $this->getResourceConfig()['modelLabel'] . ' deleted successfully.');
        $this->redirect($this->resource::getUrl('index'));
    }
    
    /**
     * Get available actions for the current context.
     */
    public function getActions(): array
    {
        $config = $this->getResourceConfig();
        $actions = [];
        
        if ($this->page === 'index') {
            // Header actions for index page
            $actions['create'] = [
                'label' => "Create {$config['modelLabel']}",
                'icon' => 'plus',
                'url' => $this->resource::getUrl('create'),
                'variant' => 'primary',
            ];
        }
        
        if (in_array($this->page, ['edit', 'view'])) {
            // Back to list action
            $actions['back'] = [
                'label' => "Back to {$config['pluralModelLabel']}",
                'icon' => 'arrow-left',
                'url' => $this->resource::getUrl('index'),
                'variant' => 'secondary',
            ];
        }
        
        return $actions;
    }
    
    /**
     * Get navigation items (for resource sub-navigation).
     */
    public function getNavigationItems(): array
    {
        if (!$this->record) {
            return [];
        }
        
        $items = [];
        $config = $this->getResourceConfig();
        
        foreach ($config['pages'] as $pageName => $pageConfig) {
            // Skip pages that don't use records
            if (!str_contains($pageConfig['route'], '{record}')) {
                continue;
            }
            
            $items[] = [
                'label' => ucfirst($pageName) . ' ' . $config['modelLabel'],
                'url' => $this->resource::getUrl($pageName, ['record' => $this->record]),
                'active' => $this->page === $pageName,
            ];
        }
        
        return $items;
    }
    
    /**
     * Get data for the current page.
     */
    public function getPageData(): array
    {
        $config = $this->getResourceConfig();
        
        $data = [
            'resource' => $this->resource,
            'page' => $this->page,
            'config' => $config,
            'title' => $this->getTitle(),
            'breadcrumbs' => $this->getBreadcrumbs(),
            'actions' => $this->getActions(),
            'navigationItems' => $this->getNavigationItems(),
        ];
        
        // Add record data for edit/view pages
        if ($this->record && in_array($this->page, ['edit', 'view'])) {
            $data['record'] = $this->getRecord();
        }
        
        return $data;
    }
    
    /**
     * Check if user can perform an action.
     */
    public function can(string $ability, mixed $record = null): bool
    {
        // TODO: Implement authorization logic
        // This would integrate with Laravel's Gate/Policy system
        return true;
    }
    
    /**
     * Get model policy for authorization.
     */
    protected function getPolicy(): ?string
    {
        $modelClass = ResourceManager::getModel($this->resource);
        
        // Try to find a policy for this model
        $policyClass = str_replace('Models\\', 'Policies\\', $modelClass) . 'Policy';
        
        return class_exists($policyClass) ? $policyClass : null;
    }
    
    /**
     * Render the appropriate view based on page type.
     */
    public function render()
    {
        $pageData = $this->getPageData();
        
        // Determine which view to use based on page type
        $viewName = match ($this->page) {
            'index' => 'livewire.adapters.resource-index',
            'create' => 'livewire.adapters.resource-create',
            'edit' => 'livewire.adapters.resource-edit',
            'view' => 'livewire.adapters.resource-view',
            default => 'livewire.adapters.resource-index',
        };
        
        return view($viewName, $pageData);
    }
    
    /**
     * Get component name for routing.
     */
    public static function getComponentName(): string
    {
        return 'adapters.livewire-resource-adapter';
    }
}