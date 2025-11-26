<?php

// Prevent loading config.php multiple times
if (defined('FOS_CONFIG_LOADED')) {
    return;
}
define('FOS_CONFIG_LOADED', true);

require 'vendor/autoload.php';

// Load environment variables first (needed for session config)
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Load helper functions
require_once 'helpers.php';

// Configure session cookies based on environment
// Only use secure cookies in production with HTTPS
$isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
           (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
$isProduction = env('APP_ENV', 'local') === 'production';
$useSecureCookies = $isHttps && $isProduction;

// Override php.ini session.cookie_secure setting for development
ini_set('session.cookie_secure', $useSecureCookies ? '1' : '0');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

session_start();

// Set timezone from environment
date_default_timezone_set(env('APP_TIMEZONE', 'America/Chicago'));

include('functions.php');

use Illuminate\Database\Capsule\Manager as Capsule;

// Simple template class with basic Blade syntax support
if (!class_exists('SimpleTemplate')) {
class SimpleTemplate {
    private $viewsPath;
    private $cachePath;

    public function __construct($viewsPath, $cachePath = null) {
        $this->viewsPath = $viewsPath;
        $this->cachePath = $cachePath ?: sys_get_temp_dir();
    }

    public function view() {
        return $this;
    }

    public function make($view, $data = []) {
        return new class($view, $data, $this->viewsPath, $this->cachePath) {
            private $view;
            private $data;
            private $viewsPath;
            private $cachePath;

            public function __construct($view, $data, $viewsPath, $cachePath) {
                $this->view = $view;
                $this->data = $data;
                $this->viewsPath = $viewsPath;
                $this->cachePath = $cachePath;
            }

            public function with($key, $value) {
                $this->data[$key] = $value;
                return $this;
            }

            public function render() {
                $filePath = $this->viewsPath . '/' . $this->view . '.blade.php';
                $content = file_get_contents($filePath);

                // Compile basic Blade syntax
                $content = $this->compileBlade($content);

                // Create cached PHP file
                $cacheFile = $this->cachePath . '/' . md5($this->view) . '.php';
                file_put_contents($cacheFile, $content);

                // Render
                extract($this->data);
                ob_start();
                include $cacheFile;
                return ob_get_clean();
            }

            private function compileBlade($content) {
                // Handle @extends
                if (preg_match("/@extends\('(.+?)'\)/", $content, $matches)) {
                    $layout = $this->viewsPath . '/' . $matches[1] . '.blade.php';
                    $layoutContent = file_get_contents($layout);

                    // Extract section content
                    preg_match("/@section\('content'\)(.*?)@endsection/s", $content, $sectionMatches);
                    $sectionContent = $sectionMatches[1] ?? '';

                    // Replace @yield in layout
                    $content = str_replace("@yield('content')", $sectionContent, $layoutContent);
                }

                // Remove remaining Blade directives that we don't need
                $content = preg_replace("/@extends\('(.+?)'\)/", '', $content);
                $content = preg_replace("/@section\('content'\)/", '', $content);
                $content = preg_replace("/@endsection/", '', $content);

                // Convert Blade echo syntax using str_replace instead of regex with callbacks
                $content = $this->compileEchos($content);

                return $content;
            }

            private function compileEchos($value) {
                $echoPattern = '/\{\{\s*(.+?)\s*\}\}/';
                preg_match_all($echoPattern, $value, $matches, PREG_SET_ORDER);
                foreach ($matches as $match) {
                    $replacement = '<?php echo htmlspecialchars(' . $match[1] . '); ?>';
                    $value = str_replace($match[0], $replacement, $value);
                }

                $rawPattern = '/\{!!\s*(.+?)\s*!!\}/';
                preg_match_all($rawPattern, $value, $matches, PREG_SET_ORDER);
                foreach ($matches as $match) {
                    $replacement = '<?php echo ' . $match[1] . '; ?>';
                    $value = str_replace($match[0], $replacement, $value);
                }

                return $value;
            }
        };
    }
}
}

$views = __DIR__ . '/' . env('VIEWS_PATH', 'views');
$cache = __DIR__ . '/' . env('CACHE_PATH', 'cache');
$template = new SimpleTemplate($views, $cache);

$capsule = new Capsule;
$capsule->addConnection([
    'driver'    => env('DB_CONNECTION', 'mysql'),
    'host'      => env('DB_HOST', 'localhost'),
    'database'  => env('DB_DATABASE', 'fos_dev'),
    'username'  => env('DB_USERNAME', 'fos_dev'),
    'password'  => env('DB_PASSWORD', 'fos_dev_password'),
    'charset'   => env('DB_CHARSET', 'utf8mb4'),
    'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
    'prefix'    => env('DB_PREFIX', ''),
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

// Configure Laravel Container for Facades
use Illuminate\Container\Container;

$app = Container::getInstance();

// Register database manager for DB facade
$app->instance('db', $capsule->getDatabaseManager());

// Configure Laravel Encryption (Crypt facade)
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Facade;

// Get the encryption key from environment
$appKey = env('APP_KEY');
if (!$appKey) {
    throw new RuntimeException('No application encryption key has been specified. Add APP_KEY to your .env file.');
}

// Parse the key (Laravel uses base64: prefix)
if (strpos($appKey, 'base64:') === 0) {
    $key = base64_decode(substr($appKey, 7));
} else {
    $key = $appKey;
}

// Create and register the encrypter
$encrypter = new Encrypter($key, 'AES-256-CBC');
$app->instance('encrypter', $encrypter);

// Set up Facade application
Facade::setFacadeApplication($app);

// Load port configuration
$portsConfigFile = __DIR__ . '/config/ports.php';
if (file_exists($portsConfigFile)) {
    $portsConfig = include $portsConfigFile;
    define('FOS_WEB_PORT', $portsConfig['web_port']);
    define('FOS_STREAM_PORT', $portsConfig['stream_port']);
    define('FOS_RTMP_PORT', $portsConfig['rtmp_port']);
} else {
    // Fallback to environment variables, then default ports
    define('FOS_WEB_PORT', env('APP_PORT', 7777));
    define('FOS_STREAM_PORT', env('STREAMING_PORT', 8000));
    define('FOS_RTMP_PORT', env('RTMP_PORT', 1935));
}