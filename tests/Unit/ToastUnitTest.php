<?php

namespace Tests\Unit;

use App\UI\Toasts\Toast;
use App\UI\Toasts\ToastAction;
use App\UI\Toasts\ToastManager;
use Tests\TestCase;

class ToastUnitTest extends TestCase
{
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
     * Test that ToastManager can convert to array
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
        $this->assertCount(1, $array['toasts']);
    }

    /**
     * Test static factory methods
     */
    public function test_static_factory_methods()
    {
        $successToast = Toast::success('Success Test');
        $this->assertEquals('Success Test', $successToast->getTitle());
        $this->assertEquals('success', $successToast->getType());

        $errorToast = Toast::error('Error Test');
        $this->assertEquals('Error Test', $errorToast->getTitle());
        $this->assertEquals('error', $errorToast->getType());

        $warningToast = Toast::warning('Warning Test');
        $this->assertEquals('Warning Test', $warningToast->getTitle());
        $this->assertEquals('warning', $warningToast->getType());

        $infoToast = Toast::info('Info Test');
        $this->assertEquals('Info Test', $infoToast->getTitle());
        $this->assertEquals('info', $infoToast->getType());
    }
}