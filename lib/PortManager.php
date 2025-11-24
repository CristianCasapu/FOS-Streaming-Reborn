<?php

namespace FOS\Security;

/**
 * FOS-Streaming Port Manager
 *
 * Manages random port selection with Cloudflare SSL support
 * and port availability checking.
 */
class PortManager
{
    /**
     * Cloudflare-supported HTTPS ports
     * These ports work with Cloudflare's SSL proxy
     */
    private const CLOUDFLARE_SSL_PORTS = [2053, 2083, 2087, 2096, 8443];

    /**
     * Check if a port is currently in use
     *
     * @param int $port Port number to check
     * @return bool True if port is available, false if in use
     */
    public static function isPortAvailable($port)
    {
        // Check using lsof
        $output = [];
        $result = 0;
        exec("lsof -i :{$port} 2>/dev/null | grep LISTEN", $output, $result);

        if (!empty($output)) {
            return false;
        }

        // Double check with netstat
        exec("netstat -tuln 2>/dev/null | grep ':{$port} ' | grep LISTEN", $output, $result);

        return empty($output);
    }

    /**
     * Get a random available Cloudflare-compatible SSL port
     *
     * @param array $excludePorts Ports to exclude from selection
     * @return int|null Available port or null if none found
     */
    public static function getRandomAvailablePort($excludePorts = [])
    {
        // Shuffle ports for randomization
        $ports = self::CLOUDFLARE_SSL_PORTS;
        shuffle($ports);

        // Find first available port
        foreach ($ports as $port) {
            if (!in_array($port, $excludePorts) && self::isPortAvailable($port)) {
                return $port;
            }
        }

        // If no Cloudflare port available, try extended range
        // Still secure ports but may not work with Cloudflare
        $extendedPorts = range(8000, 8999);
        shuffle($extendedPorts);

        foreach ($extendedPorts as $port) {
            if (!in_array($port, $excludePorts) && self::isPortAvailable($port)) {
                return $port;
            }
        }

        return null;
    }

    /**
     * Get random available port for RTMP (from streaming port ranges)
     *
     * @param array $excludePorts Ports to exclude from selection
     * @return int|null Available port or null if none found
     */
    public static function getRandomRtmpPort($excludePorts = [])
    {
        // RTMP typically uses 1935, but we want random ports
        // Use common streaming port ranges: 1935-1999, 8000-8999
        $portRanges = array_merge(
            range(1935, 1999),
            range(8000, 8999)
        );

        shuffle($portRanges);

        // Find first available port
        foreach ($portRanges as $port) {
            if (!in_array($port, $excludePorts) && self::isPortAvailable($port)) {
                return $port;
            }
        }

        return null;
    }

    /**
     * Get three different random available ports (web, stream, RTMP)
     *
     * @return array Array with 'web_port', 'stream_port', and 'rtmp_port' keys
     * @throws Exception If unable to find available ports
     */
    public static function getRandomPortSet()
    {
        $webPort = self::getRandomAvailablePort();

        if ($webPort === null) {
            throw new Exception("Unable to find available port for web service");
        }

        $streamPort = self::getRandomAvailablePort([$webPort]);

        if ($streamPort === null) {
            throw new Exception("Unable to find available port for streaming service");
        }

        $rtmpPort = self::getRandomRtmpPort([$webPort, $streamPort]);

        if ($rtmpPort === null) {
            throw new Exception("Unable to find available port for RTMP service");
        }

        return [
            'web_port' => $webPort,
            'stream_port' => $streamPort,
            'rtmp_port' => $rtmpPort
        ];
    }

    /**
     * Save port configuration to file
     *
     * @param int $webPort Web panel port
     * @param int $streamPort Streaming port
     * @param int $rtmpPort RTMP port
     * @param string $configPath Path to ports configuration file
     * @return bool Success status
     */
    public static function savePortConfig($webPort, $streamPort, $rtmpPort, $configPath)
    {
        $config = "<?php\n";
        $config .= "/**\n";
        $config .= " * FOS-Streaming Port Configuration\n";
        $config .= " *\n";
        $config .= " * This file is auto-generated during installation and contains\n";
        $config .= " * the randomly selected ports for web and streaming services.\n";
        $config .= " *\n";
        $config .= " * DO NOT EDIT MANUALLY - This file is managed by the installer\n";
        $config .= " * Generated: " . date('Y-m-d H:i:s') . "\n";
        $config .= " */\n\n";
        $config .= "return [\n";
        $config .= "    'web_port' => {$webPort},      // Web panel HTTPS port\n";
        $config .= "    'stream_port' => {$streamPort},   // Streaming HTTPS port\n";
        $config .= "    'rtmp_port' => {$rtmpPort},     // RTMP port (random)\n";
        $config .= "];\n";

        return file_put_contents($configPath, $config) !== false;
    }

    /**
     * Load port configuration from file
     *
     * @param string $configPath Path to ports configuration file
     * @return array Port configuration array
     */
    public static function loadPortConfig($configPath)
    {
        if (!file_exists($configPath)) {
            return [
                'web_port' => 7777,
                'stream_port' => 8000,
                'rtmp_port' => 1935
            ];
        }

        return include $configPath;
    }

    /**
     * Get Cloudflare-supported SSL ports
     *
     * @return array List of Cloudflare SSL ports
     */
    public static function getCloudflareSSLPorts()
    {
        return self::CLOUDFLARE_SSL_PORTS;
    }
}
