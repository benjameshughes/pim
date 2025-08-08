<?php

namespace Tests\Feature;

use App\Concerns\HasTabs;
use App\Models\Product;
use App\UI\Components\Tab;
use App\UI\Components\TabSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Tests\TestCase;

class TabSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_tab_component_creation()
    {
        $tab = Tab::make('overview')
            ->label('Overview')
            ->icon('package')
            ->badge(5, 'blue');

        $this->assertEquals('overview', $tab->getKey());
        $this->assertEquals('Overview', $tab->getLabel());
        $this->assertEquals('package', $tab->getIcon());
        $this->assertEquals(5, $tab->getBadge());
        $this->assertEquals('blue', $tab->getBadgeColor());
    }

    public function test_tab_conditional_visibility()
    {
        $tab = Tab::make('admin')
            ->label('Admin')
            ->hidden(fn () => true);

        $this->assertTrue($tab->isHidden());

        $tab2 = Tab::make('public')
            ->label('Public')
            ->hidden(false);

        $this->assertFalse($tab2->isHidden());
    }

    public function test_tab_set_creation()
    {
        $tabSet = TabSet::make()
            ->baseRoute('products.product')
            ->tabs([
                Tab::make('overview')->label('Overview'),
                Tab::make('details')->label('Details'),
            ]);

        $tabs = $tabSet->getTabs();
        $this->assertCount(2, $tabs);
        $this->assertEquals('overview', $tabs->first()->getKey());
    }

    public function test_tab_set_navigation_building()
    {
        $product = Product::factory()->create();

        $tabSet = TabSet::make()
            ->tabs([
                Tab::make('overview')
                    ->label('Overview')
                    ->icon('package')
                    ->url('/test/overview'),
                Tab::make('details')
                    ->label('Details')
                    ->icon('document')
                    ->badge(3)
                    ->url('/test/details'),
            ]);

        $navigation = $tabSet->buildNavigation($product);

        $this->assertCount(2, $navigation);
        $this->assertEquals('overview', $navigation[0]['key']);
        $this->assertEquals('Overview', $navigation[0]['label']);
        $this->assertEquals('package', $navigation[0]['icon']);
        $this->assertEquals('/test/overview', $navigation[0]['url']);

        $this->assertEquals('details', $navigation[1]['key']);
        $this->assertEquals(3, $navigation[1]['badge']);
        $this->assertEquals('/test/details', $navigation[1]['url']);
    }

    public function test_tab_set_filters_hidden_tabs()
    {
        $tabSet = TabSet::make()
            ->tabs([
                Tab::make('public')->label('Public'),
                Tab::make('private')->label('Private')->hidden(true),
            ]);

        $navigation = $tabSet->buildNavigation();
        $this->assertCount(1, $navigation);
        $this->assertEquals('public', $navigation[0]['key']);
    }

    public function test_tab_array_conversion()
    {
        $tab = Tab::make('overview')
            ->label('Overview')
            ->icon('package')
            ->badge(5, 'blue')
            ->extraAttributes(['data-test' => 'value']);

        $array = $tab->toArray();

        $this->assertEquals([
            'key' => 'overview',
            'label' => 'Overview',
            'icon' => 'package',
            'disabled' => false,
            'extraAttributes' => ['data-test' => 'value'],
            'badge' => 5,
            'badgeColor' => 'blue',
        ], $array);
    }

    public function test_base_route_defaults_to_first_tab()
    {
        $product = Product::factory()->create();

        // Test the actual route that exists - we'll verify the logic by mocking the request
        $this->get('/products/'.$product->id);

        $tabSet = TabSet::make()
            ->baseRoute('products.product')
            ->tabs([
                Tab::make('overview')->label('Overview'),
                Tab::make('variants')->label('Variants'),
            ]);

        $navigation = $tabSet->buildNavigation($product);

        // The overview tab (first tab) should be active on base route
        $this->assertTrue($navigation[0]['active']); // overview
        $this->assertFalse($navigation[1]['active']); // variants
        $this->assertEquals('overview', $navigation[0]['key']);
    }

    public function test_specific_tab_route_shows_as_active()
    {
        $product = Product::factory()->create();

        // Mock being on the variants tab route
        $this->get(route('products.product.variants', $product));

        $tabSet = TabSet::make()
            ->baseRoute('products.product')
            ->tabs([
                Tab::make('overview')->label('Overview'),
                Tab::make('variants')->label('Variants'),
            ]);

        $navigation = $tabSet->buildNavigation($product);

        // The variants tab should be active
        $this->assertFalse($navigation[0]['active']); // overview
        $this->assertTrue($navigation[1]['active']); // variants
        $this->assertEquals('variants', $navigation[1]['key']);
    }

    public function test_wire_navigate_can_be_controlled()
    {
        // Test TabSet level control
        $tabSet = TabSet::make()
            ->wireNavigate(false)
            ->tabs([
                Tab::make('overview')->label('Overview'),
                Tab::make('variants')->label('Variants'),
            ]);

        $navigation = $tabSet->buildNavigation();
        $this->assertFalse($navigation[0]['wireNavigate']);
        $this->assertFalse($navigation[1]['wireNavigate']);

        // Test individual tab override
        $tabSet2 = TabSet::make()
            ->wireNavigate(false)
            ->tabs([
                Tab::make('overview')->label('Overview')->wireNavigate(true),
                Tab::make('variants')->label('Variants'),
            ]);

        $navigation2 = $tabSet2->buildNavigation();
        $this->assertTrue($navigation2[0]['wireNavigate']); // overridden
        $this->assertFalse($navigation2[1]['wireNavigate']); // uses TabSet setting
    }
}

// Test component class for HasTabs trait testing
class TestTabComponent extends Component
{
    use HasTabs;

    public $model;

    public function mount($model = null)
    {
        $this->model = $model ?? new \stdClass;
        $this->initializeTabs();
    }

    protected function configureTabs(): TabSet
    {
        return TabSet::make()
            ->baseRoute('test.tabs')
            ->tabs([
                Tab::make('first')->label('First Tab'),
                Tab::make('second')->label('Second Tab')->badge(2),
                Tab::make('hidden')->label('Hidden Tab')->hidden(true),
            ]);
    }

    public function render()
    {
        return view('components.empty-view', [
            'tabs' => $this->getTabsForNavigation($this->model),
        ]);
    }
}
