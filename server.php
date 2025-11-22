<?php

/**
 * Router script for PHP built-in development server
 * This ensures all requests go through index.php
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$filePath = __DIR__ . '/public' . $uri;

// Serve static files directly if they exist and are files
if ($uri !== '/' && file_exists($filePath) && is_file($filePath)) {
    return false; // Serve the requested resource as-is
}

// Route everything else through index.php
$_SERVER['SCRIPT_NAME'] = '/index.php';
chdir(__DIR__ . '/public');
require __DIR__ . '/public/index.php';
