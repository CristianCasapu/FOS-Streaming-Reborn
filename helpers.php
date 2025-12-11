<?php
/**
 * FOS-Streaming Helper Functions
 *
 * Global helper functions for easier access to environment variables
 * and application configuration.
 */

if (!function_exists('env')) {
    /**
     * Get an environment variable with optional default
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function env(string $key, $default = null)
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        // Handle boolean values
        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return null;
        }

        // Handle quoted strings
        if (preg_match('/\A([\'"])(.*)\1\z/', $value, $matches)) {
            return $matches[2];
        }

        return $value;
    }
}

if (!function_exists('config')) {
    /**
     * Get configuration value using dot notation
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function config(string $key, $default = null)
    {
        $config = [
            'app.name' => env('APP_NAME', 'FOS Streaming'),
            'app.env' => env('APP_ENV', 'production'),
            'app.debug' => env('APP_DEBUG', false),
            'app.url' => env('APP_URL', 'http://localhost'),
            'app.timezone' => env('APP_TIMEZONE', 'UTC'),

            'database.driver' => env('DB_CONNECTION', 'mysql'),
            'database.host' => env('DB_HOST', 'localhost'),
            'database.port' => env('DB_PORT', 3306),
            'database.name' => env('DB_DATABASE', 'fos'),
            'database.username' => env('DB_USERNAME', 'root'),
            'database.password' => env('DB_PASSWORD', ''),
            'database.charset' => env('DB_CHARSET', 'utf8mb4'),
            'database.collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'database.prefix' => env('DB_PREFIX', ''),

            'cache.driver' => env('CACHE_DRIVER', 'file'),
            'cache.path' => env('CACHE_PATH', 'cache'),

            'session.driver' => env('SESSION_DRIVER', 'file'),
            'session.lifetime' => env('SESSION_LIFETIME', 120),

            'mail.mailer' => env('MAIL_MAILER', 'smtp'),
            'mail.host' => env('MAIL_HOST', 'localhost'),
            'mail.port' => env('MAIL_PORT', 1025),
            'mail.username' => env('MAIL_USERNAME'),
            'mail.password' => env('MAIL_PASSWORD'),
            'mail.encryption' => env('MAIL_ENCRYPTION'),
            'mail.from.address' => env('MAIL_FROM_ADDRESS', 'noreply@fos-streaming.local'),
            'mail.from.name' => env('MAIL_FROM_NAME', env('APP_NAME', 'FOS Streaming')),

            'streaming.web_ip' => env('WEB_IP', '*'),
            'streaming.auth' => env('STREAMING_AUTH', true),
            'streaming.port' => env('STREAMING_PORT', 8000),
            'streaming.rtmp_port' => env('RTMP_PORT', 1935),
            'streaming.web_port' => env('APP_PORT', 7777),

            'ffmpeg.path' => env('FFMPEG_PATH', '/usr/local/bin/ffmpeg'),
            'ffmpeg.probe_path' => env('FFPROBE_PATH', '/usr/local/bin/ffprobe'),

            'hls.path' => env('HLS_PATH', '/var/www/html/hl'),
            'hls.url_prefix' => env('HLS_URL_PREFIX', 'http://localhost:8000/live'),

            'security.rate_limit' => env('RATE_LIMIT_ENABLED', true),
            'security.max_login_attempts' => env('MAX_LOGIN_ATTEMPTS', 5),
            'security.login_timeout' => env('LOGIN_TIMEOUT', 900),
            'security.logging' => env('SECURITY_LOGGING', true),
            'security.csrf_timeout' => env('CSRF_TOKEN_TIMEOUT', 7200),

            'logging.channel' => env('LOG_CHANNEL', 'daily'),
            'logging.level' => env('LOG_LEVEL', 'info'),
        ];

        return $config[$key] ?? $default;
    }
}

if (!function_exists('app_path')) {
    /**
     * Get the path to the application directory
     *
     * @param string $path
     * @return string
     */
    function app_path(string $path = ''): string
    {
        $basePath = dirname(__FILE__);
        return $basePath . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }
}

if (!function_exists('base_path')) {
    /**
     * Get the path to the base directory
     *
     * @param string $path
     * @return string
     */
    function base_path(string $path = ''): string
    {
        $basePath = dirname(__FILE__);
        return $basePath . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }
}

if (!function_exists('storage_path')) {
    /**
     * Get the path to the storage directory
     *
     * @param string $path
     * @return string
     */
    function storage_path(string $path = ''): string
    {
        $basePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'storage';
        return $basePath . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }
}

if (!function_exists('public_path')) {
    /**
     * Get the path to the public directory
     *
     * @param string $path
     * @return string
     */
    function public_path(string $path = ''): string
    {
        $basePath = dirname(__FILE__);
        return $basePath . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }
}

if (!function_exists('asset')) {
    /**
     * Generate an asset path for the application
     *
     * @param string $path
     * @param bool $secure
     * @return string
     */
    function asset(string $path, bool $secure = null): string
    {
        $scheme = ($secure ?? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')) ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . $host . '/' . ltrim($path, '/');
    }
}

if (!function_exists('url')) {
    /**
     * Generate a URL for the application
     *
     * @param string $path
     * @return string
     */
    function url(string $path = ''): string
    {
        $base = env('APP_URL', 'http://localhost');
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('dd')) {
    /**
     * Dump and die
     *
     * @param mixed ...$vars
     * @return void
     */
    function dd(...$vars): void
    {
        foreach ($vars as $var) {
            var_dump($var);
        }
        die(1);
    }
}

if (!function_exists('dump')) {
    /**
     * Dump the passed variables
     *
     * @param mixed ...$vars
     * @return void
     */
    function dump(...$vars): void
    {
        foreach ($vars as $var) {
            var_dump($var);
        }
    }
}

if (!function_exists('abort')) {
    /**
     * Abort the application with an HTTP status code
     *
     * @param int $code
     * @param string $message
     * @return void
     */
    function abort(int $code, string $message = ''): void
    {
        http_response_code($code);
        if ($message) {
            echo $message;
        }
        die();
    }
}

if (!function_exists('old')) {
    /**
     * Retrieve an old input item
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function old(string $key, $default = null)
    {
        return $_SESSION['_old_input'][$key] ?? $default;
    }
}

if (!function_exists('flash')) {
    /**
     * Flash data to the session
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    function flash(string $key, $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }
}

if (!function_exists('session')) {
    /**
     * Get / set the specified session value
     *
     * @param string|array|null $key
     * @param mixed $default
     * @return mixed
     */
    function session($key = null, $default = null)
    {
        if (is_null($key)) {
            return $_SESSION;
        }

        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $_SESSION[$k] = $v;
            }
            return null;
        }

        return $_SESSION[$key] ?? $default;
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Get the CSRF token value
     *
     * @return string
     */
    function csrf_token(): string
    {
        if (!isset($_SESSION['_token'])) {
            $_SESSION['_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_token'];
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Generate a CSRF token field
     *
     * @return string
     */
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . csrf_token() . '">';
    }
}

if (!function_exists('method_field')) {
    /**
     * Generate a form method field
     *
     * @param string $method
     * @return string
     */
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . strtoupper($method) . '">';
    }
}

if (!function_exists('vite')) {
    /**
     * Get Vite asset paths from manifest
     *
     * Returns HTML tags for CSS and JS assets with proper hashed filenames.
     * Automatically detects development vs production mode:
     * - Development: APP_ENV=local and Vite dev server running (hot module reload)
     * - Production: Reads hashed filenames from Vite manifest
     *
     * @param string|array $entrypoints Entry point(s) like 'resources/js/app.js'
     * @return string HTML tags for the assets
     */
    function vite($entrypoints): string
    {
        $entrypoints = (array) $entrypoints;
        $basePath = dirname(__FILE__);
        $manifestPath = $basePath . '/public/build/.vite/manifest.json';
        $hotFilePath = $basePath . '/public/hot';

        // Check for development mode:
        // 1. APP_ENV is 'local' or 'development'
        // 2. Hot file exists (created by `npm run dev`)
        $appEnv = env('APP_ENV', 'production');
        $isDev = in_array($appEnv, ['local', 'development']) &&
                 file_exists($hotFilePath);

        // Development mode - use Vite dev server
        if ($isDev) {
            // Read dev server URL from hot file (default to localhost:5173)
            $devServerUrl = trim(file_get_contents($hotFilePath)) ?: 'http://localhost:5173';
            $devServerUrl = rtrim($devServerUrl, '/');

            $html = '<script type="module" src="' . $devServerUrl . '/@vite/client"></script>' . "\n";
            foreach ($entrypoints as $entry) {
                $html .= '<script type="module" src="' . $devServerUrl . '/' . $entry . '"></script>' . "\n";
            }
            return $html;
        }

        // Production mode - read from manifest
        if (!file_exists($manifestPath)) {
            // Fallback if manifest doesn't exist - return empty to avoid broken pages
            // This can happen if npm run build wasn't run
            return '<!-- Vite manifest not found. Run: npm run build -->' . "\n";
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        if (!$manifest) {
            return '<!-- Vite manifest parse error -->' . "\n";
        }

        $html = '';
        $loadedCss = [];

        foreach ($entrypoints as $entry) {
            if (!isset($manifest[$entry])) {
                $html .= '<!-- Vite entry not found: ' . htmlspecialchars($entry) . ' -->' . "\n";
                continue;
            }

            $asset = $manifest[$entry];

            // Load CSS files
            if (isset($asset['css'])) {
                foreach ($asset['css'] as $css) {
                    if (!in_array($css, $loadedCss)) {
                        $html .= '<link rel="stylesheet" href="/build/' . $css . '">' . "\n";
                        $loadedCss[] = $css;
                    }
                }
            }

            // Load the JS file
            if (isset($asset['file'])) {
                $html .= '<script type="module" src="/build/' . $asset['file'] . '"></script>' . "\n";
            }
        }

        return $html;
    }
}
