<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

// Nginx Subdirectory Fix: Remove incorrect URI injection from query string
$uri = $_SERVER['REQUEST_URI'] ?? '';
$parsedUri = parse_url($uri, PHP_URL_PATH);
if ($parsedUri) {
    foreach ($_GET as $key => $value) {
        if (($value === '' || $value === null) && str_starts_with($key, '/') && str_ends_with($parsedUri, $key)) {
            unset($_GET[$key]);
            $newQs = http_build_query($_GET);
            $_SERVER['QUERY_STRING'] = $newQs;
            $_SERVER['REQUEST_URI'] = $parsedUri . ($newQs ? '?' . $newQs : '');
            break;
        }
    }
}

$app->handleRequest(Request::capture());
