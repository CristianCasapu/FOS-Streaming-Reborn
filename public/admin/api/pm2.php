<?php
/**
 * PM2 Process Manager API
 *
 * Manages PM2 workers and system services
 *
 * Actions:
 * - status: Get PM2 and service status
 * - start: Start PM2 workers
 * - stop: Stop PM2 workers
 * - restart: Restart PM2 workers
 * - logs: Get recent logs
 * - queue_stats: Get job queue statistics
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../functions.php';

use Illuminate\Support\Facades\Crypt;

// Ensure user is authenticated
logincheck();

// Set JSON response header
header('Content-Type: application/json');

// Get action from query parameter
$action = $_GET['action'] ?? 'status';

try {
    switch ($action) {
        case 'status':
            // Get PM2 worker status
            $pm2Status = [];

            exec('pm2 jlist 2>&1', $output, $exitCode);

            if ($exitCode === 0 && !empty($output)) {
                $jsonOutput = implode('', $output);
                $processes = json_decode($jsonOutput, true);

                if ($processes) {
                    foreach ($processes as $process) {
                        $pm2Status[] = [
                            'name' => $process['name'],
                            'pm_id' => $process['pm_id'],
                            'status' => $process['pm2_env']['status'] ?? 'unknown',
                            'cpu' => $process['monit']['cpu'] ?? 0,
                            'memory' => $process['monit']['memory'] ?? 0,
                            'uptime' => $process['pm2_env']['pm_uptime'] ?? 0,
                            'restarts' => $process['pm2_env']['restart_time'] ?? 0,
                            'pid' => $process['pid'] ?? null
                        ];
                    }
                }
            }

            // Get system services status
            $services = [
                [
                    'name' => 'nginx',
                    'display_name' => 'Nginx Web Server',
                    'type' => 'system',
                    'status' => getServiceStatus('nginx'),
                    'description' => 'HTTP/RTMP/HLS server'
                ],
                [
                    'name' => 'mariadb',
                    'display_name' => 'MariaDB Database',
                    'type' => 'system',
                    'status' => getServiceStatus('mariadb'),
                    'description' => 'Database server'
                ],
                [
                    'name' => 'php-fpm',
                    'display_name' => 'PHP-FPM',
                    'type' => 'system',
                    'status' => getServiceStatus('php8.4-fpm'),
                    'description' => 'PHP FastCGI Process Manager'
                ]
            ];

            // Get queue statistics
            require_once __DIR__ . '/../../../app/Services/JobQueueService.php';
            $queueService = new \App\Services\JobQueueService();

            $queueStats = [
                'stream_import' => $queueService->getQueueStats('stream-import'),
                'ffprobe_analysis' => $queueService->getQueueStats('ffprobe-analysis')
            ];

            echo json_encode([
                'success' => true,
                'data' => [
                    'pm2_workers' => $pm2Status,
                    'system_services' => $services,
                    'queue_stats' => $queueStats,
                    'pm2_installed' => checkPM2Installed()
                ]
            ]);
            break;

        case 'start':
            // Start PM2 workers
            $workerId = $_GET['worker_id'] ?? 'all';

            if ($workerId === 'all') {
                exec('pm2 start ecosystem.config.js 2>&1', $output, $exitCode);
            } else {
                exec("pm2 start {$workerId} 2>&1", $output, $exitCode);
            }

            if ($exitCode === 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'PM2 workers started successfully'
                ]);
            } else {
                throw new Exception('Failed to start PM2 workers: ' . implode("\n", $output));
            }
            break;

        case 'stop':
            // Stop PM2 workers
            $workerId = $_GET['worker_id'] ?? 'all';

            if ($workerId === 'all') {
                exec('pm2 stop all 2>&1', $output, $exitCode);
            } else {
                exec("pm2 stop {$workerId} 2>&1", $output, $exitCode);
            }

            if ($exitCode === 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'PM2 workers stopped successfully'
                ]);
            } else {
                throw new Exception('Failed to stop PM2 workers: ' . implode("\n", $output));
            }
            break;

        case 'restart':
            // Restart PM2 workers
            $workerId = $_GET['worker_id'] ?? 'all';

            if ($workerId === 'all') {
                exec('pm2 restart all 2>&1', $output, $exitCode);
            } else {
                exec("pm2 restart {$workerId} 2>&1", $output, $exitCode);
            }

            if ($exitCode === 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'PM2 workers restarted successfully'
                ]);
            } else {
                throw new Exception('Failed to restart PM2 workers: ' . implode("\n", $output));
            }
            break;

        case 'logs':
            // Get recent PM2 logs
            $worker = $_GET['worker'] ?? 'all';
            $lines = isset($_GET['lines']) ? (int)$_GET['lines'] : 100;

            if ($worker === 'all') {
                exec("pm2 logs --nostream --lines {$lines} 2>&1", $output, $exitCode);
            } else {
                exec("pm2 logs {$worker} --nostream --lines {$lines} 2>&1", $output, $exitCode);
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'logs' => implode("\n", $output)
                ]
            ]);
            break;

        case 'service_action':
            // Control system services
            $service = $_POST['service'] ?? null;
            $serviceAction = $_POST['action'] ?? null;

            if (!$service || !$serviceAction) {
                throw new Exception('Service and action are required');
            }

            $allowedServices = ['nginx', 'mariadb', 'php8.4-fpm'];
            $allowedActions = ['start', 'stop', 'restart', 'reload'];

            if (!in_array($service, $allowedServices)) {
                throw new Exception('Invalid service');
            }

            if (!in_array($serviceAction, $allowedActions)) {
                throw new Exception('Invalid action');
            }

            // Get sudo password from settings
            $setting = Setting::first();
            if (!$setting || empty($setting->sudo_password)) {
                throw new Exception('Sudo password not configured. Please set it in Settings → System Commands Configuration.');
            }

            // Decrypt sudo password
            $sudoPassword = Crypt::decryptString($setting->sudo_password);

            $command = "echo '{$sudoPassword}' | sudo -S systemctl {$serviceAction} {$service} 2>&1";
            exec($command, $output, $exitCode);

            if ($exitCode === 0) {
                echo json_encode([
                    'success' => true,
                    'message' => ucfirst($serviceAction) . " {$service} successfully"
                ]);
            } else {
                throw new Exception("Failed to {$serviceAction} {$service}: " . implode("\n", $output));
            }
            break;

        case 'queue_stats':
            // Get detailed queue statistics
            require_once __DIR__ . '/../../../app/Services/JobQueueService.php';
            $queueService = new \App\Services\JobQueueService();

            $stats = [
                'stream_import' => $queueService->getQueueStats('stream-import'),
                'ffprobe_analysis' => $queueService->getQueueStats('ffprobe-analysis')
            ];

            echo json_encode([
                'success' => true,
                'data' => $stats
            ]);
            break;

        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
            break;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Helper function to get service status
 */
function getServiceStatus($serviceName) {
    exec("systemctl is-active {$serviceName} 2>&1", $output, $exitCode);
    $status = trim(implode('', $output));

    return [
        'active' => $exitCode === 0 && $status === 'active',
        'status' => $status,
        'enabled' => isServiceEnabled($serviceName)
    ];
}

/**
 * Helper function to check if service is enabled
 */
function isServiceEnabled($serviceName) {
    exec("systemctl is-enabled {$serviceName} 2>&1", $output, $exitCode);
    return $exitCode === 0;
}

/**
 * Check if PM2 is installed
 */
function checkPM2Installed() {
    exec('which pm2 2>&1', $output, $exitCode);
    return $exitCode === 0;
}
