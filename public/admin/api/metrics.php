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

// Helper function to safely count from a table
function safeCount($table, $conditions = []) {
    try {
        $query = DB::table($table);
        foreach ($conditions as $column => $value) {
            if (is_array($value)) {
                $query->where($column, $value[0], $value[1]);
            } else {
                $query->where($column, $value);
            }
        }
        return $query->count();
    } catch (Exception $e) {
        return 0;
    }
}

// Helper function to safely sum from a table
function safeSum($table, $column, $conditions = []) {
    try {
        $query = DB::table($table);
        foreach ($conditions as $col => $value) {
            if (is_array($value)) {
                $query->where($col, $value[0], $value[1]);
            } else {
                $query->where($col, $value);
            }
        }
        return $query->sum($column) ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}

try {
    switch ($action) {
        case 'dashboard':
            // Quick dashboard metrics with safe queries
            $metrics = [
                'platform' => [
                    'total_streams' => safeCount('streams'),
                    'active_streams' => safeCount('streams', ['enabled' => true, 'pid' => ['>', 0]]),
                    'total_subscribers' => safeCount('subscribers'),
                    'active_subscriptions' => safeCount('subscriptions', ['is_active' => true]),
                    'total_resellers' => safeCount('resellers'),
                    'active_resellers' => safeCount('resellers', ['is_active' => true]),
                ],
                'revenue' => [
                    'monthly_revenue' => (function() {
                        try {
                            return DB::table('subscriptions')
                                ->join('packages', 'subscriptions.package_id', '=', 'packages.id')
                                ->where('subscriptions.is_active', true)
                                ->whereMonth('subscriptions.created_at', date('m'))
                                ->whereYear('subscriptions.created_at', date('Y'))
                                ->sum('packages.price') ?? 0;
                        } catch (Exception $e) {
                            return 0;
                        }
                    })(),
                    'total_revenue' => (function() {
                        try {
                            return DB::table('subscriptions')
                                ->join('packages', 'subscriptions.package_id', '=', 'packages.id')
                                ->where('subscriptions.is_active', true)
                                ->sum('packages.price') ?? 0;
                        } catch (Exception $e) {
                            return 0;
                        }
                    })(),
                    'pending_commissions' => safeSum('resellers', 'pending_balance'),
                ],
                'security' => [
                    'device_fingerprints' => safeCount('device_fingerprints'),
                    'active_devices' => (function() {
                        try {
                            return DB::table('device_bindings')
                                ->where('is_active', true)
                                ->distinct('device_fingerprint_id')
                                ->count('device_fingerprint_id');
                        } catch (Exception $e) {
                            return 0;
                        }
                    })(),
                    'violations_today' => (function() {
                        try {
                            return DB::table('device_violations')
                                ->whereDate('created_at', date('Y-m-d'))
                                ->count();
                        } catch (Exception $e) {
                            return 0;
                        }
                    })(),
                    'blocked_devices' => safeCount('device_fingerprints', ['is_blocked' => true]),
                ],
                'streaming' => [
                    'srt_streams' => safeCount('streams', ['srt_enabled' => true]),
                    'proxy_streams' => safeCount('streams', ['stream_mode' => 'proxy']),
                    'transcode_streams' => safeCount('streams', ['stream_mode' => 'transcode']),
                    'v2ray_users' => safeCount('v2ray_users', ['is_active' => true]),
                    'v2ray_servers' => safeCount('v2ray_servers', ['enabled' => true]),
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
                'by_status' => (function() {
                    try {
                        return DB::table('streams')
                            ->select('enabled', DB::raw('COUNT(*) as count'))
                            ->groupBy('enabled')
                            ->get();
                    } catch (Exception $e) {
                        return [];
                    }
                })(),
                'by_mode' => (function() {
                    try {
                        return DB::table('streams')
                            ->select('stream_mode', DB::raw('COUNT(*) as count'))
                            ->groupBy('stream_mode')
                            ->get();
                    } catch (Exception $e) {
                        return [];
                    }
                })(),
                'by_protocol' => (function() {
                    try {
                        return DB::table('streams')
                            ->select('protocol', DB::raw('COUNT(*) as count'))
                            ->groupBy('protocol')
                            ->get();
                    } catch (Exception $e) {
                        return [];
                    }
                })(),
                'by_category' => (function() {
                    try {
                        return DB::table('streams')
                            ->join('categories', 'streams.cat_id', '=', 'categories.id')
                            ->select('categories.name as category', DB::raw('COUNT(*) as count'))
                            ->groupBy('categories.name')
                            ->orderBy('count', 'DESC')
                            ->limit(10)
                            ->get();
                    } catch (Exception $e) {
                        return [];
                    }
                })(),
                'uptime' => (function() {
                    try {
                        return DB::table('streams')
                            ->where('enabled', true)
                            ->select(DB::raw('AVG(CASE WHEN pid > 0 THEN 1 ELSE 0 END) * 100 as uptime_percent'))
                            ->first();
                    } catch (Exception $e) {
                        return (object)['uptime_percent' => 0];
                    }
                })(),
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
                'growth' => (function() use ($days) {
                    try {
                        return DB::table('subscribers')
                            ->select(
                                DB::raw('DATE(created_at) as date'),
                                DB::raw('COUNT(*) as new_subscribers')
                            )
                            ->where('created_at', '>=', DB::raw("DATE_SUB(NOW(), INTERVAL {$days} DAY)"))
                            ->groupBy('date')
                            ->orderBy('date', 'ASC')
                            ->get();
                    } catch (Exception $e) {
                        return [];
                    }
                })(),
                'by_package' => (function() {
                    try {
                        return DB::table('subscriptions')
                            ->join('packages', 'subscriptions.package_id', '=', 'packages.id')
                            ->select('packages.name as package', DB::raw('COUNT(DISTINCT subscriptions.subscriber_id) as count'))
                            ->where('subscriptions.is_active', true)
                            ->groupBy('packages.name')
                            ->get();
                    } catch (Exception $e) {
                        return [];
                    }
                })(),
                'subscription_status' => (function() {
                    try {
                        // Use is_active boolean to derive status
                        return DB::table('subscriptions')
                            ->select(DB::raw('CASE WHEN is_active = 1 THEN "active" ELSE "inactive" END as status'), DB::raw('COUNT(*) as count'))
                            ->groupBy('is_active')
                            ->get();
                    } catch (Exception $e) {
                        return [];
                    }
                })(),
                'trial_conversions' => [
                    'total_trials' => safeCount('trials'),
                    'active_trials' => safeCount('trials', ['is_active' => true]),
                    'expired_trials' => safeCount('trials', ['is_active' => false]),
                    'converted' => (function() {
                        try {
                            return DB::table('trials')
                                ->where('converted_to_subscription', true)
                                ->count();
                        } catch (Exception $e) {
                            return 0;
                        }
                    })(),
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
                'top_resellers' => (function() {
                    try {
                        return DB::table('resellers')
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
                            ->get();
                    } catch (Exception $e) {
                        return [];
                    }
                })(),
                'commission_summary' => [
                    'total_earned' => safeSum('resellers', 'total_earned'),
                    'total_withdrawn' => safeSum('resellers', 'total_withdrawn'),
                    'pending_balance' => safeSum('resellers', 'pending_balance'),
                    'available_balance' => safeSum('resellers', 'credit_balance'),
                ],
                'transactions_last_30_days' => (function() {
                    try {
                        return DB::table('reseller_transactions')
                            ->select(
                                DB::raw('DATE(created_at) as date'),
                                'type',
                                DB::raw('SUM(amount) as total_amount'),
                                DB::raw('COUNT(*) as count')
                            )
                            ->where('created_at', '>=', DB::raw('DATE_SUB(NOW(), INTERVAL 30 DAY)'))
                            ->groupBy('date', 'type')
                            ->orderBy('date', 'ASC')
                            ->get();
                    } catch (Exception $e) {
                        return [];
                    }
                })(),
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
                'device_activity' => (function() use ($days) {
                    try {
                        return DB::table('device_sessions')
                            ->select(
                                DB::raw('DATE(created_at) as date'),
                                DB::raw('COUNT(DISTINCT device_fingerprint_id) as unique_devices'),
                                DB::raw('COUNT(*) as total_sessions')
                            )
                            ->where('created_at', '>=', DB::raw("DATE_SUB(NOW(), INTERVAL {$days} DAY)"))
                            ->groupBy('date')
                            ->orderBy('date', 'ASC')
                            ->get();
                    } catch (Exception $e) {
                        return [];
                    }
                })(),
                'violations' => (function() use ($days) {
                    try {
                        return DB::table('device_violations')
                            ->select(
                                'violation_type',
                                DB::raw('COUNT(*) as count'),
                                DB::raw('COUNT(DISTINCT device_fingerprint_id) as unique_devices')
                            )
                            ->where('created_at', '>=', DB::raw("DATE_SUB(NOW(), INTERVAL {$days} DAY)"))
                            ->groupBy('violation_type')
                            ->orderBy('count', 'DESC')
                            ->get();
                    } catch (Exception $e) {
                        return [];
                    }
                })(),
                'blocked_devices' => (function() use ($days) {
                    try {
                        return DB::table('device_fingerprints')
                            ->select(
                                DB::raw('DATE(blocked_at) as date'),
                                DB::raw('COUNT(*) as count')
                            )
                            ->where('is_blocked', true)
                            ->whereNotNull('blocked_at')
                            ->where('blocked_at', '>=', DB::raw("DATE_SUB(NOW(), INTERVAL {$days} DAY)"))
                            ->groupBy('date')
                            ->orderBy('date', 'ASC')
                            ->get();
                    } catch (Exception $e) {
                        return [];
                    }
                })(),
                'concurrent_streams' => (function() {
                    try {
                        return DB::table('device_sessions')
                            ->select(
                                'device_fingerprint_id',
                                DB::raw('COUNT(*) as concurrent_count')
                            )
                            ->where('is_active', true)
                            ->groupBy('device_fingerprint_id')
                            ->havingRaw('COUNT(*) > 1')
                            ->orderBy('concurrent_count', 'DESC')
                            ->limit(20)
                            ->get();
                    } catch (Exception $e) {
                        return [];
                    }
                })(),
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
                'traffic_stats' => (function() use ($days) {
                    try {
                        return DB::table('v2ray_traffic_stats')
                            ->select(
                                'date',
                                DB::raw('SUM(bytes_uploaded) as total_uploaded'),
                                DB::raw('SUM(bytes_downloaded) as total_downloaded'),
                                DB::raw('SUM(bytes_total) as total_traffic')
                            )
                            ->where('date', '>=', DB::raw("DATE_SUB(CURDATE(), INTERVAL {$days} DAY)"))
                            ->groupBy('date')
                            ->orderBy('date', 'ASC')
                            ->get();
                    } catch (Exception $e) {
                        return [];
                    }
                })(),
                'top_users' => (function() {
                    try {
                        return DB::table('v2ray_users')
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
                            ->get();
                    } catch (Exception $e) {
                        return [];
                    }
                })(),
                'server_health' => (function() {
                    try {
                        return DB::table('v2ray_servers')
                            ->select(
                                'tag',
                                'address',
                                'health_status',
                                'load',
                                'current_connections',
                                'max_connections',
                                DB::raw('(current_connections / NULLIF(max_connections, 0) * 100) as load_percent')
                            )
                            ->where('enabled', true)
                            ->orderBy('load', 'DESC')
                            ->get();
                    } catch (Exception $e) {
                        return [];
                    }
                })(),
                'protocol_usage' => (function() {
                    try {
                        return DB::table('v2ray_users')
                            ->select('protocol', DB::raw('COUNT(*) as count'))
                            ->where('is_active', true)
                            ->groupBy('protocol')
                            ->get();
                    } catch (Exception $e) {
                        return [];
                    }
                })(),
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
                'actions_by_day' => (function() use ($days) {
                    try {
                        return DB::table('audit_logs')
                            ->select(
                                DB::raw('DATE(created_at) as date'),
                                DB::raw('COUNT(*) as total_actions')
                            )
                            ->where('created_at', '>=', DB::raw("DATE_SUB(NOW(), INTERVAL {$days} DAY)"))
                            ->groupBy('date')
                            ->orderBy('date', 'ASC')
                            ->get();
                    } catch (Exception $e) {
                        return [];
                    }
                })(),
                'actions_by_type' => (function() use ($days) {
                    try {
                        return DB::table('audit_logs')
                            ->select('action', DB::raw('COUNT(*) as count'))
                            ->where('created_at', '>=', DB::raw("DATE_SUB(NOW(), INTERVAL {$days} DAY)"))
                            ->groupBy('action')
                            ->orderBy('count', 'DESC')
                            ->limit(10)
                            ->get();
                    } catch (Exception $e) {
                        return [];
                    }
                })(),
                'actions_by_user_type' => (function() use ($days) {
                    try {
                        return DB::table('audit_logs')
                            ->select('user_type', DB::raw('COUNT(*) as count'))
                            ->where('created_at', '>=', DB::raw("DATE_SUB(NOW(), INTERVAL {$days} DAY)"))
                            ->groupBy('user_type')
                            ->get();
                    } catch (Exception $e) {
                        return [];
                    }
                })(),
                'failed_actions' => (function() use ($days) {
                    try {
                        return DB::table('audit_logs')
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
                            ->get();
                    } catch (Exception $e) {
                        return [];
                    }
                })(),
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
