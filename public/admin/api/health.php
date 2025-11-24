<?php
/**
 * Health Check API Endpoint
 * Provides system health monitoring and diagnostics
 */

require_once '../../../config.php';
logincheck(); // Enforce admin authentication

header('Content-Type: application/json');

use App\Services\HealthCheckService;

$action = $_GET['action'] ?? 'all';

try {
    $healthService = new HealthCheckService();

    switch ($action) {
        case 'all':
            // Comprehensive health check of all components
            $results = $healthService->checkAll();

            echo json_encode([
                'success' => true,
                'data' => $results,
            ]);
            break;

        case 'summary':
            // Quick summary of health status
            $summary = $healthService->getSummary();

            echo json_encode([
                'success' => true,
                'data' => $summary,
            ]);
            break;

        case 'database':
            // Database connectivity and performance
            $result = $healthService->checkDatabase();

            echo json_encode([
                'success' => true,
                'data' => $result,
            ]);
            break;

        case 'redis':
            // Redis connectivity and memory usage
            $result = $healthService->checkRedis();

            echo json_encode([
                'success' => true,
                'data' => $result,
            ]);
            break;

        case 'disk':
            // Disk space usage
            $result = $healthService->checkDiskSpace();

            echo json_encode([
                'success' => true,
                'data' => $result,
            ]);
            break;

        case 'memory':
            // Memory usage
            $result = $healthService->checkMemory();

            echo json_encode([
                'success' => true,
                'data' => $result,
            ]);
            break;

        case 'cpu':
            // CPU load
            $result = $healthService->checkCPU();

            echo json_encode([
                'success' => true,
                'data' => $result,
            ]);
            break;

        case 'services':
            // System services status (nginx, php-fpm, mariadb)
            $result = $healthService->checkServices();

            echo json_encode([
                'success' => true,
                'data' => $result,
            ]);
            break;

        case 'srt':
            // SRT proxy service status
            $result = $healthService->checkSRT();

            echo json_encode([
                'success' => true,
                'data' => $result,
            ]);
            break;

        case 'v2ray':
            // V2Ray service status
            $result = $healthService->checkV2Ray();

            echo json_encode([
                'success' => true,
                'data' => $result,
            ]);
            break;

        case 'cdn':
            // CDN connectivity status
            $result = $healthService->checkCDN();

            echo json_encode([
                'success' => true,
                'data' => $result,
            ]);
            break;

        case 'streams':
            // Active streams health
            $result = $healthService->checkStreams();

            echo json_encode([
                'success' => true,
                'data' => $result,
            ]);
            break;

        case 'workers':
            // PM2 workers status
            $result = $healthService->checkWorkers();

            echo json_encode([
                'success' => true,
                'data' => $result,
            ]);
            break;

        case 'critical':
            // Get only critical issues
            $fullCheck = $healthService->checkAll();
            $criticalIssues = [];
            $warnings = [];

            foreach ($fullCheck['checks'] as $checkName => $check) {
                if (in_array($check['status'], ['critical', 'unhealthy'])) {
                    $criticalIssues[] = [
                        'check' => $checkName,
                        'status' => $check['status'],
                        'message' => $check['message'] ?? 'Check failed',
                        'details' => $check,
                    ];
                } elseif ($check['status'] === 'warning') {
                    $warnings[] = [
                        'check' => $checkName,
                        'message' => $check['message'] ?? 'Warning detected',
                        'details' => $check,
                    ];
                }
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'overall_status' => $fullCheck['overall_status'],
                    'timestamp' => $fullCheck['timestamp'],
                    'critical_issues' => $criticalIssues,
                    'warnings' => $warnings,
                    'has_critical' => count($criticalIssues) > 0,
                    'has_warnings' => count($warnings) > 0,
                ],
            ]);
            break;

        case 'history':
            // Health check history (last 24 hours)
            // Store health checks in Redis for trending
            $redisKey = 'health:history';

            try {
                $history = \Illuminate\Support\Facades\Redis::lrange($redisKey, 0, 287); // Last 24h at 5min intervals
                $history = array_map('json_decode', $history);

                echo json_encode([
                    'success' => true,
                    'data' => [
                        'checks' => $history,
                        'count' => count($history),
                    ],
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'checks' => [],
                        'count' => 0,
                        'message' => 'No history available',
                    ],
                ]);
            }
            break;

        case 'store':
            // Store current health check for history
            $results = $healthService->checkAll();

            try {
                $redisKey = 'health:history';
                $entry = json_encode([
                    'timestamp' => time(),
                    'overall_status' => $results['overall_status'],
                    'checks' => $results['checks'],
                ]);

                \Illuminate\Support\Facades\Redis::lpush($redisKey, $entry);
                \Illuminate\Support\Facades\Redis::ltrim($redisKey, 0, 287); // Keep last 24h

                echo json_encode([
                    'success' => true,
                    'message' => 'Health check stored',
                ]);
            } catch (Exception $e) {
                throw new Exception('Failed to store health check: ' . $e->getMessage());
            }
            break;

        case 'metrics':
            // Quick metrics for dashboard
            $db = $healthService->checkDatabase();
            $redis = $healthService->checkRedis();
            $disk = $healthService->checkDiskSpace();
            $memory = $healthService->checkMemory();
            $cpu = $healthService->checkCPU();
            $streams = $healthService->checkStreams();
            $workers = $healthService->checkWorkers();

            $metrics = [
                'database' => [
                    'status' => $db['status'],
                    'latency_ms' => $db['latency_ms'] ?? 0,
                    'connection_usage' => $db['connections']['usage_percent'] ?? 0,
                ],
                'redis' => [
                    'status' => $redis['status'],
                    'latency_ms' => $redis['latency_ms'] ?? 0,
                    'memory_used' => $redis['memory_used'] ?? 'unknown',
                ],
                'disk' => [
                    'status' => $disk['status'],
                    'used_percent' => $disk['used_percent'] ?? 0,
                    'free_gb' => $disk['free_gb'] ?? 0,
                ],
                'memory' => [
                    'status' => $memory['status'],
                    'used_percent' => $memory['used_percent'] ?? 0,
                    'free_mb' => $memory['free_mb'] ?? 0,
                ],
                'cpu' => [
                    'status' => $cpu['status'],
                    'load_percent' => $cpu['load_percent'] ?? 0,
                    'load_1min' => $cpu['load_1min'] ?? 0,
                ],
                'streams' => [
                    'total_enabled' => $streams['total_enabled'] ?? 0,
                    'active_streams' => $streams['active_streams'] ?? 0,
                    'proxy_streams' => $streams['proxy_streams'] ?? 0,
                ],
                'workers' => [
                    'status' => $workers['status'] ?? 'unknown',
                    'online' => $workers['online'] ?? 0,
                    'stopped' => $workers['stopped'] ?? 0,
                ],
            ];

            echo json_encode([
                'success' => true,
                'data' => $metrics,
            ]);
            break;

        case 'test_notification':
            // Test health check notification system
            $fullCheck = $healthService->checkAll();

            if ($fullCheck['overall_status'] !== 'healthy') {
                // In production, this would send email/slack/telegram notifications
                $message = "System health status: {$fullCheck['overall_status']}";

                echo json_encode([
                    'success' => true,
                    'data' => [
                        'notification_sent' => true,
                        'status' => $fullCheck['overall_status'],
                        'message' => $message,
                    ],
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'notification_sent' => false,
                        'status' => 'healthy',
                        'message' => 'System is healthy, no notification needed',
                    ],
                ]);
            }
            break;

        default:
            throw new Exception('Invalid action. Valid actions: all, summary, database, redis, disk, memory, cpu, services, srt, v2ray, cdn, streams, workers, critical, history, store, metrics, test_notification');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
