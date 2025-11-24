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
 * - sync: Regenerate ecosystem.config.cjs from database and reload PM2
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../functions.php';

use Illuminate\Support\Facades\Crypt;
use App\Services\PM2WorkerService;

// Ensure user is authenticated
logincheck();

// Set JSON response header
header('Content-Type: application/json');

// Get action from query parameter
$action = $_GET['action'] ?? 'status';

// Define project root path (3 levels up from /public/admin/api/)
$projectRoot = realpath(__DIR__ . '/../../..');

try {
    switch ($action) {
        case 'install':
            // Install PM2 globally
            // When using NVM, we don't need sudo - install to user's NVM directory

            // Get npm path
            exec('which npm 2>&1', $npmPathOutput, $npmExitCode);
            $npmPath = trim(implode('', $npmPathOutput));

            if ($npmExitCode !== 0 || empty($npmPath)) {
                throw new Exception('npm command not found. Please ensure Node.js and npm are installed.');
            }

            // Check if npm is managed by NVM
            $isNVM = strpos($npmPath, '/.nvm/') !== false;

            if ($isNVM) {
                // NVM-managed npm - install without sudo
                $command = "{$npmPath} install -g pm2 2>&1";
                exec($command, $output, $exitCode);
            } else {
                // System-wide npm - requires sudo
                $setting = Setting::first();
                if (!$setting || empty($setting->sudo_password)) {
                    throw new Exception('Sudo password not configured. Please set it in Settings → System Commands Configuration.');
                }

                // Decrypt sudo password
                $sudoPassword = Crypt::decryptString($setting->sudo_password);

                $command = "echo '{$sudoPassword}' | sudo -S {$npmPath} install -g pm2 2>&1";
                exec($command, $output, $exitCode);
            }

            if ($exitCode === 0) {
                // Start workers after installation
                exec("cd {$projectRoot} && pm2 start ecosystem.config.cjs 2>&1", $startOutput, $startExitCode);

                echo json_encode([
                    'success' => true,
                    'message' => 'PM2 installed successfully and workers started'
                ]);
            } else {
                throw new Exception('Failed to install PM2: ' . implode("\n", $output));
            }
            break;

        case 'get_config':
            // Get worker configuration from ecosystem.config.cjs
            $workerName = $_GET['worker'] ?? null;

            if (!$workerName) {
                throw new Exception('Worker name is required');
            }

            // Read ecosystem.config.cjs
            $ecosystemPath = __DIR__ . '/../../../ecosystem.config.cjs';

            if (!file_exists($ecosystemPath)) {
                throw new Exception('ecosystem.config.cjs not found');
            }

            $ecosystemContent = file_get_contents($ecosystemPath);

            // Extract configuration for specific worker (basic parsing)
            // This is a simplified approach - in production, consider using a proper JS parser
            $config = extractWorkerConfig($ecosystemContent, $workerName);

            echo json_encode([
                'success' => true,
                'data' => $config
            ]);
            break;

        case 'update_config':
            // Update worker configuration in ecosystem.config.cjs
            $workerName = $_POST['worker'] ?? null;
            $configData = $_POST['config'] ?? null;

            if (!$workerName || !$configData) {
                throw new Exception('Worker name and configuration are required');
            }

            // Decode config if it's JSON string
            if (is_string($configData)) {
                $configData = json_decode($configData, true);
            }

            // Update ecosystem.config.cjs
            $ecosystemPath = __DIR__ . '/../../../ecosystem.config.cjs';

            if (!file_exists($ecosystemPath)) {
                throw new Exception('ecosystem.config.cjs not found');
            }

            $ecosystemContent = file_get_contents($ecosystemPath);
            $updatedContent = updateWorkerConfig($ecosystemContent, $workerName, $configData);

            // Backup original file
            $backupPath = $ecosystemPath . '.backup.' . date('Y-m-d_H-i-s');
            copy($ecosystemPath, $backupPath);

            // Write updated configuration
            file_put_contents($ecosystemPath, $updatedContent);

            echo json_encode([
                'success' => true,
                'message' => 'Configuration updated successfully'
            ]);
            break;

        case 'status':
            // Get PM2 worker status
            $pm2Status = [];

            // Load expected workers from database (auto-loader!)
            $dbWorkers = PM2Worker::enabled()->byPriority()->get();
            $expectedWorkers = [];

            foreach ($dbWorkers as $worker) {
                $expectedWorkers[$worker->name] = [
                    'name' => $worker->name,
                    'display_name' => $worker->display_name,
                    'script' => $worker->script,
                    'instances' => $worker->instances,
                    'exec_mode' => $worker->exec_mode,
                    'description' => $worker->description,
                    'category' => $worker->category,
                    'priority' => $worker->priority
                ];
            }

            exec('pm2 jlist 2>&1', $output, $exitCode);

            $runningProcesses = [];
            if ($exitCode === 0 && !empty($output)) {
                $jsonOutput = implode('', $output);
                $processes = json_decode($jsonOutput, true);

                if ($processes) {
                    foreach ($processes as $process) {
                        $runningProcesses[$process['name']] = [
                            'name' => $process['name'],
                            'pm_id' => $process['pm_id'],
                            'status' => $process['pm2_env']['status'] ?? 'unknown',
                            'cpu' => $process['monit']['cpu'] ?? 0,
                            'memory' => $process['monit']['memory'] ?? 0,
                            'uptime' => $process['pm2_env']['pm_uptime'] ?? 0,
                            'restarts' => $process['pm2_env']['restart_time'] ?? 0,
                            'pid' => $process['pid'] ?? null,
                            'exec_mode' => $process['pm2_env']['exec_mode'] ?? 'fork',
                            'instances' => $process['pm2_env']['instances'] ?? 1,
                            'pm_cwd' => $process['pm2_env']['pm_cwd'] ?? null,
                            'pm_exec_path' => $process['pm2_env']['pm_exec_path'] ?? null
                        ];
                    }
                }
            }

            // Merge expected workers with running processes
            foreach ($expectedWorkers as $workerName => $workerConfig) {
                if (isset($runningProcesses[$workerName])) {
                    // Worker is running - use actual data
                    $pm2Status[] = array_merge($workerConfig, $runningProcesses[$workerName]);
                } else {
                    // Worker is not running - show as stopped
                    $pm2Status[] = [
                        'name' => $workerConfig['name'],
                        'display_name' => $workerConfig['display_name'] ?? $workerConfig['name'],
                        'pm_id' => null,
                        'status' => 'stopped',
                        'cpu' => 0,
                        'memory' => 0,
                        'uptime' => 0,
                        'restarts' => 0,
                        'pid' => null,
                        'exec_mode' => $workerConfig['exec_mode'],
                        'instances' => $workerConfig['instances'],
                        'script' => $workerConfig['script'],
                        'description' => $workerConfig['description'],
                        'category' => $workerConfig['category'] ?? null,
                        'priority' => $workerConfig['priority'] ?? 100,
                        'pm_cwd' => null,
                        'pm_exec_path' => null
                    ];
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
                exec("cd {$projectRoot} && pm2 start ecosystem.config.cjs 2>&1", $output, $exitCode);
            } else {
                exec("cd {$projectRoot} && pm2 start {$workerId} 2>&1", $output, $exitCode);
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
                exec("cd {$projectRoot} && pm2 stop all 2>&1", $output, $exitCode);
            } else {
                exec("cd {$projectRoot} && pm2 stop {$workerId} 2>&1", $output, $exitCode);
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
                exec("cd {$projectRoot} && pm2 restart all 2>&1", $output, $exitCode);
            } else {
                exec("cd {$projectRoot} && pm2 restart {$workerId} 2>&1", $output, $exitCode);
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
                exec("cd {$projectRoot} && pm2 logs --nostream --lines {$lines} 2>&1", $output, $exitCode);
            } else {
                exec("cd {$projectRoot} && pm2 logs {$worker} --nostream --lines {$lines} 2>&1", $output, $exitCode);
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

        case 'sync':
            // Regenerate ecosystem.config.cjs from database and reload PM2
            $workerService = new PM2WorkerService();
            $isDev = env('APP_ENV') === 'local' || env('APP_ENV') === 'development';

            // Generate ecosystem.config.cjs from database
            $result = $workerService->syncWithPM2($isDev);

            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'message' => $result['message'],
                    'workers_count' => $result['workers_count'],
                    'output' => $result['output']
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => $result['message'],
                    'output' => $result['output'] ?? []
                ]);
            }
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

/**
 * Extract worker configuration from ecosystem.config.cjs
 */
function extractWorkerConfig($content, $workerName) {
    $config = [
        'name' => $workerName,
        'script' => '',
        'instances' => 1,
        'exec_mode' => 'fork',
        'max_memory_restart' => '500M',
        'max_restarts' => 10,
        'min_uptime' => '10s',
        'cron_restart' => '',
        'log_level' => 'warn'
    ];

    // Find the worker block in the apps array
    $pattern = '/\{\s*name:\s*[\'"]' . preg_quote($workerName, '/') . '[\'"].*?\}/s';
    if (preg_match($pattern, $content, $matches)) {
        $workerBlock = $matches[0];

        // Extract individual properties
        if (preg_match('/script:\s*[\'"]([^\'"]+)[\'"]/', $workerBlock, $m)) {
            $config['script'] = $m[1];
        }
        if (preg_match('/instances:\s*(\d+)/', $workerBlock, $m)) {
            $config['instances'] = (int)$m[1];
        }
        if (preg_match('/exec_mode:\s*[\'"]([^\'"]+)[\'"]/', $workerBlock, $m)) {
            $config['exec_mode'] = $m[1];
        }
        if (preg_match('/max_memory_restart:\s*[\'"]([^\'"]+)[\'"]/', $workerBlock, $m)) {
            $config['max_memory_restart'] = $m[1];
        }
        if (preg_match('/max_restarts:\s*(\d+)/', $workerBlock, $m)) {
            $config['max_restarts'] = (int)$m[1];
        }
        if (preg_match('/min_uptime:\s*[\'"]([^\'"]+)[\'"]/', $workerBlock, $m)) {
            $config['min_uptime'] = $m[1];
        }
        if (preg_match('/cron_restart:\s*[\'"]([^\'"]+)[\'"]/', $workerBlock, $m)) {
            $config['cron_restart'] = $m[1];
        }
        // Extract LOG_LEVEL from env section
        if (preg_match('/LOG_LEVEL:\s*[\'"]([^\'"]+)[\'"]/', $workerBlock, $m)) {
            $config['log_level'] = $m[1];
        }
    }

    return $config;
}

/**
 * Update worker configuration in ecosystem.config.cjs
 */
function updateWorkerConfig($content, $workerName, $configData) {
    // Find the worker block
    $pattern = '/(\{\s*name:\s*[\'"]' . preg_quote($workerName, '/') . '[\'"].*?\})/s';

    if (preg_match($pattern, $content, $matches)) {
        $workerBlock = $matches[1];
        $newBlock = $workerBlock;

        // Update each configuration value
        if (isset($configData['script'])) {
            $newBlock = preg_replace(
                '/script:\s*[\'"][^\'"]*[\'"]/',
                "script: '{$configData['script']}'",
                $newBlock
            );
        }

        if (isset($configData['instances'])) {
            $newBlock = preg_replace(
                '/instances:\s*\d+/',
                "instances: {$configData['instances']}",
                $newBlock
            );
        }

        if (isset($configData['exec_mode'])) {
            $newBlock = preg_replace(
                '/exec_mode:\s*[\'"][^\'"]*[\'"]/',
                "exec_mode: '{$configData['exec_mode']}'",
                $newBlock
            );
        }

        if (isset($configData['max_memory_restart'])) {
            $newBlock = preg_replace(
                '/max_memory_restart:\s*[\'"][^\'"]*[\'"]/',
                "max_memory_restart: '{$configData['max_memory_restart']}'",
                $newBlock
            );
        }

        if (isset($configData['max_restarts'])) {
            $newBlock = preg_replace(
                '/max_restarts:\s*\d+/',
                "max_restarts: {$configData['max_restarts']}",
                $newBlock
            );
        }

        if (isset($configData['min_uptime'])) {
            $newBlock = preg_replace(
                '/min_uptime:\s*[\'"][^\'"]*[\'"]/',
                "min_uptime: '{$configData['min_uptime']}'",
                $newBlock
            );
        }

        if (isset($configData['cron_restart'])) {
            if (!empty($configData['cron_restart'])) {
                $newBlock = preg_replace(
                    '/cron_restart:\s*[\'"][^\'"]*[\'"]/',
                    "cron_restart: '{$configData['cron_restart']}'",
                    $newBlock
                );
            }
        }

        if (isset($configData['log_level'])) {
            // Update LOG_LEVEL in env sections
            $newBlock = preg_replace(
                '/LOG_LEVEL:\s*[\'"][^\'"]*[\'"]/',
                "LOG_LEVEL: '{$configData['log_level']}'",
                $newBlock
            );
        }

        // Replace the old block with the new one
        $content = str_replace($workerBlock, $newBlock, $content);
    }

    return $content;
}
