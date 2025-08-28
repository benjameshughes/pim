<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Actions\Import\SimpleImportAction;
use App\Events\Products\ProductImportProgress;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Event;

// Create a minimal Laravel app for testing
$app = new Application(__DIR__);

// Simple test to see what events are dispatched
$events = [];
Event::listen(ProductImportProgress::class, function ($event) use (&$events) {
    $events[] = [
        'action' => $event->action,
        'message' => $event->message,
        'processed' => $event->processed,
        'total' => $event->total
    ];
});

// Create test CSV
$csvContent = "sku,title,price\nTEST001-RED,Test Product Red,19.99\nTEST001-BLUE,Test Product Blue,21.99";
$filePath = __DIR__ . '/debug_test.csv';
file_put_contents($filePath, $csvContent);

// Run import
$action = new SimpleImportAction();
$result = $action->execute([
    'file' => $filePath,
    'mappings' => ['sku' => 0, 'title' => 1, 'price' => 2, 'barcode' => '', 'brand' => ''],
    'importId' => 'debug_test',
]);

// Print events
echo "Events dispatched:\n";
foreach ($events as $i => $event) {
    echo ($i + 1) . ". {$event['action']}: {$event['message']} ({$event['processed']}/{$event['total']})\n";
}

// Clean up
unlink($filePath);