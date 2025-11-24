<?php

namespace App\Services;

use Illuminate\Database\Capsule\Manager as Capsule;
use GuzzleHttp\Client;

/**
 * Health Check Service
 *
 * Comprehensive system health monitoring
 * Phase 7: Security Hardening
 */
class HealthCheckService
{
    private $checks = [];
    private $results = [];

    /**
     * Run all health checks
     */
    public function checkAll()
    {
        $this->results = [
            'timestamp' => date('c'), // ISO 8601 format
            'overall_status' => 'healthy',
            'checks' => [
                'database' => $this->checkDatabase(),
                'redis' => $this->checkRedis(),
                'disk' => $this->checkDiskSpace(),
                'memory' => $this->checkMemory(),
                'cpu' => $this->checkCPU(),
                'services' => $this->checkServices(),
                'srt' => $this->checkSRT(),
                'v2ray' => $this->checkV2Ray(),
                'cdn' => $this->checkCDN(),
                'streams' => $this->checkStreams(),
                'workers' => $this->checkWorkers(),
            ],
        ];

        // Determine overall status
        $this->results['overall_status'] = $this->determineOverallStatus();

        return $this->results;
    }

    /**
     * Check database connectivity and status
     */
    public function checkDatabase()
    {
        try {
            $start = microtime(true);
            Capsule::connection()->select('SELECT 1');
            $latency = round((microtime(true) - $start) * 1000, 2);

            // Check connection count
            $connections = Capsule::connection()->select('SHOW STATUS LIKE "Threads_connected"');
            $connectedThreads = $connections[0]->Value ?? 0;

            $maxConnections = Capsule::connection()->select('SHOW VARIABLES LIKE "max_connections"');
            $maxConn = $maxConnections[0]->Value ?? 151;

            $connectionUsage = round(($connectedThreads / $maxConn) * 100, 2);

            return [
                'status' => 'healthy',
                'latency_ms' => $latency,
                'connections' => [
                    'current' => $connectedThreads,
                    'max' => $maxConn,
                    'usage_percent' => $connectionUsage,
                ],
                'message' => "Database responsive ({$latency}ms)",
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'message' => 'Database connection failed',
            ];
        }
    }

    /**
     * Check Redis connectivity and status
     */
    public function checkRedis()
    {
        // Check if Redis extension is loaded
        if (!extension_loaded('redis')) {
            return [
                'status' => 'disabled',
                'latency_ms' => 0,
                'memory_used' => 'N/A',
                'connected_clients' => 0,
                'message' => 'Redis extension not installed',
            ];
        }

        try {
            $redis = new \Redis();
            $connected = @$redis->connect('127.0.0.1', 6379, 1);

            if (!$connected) {
                return [
                    'status' => 'unhealthy',
                    'latency_ms' => 0,
                    'memory_used' => 'N/A',
                    'connected_clients' => 0,
                    'message' => 'Redis not running',
                ];
            }

            $start = microtime(true);
            $redis->ping();
            $latency = round((microtime(true) - $start) * 1000, 2);

            // Get Redis info
            $info = $redis->info();
            $memoryUsed = $info['used_memory_human'] ?? 'unknown';
            $connectedClients = $info['connected_clients'] ?? 0;

            $redis->close();

            return [
                'status' => 'healthy',
                'latency_ms' => $latency,
                'memory_used' => $memoryUsed,
                'connected_clients' => $connectedClients,
                'message' => "Redis responsive ({$latency}ms)",
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'latency_ms' => 0,
                'memory_used' => 'N/A',
                'connected_clients' => 0,
                'error' => $e->getMessage(),
                'message' => 'Redis connection failed',
            ];
        }
    }

    /**
     * Check disk space
     */
    public function checkDiskSpace()
    {
        $path = __DIR__ . '/../..';

        $totalSpace = disk_total_space($path);
        $freeSpace = disk_free_space($path);
        $usedSpace = $totalSpace - $freeSpace;

        $usedPercent = round(($usedSpace / $totalSpace) * 100, 2);

        $status = 'healthy';
        if ($usedPercent > 90) {
            $status = 'critical';
        } elseif ($usedPercent > 80) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'total_gb' => round($totalSpace / 1024 / 1024 / 1024, 2),
            'used_gb' => round($usedSpace / 1024 / 1024 / 1024, 2),
            'free_gb' => round($freeSpace / 1024 / 1024 / 1024, 2),
            'used_percent' => $usedPercent,
            'message' => "Disk usage: {$usedPercent}%",
        ];
    }

    /**
     * Check memory usage
     */
    public function checkMemory()
    {
        if (!function_exists('sys_getloadavg')) {
            return ['status' => 'unknown', 'message' => 'Unable to check memory'];
        }

        $memInfo = file_get_contents('/proc/meminfo');
        preg_match_all('/^(\w+):\s+(\d+)/m', $memInfo, $matches);
        $memStats = array_combine($matches[1], $matches[2]);

        $totalMem = $memStats['MemTotal'] ?? 0;
        $freeMem = $memStats['MemFree'] ?? 0;
        $availableMem = $memStats['MemAvailable'] ?? $freeMem;

        $usedMem = $totalMem - $availableMem;
        $usedPercent = $totalMem > 0 ? round(($usedMem / $totalMem) * 100, 2) : 0;

        $status = 'healthy';
        if ($usedPercent > 90) {
            $status = 'critical';
        } elseif ($usedPercent > 80) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'total_mb' => round($totalMem / 1024, 2),
            'used_mb' => round($usedMem / 1024, 2),
            'free_mb' => round($availableMem / 1024, 2),
            'used_percent' => $usedPercent,
            'message' => "Memory usage: {$usedPercent}%",
        ];
    }

    /**
     * Check CPU load
     */
    public function checkCPU()
    {
        if (!function_exists('sys_getloadavg')) {
            return ['status' => 'unknown', 'message' => 'Unable to check CPU'];
        }

        $load = sys_getloadavg();
        $cpuCount = $this->getCPUCount();

        $load1 = round($load[0], 2);
        $load5 = round($load[1], 2);
        $load15 = round($load[2], 2);

        $loadPercent = $cpuCount > 0 ? round(($load1 / $cpuCount) * 100, 2) : 0;

        $status = 'healthy';
        if ($loadPercent > 90) {
            $status = 'critical';
        } elseif ($loadPercent > 75) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'load_1min' => $load1,
            'load_5min' => $load5,
            'load_15min' => $load15,
            'cpu_cores' => $cpuCount,
            'load_percent' => $loadPercent,
            'message' => "CPU load: {$load1} ({$loadPercent}%)",
        ];
    }

    /**
     * Get CPU count
     */
    private function getCPUCount()
    {
        if (is_file('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            preg_match_all('/^processor/m', $cpuinfo, $matches);
            return count($matches[0]);
        }

        return 1;
    }

    /**
     * Check system services
     */
    public function checkServices()
    {
        $services = [
            'nginx' => $this->checkService('nginx'),
            'php-fpm' => $this->checkService('php8.4-fpm'),
            'mariadb' => $this->checkService('mariadb'),
        ];

        $allHealthy = !in_array('stopped', array_column($services, 'status'));

        return [
            'status' => $allHealthy ? 'healthy' : 'unhealthy',
            'services' => $services,
            'message' => $allHealthy ? 'All services running' : 'Some services are down',
        ];
    }

    /**
     * Check individual service
     */
    private function checkService($serviceName)
    {
        try {
            $output = shell_exec("systemctl is-active {$serviceName} 2>&1");
            $isActive = trim($output) === 'active';

            return [
                'name' => $serviceName,
                'status' => $isActive ? 'running' : 'stopped',
            ];

        } catch (\Exception $e) {
            return [
                'name' => $serviceName,
                'status' => 'unknown',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check SRT service
     */
    public function checkSRT()
    {
        $srtPort = env('SRT_PROXY_PORT', 9000);

        $socket = @fsockopen('localhost', $srtPort, $errno, $errstr, 1);

        if ($socket) {
            fclose($socket);

            $stats = Redis::get('srt_proxy:stats');
            $stats = $stats ? json_decode($stats, true) : [];

            return [
                'status' => 'healthy',
                'port' => $srtPort,
                'active_proxies' => $stats['activeProxies'] ?? 0,
                'total_clients' => $stats['totalClients'] ?? 0,
                'message' => 'SRT proxy operational',
            ];
        }

        return [
            'status' => 'unhealthy',
            'port' => $srtPort,
            'error' => $errstr,
            'message' => 'SRT proxy not responding',
        ];
    }

    /**
     * Check V2Ray service
     */
    public function checkV2Ray()
    {
        $activeUsers = Capsule::table('v2ray_users')
            ->where('is_active', true)
            ->count();

        $healthyServers = Capsule::table('v2ray_servers')
            ->where('enabled', true)
            ->where('health_status', 'healthy')
            ->count();

        $totalServers = Capsule::table('v2ray_servers')
            ->where('enabled', true)
            ->count();

        $status = 'healthy';
        if ($totalServers > 0 && $healthyServers === 0) {
            $status = 'critical';
        } elseif ($totalServers > 0 && $healthyServers < ($totalServers / 2)) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'active_users' => $activeUsers,
            'healthy_servers' => $healthyServers,
            'total_servers' => $totalServers,
            'message' => "{$healthyServers}/{$totalServers} servers healthy",
        ];
    }

    /**
     * Check CDN status
     */
    public function checkCDN()
    {
        if (!env('CDN_PROVIDER')) {
            return [
                'status' => 'disabled',
                'message' => 'CDN not configured',
            ];
        }

        try {
            $cdnService = new CDNService();
            $isConnected = $cdnService->testConnection();

            return [
                'status' => $isConnected ? 'healthy' : 'unhealthy',
                'provider' => env('CDN_PROVIDER'),
                'message' => $isConnected ? 'CDN operational' : 'CDN connection failed',
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'message' => 'CDN check failed',
            ];
        }
    }

    /**
     * Check streams health
     */
    public function checkStreams()
    {
        $totalStreams = Capsule::table('streams')->where('enabled', true)->count();
        $activeStreams = Capsule::table('streams')
            ->where('enabled', true)
            ->where('pid', '>', 0)
            ->count();

        $proxyStreams = Capsule::table('streams')
            ->where('enabled', true)
            ->where('stream_mode', 'proxy')
            ->where('proxy_status', 'active')
            ->count();

        return [
            'status' => 'healthy',
            'total_enabled' => $totalStreams,
            'active_streams' => $activeStreams,
            'proxy_streams' => $proxyStreams,
            'message' => "{$activeStreams}/{$totalStreams} streams active",
        ];
    }

    /**
     * Check PM2 workers
     */
    public function checkWorkers()
    {
        try {
            exec('pm2 jlist 2>&1', $output, $returnCode);

            if ($returnCode !== 0) {
                return [
                    'status' => 'unhealthy',
                    'message' => 'PM2 not responding',
                ];
            }

            $workers = json_decode(implode('', $output), true);

            if (!$workers) {
                return [
                    'status' => 'warning',
                    'message' => 'No workers running',
                ];
            }

            $online = 0;
            $stopped = 0;

            foreach ($workers as $worker) {
                if ($worker['pm2_env']['status'] === 'online') {
                    $online++;
                } else {
                    $stopped++;
                }
            }

            $status = 'healthy';
            if ($stopped > 0) {
                $status = 'warning';
            }
            if ($online === 0) {
                $status = 'critical';
            }

            return [
                'status' => $status,
                'total_workers' => count($workers),
                'online' => $online,
                'stopped' => $stopped,
                'message' => "{$online} workers online, {$stopped} stopped",
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'unknown',
                'error' => $e->getMessage(),
                'message' => 'Unable to check workers',
            ];
        }
    }

    /**
     * Determine overall status
     */
    private function determineOverallStatus()
    {
        $statuses = array_column($this->results['checks'], 'status');

        if (in_array('critical', $statuses)) {
            return 'critical';
        }

        if (in_array('unhealthy', $statuses)) {
            return 'unhealthy';
        }

        if (in_array('warning', $statuses)) {
            return 'warning';
        }

        if (in_array('unknown', $statuses)) {
            return 'degraded';
        }

        return 'healthy';
    }

    /**
     * Get health status summary
     */
    public function getSummary()
    {
        $fullCheck = $this->checkAll();

        return [
            'status' => $fullCheck['overall_status'],
            'timestamp' => $fullCheck['timestamp'],
            'critical_issues' => $this->getCriticalIssues($fullCheck),
            'warnings' => $this->getWarnings($fullCheck),
        ];
    }

    /**
     * Get critical issues
     */
    private function getCriticalIssues($results)
    {
        $issues = [];

        foreach ($results['checks'] as $checkName => $check) {
            if (in_array($check['status'], ['critical', 'unhealthy'])) {
                $issues[] = [
                    'check' => $checkName,
                    'status' => $check['status'],
                    'message' => $check['message'] ?? 'Check failed',
                ];
            }
        }

        return $issues;
    }

    /**
     * Get warnings
     */
    private function getWarnings($results)
    {
        $warnings = [];

        foreach ($results['checks'] as $checkName => $check) {
            if ($check['status'] === 'warning') {
                $warnings[] = [
                    'check' => $checkName,
                    'message' => $check['message'] ?? 'Warning detected',
                ];
            }
        }

        return $warnings;
    }
}
