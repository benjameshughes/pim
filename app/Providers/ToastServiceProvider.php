<?php

namespace App\Providers;

use App\Toasts\Toast;
use App\Toasts\ToastManager;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class ToastServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge the toast configuration
        $this->mergeConfigFrom(__DIR__.'/../../config/toasts.php', 'toasts');

        // Register the ToastManager as a singleton
        $this->app->singleton(ToastManager::class, function ($app) {
            return new ToastManager($app['session']);
        });

        // Register Toast facade alias
        $this->app->alias(ToastManager::class, 'toasts');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Publish the configuration file
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/toasts.php' => config_path('toasts.php'),
            ], 'toasts-config');
        }

        // Register Blade directives
        $this->registerBladeDirectives();

        // Register helper functions
        $this->registerHelperFunctions();
    }

    /**
     * Register custom Blade directives for toasts.
     */
    protected function registerBladeDirectives(): void
    {
        // @toastContainer directive
        Blade::directive('toastContainer', function ($expression) {
            return "<?php echo view('components.toast-container')->render(); ?>";
        });

        // @toast directive for quick toast creation in Blade
        Blade::directive('toast', function ($expression) {
            return "<?php app('toasts')->add($expression); ?>";
        });
    }

    /**
     * Register global helper functions.
     */
    protected function registerHelperFunctions(): void
    {
        // Create a helper file that can be loaded once
        $helperFile = __DIR__ . '/../../helpers/toast_helpers.php';
        
        if (!file_exists($helperFile)) {
            // We'll create the helper functions in a separate file to avoid redeclaration issues
            $this->createHelperFile($helperFile);
        }
        
        if (file_exists($helperFile)) {
            require_once $helperFile;
        }
    }

    /**
     * Create the helper file with toast functions.
     */
    protected function createHelperFile(string $path): void
    {
        $directory = dirname($path);
        
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        $content = <<<'PHP'
<?php

if (!function_exists('toast')) {
    /**
     * Create a new toast notification.
     *
     * @param string|null $title
     * @param string|null $body
     * @return \App\Toasts\Toast|\App\Toasts\ToastManager
     */
    function toast(?string $title = null, ?string $body = null)
    {
        $toastManager = app(\App\Toasts\ToastManager::class);

        if ($title === null) {
            return $toastManager;
        }

        return $toastManager->info($title, $body);
    }
}

if (!function_exists('toast_success')) {
    /**
     * Create a success toast notification.
     *
     * @param string $title
     * @param string|null $body
     * @return \App\Toasts\Toast
     */
    function toast_success(string $title, ?string $body = null): \App\Toasts\Toast
    {
        return app(\App\Toasts\ToastManager::class)->success($title, $body);
    }
}

if (!function_exists('toast_error')) {
    /**
     * Create an error toast notification.
     *
     * @param string $title
     * @param string|null $body
     * @return \App\Toasts\Toast
     */
    function toast_error(string $title, ?string $body = null): \App\Toasts\Toast
    {
        return app(\App\Toasts\ToastManager::class)->error($title, $body);
    }
}

if (!function_exists('toast_warning')) {
    /**
     * Create a warning toast notification.
     *
     * @param string $title
     * @param string|null $body
     * @return \App\Toasts\Toast
     */
    function toast_warning(string $title, ?string $body = null): \App\Toasts\Toast
    {
        return app(\App\Toasts\ToastManager::class)->warning($title, $body);
    }
}

if (!function_exists('toast_info')) {
    /**
     * Create an info toast notification.
     *
     * @param string $title
     * @param string|null $body
     * @return \App\Toasts\Toast
     */
    function toast_info(string $title, ?string $body = null): \App\Toasts\Toast
    {
        return app(\App\Toasts\ToastManager::class)->info($title, $body);
    }
}
PHP;

        file_put_contents($path, $content);
    }
}