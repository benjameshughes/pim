 <?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;


 return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // 🎭 LEGENDARY webhook middleware registration
        $middleware->alias([
            'shopify.webhook' => \App\Http\Middleware\ShopifyWebhookMiddleware::class,
        ]);
        
        // Add request logging for debugging - TEMPORARILY DISABLED FOR TESTING
        // $middleware->web(\App\Http\Middleware\LogRequests::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
