<?php
/**
 * WebsiteHealthLog Model
 *
 * Tracks system services and infrastructure health monitoring
 */
class WebsiteHealthLog extends FosStreaming {

    protected $table = 'website_health_logs';

    /**
     * Log a service status check
     *
     * @param string $component Component name (nginx, mariadb, php-fpm)
     * @param string $status Health status
     * @param bool $isActive Is service active?
     * @param bool $isEnabled Is service enabled on boot?
     * @param int|null $uptimeSeconds Service uptime in seconds
     * @param string|null $message Status message
     * @return static|null
     */
    public static function logServiceStatus(
        string $component,
        string $status,
        bool $isActive,
        bool $isEnabled,
        ?int $uptimeSeconds = null,
        ?string $message = null
    ): ?self
    {
        $log = new self();
        $log->component = $component;
        $log->status = $status;
        $log->is_active = $isActive;
        $log->is_enabled = $isEnabled;
        $log->uptime_seconds = $uptimeSeconds;
        $log->message = $message;
        $log->checked_at = date('Y-m-d H:i:s');

        return $log->save() ? $log : null;
    }

    /**
     * Log a system metric (CPU, memory, disk)
     *
     * @param string $component Component name (cpu, memory, disk)
     * @param string $metricName Metric name (cpu_load, memory_usage, disk_usage)
     * @param string $metricValue Current value
     * @param string $metricUnit Unit (%, MB, GB)
     * @param string $status Health status
     * @param string|null $thresholdWarning Warning threshold
     * @param string|null $thresholdCritical Critical threshold
     * @param string|null $message Status message
     * @return static|null
     */
    public static function logMetric(
        string $component,
        string $metricName,
        string $metricValue,
        string $metricUnit,
        string $status,
        ?string $thresholdWarning = null,
        ?string $thresholdCritical = null,
        ?string $message = null
    ): ?self
    {
        $log = new self();
        $log->component = $component;
        $log->metric_name = $metricName;
        $log->metric_value = $metricValue;
        $log->metric_unit = $metricUnit;
        $log->status = $status;
        $log->threshold_warning = $thresholdWarning;
        $log->threshold_critical = $thresholdCritical;
        $log->message = $message;
        $log->checked_at = date('Y-m-d H:i:s');

        return $log->save() ? $log : null;
    }

    /**
     * Get latest health status for all components
     *
     * @return array
     */
    public static function getLatestHealthStatus(): array
    {
        $results = \DB::select("
            SELECT w1.*
            FROM website_health_logs w1
            INNER JOIN (
                SELECT component, MAX(checked_at) as max_checked
                FROM website_health_logs
                GROUP BY component
            ) w2 ON w1.component = w2.component AND w1.checked_at = w2.max_checked
            ORDER BY w1.component
        ");

        $status = [];
        foreach ($results as $row) {
            $status[$row->component] = (array)$row;
        }

        return $status;
    }

    /**
     * Get critical alerts that haven't been sent
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getPendingCriticalAlerts()
    {
        return self::where('status', 'critical')
            ->where('alert_sent', 0)
            ->orderBy('checked_at', 'desc')
            ->get();
    }

    /**
     * Mark alert as sent
     *
     * @param int $id Log ID
     * @return bool
     */
    public static function markAlertSent(int $id): bool
    {
        $log = self::find($id);
        if (!$log) {
            return false;
        }

        $log->alert_sent = 1;
        $log->alert_sent_at = date('Y-m-d H:i:s');

        return $log->save();
    }

    /**
     * Get service downtime events
     *
     * @param int $hours Number of hours to look back
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getServiceDowntime(int $hours = 24)
    {
        return self::whereIn('component', ['nginx', 'mariadb', 'php-fpm'])
            ->where('is_active', 0)
            ->where('checked_at', '>=', date('Y-m-d H:i:s', strtotime("-{$hours} hours")))
            ->orderBy('checked_at', 'desc')
            ->get();
    }

    /**
     * Get metric history for a component
     *
     * @param string $component Component name
     * @param string $metricName Metric name
     * @param int $hours Number of hours to look back
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getMetricHistory(string $component, string $metricName, int $hours = 24)
    {
        return self::where('component', $component)
            ->where('metric_name', $metricName)
            ->where('checked_at', '>=', date('Y-m-d H:i:s', strtotime("-{$hours} hours")))
            ->orderBy('checked_at')
            ->get();
    }

    /**
     * Clean up old logs (keep last 30 days)
     *
     * @param int $days Number of days to keep
     * @return int Number of deleted records
     */
    public static function cleanup(int $days = 30): int
    {
        return self::where('created_at', '<', date('Y-m-d H:i:s', strtotime("-{$days} days")))
            ->delete();
    }
}
