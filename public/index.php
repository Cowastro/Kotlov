<?php

use App\Support\HttpCapacity;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Refuse excess work before Composer, sessions, authentication or database access.
require_once __DIR__.'/../app/Support/HttpCapacity.php';
$capacity = new HttpCapacity(__DIR__.'/../storage/framework/http-capacity');
$capacityLease = $capacity->acquire($_SERVER);
if ($capacityLease === null) {
    $busy = $capacity->busyResponse($_SERVER);
    http_response_code(503);
    header('Content-Type: '.$busy['type']);
    header('Retry-After: 5');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
        echo $busy['body'];
    }
    exit;
}
// Also covers fatal errors, streamed downloads and Laravel termination callbacks.
register_shutdown_function(static fn () => HttpCapacity::release($capacityLease));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
