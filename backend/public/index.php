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

// Custom Environment Loading based on Host
$envFile = '.env';
if (isset($_SERVER['HTTP_HOST'])) {
    if (str_contains($_SERVER['HTTP_HOST'], 'localhost') || str_contains($_SERVER['HTTP_HOST'], '172.19.0.2:8000')) {
        if (file_exists(__DIR__ . '/../.env.local')) {
            $envFile = '.env.local';
        }
    } elseif (str_contains($_SERVER['HTTP_HOST'], 'easypagamentos.com')) {
        if (file_exists(__DIR__ . '/../.env.production')) {
            $envFile = '.env.production';
        }
    }
}

// Load environment variables manually if a specific file is selected
if ($envFile !== '.env') {
    try {
        $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__.'/../', $envFile);
        $dotenv->load();
    } catch (\Exception $e) {
        // Ignore errors, let Laravel handle default .env loading
    }
}

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
