<?php

namespace FOS\Security;

/**
 * FOS-Streaming Port Helper
 *
 * Helper functions for accessing port configuration in PHP application
 */
class PortHelper
{
    private static $config = null;

    /**
     * Load port configuration
     *
     * @return array Port configuration
     */
    private static function loadConfig()
    {
        if (self::$config === null) {
            $configPath = __DIR__ . '/../config/ports.php';

            if (file_exists($configPath)) {
                self::$config = include $configPath;
            } else {
                // Fallback to defaults
                self::$config = [
                    'web_port' => 7777,
                    'stream_port' => 8000,
                    'rtmp_port' => 1935
                ];
            }
        }

        return self::$config;
    }

    /**
     * Get web panel port
     *
     * @return int Web port number
     */
    public static function getWebPort()
    {
        $config = self::loadConfig();
        return $config['web_port'];
    }

    /**
     * Get streaming port
     *
     * @return int Streaming port number
     */
    public static function getStreamPort()
    {
        $config = self::loadConfig();
        return $config['stream_port'];
    }

    /**
     * Get RTMP port
     *
     * @return int RTMP port number
     */
    public static function getRtmpPort()
    {
        $config = self::loadConfig();
        return $config['rtmp_port'];
    }

    /**
     * Get all ports
     *
     * @return array All port configuration
     */
    public static function getAllPorts()
    {
        return self::loadConfig();
    }

    /**
     * Check if using HTTPS
     *
     * @return bool True if HTTPS is enabled
     */
    public static function isHttps()
    {
        return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    }

    /**
     * Get current protocol (http or https)
     *
     * @return string Protocol
     */
    public static function getProtocol()
    {
        return self::isHttps() ? 'https' : 'http';
    }

    /**
     * Get full web panel URL
     *
     * @param string|null $ip Server IP address (uses current if null)
     * @return string Full URL
     */
    public static function getWebUrl($ip = null)
    {
        if ($ip === null) {
            $ip = $_SERVER['SERVER_ADDR'] ?? 'localhost';
        }

        return self::getProtocol() . '://' . $ip . ':' . self::getWebPort();
    }

    /**
     * Get full streaming URL
     *
     * @param string|null $ip Server IP address (uses current if null)
     * @return string Full URL
     */
    public static function getStreamUrl($ip = null)
    {
        if ($ip === null) {
            $ip = $_SERVER['SERVER_ADDR'] ?? 'localhost';
        }

        return self::getProtocol() . '://' . $ip . ':' . self::getStreamPort();
    }

    /**
     * Get RTMP URL
     *
     * @param string|null $ip Server IP address (uses current if null)
     * @return string Full RTMP URL
     */
    public static function getRtmpUrl($ip = null)
    {
        if ($ip === null) {
            $ip = $_SERVER['SERVER_ADDR'] ?? 'localhost';
        }

        return 'rtmp://' . $ip . ':' . self::getRtmpPort();
    }
}
