<?php

/**
 * FOS Streaming v70 - Front Controller
 *
 * This file serves as the entry point for all requests.
 * It loads the application configuration and routes requests.
 */

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Check If The Application Is Under Maintenance
|--------------------------------------------------------------------------
*/

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
*/

require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Load Application Configuration
|--------------------------------------------------------------------------
*/

require __DIR__.'/../config.php';

/*
|--------------------------------------------------------------------------
| Handle The Request
|--------------------------------------------------------------------------
*/

// Get the request URI
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Parse the URI to remove query string
$uri = parse_url($requestUri, PHP_URL_PATH);

// Get admin path from environment (default: /admin)
// Use env() helper which checks $_ENV, $_SERVER, and getenv()
$adminPath = env('ADMIN_PATH', '/admin');
$adminPath = rtrim($adminPath, '/'); // Remove trailing slash

// Check if this is an admin route
$isAdminRoute = $uri === $adminPath || strpos($uri, $adminPath . '/') === 0;

// Check if this is an admin API call (e.g., /adminx/api/auth.php)
$isAdminApi = $isAdminRoute && strpos($uri, $adminPath . '/api/') === 0;

// Handle admin API requests - route to the actual PHP files in /public/admin/api/
if ($isAdminApi) {
    // Always convert admin path to /admin/api/ for the actual file location
    // /adminx/api/auth.php -> /admin/api/auth.php (maps to public/admin/api/auth.php)
    $apiPath = str_replace($adminPath . '/api/', '/admin/api/', $uri);
    $apiFile = __DIR__ . $apiPath;

    if (is_file($apiFile) && pathinfo($apiFile, PATHINFO_EXTENSION) === 'php') {
        require $apiFile;
        exit;
    }

    // API file not found
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'API endpoint not found', 'debug' => [
        'requested' => $uri,
        'converted' => $apiPath,
        'file_path' => $apiFile,
        'file_exists' => file_exists($apiFile),
        'admin_path' => $adminPath
    ]]);
    exit;
}

// Simple router for admin UI
if ($isAdminRoute) {
    // Admin UI - Serve Vue SPA
    require __DIR__.'/app.html';
    exit;
}

// Check if file exists in public directory (CSS, JS, images, etc.)
$filePath = __DIR__ . $uri;
if (is_file($filePath)) {
    // Serve static file
    return false;
}

// Check for API routes
if (strpos($uri, '/api/') === 0) {
    // API routes - let them fall through to legacy routing
    $rootFile = __DIR__ . '/../' . ltrim($uri, '/');

    if (is_file($rootFile) && pathinfo($rootFile, PATHINFO_EXTENSION) === 'php') {
        require $rootFile;
        exit;
    }
}

// Check for legacy PHP files in root directory
$rootFile = __DIR__ . '/../' . ltrim($uri, '/');
if (is_file($rootFile) && pathinfo($rootFile, PATHINFO_EXTENSION) === 'php') {
    require $rootFile;
    exit;
}

// Try common legacy file patterns
$possibleFiles = [
    __DIR__ . '/../' . ltrim($uri, '/') . '.php',
    __DIR__ . '/../' . ltrim($uri, '/'),
];

foreach ($possibleFiles as $file) {
    if (is_file($file)) {
        require $file;
        exit;
    }
}

// If no file found, serve the subscriber SPA for all other routes
// This enables Vue Router history mode to work
require __DIR__.'/subscriber.html';
exit;
