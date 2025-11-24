<?php
/**
 * Metrics API Endpoint
 * Provides performance metrics and analytics
 */

require_once '../../../config.php';
logincheck(); // Enforce admin authentication

header('Content-Type: application/json');

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

$action = $_GET['action'] ?? 'dashboard';

try {
    switch ($action) {
        case 'dashboard':
            // Quick dashboard metrics
            $metrics = [
                'platform' => [
                    'total_streams' => DB::table('streams')->count(),
                    'active_streams' => DB::table('streams')->where('enabled', true)->where('pid', '>', 0)->count(),
                    'total_subscribers' => DB::table('subscribers')->count(),
                    'active_subscriptions' => DB::table('subscriptions')->where('status', 'active')->count(),
                    'total_resellers' => DB::table('resellers')->count(),
                    'active_resellers' => DB::table('resellers')->where('status', 'active')->count(),
                ],
                'revenue' => [
                    'monthly_revenue' => DB::table('subscriptions')
                        ->where('status', 'active')
                        ->whereMonth('created_at', date('m'))
                        ->whereYear('created_at', date('Y'))
                        ->sum('price'),
                    'total_revenue' => DB::table('subscriptions')
                        ->where('status', 'active')
                        ->sum('price'),
                    'pending_commissions' => DB::table('resellers')->sum('pending_balance'),
                ],
                'security' => [
                    'device_fingerprints' => DB::table('device_fingerprints')->count(),
                    'active_devices' => DB::table('device_bindings')
                        ->where('is_active', true)
                        ->distinct('device_fingerprint_id')
                        ->count(),
                    'violations_today' => DB::table('device_violations')
                        ->whereDate('created_at', date('Y-m-d'))
                        ->count(),
                    'blocked_devices' => DB::table('device_fingerprints')
                        ->where('is_blocked', true)
                        ->count(),
                ],
                'streaming' => [
                    'srt_streams' => DB::table('streams')->where('srt_enabled', true)->count(),
                    'proxy_streams' => DB::table('streams')->where('stream_mode', 'proxy')->count(),
                    'transcode_streams' => DB::table('streams')->where('stream_mode', 'transcode')->count(),
                    'v2ray_users' => DB::table('v2ray_users')->where('is_active', true)->count(),
                    'v2ray_servers' => DB::table('v2ray_servers')->where('enabled', true)->count(),
                ],
            ];

            echo json_encode([
                'success' => true,
                'data' => $metrics,
                'timestamp' => time(),
            ]);
            break;

        case 'streams':
            // Stream performance metrics
            $days = isset($_GET['days']) ? min(90, max(1, intval($_GET['days']))) : 7;

            $streamMetrics = [
                'by_status' => DB::table('streams')
                    ->select('enabled', DB::raw('COUNT(*) as count'))
                    ->groupBy('enabled')
                    ->get(),
                'by_mode' => DB::table('streams')
                    ->select('stream_mode', DB::raw('COUNT(*) as count'))
                    ->groupBy('stream_mode')
                    ->get(),
                'by_protocol' => DB::table('streams')
                    ->select('protocol', DB::raw('COUNT(*) as count'))
                    ->groupBy('protocol')
                    ->get(),
                'by_category' => DB::table('streams')
                    ->join('categories', 'streams.category_id', '=', 'categories.id')
                    ->select('categories.name as category', DB::raw('COUNT(*) as count'))
                    ->groupBy('categories.name')
                    ->orderBy('count', 'DESC')
                    ->limit(10)
                    ->get(),
                'uptime' => DB::table('streams')
                    ->where('enabled', true)
                    ->select(DB::raw('AVG(CASE WHEN pid > 0 THEN 1 ELSE 0 END) * 100 as uptime_percent'))
                    ->first(),
            ];

            echo json_encode([
                'success' => true,
                'data' => $streamMetrics,
            ]);
            break;

        case 'subscribers':
            // Subscriber metrics
            $days = isset($_GET['days']) ? min(90, max(1, intval($_GET['days']))) : 30;

            $subscriberMetrics = [
                'growth' => DB::table('subscribers')
                    ->select(
                        DB::raw('DATE(created_at) as date'),
                        DB::raw('COUNT(*) as new_subscribers')
                    )
                    ->where('created_at', '>=', DB::raw("DATE_SUB(NOW(), INTERVAL {$days} DAY)"))
                    ->groupBy('date')
                    ->orderBy('date', 'ASC')
                    ->get(),
                'by_package' => DB::table('subscriptions')
                    ->join('packages', 'subscriptions.package_id', '=', 'packages.id')
                    ->select('packages.name as package', DB::raw('COUNT(DISTINCT subscriptions.subscriber_id) as count'))
                    ->where('subscriptions.status', 'active')
                    ->groupBy('packages.name')
                    ->get(),
                'subscription_status' => DB::table('subscriptions')
                    ->select('status', DB::raw('COUNT(*) as count'))
                    ->groupBy('status')
                    ->get(),
                'trial_conversions' => [
                    'total_trials' => DB::table('trials')->count(),
                    'active_trials' => DB::table('trials')->where('status', 'active')->count(),
                    'expired_trials' => DB::table('trials')->where('status', 'expired')->count(),
                    'converted' => DB::table('trials')
                        ->whereExists(function($query) {
                            $query->select(DB::raw(1))
                                ->from('subscriptions')
                                ->whereRaw('subscriptions.subscriber_id = trials.subscriber_id')
                                ->where('subscriptions.status', 'active');
                        })
                        ->count(),
                ],
            ];

            echo json_encode([
                'success' => true,
                'data' => $subscriberMetrics,
            ]);
            break;

        case 'resellers':
            // Reseller performance metrics
            $resellerMetrics = [
                'top_resellers' => DB::table('resellers')
                    ->select(
                        'resellers.id',
                        'resellers.username',
                        'resellers.company_name',
                        DB::raw('COUNT(DISTINCT reseller_subscribers.subscriber_id) as total_subscribers'),
                        'resellers.total_earned',
                        'resellers.credit_balance'
                    )
                    ->leftJoin('reseller_subscribers', 'resellers.id', '=', 'reseller_subscribers.reseller_id')
                    ->groupBy('resellers.id', 'resellers.username', 'resellers.company_name', 'resellers.total_earned', 'resellers.credit_balance')
                    ->orderBy('total_subscribers', 'DESC')
                    ->limit(10)
                    ->get(),
                'commission_summary' => [
                    'total_earned' => DB::table('resellers')->sum('total_earned'),
                    'total_withdrawn' => DB::table('resellers')->sum('total_withdrawn'),
                    'pending_balance' => DB::table('resellers')->sum('pending_balance'),
                    'available_balance' => DB::table('resellers')->sum('credit_balance'),
                ],
                'transactions_last_30_days' => DB::table('reseller_transactions')
                    ->select(
                        DB::raw('DATE(created_at) as date'),
                        'type',
                        DB::raw('SUM(amount) as total_amount'),
                        DB::raw('COUNT(*) as count')
                    )
                    ->where('created_at', '>=', DB::raw('DATE_SUB(NOW(), INTERVAL 30 DAY)'))
                    ->groupBy('date', 'type')
                    ->orderBy('date', 'ASC')
                    ->get(),
            ];

            echo json_encode([
                'success' => true,
                'data' => $resellerMetrics,
            ]);
            break;

        case 'security':
            // Security and device metrics
            $days = isset($_GET['days']) ? min(90, max(1, intval($_GET['days']))) : 7;

            $securityMetrics = [
                'device_activity' => DB::table('device_sessions')
                    ->select(
                        DB::raw('DATE(created_at) as date'),
                        DB::raw('COUNT(DISTINCT device_fingerprint_id) as unique_devices'),
                        DB::raw('COUNT(*) as total_sessions')
                    )
                    ->where('created_at', '>=', DB::raw("DATE_SUB(NOW(), INTERVAL {$days} DAY)"))
                    ->groupBy('date')
                    ->orderBy('date', 'ASC')
                    ->get(),
                'violations' => DB::table('device_violations')
                    ->select(
                        'violation_type',
                        DB::raw('COUNT(*) as count'),
                        DB::raw('COUNT(DISTINCT device_fingerprint_id) as unique_devices')
                    )
                    ->where('created_at', '>=', DB::raw("DATE_SUB(NOW(), INTERVAL {$days} DAY)"))
                    ->groupBy('violation_type')
                    ->orderBy('count', 'DESC')
                    ->get(),
                'blocked_devices' => DB::table('device_fingerprints')
                    ->select(
                        DB::raw('DATE(blocked_at) as date'),
                        DB::raw('COUNT(*) as count')
                    )
                    ->where('is_blocked', true)
                    ->whereNotNull('blocked_at')
                    ->where('blocked_at', '>=', DB::raw("DATE_SUB(NOW(), INTERVAL {$days} DAY)"))
                    ->groupBy('date')
                    ->orderBy('date', 'ASC')
                    ->get(),
                'concurrent_streams' => DB::table('device_sessions')
                    ->select(
                        'device_fingerprint_id',
                        DB::raw('COUNT(*) as concurrent_count')
                    )
                    ->where('is_active', true)
                    ->groupBy('device_fingerprint_id')
                    ->havingRaw('COUNT(*) > 1')
                    ->orderBy('concurrent_count', 'DESC')
                    ->limit(20)
                    ->get(),
            ];

            echo json_encode([
                'success' => true,
                'data' => $securityMetrics,
            ]);
            break;

        case 'v2ray':
            // V2Ray traffic and performance metrics
            $days = isset($_GET['days']) ? min(90, max(1, intval($_GET['days']))) : 7;

            $v2rayMetrics = [
                'traffic_stats' => DB::table('v2ray_traffic_stats')
                    ->select(
                        'date',
                        DB::raw('SUM(bytes_uploaded) as total_uploaded'),
                        DB::raw('SUM(bytes_downloaded) as total_downloaded'),
                        DB::raw('SUM(bytes_total) as total_traffic')
                    )
                    ->where('date', '>=', DB::raw("DATE_SUB(CURDATE(), INTERVAL {$days} DAY)"))
                    ->groupBy('date')
                    ->orderBy('date', 'ASC')
                    ->get(),
                'top_users' => DB::table('v2ray_users')
                    ->select(
                        'v2ray_users.id',
                        'v2ray_users.email',
                        'subscribers.username',
                        'v2ray_users.bytes_uploaded',
                        'v2ray_users.bytes_downloaded',
                        DB::raw('(v2ray_users.bytes_uploaded + v2ray_users.bytes_downloaded) as total_traffic')
                    )
                    ->join('subscribers', 'v2ray_users.subscriber_id', '=', 'subscribers.id')
                    ->where('v2ray_users.is_active', true)
                    ->orderBy('total_traffic', 'DESC')
                    ->limit(20)
                    ->get(),
                'server_health' => DB::table('v2ray_servers')
                    ->select(
                        'tag',
                        'address',
                        'health_status',
                        'load',
                        'current_connections',
                        'max_connections',
                        DB::raw('(current_connections / max_connections * 100) as load_percent')
                    )
                    ->where('enabled', true)
                    ->orderBy('load', 'DESC')
                    ->get(),
                'protocol_usage' => DB::table('v2ray_users')
                    ->select('protocol', DB::raw('COUNT(*) as count'))
                    ->where('is_active', true)
                    ->groupBy('protocol')
                    ->get(),
            ];

            echo json_encode([
                'success' => true,
                'data' => $v2rayMetrics,
            ]);
            break;

        case 'audit':
            // Audit log metrics
            $days = isset($_GET['days']) ? min(90, max(1, intval($_GET['days']))) : 7;

            $auditMetrics = [
                'actions_by_day' => DB::table('audit_logs')
                    ->select(
                        DB::raw('DATE(created_at) as date'),
                        DB::raw('COUNT(*) as total_actions')
                    )
                    ->where('created_at', '>=', DB::raw("DATE_SUB(NOW(), INTERVAL {$days} DAY)"))
                    ->groupBy('date')
                    ->orderBy('date', 'ASC')
                    ->get(),
                'actions_by_type' => DB::table('audit_logs')
                    ->select('action', DB::raw('COUNT(*) as count'))
                    ->where('created_at', '>=', DB::raw("DATE_SUB(NOW(), INTERVAL {$days} DAY)"))
                    ->groupBy('action')
                    ->orderBy('count', 'DESC')
                    ->limit(10)
                    ->get(),
                'actions_by_user_type' => DB::table('audit_logs')
                    ->select('user_type', DB::raw('COUNT(*) as count'))
                    ->where('created_at', '>=', DB::raw("DATE_SUB(NOW(), INTERVAL {$days} DAY)"))
                    ->groupBy('user_type')
                    ->get(),
                'failed_actions' => DB::table('audit_logs')
                    ->select(
                        'action',
                        'entity_type',
                        DB::raw('COUNT(*) as count')
                    )
                    ->where('status', 'failed')
                    ->where('created_at', '>=', DB::raw("DATE_SUB(NOW(), INTERVAL {$days} DAY)"))
                    ->groupBy('action', 'entity_type')
                    ->orderBy('count', 'DESC')
                    ->limit(10)
                    ->get(),
            ];

            echo json_encode([
                'success' => true,
                'data' => $auditMetrics,
            ]);
            break;

        case 'redis_stats':
            // Redis statistics from cache
            try {
                $info = Redis::info();

                $redisStats = [
                    'version' => $info['redis_version'] ?? 'unknown',
                    'uptime_days' => isset($info['uptime_in_seconds']) ? round($info['uptime_in_seconds'] / 86400, 1) : 0,
                    'connected_clients' => $info['connected_clients'] ?? 0,
                    'used_memory_human' => $info['used_memory_human'] ?? '0',
                    'used_memory_peak_human' => $info['used_memory_peak_human'] ?? '0',
                    'total_commands_processed' => $info['total_commands_processed'] ?? 0,
                    'instantaneous_ops_per_sec' => $info['instantaneous_ops_per_sec'] ?? 0,
                    'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                    'keyspace_misses' => $info['keyspace_misses'] ?? 0,
                    'hit_rate' => ($info['keyspace_hits'] ?? 0) > 0
                        ? round(($info['keyspace_hits'] / (($info['keyspace_hits'] + $info['keyspace_misses']) ?: 1)) * 100, 2)
                        : 0,
                ];

                echo json_encode([
                    'success' => true,
                    'data' => $redisStats,
                ]);
            } catch (Exception $e) {
                throw new Exception('Failed to get Redis stats: ' . $e->getMessage());
            }
            break;

        case 'system':
            // System resource usage trends
            $days = isset($_GET['days']) ? min(30, max(1, intval($_GET['days']))) : 7;

            // Get historical data from Redis if available
            try {
                $historyKey = 'health:history';
                $history = Redis::lrange($historyKey, 0, ($days * 288) - 1); // 5-min intervals

                $systemMetrics = [
                    'cpu_usage' => [],
                    'memory_usage' => [],
                    'disk_usage' => [],
                    'database_latency' => [],
                ];

                foreach ($history as $entry) {
                    $data = json_decode($entry, true);
                    $timestamp = $data['timestamp'] ?? time();

                    if (isset($data['checks']['cpu']['load_percent'])) {
                        $systemMetrics['cpu_usage'][] = [
                            'timestamp' => $timestamp,
                            'value' => $data['checks']['cpu']['load_percent'],
                        ];
                    }

                    if (isset($data['checks']['memory']['used_percent'])) {
                        $systemMetrics['memory_usage'][] = [
                            'timestamp' => $timestamp,
                            'value' => $data['checks']['memory']['used_percent'],
                        ];
                    }

                    if (isset($data['checks']['disk']['used_percent'])) {
                        $systemMetrics['disk_usage'][] = [
                            'timestamp' => $timestamp,
                            'value' => $data['checks']['disk']['used_percent'],
                        ];
                    }

                    if (isset($data['checks']['database']['latency_ms'])) {
                        $systemMetrics['database_latency'][] = [
                            'timestamp' => $timestamp,
                            'value' => $data['checks']['database']['latency_ms'],
                        ];
                    }
                }

                echo json_encode([
                    'success' => true,
                    'data' => $systemMetrics,
                    'data_points' => count($history),
                ]);

            } catch (Exception $e) {
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'cpu_usage' => [],
                        'memory_usage' => [],
                        'disk_usage' => [],
                        'database_latency' => [],
                    ],
                    'message' => 'No historical data available',
                ]);
            }
            break;

        case 'export':
            // Export metrics to CSV
            $type = $_GET['type'] ?? 'dashboard';
            $format = $_GET['format'] ?? 'json';

            if ($format !== 'csv' && $format !== 'json') {
                throw new Exception('Invalid format. Use csv or json');
            }

            // Get metrics based on type
            // This would be expanded to support different export types
            $data = [];

            if ($format === 'csv') {
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="metrics_' . $type . '_' . date('Y-m-d') . '.csv"');

                // Output CSV
                // Implementation depends on data structure
                echo "Metric,Value,Timestamp\n";
                foreach ($data as $row) {
                    // CSV output logic
                }
            } else {
                echo json_encode([
                    'success' => true,
                    'data' => $data,
                    'exported_at' => time(),
                ]);
            }
            break;

        default:
            throw new Exception('Invalid action. Valid actions: dashboard, streams, subscribers, resellers, security, v2ray, audit, redis_stats, system, export');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
