<?php

use App\Livewire\Components\ToastContainer;
use App\UI\Toasts\Facades\Toast;
use App\UI\Toasts\Toast as ToastNotification;
use App\UI\Toasts\ToastAction;
use App\UI\Toasts\ToastManager;
use Livewire\Livewire;

beforeEach(function () {
    // Clear any existing toasts before each test
    app(ToastManager::class)->clear();
});

describe('Toast Builder/Fluent API', function () {
    test('creates basic toast with fluent API', function () {
        $toast = ToastNotification::make()
            ->title('Test Title')
            ->body('Test Body')
            ->type('success')
            ->position('top-right');

        expect($toast->getTitle())->toBe('Test Title');
        expect($toast->getBody())->toBe('Test Body');
        expect($toast->getType())->toBe('success');
        expect($toast->getPosition())->toBe('top-right');
    });

    test('creates toast with static type methods', function () {
        $successToast = ToastNotification::success()->title('Success!');
        expect($successToast->getType())->toBe('success');

        $errorToast = ToastNotification::error()->title('Error!');
        expect($errorToast->getType())->toBe('error');

        $warningToast = ToastNotification::warning()->title('Warning!');
        expect($warningToast->getType())->toBe('warning');

        $infoToast = ToastNotification::info()->title('Info!');
        expect($infoToast->getType())->toBe('info');
    });

    test('configures timing options correctly', function () {
        $toast = ToastNotification::make()
            ->title('Test')
            ->duration(5000)
            ->delay(1000)
            ->persistent(true);

        expect($toast->getDuration())->toBe(5000);
        expect($toast->getDelay())->toBe(1000);
        expect($toast->isPersistent())->toBeTrue();
    });

    test('configures styling options correctly', function () {
        $toast = ToastNotification::make()
            ->title('Test')
            ->icon('star')
            ->class(['custom-class', 'another-class'])
            ->closable(false);

        expect($toast->getIcon())->toBe('star');
        expect($toast->getClasses())->toBe(['custom-class', 'another-class']);
        expect($toast->isClosable())->toBeFalse();
    });

    test('adds toast actions correctly', function () {
        $action1 = ToastAction::make('Confirm')
            ->url('/confirm')
            ->icon('check')
            ->shouldCloseToast(true);

        $action2 = ToastAction::make('Cancel')
            ->shouldCloseToast(false);

        $toast = ToastNotification::make()
            ->title('Test')
            ->action($action1)
            ->action($action2);

        $actions = $toast->getActions();
        expect($actions)->toHaveCount(2);
        expect($actions[0]->getLabel())->toBe('Confirm');
        expect($actions[1]->getLabel())->toBe('Cancel');
    });

    test('converts toast to array correctly', function () {
        $toast = ToastNotification::success()
            ->title('Success!')
            ->body('Operation completed')
            ->duration(5000)
            ->icon('check');

        $array = $toast->toArray();

        expect($array)->toHaveKeys([
            'id', 'title', 'body', 'type', 'position', 'closable',
            'persistent', 'duration', 'icon', 'actions', 'data',
            'type_config', 'position_config'
        ]);

        expect($array['title'])->toBe('Success!');
        expect($array['body'])->toBe('Operation completed');
        expect($array['type'])->toBe('success');
        expect($array['duration'])->toBe(5000);
        expect($array['icon'])->toBe('check');
    });
});

describe('Toast Manager', function () {
    test('adds toast to manager and stores in session', function () {
        $toast = ToastNotification::success('Test', 'Message');
        $manager = app(ToastManager::class);

        $manager->add($toast);
        $toasts = $manager->getToasts();

        expect($toasts)->toHaveCount(1);
        expect($toasts->first()->getTitle())->toBe('Test');
    });

    test('groups toasts by position', function () {
        $manager = app(ToastManager::class);

        $toast1 = ToastNotification::success('Test 1')->position('top-right');
        $toast2 = ToastNotification::error('Test 2')->position('top-left');
        $toast3 = ToastNotification::info('Test 3')->position('top-right');

        $manager->add($toast1)->add($toast2)->add($toast3);

        $grouped = $manager->getToastsByPosition();

        expect($grouped)->toHaveCount(2);
        expect($grouped->get('top-right'))->toHaveCount(2);
        expect($grouped->get('top-left'))->toHaveCount(1);
    });

    test('limits maximum number of toasts', function () {
        $manager = app(ToastManager::class);

        // Add more toasts than the limit (5 by default)
        for ($i = 1; $i <= 7; $i++) {
            $toast = ToastNotification::info("Toast {$i}");
            $manager->add($toast);
        }

        $toasts = $manager->getToasts();
        expect($toasts)->toHaveCount(5);

        // Should have the last 5 toasts
        $titles = $toasts->pluck('title')->toArray();
        expect($titles)->toContain('Toast 7');
        expect($titles)->toContain('Toast 6');
        expect($titles)->not->toContain('Toast 1');
        expect($titles)->not->toContain('Toast 2');
    });

    test('removes specific toast by ID', function () {
        $manager = app(ToastManager::class);

        $toast1 = ToastNotification::success('Test 1');
        $toast2 = ToastNotification::error('Test 2');

        $manager->add($toast1)->add($toast2);

        expect($manager->getToasts())->toHaveCount(2);

        $manager->remove($toast1->getId());

        $remainingToasts = $manager->getToasts();
        expect($remainingToasts)->toHaveCount(1);
        expect($remainingToasts->first()->getTitle())->toBe('Test 2');
    });

    test('clears all toasts', function () {
        $manager = app(ToastManager::class);

        $manager->add(ToastNotification::success('Test 1'));
        $manager->add(ToastNotification::error('Test 2'));

        expect($manager->getToasts())->toHaveCount(2);

        $manager->clear();

        expect($manager->getToasts())->toHaveCount(0);
    });

    test('provides fluent API methods', function () {
        $manager = app(ToastManager::class);

        $successToast = $manager->success('Success!', 'It worked!');
        expect($successToast)->toBeInstanceOf(ToastNotification::class);
        expect($successToast->getType())->toBe('success');
        expect($successToast->getTitle())->toBe('Success!');
        expect($successToast->getBody())->toBe('It worked!');

        $errorToast = $manager->error('Error!', 'Something failed!');
        expect($errorToast->getType())->toBe('error');

        $warningToast = $manager->warning('Warning!', 'Be careful!');
        expect($warningToast->getType())->toBe('warning');

        $infoToast = $manager->info('Info!', 'Here\'s some info!');
        expect($infoToast->getType())->toBe('info');
    });

    test('reconstructs toasts from session data', function () {
        $manager = app(ToastManager::class);

        // Create a toast with complex configuration
        $originalToast = ToastNotification::success('Test Title', 'Test Body')
            ->duration(5000)
            ->position('bottom-left')
            ->persistent(true)
            ->icon('star')
            ->action(ToastAction::make('Click Me')->url('/test'));

        $manager->add($originalToast);

        // Get toasts back (this should reconstruct from session)
        $reconstructedToasts = $manager->getToasts();
        $reconstructed = $reconstructedToasts->first();

        expect($reconstructed->getTitle())->toBe('Test Title');
        expect($reconstructed->getBody())->toBe('Test Body');
        expect($reconstructed->getDuration())->toBe(5000);
        expect($reconstructed->getPosition())->toBe('bottom-left');
        expect($reconstructed->isPersistent())->toBeTrue();
        expect($reconstructed->getIcon())->toBe('star');
        expect($reconstructed->getActions())->toHaveCount(1);
        expect($reconstructed->getActions()[0]->getLabel())->toBe('Click Me');
    });
});

describe('Toast Facade', function () {
    test('uses facade for quick toast creation', function () {
        $toast = Toast::success('Facade Test', 'This works!');

        expect($toast)->toBeInstanceOf(ToastNotification::class);
        expect($toast->getType())->toBe('success');
        expect($toast->getTitle())->toBe('Facade Test');
        expect($toast->getBody())->toBe('This works!');
    });

    test('sends toast through facade', function () {
        Toast::info('Facade Send Test')->send();

        $manager = app(ToastManager::class);
        $toasts = $manager->getToasts();

        expect($toasts)->toHaveCount(1);
        expect($toasts->first()->getTitle())->toBe('Facade Send Test');
    });

    test('manages toasts through facade', function () {
        Toast::success('Test 1')->send();
        Toast::error('Test 2')->send();

        expect(Toast::getToasts())->toHaveCount(2);

        Toast::clear();

        expect(Toast::getToasts())->toHaveCount(0);
    });
});

describe('Toast Actions', function () {
    test('creates action with all properties', function () {
        $action = ToastAction::make('Test Action')
            ->url('/test-url')
            ->icon('test-icon')
            ->class(['class1', 'class2'])
            ->shouldCloseToast(false);

        expect($action->getLabel())->toBe('Test Action');
        expect($action->getUrl())->toBe('/test-url');
        expect($action->getIcon())->toBe('test-icon');
        expect($action->getClasses())->toBe(['class1', 'class2']);
        expect($action->getShouldCloseToast())->toBeFalse();
    });

    test('converts action to array correctly', function () {
        $action = ToastAction::make('Test')
            ->url('/test')
            ->icon('icon')
            ->class('btn btn-primary')
            ->shouldCloseToast(true);

        $array = $action->toArray();

        expect($array)->toHaveKeys([
            'label', 'url', 'icon', 'classes', 'should_close_toast'
        ]);

        expect($array['label'])->toBe('Test');
        expect($array['url'])->toBe('/test');
        expect($array['icon'])->toBe('icon');
        expect($array['classes'])->toBe(['btn', 'btn-primary']);
        expect($array['should_close_toast'])->toBeTrue();
    });
});

describe('Livewire Toast Container Integration', function () {
    test('toast container component loads without errors', function () {
        $component = Livewire::test(ToastContainer::class);

        $component->assertOk();
    });

    test('displays toasts through computed property', function () {
        // Add some toasts
        Toast::success('Success Toast', 'This is a success message')->send();
        Toast::error('Error Toast', 'This is an error message')->send();

        $component = Livewire::test(ToastContainer::class);

        // Check that toasts are accessible by calling the component method directly
        $toastsByPosition = $component->get('toastsByPosition');

        expect($toastsByPosition)->not->toBeEmpty();
        expect($toastsByPosition->flatten())->toHaveCount(2);
    });

    test('removes toast via livewire method', function () {
        $toast = Toast::info('Test Toast')->send();
        $toastId = $toast->getId();

        $component = Livewire::test(ToastContainer::class);

        // Verify toast exists
        expect($component->get('toastsByPosition')->flatten())->toHaveCount(1);

        // Remove the toast
        $component->call('removeToast', $toastId);

        // Verify toast is removed
        expect($component->get('toastsByPosition')->flatten())->toHaveCount(0);
    });

    test('clears all toasts via livewire method', function () {
        Toast::success('Toast 1')->send();
        Toast::error('Toast 2')->send();
        Toast::warning('Toast 3')->send();

        $component = Livewire::test(ToastContainer::class);

        // Verify toasts exist
        expect($component->get('toastsByPosition')->flatten())->toHaveCount(3);

        // Clear all toasts
        $component->call('clearAllToasts');

        // Verify all toasts are cleared
        expect($component->get('toastsByPosition')->flatten())->toHaveCount(0);
    });

    test('handles toast action via livewire method', function () {
        $component = Livewire::test(ToastContainer::class);

        $actionData = [
            'label' => 'Test Action',
            'url' => '/test',
            'should_close_toast' => true
        ];

        $component->call('handleToastAction', 'test-toast-id', $actionData)
            ->assertDispatched('toast-action-clicked', [
                'toastId' => 'test-toast-id',
                'actionData' => $actionData
            ]);
    });
});

describe('Helper Functions', function () {
    test('main toast helper works correctly', function () {
        // Test without parameters (returns manager)
        $manager = toast();
        expect($manager)->toBeInstanceOf(ToastManager::class);

        // Test with parameters (creates info toast)
        $toast = toast('Helper Test', 'This is a test message');
        expect($toast)->toBeInstanceOf(ToastNotification::class);
        expect($toast->getType())->toBe('info');
        expect($toast->getTitle())->toBe('Helper Test');
        expect($toast->getBody())->toBe('This is a test message');
    });

    test('specific toast type helpers work correctly', function () {
        $successToast = toast_success('Success!', 'It worked!');
        expect($successToast->getType())->toBe('success');

        $errorToast = toast_error('Error!', 'Something failed!');
        expect($errorToast->getType())->toBe('error');

        $warningToast = toast_warning('Warning!', 'Be careful!');
        expect($warningToast->getType())->toBe('warning');

        $infoToast = toast_info('Info!', 'Here\'s some info!');
        expect($infoToast->getType())->toBe('info');
    });

    test('helper functions can send toasts', function () {
        toast_success('Helper Success')->send();

        $manager = app(ToastManager::class);
        $toasts = $manager->getToasts();

        expect($toasts)->toHaveCount(1);
        expect($toasts->first()->getType())->toBe('success');
        expect($toasts->first()->getTitle())->toBe('Helper Success');
    });
});

describe('Configuration Integration', function () {
    test('uses default configuration values', function () {
        $toast = ToastNotification::make();

        expect($toast->getPosition())->toBe(config('toasts.defaults.position'));
        expect($toast->getDuration())->toBe(config('toasts.defaults.duration'));
        expect($toast->getType())->toBe(config('toasts.defaults.type'));
        expect($toast->isClosable())->toBe(config('toasts.defaults.closable'));
        expect($toast->isPersistent())->toBe(config('toasts.defaults.persistent'));
    });

    test('gets type configuration correctly', function () {
        $successToast = ToastNotification::success()->title('Test');
        $typeConfig = $successToast->getTypeConfig();

        expect($typeConfig)->toHaveKeys([
            'icon', 'background', 'border', 'text', 'icon_color', 'close_hover'
        ]);

        expect($typeConfig['icon'])->toBe('circle-check');
    });

    test('gets position configuration correctly', function () {
        $toast = ToastNotification::make()->position('top-right');
        $positionConfig = $toast->getPositionConfig();

        expect($positionConfig)->toHaveKeys(['container', 'alignment']);
        expect($positionConfig['container'])->toContain('fixed top-4 right-4');
    });
});

describe('Toast Manager Edge Cases', function () {
    test('handles mixed action data types during reconstruction', function () {
        $manager = app(ToastManager::class);
        
        // Simulate the edge case where session might contain mixed action types
        $toastData = [
            'id' => 'test-id-123',
            'title' => 'Test Toast',
            'body' => 'Test Body',
            'type' => 'success',
            'position' => 'top-right',
            'closable' => true,
            'persistent' => false,
            'duration' => 4000,
            'icon' => null,
            'data' => [],
            'actions' => [
                // Simulate an array action (normal case)
                [
                    'label' => 'Array Action',
                    'url' => '/test',
                    'icon' => null,
                    'classes' => [],
                    'should_close_toast' => true,
                ],
                // Simulate a ToastAction object (edge case that was causing the error)
                ToastAction::make('Object Action')->url('/object')->icon('star'),
                // Simulate invalid action data (should be skipped)
                'invalid_action_data',
                // Simulate array without label (should be skipped)
                ['url' => '/no-label']
            ]
        ];
        
        // Use reflection to call the private reconstructToast method
        $reflection = new ReflectionClass($manager);
        $reconstructMethod = $reflection->getMethod('reconstructToast');
        $reconstructMethod->setAccessible(true);
        
        // This should not throw an error about using object as array
        $reconstructedToast = $reconstructMethod->invoke($manager, $toastData);
        
        expect($reconstructedToast)->toBeInstanceOf(\App\UI\Toasts\Contracts\ToastContract::class);
        expect($reconstructedToast->getTitle())->toBe('Test Toast');
        expect($reconstructedToast->getActions())->toHaveCount(2); // Only valid actions should be added
        expect($reconstructedToast->getActions()[0]->getLabel())->toBe('Array Action');
        expect($reconstructedToast->getActions()[1]->getLabel())->toBe('Object Action');
    });
});