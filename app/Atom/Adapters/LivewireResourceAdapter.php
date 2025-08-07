<?php

namespace App\Atom\Adapters;

use App\Atom\Navigation\NavigationManager;
use App\Atom\Resources\Resource;
use App\Atom\Resources\ResourceManager;
use App\Atom\Tables\Concerns\InteractsWithTable;
use App\Atom\Tables\Table;
use App\Livewire\Concerns\InteractsWithNotifications;
// use App\UI\Toasts\Facades\Toast; // Temporarily removed for debugging
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
class LivewireResourceAdapter extends Component
{
    use InteractsWithTable, WithPagination, InteractsWithNotifications;
    
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
        
        // Configure the existing table (which has Livewire context) with the resource
        $table->model($this->resource::getModel());
        return $this->resource::table($table);
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
     * Navigation breadcrumbs using NavigationManager.
     */
    public function getBreadcrumbs(): array
    {
        return NavigationManager::generateBreadcrumbs();
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
        $recordTitle = $record->{$this->resource::getRecordTitleAttribute()} ?? "#{$record->getKey()}";
        $record->delete();
        
        // Toast temporarily disabled for debugging
        // Toast::success('Record Deleted', "'{$recordTitle}' was deleted successfully.")
        //     ->persist() // Persist across wire:navigate  
        //     ->send();
        
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
     * Get sub-navigation items using NavigationManager.
     */
    public function getSubNavigationItems(): array
    {
        if (!$this->record) {
            return [];
        }
        
        $record = $this->getRecord();
        $subNavItems = NavigationManager::getSubNavigation($this->resource, $record);
        
        return $subNavItems->map(function($item) {
            return [
                'label' => $item->getLabel(),
                'url' => $item->getUrl(),
                'icon' => $item->getIcon(),
                'badge' => $item->getBadge(),
                'active' => NavigationManager::isNavigationActive($item),
                'metadata' => $item->getMetadata(),
            ];
        })->toArray();
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
            'subNavigationItems' => $this->getSubNavigationItems(),
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
     * Auto-detect the best layout for the current Laravel project.
     */
    protected function detectLayout(): string
    {
        // Common Laravel layout paths in order of preference
        $layouts = [
            'components.layouts.app',     // Laravel 11+ component layout
            'layouts.app',                // Traditional Laravel layout  
            'app',                        // Simple app layout
            'components.layout',          // Alternative component layout
            'layout',                     // Minimal layout
        ];
        
        foreach ($layouts as $layout) {
            if (view()->exists($layout)) {
                return $layout;
            }
        }
        
        // Fallback: create a minimal layout inline if none found
        return 'atom::minimal-layout';
    }
    
    /**
     * Determine which view to use with fallbacks.
     */
    protected function getViewName(): string
    {
        $pageViews = [
            'index' => ['atom.resources.index', 'atom::resources.index'],
            'create' => ['atom.resources.create', 'atom::resources.create'], 
            'edit' => ['atom.resources.edit', 'atom::resources.edit'],
            'view' => ['atom.resources.view', 'atom::resources.view'],
        ];
        
        $possibleViews = $pageViews[$this->page] ?? $pageViews['index'];
        
        foreach ($possibleViews as $viewName) {
            if (view()->exists($viewName)) {
                return $viewName;
            }
        }
        
        // Ultimate fallback
        return 'atom::resources.default';
    }

    /**
     * Render the appropriate view based on page type.
     */
    public function render()
    {
        $pageData = $this->getPageData();
        $viewName = $this->getViewName();
        
        return view($viewName, $pageData)->layout($this->detectLayout());
    }
    
    /**
     * Get component name for routing.
     */
    public static function getComponentName(): string
    {
        return 'adapters.livewire-resource-adapter';
    }
    
    // ==============================================
    // UNIVERSAL ELEMENT SYSTEM 🚀
    // Drop any of these into ANY Blade template!
    // ==============================================
    
    /**
     * {{ $this->navigation }} - Universal navigation element
     */
    public function getNavigationProperty()
    {
        $navigationItems = NavigationManager::getGroupedItems();
        
        return $this->renderElement('navigation.main', [
            'items' => $navigationItems,
            'currentRoute' => request()->route()->getName(),
            'user' => auth()->user(),
        ]);
    }
    
    /**
     * {{ $this->breadcrumbs }} - Smart breadcrumb element  
     */
    public function getBreadcrumbsProperty()
    {
        $breadcrumbs = $this->getBreadcrumbs();
        
        return $this->renderElement('navigation.breadcrumbs', [
            'breadcrumbs' => $breadcrumbs,
            'current' => $this->getTitle(),
        ]);
    }
    
    /**
     * {{ $this->actions }} - Context-aware action buttons
     */
    public function getActionsProperty()
    {
        $actions = $this->getActions();
        
        return $this->renderElement('actions.buttons', [
            'actions' => $actions,
            'context' => $this->page,
        ]);
    }
    
    /**
     * {{ $this->subNavigation }} - Sub-navigation tabs/menu
     */
    public function getSubNavigationProperty()
    {
        $subNav = $this->getSubNavigationItems();
        
        return $this->renderElement('navigation.sub', [
            'items' => $subNav,
            'active' => request()->url(),
        ]);
    }
    
    /**
     * {{ $this->search }} - Global search bar
     */
    public function getSearchProperty()
    {
        return $this->renderElement('search.global', [
            'placeholder' => 'Search ' . $this->getResourceConfig()['pluralModelLabel'] . '...',
            'route' => $this->resource::getUrl('index'),
        ]);
    }
    
    /**
     * {{ $this->filters }} - Table filters
     */
    public function getFiltersProperty()
    {
        // Get available filters from the resource table configuration
        $table = $this->table(new \App\Atom\Tables\Table());
        
        return $this->renderElement('table.filters', [
            'filters' => $table->getFilters(),
            'active' => $this->tableFilters,
        ]);
    }
    
    /**
     * {{ $this->pagination }} - Smart pagination
     */
    public function getPaginationProperty()
    {
        return $this->renderElement('table.pagination', [
            'paginator' => $this->getTableQuery()->paginate($this->tableRecordsPerPage),
            'perPageOptions' => [10, 25, 50, 100],
        ]);
    }
    
    /**
     * {{ $this->stats }} - Resource statistics
     */
    public function getStatsProperty()
    {
        $modelClass = $this->resource::getModel();
        
        return $this->renderElement('stats.cards', [
            'total' => $modelClass::count(),
            'thisMonth' => $modelClass::whereMonth('created_at', now()->month)->count(),
            'todayCount' => $modelClass::whereDate('created_at', today())->count(),
        ]);
    }
    
    /**
     * {{ $this->userMenu }} - User profile dropdown
     */
    public function getUserMenuProperty()
    {
        return $this->renderElement('user.menu', [
            'user' => auth()->user(),
            'profileRoute' => route('profile.show', [], false),
            'logoutRoute' => route('logout', [], false),
        ]);
    }
    
    /**
     * {{ $this->notifications }} - Toast notifications
     */
    public function getNotificationsProperty()
    {
        return $this->renderElement('notifications.container', [
            'notifications' => $this->getNotifications(),
            'position' => 'top-right',
        ]);
    }
    
    /**
     * Universal element renderer with smart fallbacks
     */
    protected function renderElement(string $elementType, array $data = []): string
    {
        // Try user's custom view first
        $customView = "atom.elements.{$elementType}";
        if (view()->exists($customView)) {
            return view($customView, $data)->render();
        }
        
        // Try framework default
        $defaultView = "atom::elements.{$elementType}";
        if (view()->exists($defaultView)) {
            return view($defaultView, $data)->render();
        }
        
        // Auto-detect CSS framework and use appropriate template
        $cssFramework = $this->detectCssFramework();
        $frameworkView = "atom::elements.{$cssFramework}.{$elementType}";
        if (view()->exists($frameworkView)) {
            return view($frameworkView, $data)->render();
        }
        
        // Ultimate fallback: render as simple HTML
        return $this->renderFallbackElement($elementType, $data);
    }
    
    /**
     * Auto-detect CSS framework in use
     */
    protected function detectCssFramework(): string
    {
        // Check for Tailwind
        if (file_exists(public_path('css/app.css'))) {
            $css = file_get_contents(public_path('css/app.css'));
            if (str_contains($css, 'tailwind') || str_contains($css, '@apply')) {
                return 'tailwind';
            }
        }
        
        // Check for Bootstrap
        if (file_exists(public_path('css/bootstrap.css')) || 
            file_exists(public_path('css/app.css')) && str_contains(file_get_contents(public_path('css/app.css')), 'bootstrap')) {
            return 'bootstrap';
        }
        
        // Check Vite manifest for framework hints
        if (file_exists(public_path('build/manifest.json'))) {
            $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
            foreach ($manifest as $file => $data) {
                if (str_contains($file, 'tailwind')) return 'tailwind';
                if (str_contains($file, 'bootstrap')) return 'bootstrap';
            }
        }
        
        return 'minimal'; // Plain HTML fallback
    }
    
    /**
     * Render fallback HTML when no views exist
     */
    protected function renderFallbackElement(string $elementType, array $data): string
    {
        return match($elementType) {
            'navigation.main' => '<nav class="navigation"><!-- Navigation items would go here --></nav>',
            'navigation.breadcrumbs' => '<div class="breadcrumbs"><!-- Breadcrumbs --></div>',
            'actions.buttons' => '<div class="actions"><!-- Action buttons --></div>',
            'table.filters' => '<div class="filters"><!-- Table filters --></div>',
            'notifications.container' => '<div class="notifications"><!-- Notifications --></div>',
            default => '<div class="atom-element"><!-- ' . $elementType . ' --></div>',
        };
    }
}