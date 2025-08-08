<?php

namespace Tests;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Support\Collection;
use Laravel\Dusk\Browser;
use Laravel\Dusk\TestCase as BaseTestCase;
use PHPUnit\Framework\Attributes\BeforeClass;

abstract class DuskTestCase extends BaseTestCase
{
    /**
     * Prepare for Dusk test execution.
     */
    #[BeforeClass]
    public static function prepare(): void
    {
        if (! static::runningInSail()) {
            static::startChromeDriver(['--port=9515']);
        }
        
        // Register custom browser macro for checking console errors
        Browser::macro('assertNoConsoleErrors', function () {
            $logs = $this->driver->manage()->getLog('browser');
            $errors = collect($logs)->filter(function ($log) {
                if ($log['level'] !== 'SEVERE') {
                    return false;
                }
                
                // Ignore non-critical errors
                $ignoredPatterns = [
                    '/favicon\.ico.*Failed to load resource/',  // Missing favicon
                    '/net::ERR_FAILED.*favicon\.ico/',         // Favicon network errors
                ];
                
                foreach ($ignoredPatterns as $pattern) {
                    if (preg_match($pattern, $log['message'])) {
                        return false;
                    }
                }
                
                return true;
            });
            
            if ($errors->isNotEmpty()) {
                throw new \PHPUnit\Framework\AssertionFailedError(
                    'Console errors found: ' . $errors->pluck('message')->implode(', ')
                );
            }
            
            return $this;
        });
    }

    /**
     * Create the RemoteWebDriver instance.
     */
    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments(collect([
            $this->shouldStartMaximized() ? '--start-maximized' : '--window-size=1920,1080',
            '--disable-search-engine-choice-screen',
            '--disable-smooth-scrolling',
            '--enable-logging',
            '--log-level=0',
        ])->unless($this->hasHeadlessDisabled(), function (Collection $items) {
            return $items->merge([
                '--disable-gpu',
                '--headless=new',
            ]);
        })->all());

        $capabilities = DesiredCapabilities::chrome()->setCapability(
            ChromeOptions::CAPABILITY, $options
        );
        
        // Enable browser logging
        $capabilities->setCapability('goog:loggingPrefs', [
            'browser' => 'ALL'
        ]);

        return RemoteWebDriver::create(
            $_ENV['DUSK_DRIVER_URL'] ?? env('DUSK_DRIVER_URL') ?? 'http://localhost:9515',
            $capabilities
        );
    }
}
