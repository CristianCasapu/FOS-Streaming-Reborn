<?php

namespace App\Services;

/**
 * PathDetectionService
 *
 * Auto-detects system paths, user/group, and binary locations
 * for streaming services configuration.
 */
class PathDetectionService
{
    /**
     * Get all auto-detected paths and system info
     *
     * @return array
     */
    public static function detectAll(): array
    {
        return [
            'user' => self::detectUser(),
            'group' => self::detectGroup(),
            'project_root' => self::detectProjectRoot(),
            'paths' => self::detectPaths(),
            'binaries' => self::detectBinaries(),
            'php_fpm' => self::detectPhpFpm(),
        ];
    }

    /**
     * Detect current system user
     *
     * @return string
     */
    public static function detectUser(): string
    {
        // Try multiple methods
        if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            $userInfo = posix_getpwuid(posix_geteuid());
            if ($userInfo && isset($userInfo['name'])) {
                return $userInfo['name'];
            }
        }

        // Fallback to environment
        $user = getenv('USER') ?: getenv('USERNAME');
        if ($user) {
            return $user;
        }

        // Fallback to whoami command
        $whoami = trim(shell_exec('whoami 2>/dev/null') ?? '');
        if ($whoami) {
            return $whoami;
        }

        return 'www-data'; // Default fallback
    }

    /**
     * Detect current user's primary group
     *
     * @return string
     */
    public static function detectGroup(): string
    {
        // Try posix functions
        if (function_exists('posix_getgrgid') && function_exists('posix_getegid')) {
            $groupInfo = posix_getgrgid(posix_getegid());
            if ($groupInfo && isset($groupInfo['name'])) {
                return $groupInfo['name'];
            }
        }

        // Fallback to id command
        $group = trim(shell_exec('id -gn 2>/dev/null') ?? '');
        if ($group) {
            return $group;
        }

        // Use same as user (common on Linux)
        return self::detectUser();
    }

    /**
     * Detect project root directory
     *
     * @return string
     */
    public static function detectProjectRoot(): string
    {
        // Use base_path() if available (Laravel helper)
        if (function_exists('base_path')) {
            return base_path();
        }

        // Fallback: traverse up from this file
        $dir = __DIR__;
        while ($dir !== '/' && $dir !== '') {
            if (file_exists($dir . '/composer.json') && file_exists($dir . '/config.php')) {
                return $dir;
            }
            $dir = dirname($dir);
        }

        // Last resort: use getcwd
        return getcwd() ?: '/var/www/html';
    }

    /**
     * Detect all relevant paths
     *
     * @return array
     */
    public static function detectPaths(): array
    {
        $root = self::detectProjectRoot();

        return [
            'streams_path' => $root . '/fospackv69/fos/streams',
            'logs_path' => $root . '/fospackv69/fos/logs',
            'nginx_config_path' => $root . '/fospackv69/fos/nginx/conf/nginx-streaming.conf',
            'nginx_binary_path' => $root . '/fospackv69/fos/nginx/sbin/nginx_fos',
            'php_fpm_config_path' => $root . '/fospackv69/fos/php/etc/php-fpm.conf',
            'php_fpm_pool_path' => $root . '/fospackv69/fos/php/etc/pool.d/www.conf',
            'php_fpm_binary_path' => self::findPhpFpmBinary($root),
            'public_path' => $root . '/public',
        ];
    }

    /**
     * Detect binary locations
     *
     * @return array
     */
    public static function detectBinaries(): array
    {
        $root = self::detectProjectRoot();

        return [
            'ffmpeg' => self::findBinary('ffmpeg', [
                '/usr/bin/ffmpeg',
                '/usr/local/bin/ffmpeg',
                $root . '/fospackv69/fos/bin/ffmpeg',
            ]),
            'ffprobe' => self::findBinary('ffprobe', [
                '/usr/bin/ffprobe',
                '/usr/local/bin/ffprobe',
                $root . '/fospackv69/fos/bin/ffprobe',
            ]),
            'nginx' => self::findBinary('nginx_fos', [
                $root . '/fospackv69/fos/nginx/sbin/nginx_fos',
            ]) ?: self::findBinary('nginx', [
                '/usr/sbin/nginx',
                '/usr/local/nginx/sbin/nginx',
            ]),
            'php_fpm' => self::findPhpFpmBinary($root),
        ];
    }

    /**
     * Find PHP-FPM binary
     *
     * @param string $root Project root
     * @return string|null
     */
    private static function findPhpFpmBinary(string $root): ?string
    {
        $candidates = [
            $root . '/fospackv69/fos/php/sbin/php-fpm',
            '/usr/sbin/php-fpm',
            '/usr/sbin/php-fpm8.4',
            '/usr/sbin/php-fpm8.3',
            '/usr/sbin/php-fpm8.2',
            '/usr/sbin/php-fpm8.1',
            '/usr/local/sbin/php-fpm',
        ];

        // Also try which command
        $which = trim(shell_exec('which php-fpm 2>/dev/null') ?? '');
        if ($which && file_exists($which)) {
            array_unshift($candidates, $which);
        }

        foreach ($candidates as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Find a binary by name
     *
     * @param string $name Binary name
     * @param array $candidates Candidate paths
     * @return string|null
     */
    private static function findBinary(string $name, array $candidates = []): ?string
    {
        // Check candidates first
        foreach ($candidates as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        // Try which command
        $which = trim(shell_exec("which {$name} 2>/dev/null") ?? '');
        if ($which && file_exists($which)) {
            return $which;
        }

        return null;
    }

    /**
     * Detect PHP-FPM Streaming configuration
     * Uses project-local socket for better portability
     *
     * @return array
     */
    public static function detectPhpFpm(): array
    {
        $root = self::detectProjectRoot();
        $user = self::detectUser();
        $group = self::detectGroup();

        return [
            'binary' => self::findPhpFpmBinary($root),
            'config_path' => $root . '/fospackv69/fos/php/etc/php-fpm-streaming.conf',
            'pool_path' => $root . '/fospackv69/fos/php/etc/pool.d-streaming/streaming.conf',
            'pid_file' => $root . '/fospackv69/fos/php/php-fpm-streaming.pid',
            'socket' => $root . '/fospackv69/fos/php/php-fpm-streaming.socket',
            'error_log' => $root . '/fospackv69/fos/logs/php-fpm-streaming-error.log',
            'slow_log' => $root . '/fospackv69/fos/logs/php-fpm-streaming-slow.log',
            'user' => $user,
            'group' => $group,
            'pm' => 'dynamic',
            'pm_max_children' => 50,
            'pm_start_servers' => 5,
            'pm_min_spare_servers' => 5,
            'pm_max_spare_servers' => 35,
            'pm_max_requests' => 500,
        ];
    }

    /**
     * Get PHP-FPM streaming socket path
     * Convenience method for other services
     *
     * @return string
     */
    public static function getPhpFpmStreamingSocket(): string
    {
        return self::detectProjectRoot() . '/fospackv69/fos/php/php-fpm-streaming.socket';
    }

    /**
     * Get PHP-FPM streaming PID file path
     *
     * @return string
     */
    public static function getPhpFpmStreamingPidFile(): string
    {
        return self::detectProjectRoot() . '/fospackv69/fos/php/php-fpm-streaming.pid';
    }

    /**
     * Check if a path exists and is writable
     *
     * @param string $path
     * @return array
     */
    public static function checkPath(string $path): array
    {
        return [
            'path' => $path,
            'exists' => file_exists($path),
            'writable' => is_writable($path),
            'readable' => is_readable($path),
            'is_dir' => is_dir($path),
            'is_file' => is_file($path),
        ];
    }

    /**
     * Create directory if it doesn't exist
     *
     * @param string $path
     * @param int $mode
     * @return bool
     */
    public static function ensureDirectory(string $path, int $mode = 0755): bool
    {
        if (!file_exists($path)) {
            return @mkdir($path, $mode, true);
        }
        return is_dir($path);
    }

    /**
     * Get PHP version info
     *
     * @return array
     */
    public static function getPhpInfo(): array
    {
        return [
            'version' => PHP_VERSION,
            'major' => PHP_MAJOR_VERSION,
            'minor' => PHP_MINOR_VERSION,
            'sapi' => PHP_SAPI,
            'extensions' => get_loaded_extensions(),
        ];
    }
}
