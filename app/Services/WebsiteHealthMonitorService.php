<?php

namespace App\Services;

/**
 * WebsiteHealthMonitorService
 *
 * Monitors system services and infrastructure health
 * Used by the website-health PM2 worker
 *
 * Related Documentation: /docs/PM2_STREAM_MANAGEMENT_ANALYSIS.md
 */
class WebsiteHealthMonitorService
{
    private $logger;

    public function __construct()
    {
        $this->logger = new \App\Services\LoggerService('website-health');
    }

    /**
     * Monitor all system components
     *
     * @return array Statistics
     */
    public function monitorSystem(): array
    {
        $stats = [
            'checked' => 0,
            'healthy' => 0,
            'warning' => 0,
            'critical' => 0
        ];

        // Monitor system services
        $stats = $this->monitorServices($stats);

        // Monitor system resources
        $stats = $this->monitorResources($stats);

        return $stats;
    }

    /**
     * Monitor system services (Nginx, MariaDB, PHP-FPM)
     *
     * @param array $stats Current statistics
     * @return array Updated statistics
     */
    private function monitorServices(array $stats): array
    {
        $services = [
            // Streaming Services (dedicated high-performance)
            'fos-nginx-streaming' => 'Nginx Streaming Service',
            'fos-php-fpm-streaming' => 'PHP-FPM Streaming Service',
            // Core Platform Services
            'fos-nginx' => 'Nginx Admin Panel',
            'php8.4-fpm' => 'PHP-FPM Admin',
            'mariadb' => 'MariaDB Database'
        ];

        foreach ($services as $serviceName => $displayName) {
            $stats['checked']++;

            $health = $this->checkServiceHealth($serviceName);

            // Log service status
            \WebsiteHealthLog::logServiceStatus(
                $serviceName,
                $health['status'],
                $health['is_active'],
                $health['is_enabled'],
                $health['uptime'],
                $health['message']
            );

            if ($health['status'] === 'healthy') {
                $stats['healthy']++;
            } elseif ($health['status'] === 'warning') {
                $stats['warning']++;
            } else {
                $stats['critical']++;
                $this->logger->error("Service {$serviceName} is {$health['status']}: {$health['message']}");
            }
        }

        return $stats;
    }

    /**
     * Monitor system resources (CPU, Memory, Disk)
     *
     * @param array $stats Current statistics
     * @return array Updated statistics
     */
    private function monitorResources(array $stats): array
    {
        // Monitor CPU
        $cpuHealth = $this->checkCpuHealth();
        $stats['checked']++;
        $stats[$cpuHealth['status']]++;

        \WebsiteHealthLog::logMetric(
            'cpu',
            'cpu_load',
            $cpuHealth['value'],
            '%',
            $cpuHealth['status'],
            '70',
            '90',
            $cpuHealth['message']
        );

        // Monitor Memory
        $memoryHealth = $this->checkMemoryHealth();
        $stats['checked']++;
        $stats[$memoryHealth['status']]++;

        \WebsiteHealthLog::logMetric(
            'memory',
            'memory_usage',
            $memoryHealth['value'],
            '%',
            $memoryHealth['status'],
            '80',
            '95',
            $memoryHealth['message']
        );

        // Monitor Disk
        $diskHealth = $this->checkDiskHealth();
        $stats['checked']++;
        $stats[$diskHealth['status']]++;

        \WebsiteHealthLog::logMetric(
            'disk',
            'disk_usage',
            $diskHealth['value'],
            '%',
            $diskHealth['status'],
            '85',
            '95',
            $diskHealth['message']
        );

        return $stats;
    }

    /**
     * Check service health
     *
     * @param string $serviceName Service name
     * @return array Health information
     */
    private function checkServiceHealth(string $serviceName): array
    {
        // Check if service is active
        exec("systemctl is-active {$serviceName} 2>&1", $activeOutput, $activeExitCode);
        $isActive = ($activeExitCode === 0 && trim(implode('', $activeOutput)) === 'active');

        // Check if service is enabled
        exec("systemctl is-enabled {$serviceName} 2>&1", $enabledOutput, $enabledExitCode);
        $isEnabled = ($enabledExitCode === 0);

        // Get uptime if active
        $uptime = null;
        if ($isActive) {
            exec("systemctl show {$serviceName} --property=ActiveEnterTimestamp --value 2>&1", $uptimeOutput);
            if (!empty($uptimeOutput)) {
                $startTime = strtotime(trim($uptimeOutput[0]));
                if ($startTime) {
                    $uptime = time() - $startTime;
                }
            }
        }

        // Determine status
        if ($isActive) {
            $status = 'healthy';
            $message = "{$serviceName} is active and running";
        } else {
            $status = 'critical';
            $message = "{$serviceName} is not active";
        }

        return [
            'status' => $status,
            'is_active' => $isActive,
            'is_enabled' => $isEnabled,
            'uptime' => $uptime,
            'message' => $message
        ];
    }

    /**
     * Check CPU health
     *
     * @return array Health information
     */
    private function checkCpuHealth(): array
    {
        // Get CPU load average (1 minute)
        exec("cat /proc/loadavg 2>&1", $output);
        if (empty($output)) {
            return [
                'status' => 'unknown',
                'value' => '0',
                'message' => 'Unable to read CPU load'
            ];
        }

        $parts = explode(' ', $output[0]);
        $loadAvg = floatval($parts[0]);

        // Get number of CPU cores
        exec("nproc 2>&1", $coreOutput);
        $cores = !empty($coreOutput) ? intval($coreOutput[0]) : 1;

        // Calculate CPU usage percentage
        $cpuPercent = ($loadAvg / $cores) * 100;

        // Determine status
        if ($cpuPercent < 70) {
            $status = 'healthy';
        } elseif ($cpuPercent < 90) {
            $status = 'warning';
        } else {
            $status = 'critical';
        }

        return [
            'status' => $status,
            'value' => number_format($cpuPercent, 2),
            'message' => "CPU load: {$loadAvg} ({$cores} cores)"
        ];
    }

    /**
     * Check memory health
     *
     * @return array Health information
     */
    private function checkMemoryHealth(): array
    {
        exec("free | grep Mem | awk '{print ($3/$2) * 100.0}' 2>&1", $output);

        if (empty($output)) {
            return [
                'status' => 'unknown',
                'value' => '0',
                'message' => 'Unable to read memory usage'
            ];
        }

        $memPercent = floatval($output[0]);

        // Determine status
        if ($memPercent < 80) {
            $status = 'healthy';
        } elseif ($memPercent < 95) {
            $status = 'warning';
        } else {
            $status = 'critical';
        }

        return [
            'status' => $status,
            'value' => number_format($memPercent, 2),
            'message' => "Memory usage: " . number_format($memPercent, 2) . "%"
        ];
    }

    /**
     * Check disk health
     *
     * @return array Health information
     */
    private function checkDiskHealth(): array
    {
        exec("df -h / | tail -1 | awk '{print $5}' | sed 's/%//' 2>&1", $output);

        if (empty($output)) {
            return [
                'status' => 'unknown',
                'value' => '0',
                'message' => 'Unable to read disk usage'
            ];
        }

        $diskPercent = floatval($output[0]);

        // Determine status
        if ($diskPercent < 85) {
            $status = 'healthy';
        } elseif ($diskPercent < 95) {
            $status = 'warning';
        } else {
            $status = 'critical';
        }

        return [
            'status' => $status,
            'value' => number_format($diskPercent, 2),
            'message' => "Disk usage: " . number_format($diskPercent, 2) . "%"
        ];
    }

    /**
     * Get current system health status
     *
     * @return array
     */
    public function getCurrentHealth(): array
    {
        return \WebsiteHealthLog::getLatestHealthStatus();
    }

    /**
     * Get pending critical alerts
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPendingAlerts()
    {
        return \WebsiteHealthLog::getPendingCriticalAlerts();
    }

    /**
     * Send pending alerts
     * This would integrate with email/notification system
     *
     * @return int Number of alerts sent
     */
    public function sendPendingAlerts(): int
    {
        $alerts = $this->getPendingAlerts();
        $sent = 0;

        foreach ($alerts as $alert) {
            // TODO: Integrate with email/notification service
            // For now, just mark as sent
            $this->logger->critical(
                "ALERT: {$alert->component} - {$alert->status} - {$alert->message}"
            );

            \WebsiteHealthLog::markAlertSent($alert->id);
            $sent++;
        }

        return $sent;
    }
}
