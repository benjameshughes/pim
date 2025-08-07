<?php

namespace Tests\Feature;

use App\Livewire\Examples\ToastDemo;
use App\UI\Toasts\Toast;
use App\UI\Toasts\ToastAction;
use App\UI\Toasts\ToastManager;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Test our FilamentPHP-style toast system to ensure it works exactly like the table system
 */
class FilamentToastSystemTest extends TestCase
{
    /**
     * Test that InteractsWithToasts trait provides magic property access
     */
    public function test_magic_property_provides_toast_manager()
    {
        $component = Livewire::test(ToastDemo::class);
        
        // The magic property should return a ToastManager instance
        $this->assertInstanceOf(ToastManager::class, $component->instance()->getToastsProperty());
    }

    /**
     * Test that Toast builder pattern works like FilamentPHP
     */
    public function test_toast_builder_pattern()
    {
        $toast = Toast::success('Test Title')
            ->body('Test body content')
            ->position('top-right')
            ->duration(5000)
            ->persistent(false)
            ->closable(true);

        $this->assertEquals('Test Title', $toast->getTitle());
        $this->assertEquals('Test body content', $toast->getBody());
        $this->assertEquals('top-right', $toast->getPosition());
        $this->assertEquals(5000, $toast->getDuration());
        $this->assertFalse($toast->isPersistent());
        $this->assertTrue($toast->isClosable());
        $this->assertEquals('success', $toast->getType());
    }

    /**
     * Test that toast actions work like FilamentPHP actions
     */
    public function test_toast_actions_builder_pattern()
    {
        $action = ToastAction::make('Click Me')
            ->url('https://example.com')
            ->color('primary')
            ->variant('filled')
            ->openInNewTab(true)
            ->shouldCloseToast(false);

        $this->assertEquals('Click Me', $action->getLabel());
        $this->assertEquals('https://example.com', $action->getUrl());
        $this->assertEquals('primary', $action->getColor());
        $this->assertEquals('filled', $action->getVariant());
        $this->assertTrue($action->getOpenInNewTab());
        $this->assertFalse($action->getShouldCloseToast());
    }

    /**
     * Test that toasts can be added with actions
     */
    public function test_toasts_with_actions()
    {
        $action = ToastAction::make('Test Action')
            ->url('/test')
            ->color('success');

        $toast = Toast::info('Test Toast')
            ->body('Test content')
            ->action($action);

        $actions = $toast->getActions();
        $this->assertCount(1, $actions);
        $this->assertEquals('Test Action', $actions[0]->getLabel());
    }

    /**
     * Test that ToastManager stores and retrieves toasts
     */
    public function test_toast_manager_stores_toasts()
    {
        $manager = app(ToastManager::class);
        
        // Clear any existing toasts
        $manager->clear();
        $this->assertCount(0, $manager->getToasts());

        // Create and add a toast
        $toast = Toast::success('Test')->body('Test body');
        $manager->add($toast);

        $toasts = $manager->getToasts();
        $this->assertCount(1, $toasts);
        $this->assertEquals('Test', $toasts->first()->getTitle());
    }

    /**
     * Test that the ToastManager can be converted to array for rendering
     */
    public function test_toast_manager_to_array()
    {
        $manager = app(ToastManager::class);
        $manager->clear();

        // Add a test toast
        $toast = Toast::error('Error Test')
            ->body('Error body')
            ->position('bottom-left');
        $manager->add($toast);

        $array = $manager->toArray();
        
        $this->assertTrue($array['hasToasts']);
        $this->assertArrayHasKey('toasts', $array);
        $this->assertArrayHasKey('toastsByPosition', $array);
        $this->assertCount(1, $array['toasts']);
    }

    /**
     * Test that the Htmlable interface works for magic property rendering
     */
    public function test_htmlable_interface_renders()
    {
        $manager = app(ToastManager::class);
        $manager->clear();

        // Add a toast
        $toast = Toast::info('HTML Test')->body('This should render');
        $manager->add($toast);

        // Test that it can be converted to HTML string
        $html = $manager->toHtml();
        $this->assertStringContainsString('HTML Test', $html);
        
        // Test that __toString works (this is what makes {{ $this->toasts }} work)
        $string = (string) $manager;
        $this->assertStringContainsString('HTML Test', $string);
    }

    /**
     * Test that toast facade methods work
     */
    public function test_toast_facade_methods()
    {
        $manager = app(ToastManager::class);
        $manager->clear();

        // Test facade methods
        $successToast = $manager->success('Facade Success', 'Success body');
        $this->assertEquals('Facade Success', $successToast->getTitle());
        $this->assertEquals('success', $successToast->getType());

        $errorToast = $manager->error('Facade Error', 'Error body');
        $this->assertEquals('Facade Error', $errorToast->getTitle());
        $this->assertEquals('error', $errorToast->getType());

        $warningToast = $manager->warning('Facade Warning', 'Warning body');
        $this->assertEquals('Facade Warning', $warningToast->getTitle());
        $this->assertEquals('warning', $warningToast->getType());

        $infoToast = $manager->info('Facade Info', 'Info body');
        $this->assertEquals('Facade Info', $infoToast->getTitle());
        $this->assertEquals('info', $infoToast->getType());
    }

    /**
     * Test navigation persistence functionality
     */
    public function test_navigation_persistence()
    {
        $toast = Toast::success('Persistent Toast')
            ->persist(true); // Should survive navigation

        $this->assertTrue($toast->getNavigatePersist());

        $regularToast = Toast::info('Regular Toast');
        $this->assertFalse($regularToast->getNavigatePersist());
    }

    /**
     * Test that the complete system integrates properly in Livewire
     */
    public function test_livewire_integration()
    {
        $component = Livewire::test(ToastDemo::class);
        
        // Test that the component uses the InteractsWithToasts trait
        $this->assertTrue(method_exists($component->instance(), 'getToastsProperty'));
        $this->assertTrue(method_exists($component->instance(), 'addToast'));
        $this->assertTrue(method_exists($component->instance(), 'removeToast'));
        $this->assertTrue(method_exists($component->instance(), 'clearAllToasts'));
        $this->assertTrue(method_exists($component->instance(), 'executeToastAction'));

        // Test that the magic property works
        $toastManager = $component->instance()->getToastsProperty();
        $this->assertInstanceOf(ToastManager::class, $toastManager);
    }
}